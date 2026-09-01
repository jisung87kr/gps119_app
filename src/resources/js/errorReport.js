// 웹뷰 JS 에러 수집 (M-16).
//
// 🔴 **지금까지 앱 웹뷰에서 JS 에러가 나면 아무 데도 안 남았다.** 셸은 원격 URL 을
//    띄우므로 콘솔을 볼 방법이 없고(사파리 인스펙터를 붙이지 않는 한), 그래서
//    「카드가 안 보인다」 같은 증상을 화면에 진단 문자열을 그려서 쫓았다.
//
// 🔑 **판정과 전송을 나눈다.** 「이 에러를 보낼 것인가」는 순수 함수라 Vitest 가 지키고,
//    실제 전송만 부작용이다. 이 게이트가 없으면 렌더 루프 안의 에러 하나가 초당 수백
//    건을 올려보내 서버 스로틀(429)을 먹고, 정작 다른 화면의 에러가 묻힌다.

/** 한 페이로드에 담는 문자열 상한. 서버 검증값과 맞춰 둔다. */
const LIMITS = { message: 500, source: 300, stack: 2000, url: 500 };

function clip(value, max) {
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (!trimmed) return null;

    return trimmed.length > max ? trimmed.slice(0, max) : trimmed;
}

function toInt(value) {
    return Number.isFinite(value) ? Math.trunc(value) : null;
}

/**
 * 브라우저 이벤트를 서버가 받는 모양으로 «경계에서» 바꾼다.
 *
 * 🔑 여기서 한 번만 검증한다. 안쪽에서 다시 `if (!x)` 를 뿌리지 않는다.
 *
 * @returns {object|null} 보낼 것이 없으면 null
 */
export function normalizeError(event, { kind = 'error' } = {}) {
    if (!event || typeof event !== 'object') return null;

    // unhandledrejection 은 reason 에, error 이벤트는 error/message 에 들어온다.
    const reason = kind === 'unhandledrejection' ? event.reason : event.error;

    const message =
        clip(reason?.message, LIMITS.message)
        ?? clip(typeof reason === 'string' ? reason : null, LIMITS.message)
        ?? clip(event.message, LIMITS.message);

    // 메시지가 없으면 보내봐야 로그에 빈 줄만 남는다.
    if (!message) return null;

    return {
        kind,
        message,
        source: clip(event.filename, LIMITS.source),
        line: toInt(event.lineno),
        column: toInt(event.colno),
        stack: clip(reason?.stack, LIMITS.stack),
    };
}

/**
 * 같은 에러의 «반복»과 전체 폭주를 막는 게이트.
 *
 * 🔑 시계를 주입받는다. 안 그러면 가짜 타이머로 검증할 수 없다.
 *
 * @param {{maxTotal?: number, maxPerKey?: number, windowMs?: number}} opts
 */
export function createReportGate({ maxTotal = 10, maxPerKey = 3, windowMs = 60_000 } = {}) {
    /** @type {Map<string, number[]>} 키별 전송 시각 */
    const seen = new Map();
    let total = [];

    const fresh = (times, now) => times.filter((t) => now - t < windowMs);

    return {
        /**
         * @param {string} key 같은 에러로 볼 기준
         * @param {number} now epoch ms
         */
        allow(key, now) {
            total = fresh(total, now);
            if (total.length >= maxTotal) return false;

            const times = fresh(seen.get(key) ?? [], now);
            if (times.length >= maxPerKey) {
                seen.set(key, times);

                return false;
            }

            times.push(now);
            seen.set(key, times);
            total.push(now);

            return true;
        },
    };
}

/** 같은 에러인지 가르는 기준. 줄·열까지 보면 소스맵 없는 번들에서 전부 달라진다. */
export function reportKey(payload) {
    return [payload.kind, payload.message, payload.source ?? '', payload.line ?? ''].join('|');
}

/**
 * 전역 에러 핸들러를 붙인다 (부작용).
 *
 * ⚠️ **app.js 의 «맨 처음»에서 부른다.** 이 줄보다 먼저 난 에러는 못 잡는다 —
 *    레이아웃의 인라인 모듈이 먼저 도는 화면이 그렇다. 전부는 못 잡는다는 뜻이고,
 *    그래도 지금(아무것도 못 잡는다)보다는 낫다.
 *
 * 🔑 **보고가 실패해도 삼킨다.** 에러 보고가 앱을 멈추면 본말이 전도된다.
 *    다만 삼키는 곳은 여기 «한 곳»이고, 콘솔에는 남긴다.
 *
 * @param {object} opts
 * @param {Window} opts.env
 * @param {(payload: object) => void} [opts.send] 주입용(테스트)
 * @param {() => number} [opts.now] 주입용(테스트)
 * @param {() => object} [opts.context] 매 보고에 덧붙일 값
 */
export function initErrorReporting({
    env = globalThis,
    endpoint = '/api/client-errors',
    send = null,
    now = () => Date.now(),
    context = () => ({}),
    gate = createReportGate(),
} = {}) {
    if (typeof env?.addEventListener !== 'function') return null;

    const post = send ?? ((payload) => {
        // 🔴 **CSRF 토큰을 실어야 한다.** Sanctum 이 같은 오리진 요청을 stateful 로
        //    처리해서 api 라우트에도 토큰을 요구한다 — 없으면 419 로 조용히 버려진다.
        //    (실제로 그렇게 «보내는데 아무것도 안 남는» 상태를 한 번 겪었다.)
        //    axios 는 이걸 알아서 하지만 여기서는 keepalive 때문에 fetch 를 쓴다.
        const token = env.document
            ?.querySelector?.('meta[name="csrf-token"]')
            ?.getAttribute?.('content');

        // keepalive: 페이지를 떠나는 중에 난 에러도 전송을 마친다.
        env.fetch?.(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify(payload),
            keepalive: true,
        })?.catch?.(() => {});
    });

    const handle = (event, kind) => {
        try {
            const payload = normalizeError(event, { kind });
            if (!payload) return;
            if (!gate.allow(reportKey(payload), now())) return;

            post({ ...payload, ...context(), url: clip(env.location?.href, LIMITS.url) });
        } catch (e) {
            // 보고 «경로»가 깨진 경우. 여기서 다시 보고하면 무한루프다.
            env.console?.warn?.('[gps119] 에러 보고 실패', e);
        }
    };

    const onError = (event) => handle(event, 'error');
    const onRejection = (event) => handle(event, 'unhandledrejection');

    env.addEventListener('error', onError);
    env.addEventListener('unhandledrejection', onRejection);

    return () => {
        env.removeEventListener('error', onError);
        env.removeEventListener('unhandledrejection', onRejection);
    };
}
