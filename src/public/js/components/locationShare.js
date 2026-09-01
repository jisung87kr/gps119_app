// 참가자 위치 자동공유 (FE-2.2).
//
// navigator.geolocation.watchPosition 으로 위치를 받아 적응형 주기로
// POST /api/events/{id}/location 전송. 공유 토글은 PATCH /api/events/{id}/sharing.
//
// 적응형 주기(05/04):
//   - 이동(직전 전송 위치에서 MOVE_THRESHOLD_M 초과) → 최소 MOVE_INTERVAL_MS(약 5초) 간격 전송
//   - 정지 → STATIONARY_INTERVAL_MS(약 30초) 하트비트 전송
//   백엔드 throttle 은 routes/api.php 의 `throttle:30,1`(= 분당 30회)이다.
//   ⚠️ 예전 주석은 "백엔드 throttle(2/s)와 정합"이라 적혀 있었으나 실제 백엔드는
//      분당 2회였다(Laravel 문법이 `최대횟수,분`). 이동 중 12건 중 10건이 429 로
//      버려지고 있었다. 값을 바꿀 때는 반드시 양쪽을 같이 볼 것.
//
// 견고성(경량): 전송 실패분은 작은 버퍼에 보관 후 다음 전송 시 복구 전송.
//   429(스로틀)는 "지금 너무 빠르다"는 뜻이라 즉시 재시도하면 더 악화된다 →
//   백오프 창 동안 전송을 건너뛴다(버퍼에는 남겨 다음 창에서 복구).

const MOVE_THRESHOLD_M = 10;        // 이동 판정 거리
const MOVE_INTERVAL_MS = 5000;      // 이동 중 최소 전송 간격
const STATIONARY_INTERVAL_MS = 30000; // 정지 중 하트비트 간격
const MAX_BUFFER = 12;              // 실패 버퍼 상한(이동 1분치)
const THROTTLE_BACKOFF_MS = 20000;  // 429 수신 시 전송 중단 창

const GEO_OPTIONS = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };

// 두 좌표 간 거리(m) — 하버사인
function distanceM(aLat, aLng, bLat, bLng) {
    const R = 6371000;
    const dLat = (bLat - aLat) * Math.PI / 180;
    const dLng = (bLng - aLng) * Math.PI / 180;
    const s = Math.sin(dLat / 2) ** 2 +
        Math.cos(aLat * Math.PI / 180) * Math.cos(bLat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(s));
}

function clampInt(v, min, max) {
    if (v == null || isNaN(v)) return null;
    return Math.min(max, Math.max(min, Math.round(v)));
}

// window.axios 준비 대기(최대 3초) — Phase0/1 QA 교훈
function waitForAxios() {
    return new Promise((resolve) => {
        if (window.axios) return resolve(window.axios);
        let waited = 0;
        const t = setInterval(() => {
            waited += 100;
            if (window.axios) { clearInterval(t); resolve(window.axios); }
            else if (waited >= 3000) { clearInterval(t); resolve(null); }
        }, 100);
    });
}

/**
 * 기본 트래커 — 브라우저 `navigator.geolocation`.
 *
 * ⚠️ **앱이 백그라운드로 가면 멈춘다.** 그게 N3 가 네이티브 플러그인을 붙이는 이유다.
 *    여기서는 그 사실을 `supportsBackground: false` 로 «말한다» — 부르는 쪽이
 *    「화면을 닫으면 끊긴다」를 사용자에게 설명할 수 있어야 하기 때문이다.
 */
function webTracker(env = globalThis) {
    let watchId = null;

    return {
        kind: 'web',
        supportsBackground: false,
        get supported() { return Boolean(env.navigator && 'geolocation' in env.navigator); },

        // 세 번째 인자(opts)는 «네이티브 전용»이다. 브라우저는 watchPosition 이
        // 알아서 프롬프트를 띄우므로 받을 것이 없다.
        start(onFix, onError) {
            if (watchId != null) return;
            watchId = env.navigator.geolocation.watchPosition(onFix, onError, GEO_OPTIONS);
        },

        stop() {
            if (watchId != null) {
                env.navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        },

        /**
         * 브라우저 권한이 바뀌면 알려준다.
         *
         * 🔴 **한 번 거부하면 브라우저는 다시 묻지 않는다.** 어떤 API 로도 프롬프트를
         *    되살릴 수 없고, 사용자가 주소창 아이콘에서 직접 허용으로 바꿔야 한다.
         *    그런데 바꾸고 나서도 «새로고침하기 전까지» 아무 일이 안 일어나면,
         *    사용자는 고쳤는데도 화면이 그대로라 「안 되네」로 읽는다.
         *    Permissions API 의 change 로 그 순간을 잡아 스스로 다시 시작한다.
         *
         * @returns {() => void} 해제 함수
         */
        onPermissionChange(handler) {
            let status = null;
            const onChange = () => handler(status.state);

            try {
                env.navigator.permissions?.query?.({ name: 'geolocation' })
                    .then((s) => { status = s; s.addEventListener?.('change', onChange); })
                    .catch(() => {});
            } catch {
                // 지원하지 않는 브라우저 — 새로고침 전까지는 못 잡는다.
            }

            return () => status?.removeEventListener?.('change', onChange);
        },

        /**
         * 브라우저가 «지금» 아는 권한 상태. 'granted'|'denied'|'prompt'|null
         *
         * 🔑 onPermissionChange 는 «바뀔 때»만 부른다 — 현재 값은 한 번도 주지 않는다.
         *    그래서 화면은 첫 좌표가 올 때까지(콜드 픽스면 10초 이상) 아무것도 모른 채
         *    있었다. 이건 그 «처음 한 번»을 채운다.
         */
        async readWebPermission() {
            if (!this.supported) return 'unsupported';

            try {
                const st = await env.navigator.permissions?.query?.({ name: 'geolocation' });

                return st?.state ?? null;
            } catch {
                return null;
            }
        },

        // 브라우저는 「항상 허용」을 줄 수 없다 — 탭이 죽으면 끝난다.
        // 그래서 최선이 when_in_use 이고, 그건 «사실»이다(M-5 의 foreground_only).
        async readPermission() {
            if (!this.supported) return 'services_off';

            try {
                const st = await env.navigator.permissions?.query?.({ name: 'geolocation' });
                if (st?.state === 'granted') return 'when_in_use';
                if (st?.state === 'denied') return 'denied';

                return 'not_determined';
            } catch {
                return null;
            }
        },
    };
}

export function createLocationSharer(config = {}, env = globalThis) {
    const projectId = config.projectId;
    const onChange = typeof config.onChange === 'function' ? config.onChange : () => {};

    const state = {
        sharing: false,
        // 🔴 동의가 없어 «수집이 막힌» 상태. null 이면 정상.
        //    화면은 이 값을 보고 동의 UI 를 띄운다 — 「전송 실패」로만 말하면
        //    사용자는 고칠 방법을 모른다.
        consentRequired: null,
        // 🔴 **초기값은 «모름»(null)이다.** 예전 초기값이 'unsupported' 여서, 아직
        //    아무것도 확인하기 «전»에 화면이 「위치를 쓸 수 없는 브라우저입니다」라는
        //    붉은 단정을 띄웠다 — 실기기에서 10초쯤 떠 있다 사라졌다(2026-09-01).
        //    「모른다」를 「불가능하다」로 말하면 사용자는 멀쩡한 기기를 의심한다.
        //    (decidePermissionStep 이 permission===null 을 그냥 두는 것과 같은 이유다.)
        permission: null, // null(모름) | unsupported | prompt | granted | denied
        sentCount: 0,
        lastSentAt: null,
        lastAccuracy: null,
        bufferedCount: 0,
        error: null,
    };

    // 위치 «취득»은 트래커가 한다. 이 파일은 «언제 보내는가»만 갖는다 (02 §3-3).
    //
    // 🔑 **주입받되, 기본값이 «네이티브 우선»이다.** 앱에서는 번들이 네이티브 트래커를
    //    window 로 건네고(app.js), 웹이거나 그 앱이 백그라운드 위치를 모르면 null 이라
    //    웹 구현으로 떨어진다. 이 파일은 Capacitor 를 영영 모른다 —
    //    `__gps119Bridge` 는 주입 지점의 이름일 뿐이다.
    //
    // 🔴 **기본값을 여기 둔 이유.** 예전에는 호출부가 `tracker:` 를 «직접» 넘겨야 했고,
    //    세 곳 중 활동 화면 하나만 넘기고 있었다. 그래서 지령·출동 화면과 셸 전송기는
    //    「항상 허용」 권한이 있어도 웹 watchPosition 으로 돌았고, **화면을 끄는 순간
    //    위치가 끊겼다** — 실서버에서 출동 수락 후 5분 만에 멈춘 것이 그것이다
    //    (2026-09-01). 배선을 호출부마다 반복하면 다음에 또 빠뜨린다.
    const tracker = config.tracker
        || env.__gps119Bridge?.locationTracker
        || webTracker(env);
    let lastSent = null;   // {lat,lng,t}
    let buffer = [];       // 실패 payload 보관
    let backoffUntil = 0;  // 429 백오프 만료 시각(ms epoch). 0 이면 백오프 아님

    function emit() { onChange({ ...state }); }

    /**
     * @returns {{consentRequired: Array}|null} 동의가 필요하면 그 항목들, 아니면 null
     */
    async function patchSharing(on) {
        const axios = await waitForAxios();
        if (!axios) return null;
        try {
            await axios.patch(`/api/events/${projectId}/sharing`, { sharing_location: on },
                { headers: { Accept: 'application/json' } });

            return null;
        } catch (e) {
            // 🔴 **동의 거절만은 삼키지 않는다.** 나머지 실패는 다음 ping 이 캐시를
            //    갱신해 주지만, 동의는 사용자가 행동해야만 풀린다 — 그대로 진행하면
            //    watch 만 돌면서 ping 이 전부 막히고 화면은 「공유 중」이라 말한다.
            const items = consentRequiredItems(e);
            if (items) return { consentRequired: items };

            // 토글 실패해도 로컬 watch 상태는 사용자의 의도대로 진행(다음 ping에서 백엔드가 캐시 갱신)
            console.warn('[locationShare] sharing 토글 실패', e);

            return null;
        }
    }

    async function postPing(payload) {
        const axios = await waitForAxios();
        if (!axios) throw new Error('no-axios');
        await axios.post(`/api/events/${projectId}/location`, payload,
            { headers: { Accept: 'application/json' } });
    }

    // 429(Too Many Requests) 여부. axios 는 e.response.status, fetch 폴백은 e.status.
    function isThrottled(e) {
        return (e && (e.response?.status === 429 || e.status === 429)) || false;
    }

    /**
     * 서버가 「약관 동의가 필요하다」고 했는가 (409).
     *
     * 🔑 429 와 «다르게» 다뤄야 한다. 429 는 기다리면 풀리지만 이건 사용자가
     *    행동해야 풀린다 — 기다리며 재시도하면 영원히 안 된다.
     *
     * @returns {Array|null} 동의해야 할 항목들. 해당 없으면 null.
     */
    function consentRequiredItems(e) {
        const res = e?.response ?? e;
        if (res?.status !== 409) return null;

        const errors = res.data?.errors ?? res.errors;
        if (errors?.code !== 'consent_required') return null;

        return Array.isArray(errors.items) ? errors.items : [];
    }

    // 적응형: 보낼지 판단
    function shouldSend(lat, lng) {
        // 백오프 중에는 전송하지 않는다(버퍼에는 계속 쌓여 창이 끝나면 복구된다)
        if (Date.now() < backoffUntil) return false;
        if (!lastSent) return true;
        const elapsed = Date.now() - lastSent.t;
        const moved = distanceM(lastSent.lat, lastSent.lng, lat, lng);
        if (moved > MOVE_THRESHOLD_M && elapsed >= MOVE_INTERVAL_MS) return true;
        if (elapsed >= STATIONARY_INTERVAL_MS) return true;
        return false;
    }

    async function flushBuffer() {
        if (buffer.length === 0) return;
        const pending = buffer.slice().sort((a, b) => a.recorded_at.localeCompare(b.recorded_at));
        buffer = [];
        state.bufferedCount = 0;
        for (const p of pending) {
            try {
                await postPing(p);
            } catch (e) {
                pushBuffer(p); // 또 실패하면 다시 보관
                // 429 면 남은 것도 어차피 막힌다 → 즉시 중단하고 백오프
                if (isThrottled(e)) {
                    backoffUntil = Date.now() + THROTTLE_BACKOFF_MS;
                    pending.slice(pending.indexOf(p) + 1).forEach(pushBuffer);
                    return;
                }
            }
        }
    }

    function pushBuffer(payload) {
        buffer.push(payload);
        if (buffer.length > MAX_BUFFER) buffer.shift(); // 가장 오래된 것 폐기(과설계 방지)
        state.bufferedCount = buffer.length;
    }

    async function handlePosition(pos) {
        const c = pos.coords;
        const lat = c.latitude;
        const lng = c.longitude;
        state.permission = 'granted';

        if (!shouldSend(lat, lng)) return;

        const payload = {
            latitude: lat,
            longitude: lng,
            accuracy: clampInt(c.accuracy, 0, 65535),
            heading: c.heading != null && !isNaN(c.heading)
                ? clampInt(c.heading, 0, 359) : null,
            speed: c.speed != null && !isNaN(c.speed) ? clampInt(c.speed, 0, 65535) : null,
            recorded_at: new Date(pos.timestamp || Date.now()).toISOString(),
        };

        try {
            await flushBuffer();          // 밀린 실패분 먼저 복구
            // 복구 중에 429 를 만나 백오프가 걸렸다면 현재 건도 보내지 않는다.
            // (이 확인이 없으면 서버가 "너무 빠르다"고 한 직후에 한 건을 더 때린다)
            if (Date.now() < backoffUntil) {
                pushBuffer(payload);
                state.error = '전송 속도 조절 중';
                emit();
                return;
            }
            await postPing(payload);
            lastSent = { lat, lng, t: Date.now() };
            state.sentCount += 1;
            state.lastSentAt = payload.recorded_at;
            state.lastAccuracy = payload.accuracy;
            state.error = null;
        } catch (e) {
            // 🔴 동의가 없으면 재시도해도 영원히 막힌다. 버퍼에 쌓지 않고 멈춘다 —
            //    쌓아두면 나중에 «동의 전 위치»가 한꺼번에 올라간다.
            const items = consentRequiredItems(e);
            if (items) {
                state.consentRequired = items;
                state.error = null;
                stopWatch();
                state.sharing = false;
                emit();

                return;
            }

            // 조용히 재시도: 버퍼에 보관 후 다음 tick
            pushBuffer(payload);
            if (isThrottled(e)) {
                // 서버가 "너무 빠르다"고 했다 — 즉시 재시도하면 악화된다
                backoffUntil = Date.now() + THROTTLE_BACKOFF_MS;
                state.error = '전송 속도 조절 중';
            } else {
                state.error = '전송 지연(재시도 중)';
            }
        }
        emit();
    }

    function handleError(err) {
        if (err && err.code === err.PERMISSION_DENIED) state.permission = 'denied';
        state.error = geoErrorMessage(err, tracker.kind === 'native');
        emit();
    }

    let stopPermissionWatch = null;

    /**
     * 권한이 «허용»으로 바뀌면 스스로 다시 시작한다.
     *
     * 🔑 사용자는 주소창에서 고친 뒤 «화면이 저절로 살아나기»를 기대한다.
     *    새로고침을 요구하면 대부분 그냥 「안 되는구나」로 끝난다.
     */
    /**
     * 권한의 «현재» 값을 한 번 읽어 초기 공백을 메운다.
     *
     * 🔑 **첫 좌표가 이미 판정을 바꿔놨으면 덮지 않는다.** 늦게 도착한 초기 읽기가
     *    최신 상태를 되돌리면, 화면이 「공유 중」에서 「확인 중」으로 거꾸로 간다.
     * 🔑 네이티브 트래커에는 이 메서드가 없다 — 그쪽은 화면이 osPermission 을 따로
     *    읽는다(M-5). 없으면 조용히 아무것도 하지 않는다.
     */
    function primePermission() {
        if (typeof tracker.readWebPermission !== 'function') return;

        tracker.readWebPermission()
            .then((next) => {
                if (!next || state.permission != null) return;
                state.permission = next;
                emit();
            })
            .catch(() => {});
    }

    function watchPermission() {
        if (stopPermissionWatch || typeof tracker.onPermissionChange !== 'function') return;

        stopPermissionWatch = tracker.onPermissionChange((next) => {
            if (next === 'granted') {
                state.permission = 'granted';
                state.error = null;
                stopWatch();
                startWatch();
            } else if (next === 'denied') {
                state.permission = 'denied';
                stopWatch();
            }
            emit();
        });
    }

    function startWatch(opts) {
        tracker.start(handlePosition, handleError, opts);
    }

    function stopWatch() {
        tracker.stop();
    }

    return {
        get state() { return { ...state }; },

        // 공유 시작: sharing on(PATCH) + watch 시작
        async enable(opts) {
            if (!tracker.supported) {
                state.permission = 'unsupported';
                state.error = '이 기기에서는 위치를 쓸 수 없습니다.';
                emit();
                return;
            }
            state.sharing = true;
            state.consentRequired = null;
            emit();

            const blocked = await patchSharing(true);
            if (blocked) {
                // 켜지 않는다. 여기서 watch 를 시작하면 위치를 «수집»하게 되고,
                // 그건 정확히 동의 없이 하면 안 되는 일이다.
                state.sharing = false;
                state.consentRequired = blocked.consentRequired;
                emit();

                return;
            }

            startWatch(opts);
            primePermission();
            watchPermission();
        },

        // 공유 «이어받기»: 서버에 이미 sharing_location=true 인 사람만 부른다.
        // enable() 과 달리 PATCH 를 보내지 않는다 — 셸이 매 페이지마다 공유를 다시
        // 켜버리면, 사용자가 끈 공유가 화면을 옮기는 것만으로 되살아난다.
        resume() {
            if (!tracker.supported) {
                state.permission = 'unsupported';
                emit();
                return;
            }
            state.sharing = true;
            emit();
            startWatch();
            primePermission();
        },

        // 공유 중지: watch 중지 + sharing off(PATCH)
        async disable() {
            state.sharing = false;
            lastSent = null;
            // 🔑 **껐으면 이전 오류도 지운다.** 안 지우면 「공유 중지됨」 아래에
            //    「위치 권한이 거부되었습니다」가 계속 남아, 끈 사람에게 고칠 것도 없는
            //    경고를 보여준다(2026-08-31 실기기).
            state.error = null;
            stopWatch();
            emit();
            await patchSharing(false);
        },

        /**
         * 취득만 다시 시작한다. **공유 상태(sharing)는 건드리지 않는다.**
         *
         * 🔴 예전에는 권한 승격을 disable()+enable() 로 했다. 그러면 sharing 이 잠깐
         *    «꺼짐»으로 서버에 기록되고(PATCH 2회), 관제는 그 순간 이 사람을 「공유
         *    꺼짐」으로 본다. 구조 앱에서 버튼 한 번에 위치가 끊기면 안 된다.
         *    재시작마다 위치가 몰려 나가 스로틀(429)에 걸리는 부작용도 있었다.
         */
        async restart(opts) {
            stopWatch();
            startWatch(opts);
        },

        async toggle() {
            return this.state.sharing ? this.disable() : this.enable();
        },

        destroy() {
            stopWatch();
            stopPermissionWatch?.();
            stopPermissionWatch = null;
        },
    };
}

/**
 * 오류 문구.
 *
 * 🔴 **웹과 앱은 «고치는 곳»이 다르다.** 앱에서 「브라우저 설정에서 허용해 주세요」라고
 *    하면 사용자는 있지도 않은 주소창을 찾는다 — 앱 웹뷰에는 브라우저 UI 가 없다.
 *    실제로 앱에서 그 문구가 떴다(2026-08-31).
 *
 * @param {boolean} isNative 앱(웹뷰) 안인가
 */
function geoErrorMessage(err, isNative = false) {
    if (!err) return '위치 오류';
    switch (err.code) {
        case err.PERMISSION_DENIED:
            return isNative
                ? '위치 권한이 꺼져 있습니다. 설정에서 허용해 주세요.'
                : '위치 권한이 꺼져 있습니다. 주소창의 위치 아이콘에서 허용해 주세요.';
        case err.POSITION_UNAVAILABLE: return '위치를 확인할 수 없습니다.';
        case err.TIMEOUT: return '위치 확인이 늦어지고 있습니다. 다시 시도합니다.';
        default: return '위치를 가져오지 못했습니다.';
    }
}

export default createLocationSharer;
