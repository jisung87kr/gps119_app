// 참가자 위치 자동공유 (FE-2.2).
//
// navigator.geolocation.watchPosition 으로 위치를 받아 적응형 주기로
// POST /api/events/{id}/location 전송. 공유 토글은 PATCH /api/events/{id}/sharing.
//
// 적응형 주기(05/04):
//   - 이동(직전 전송 위치에서 MOVE_THRESHOLD_M 초과) → 최소 MOVE_INTERVAL_MS(약 5초) 간격 전송
//   - 정지 → STATIONARY_INTERVAL_MS(약 30초) 하트비트 전송
//   백엔드 throttle(2/s)·sharing-off 스킵과 정합.
//
// 견고성(경량): 전송 실패분은 작은 버퍼(최대 2개)에 보관 후 다음 전송 시 복구 전송.

const MOVE_THRESHOLD_M = 10;        // 이동 판정 거리
const MOVE_INTERVAL_MS = 5000;      // 이동 중 최소 전송 간격
const STATIONARY_INTERVAL_MS = 30000; // 정지 중 하트비트 간격
const MAX_BUFFER = 2;               // 오프라인 실패 버퍼 상한

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

export function createLocationSharer(config = {}) {
    const projectId = config.projectId;
    const onChange = typeof config.onChange === 'function' ? config.onChange : () => {};

    const state = {
        sharing: false,
        permission: 'unsupported', // unsupported | prompt | granted | denied
        sentCount: 0,
        lastSentAt: null,
        lastAccuracy: null,
        bufferedCount: 0,
        error: null,
    };

    let watchId = null;
    let lastSent = null;   // {lat,lng,t}
    let buffer = [];       // 실패 payload 보관

    function emit() { onChange({ ...state }); }

    async function patchSharing(on) {
        const axios = await waitForAxios();
        if (!axios) return;
        try {
            await axios.patch(`/api/events/${projectId}/sharing`, { sharing_location: on },
                { headers: { Accept: 'application/json' } });
        } catch (e) {
            // 토글 실패해도 로컬 watch 상태는 사용자의 의도대로 진행(다음 ping에서 백엔드가 캐시 갱신)
            console.warn('[locationShare] sharing 토글 실패', e);
        }
    }

    async function postPing(payload) {
        const axios = await waitForAxios();
        if (!axios) throw new Error('no-axios');
        await axios.post(`/api/events/${projectId}/location`, payload,
            { headers: { Accept: 'application/json' } });
    }

    // 적응형: 보낼지 판단
    function shouldSend(lat, lng) {
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
            try { await postPing(p); }
            catch (e) { pushBuffer(p); } // 또 실패하면 다시 보관
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
            await postPing(payload);
            lastSent = { lat, lng, t: Date.now() };
            state.sentCount += 1;
            state.lastSentAt = payload.recorded_at;
            state.lastAccuracy = payload.accuracy;
            state.error = null;
        } catch (e) {
            // 조용히 재시도: 버퍼에 보관 후 다음 tick
            pushBuffer(payload);
            state.error = '전송 지연(재시도 중)';
        }
        emit();
    }

    function handleError(err) {
        if (err && err.code === err.PERMISSION_DENIED) state.permission = 'denied';
        state.error = geoErrorMessage(err);
        emit();
    }

    function startWatch() {
        if (watchId != null) return;
        watchId = navigator.geolocation.watchPosition(handlePosition, handleError, GEO_OPTIONS);
    }

    function stopWatch() {
        if (watchId != null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    return {
        get state() { return { ...state }; },

        // 공유 시작: sharing on(PATCH) + watch 시작
        async enable() {
            if (!('geolocation' in navigator)) {
                state.permission = 'unsupported';
                state.error = '이 기기는 위치 기능을 지원하지 않습니다.';
                emit();
                return;
            }
            state.sharing = true;
            emit();
            await patchSharing(true);
            startWatch();
        },

        // 공유 중지: watch 중지 + sharing off(PATCH)
        async disable() {
            state.sharing = false;
            lastSent = null;
            stopWatch();
            emit();
            await patchSharing(false);
        },

        async toggle() {
            return this.state.sharing ? this.disable() : this.enable();
        },

        destroy() {
            stopWatch();
        },
    };
}

function geoErrorMessage(err) {
    if (!err) return '위치 오류';
    switch (err.code) {
        case err.PERMISSION_DENIED: return '위치 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.';
        case err.POSITION_UNAVAILABLE: return '위치 정보를 사용할 수 없습니다.';
        case err.TIMEOUT: return '위치 확인 시간이 초과되었습니다(재시도).';
        default: return '위치 오류가 발생했습니다.';
    }
}

export default createLocationSharer;
