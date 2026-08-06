import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Laravel Echo + Reverb 초기화 (FE-0.1)
 *
 * Reverb 는 pusher 프로토콜과 호환되므로 broadcaster 는 'reverb'(내부적으로 pusher-js 사용).
 * 인가가 필요한 private/presence 채널은 /broadcasting/auth 로 인가 요청이 나간다(SPEC-05a).
 *
 * 🔑 접속 주소는 «현재 페이지»에서 유도한다. VITE_REVERB_* 는 «명시 override» 일 뿐이다.
 *    Apache 가 같은 오리진에서 /app 을 reverb 로 역프록시하므로(docker/apache/common.conf)
 *    WS 는 언제나 페이지와 같은 host·port·scheme 로 붙으면 된다.
 *
 *    예전엔 VITE_REVERB_HOST 를 .env 에 «박아» 뒀는데 그게 두 번 사고를 냈다:
 *      1. 노트북 LAN IP 가 바뀌자 실기기에서 실시간이 죽었다. VITE_ 는 빌드 타임에
 *         박히므로 재빌드 전까지 안 고쳐지고, 증상은 「화면은 뜨는데 연결중」뿐이라
 *         원인을 Apache·방화벽·Reverb 로 한참 찾게 된다.
 *      2. https 를 켜자 «혼합 콘텐츠»로 아예 차단됐다 — https 페이지에서 ws:// 는 못 쓴다.
 *    둘 다 「접속 주소를 두 곳(주소창·빌드산출물)에 적어 뒀다」는 한 가지 원인이다.
 */

// 브라우저에서만 심는다. 가드가 없으면 node(Vitest)에서 import 하는 순간 터져
// resolveReverbConfig 를 «단위»로 검증할 수 없게 된다.
if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
}

let echoInstance = null;

/**
 * Echo 접속 설정을 결정한다. **순수 함수** — 테스트에서 가짜 env·location 을 넣는다.
 *
 * @param {Record<string, string|undefined>} env      import.meta.env 상당
 * @param {{protocol: string, hostname: string, port: string}} loc  window.location 상당
 */
export function resolveReverbConfig(env = {}, loc = {}) {
    // scheme: override > 페이지 프로토콜 > http
    const pageScheme = typeof loc.protocol === 'string' ? loc.protocol.replace(':', '') : '';
    const scheme = env.VITE_REVERB_SCHEME || pageScheme || 'http';
    const forceTLS = scheme === 'https';

    // host: override > 페이지 호스트. 페이지 호스트가 비면(파일 열기 등) localhost 로 떨어진다.
    const host = env.VITE_REVERB_HOST || loc.hostname || 'localhost';

    // port: override > 페이지 포트 > scheme 기본포트.
    //       location.port 는 기본포트(80/443)일 때 빈 문자열이라 여기서 되살린다.
    const port = Number(env.VITE_REVERB_PORT || loc.port || (forceTLS ? 443 : 80));

    return { scheme, forceTLS, host, port };
}

/**
 * Echo 싱글턴 생성. 이미 만들어졌으면 재사용한다.
 * Reverb 접속 실패 시에도 throw 하지 않고 인스턴스를 반환(연결 상태는 onConnectionStateChange 로 감지).
 */
export function createEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    // 크로스-번들 싱글턴: app.js 와 control/main.js 는 별도 번들이라 모듈 스코프
    // echoInstance 가 공유되지 않는다. 한 페이지에 둘 다 로드돼도 Echo(=WS 연결)가
    // 두 번 생기지 않도록 이미 초기화된 window.Echo 를 재사용한다.
    if (typeof window !== 'undefined' && window.Echo) {
        echoInstance = window.Echo;
        return echoInstance;
    }

    const { forceTLS, host, port } = resolveReverbConfig(import.meta.env, window.location);

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echoInstance;
    return echoInstance;
}

/**
 * 웹소켓 연결 상태 변화를 구독하는 헬퍼.
 * WS 가 끊기거나(unavailable/failed) 끝내 못 붙으면 onDown 콜백으로 폴링 폴백을 트리거한다(R5).
 *
 * @param {(state: string) => void} onUp   연결됨(connected)
 * @param {(state: string) => void} onDown 끊김/실패(unavailable|failed|disconnected)
 */
export function onConnectionStateChange(onUp, onDown) {
    const echo = createEcho();
    // pusher-js 내부 커넥터 접근 (reverb=pusher 프로토콜)
    const connector = echo?.connector?.pusher?.connection;
    if (!connector) {
        // 커넥터를 못 잡으면 안전하게 폴백으로 간주
        onDown?.('unavailable');
        return;
    }

    connector.bind('state_change', ({ current }) => {
        if (current === 'connected') {
            onUp?.(current);
        } else if (['unavailable', 'failed', 'disconnected'].includes(current)) {
            onDown?.(current);
        }
    });
}

export default createEcho;
