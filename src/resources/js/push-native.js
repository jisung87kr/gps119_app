// 앱 푸시 (FCM/APNs) — mobile-app 에픽 N3.
//
// 🔴 **앱 안에서는 웹 푸시가 원리적으로 불가능하다**(M-24). 앱 웹뷰에는 서비스워커가
//    없어서 PushSubscription 을 만들 수 없다. 그래서 앱 사용자에게 닿는 길은 이것뿐이다.
//
// 🔑 **토큰 등록은 «웹 JS»가 한다 — 셸이 서버로 직접 쏘지 않는다.**
//    셸(네이티브)에서 보낸 HTTP 에는 웹뷰의 세션 쿠키가 없어서 /api/devices 가 401 이다.
//    셸은 토큰을 웹뷰로 넘기기만 하고, 등록은 이미 로그인된 이 페이지가 한다 —
//    그래서 서버는 한 줄도 바뀌지 않는다. 원격 URL 방식(01 A안)의 이점을 그대로 쓴다.
//
// Capacitor 플러그인은 npm import 없이 `Capacitor.Plugins` 로 접근한다. 셸이 원격 URL 을
// 띄우므로 이 번들은 «앱에 들어 있지 않고», 플러그인 프록시는 셸이 주입한 전역에만 있다.

import { hasNativeCapability, nativePlatform, NativeCapability } from './native/bridge';

/** 마지막으로 받은 FCM/APNs 토큰. 해제할 때 서버에 알려줘야 한다. */
let lastToken = null;

/** 이 페이지에서 앱 푸시를 쓸 수 있는가. */
export function isNativePushSupported(env = globalThis) {
    return hasNativeCapability(NativeCapability.PUSH_TOKEN, env);
}

function plugin(env = globalThis) {
    return env.Capacitor?.Plugins?.PushNotifications ?? null;
}

/**
 * 권한 상태 → push.js 와 «같은» 어휘로 변환한다.
 * UI 가 웹/앱을 구분하지 않아도 되도록 상태 어휘를 하나로 유지한다.
 *
 * @returns {Promise<'unsupported'|'denied'|'subscribed'|'default'>}
 */
export async function nativePushStatus(env = globalThis) {
    const p = plugin(env);
    if (!p) return 'unsupported';

    const { receive } = await p.checkPermissions();

    // denied 는 앱 안에서 되돌릴 수 없다 — OS 설정으로 가야 한다.
    if (receive === 'denied') return 'denied';
    if (receive !== 'granted') return 'default';

    // 권한이 있어도 «서버가 아는가»는 별개다. 등록해 둔 토큰이 있어야 켜진 것이다.
    return lastToken ? 'subscribed' : 'default';
}

/**
 * 등록 이벤트를 한 번 기다린다.
 *
 * register() 는 즉시 반환하고 토큰은 «이벤트»로 온다. 기다리지 않으면
 * 「켰다」고 표시한 뒤 서버에는 아무것도 안 보내는 상태가 된다.
 */
function awaitToken(p, timeoutMs = 15000) {
    return new Promise((resolve, reject) => {
        let done = false;
        const finish = (fn, arg) => {
            if (done) return;
            done = true;
            fn(arg);
        };

        p.addListener('registration', (t) => finish(resolve, t?.value ?? null));
        p.addListener('registrationError', (e) => finish(reject, new Error(e?.error || 'registration-error')));

        // 응답이 영영 안 오면 버튼이 「처리 중…」에 멈춘다. 시간 제한을 둔다.
        setTimeout(() => finish(reject, new Error('timeout')), timeoutMs);

        p.register();
    });
}

/**
 * 앱 푸시 켜기. 권한 → 등록 → 토큰 수신 → 서버 등록.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
export async function enableNativePush(env = globalThis) {
    const p = plugin(env);
    if (!p) return { ok: false, reason: 'unsupported' };

    let permission = await p.checkPermissions();
    if (permission.receive !== 'granted') {
        permission = await p.requestPermissions();
    }

    if (permission.receive === 'denied') return { ok: false, reason: 'denied' };
    if (permission.receive !== 'granted') return { ok: false, reason: 'dismissed' };

    let token;
    try {
        token = await awaitToken(p);
    } catch (e) {
        return { ok: false, reason: 'registration-failed' };
    }

    if (!token) return { ok: false, reason: 'registration-failed' };

    try {
        // platform 은 셸이 보고하는 값을 쓴다 — ios 는 FCM→APNs 경유라 서버 분기가 다르다.
        await env.axios.post('/api/devices', {
            platform: nativePlatform(env),
            token,
        });
    } catch (e) {
        // 서버가 모르면 알림은 «영영» 오지 않는다. 켜진 것처럼 보이게 두지 않는다.
        return { ok: false, reason: 'server-rejected' };
    }

    lastToken = token;

    return { ok: true };
}

/**
 * 앱 푸시 끄기. 서버에서 통로를 지운다.
 *
 * OS 권한 자체는 건드리지 않는다 — 앱이 사용자의 OS 설정을 임의로 되돌리면 안 되고,
 * 「서버가 안 보낸다」로 충분하다.
 */
export async function disableNativePush(env = globalThis) {
    if (!lastToken) return { ok: true };

    try {
        await env.axios.delete('/api/devices/current', { data: { token: lastToken } });
    } catch (e) {
        return { ok: false, reason: 'server-error' };
    }

    lastToken = null;

    return { ok: true };
}

/**
 * 알림을 «탭»했을 때의 착지. 웹 푸시의 sw.js notificationclick 과 같은 역할이다.
 *
 * 페이로드의 url 은 서버가 넣는다(PushMessage::$url) — 예: /control?request=83.
 * 규약을 웹/앱이 공유하므로 딥링크 처리를 두 벌로 짜지 않는다.
 */
export function initNativePushRouting(env = globalThis) {
    const p = plugin(env);
    if (!p) return;

    p.addListener('pushNotificationActionPerformed', (action) => {
        const url = action?.notification?.data?.url;

        // 외부 주소로 튀지 않게 «같은 오리진의 경로»만 받는다. 페이로드는 서버가
        // 넣지만, 신뢰 경계 밖에서 온 값처럼 다루는 편이 안전하다.
        if (typeof url === 'string' && url.startsWith('/')) {
            env.location.assign(url);
        }
    });
}

/** 테스트 전용 — 모듈 상태를 비운다. */
export function __resetNativePushState() {
    lastToken = null;
}
