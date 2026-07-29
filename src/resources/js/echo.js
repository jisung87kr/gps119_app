import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Laravel Echo + Reverb 초기화 (FE-0.1)
 *
 * Reverb 는 pusher 프로토콜과 호환되므로 broadcaster 는 'reverb'(내부적으로 pusher-js 사용).
 * 접속 정보는 DevOps 가 세팅한 VITE_REVERB_* 환경변수를 그대로 사용한다.
 *   - VITE_REVERB_HOST=localhost
 *   - VITE_REVERB_PORT=9055   (Apache WS 프록시 → Reverb 데몬)
 *   - VITE_REVERB_APP_KEY / VITE_REVERB_SCHEME
 *
 * 인가가 필요한 private/presence 채널은 /broadcasting/auth 로 인가 요청이 나간다(SPEC-05a).
 */

window.Pusher = Pusher;

let echoInstance = null;

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

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const forceTLS = scheme === 'https';

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
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
