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
    // ⚠️ FirebaseMessaging 이다. '@capacitor/push-notifications' 는 iOS 에서 «APNs 토큰»을
    //    주는데, 서버는 iOS 도 FCM 경유를 전제한다(PushPlatform::usesFcm()). APNs 토큰을
    //    FCM 에 보내면 INVALID_ARGUMENT 가 오고, 서버는 그걸 「죽은 기기」로 읽어
    //    **살아 있는 아이폰을 폐기**한다 — 알림이 안 오는 데서 끝나지 않는다.
    return env.Capacitor?.Plugins?.FirebaseMessaging ?? null;
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

    // 🔑 FirebaseMessaging 은 getToken() 이 «토큰을 직접» 돌려준다.
    //    (구 플러그인은 register() 후 이벤트를 기다려야 했다 — 안 기다리면
    //     「켰다」고 표시한 뒤 서버엔 아무것도 안 보낸 상태가 됐다.)
    let token;
    try {
        ({ token } = await p.getToken());
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

    p.addListener('notificationActionPerformed', (action) => {
        const url = safePath(action?.notification?.data?.url);

        if (url) env.location.assign(url);
    });

    // 🔴 앱을 «보고 있을 때» 온 푸시는 이 리스너가 없으면 통째로 버려진다.
    //    실측 로그: `Notifying listeners for event notificationReceived` 직후
    //    `No listeners found for event notificationReceived`. 시스템 알림도 뜨지
    //    않는다 — 포그라운드에서는 OS 가 표시를 앱에 «위임»하기 때문이다.
    //
    //    구조 앱에서 「대원이 앱을 켜 두고 있었더니 배정을 못 받았다」는 최악이다.
    //    앱이 열려 있다는 이유로 더 늦게 아는 셈이 된다.
    p.addListener('notificationReceived', (event) => {
        const banner = toForegroundBanner(event);

        if (banner) showForegroundBanner(banner, env);
    });
}

/**
 * 같은 오리진의 «경로»만 통과시킨다.
 *
 * 페이로드는 서버가 넣지만 신뢰 경계 밖에서 온 값처럼 다룬다. `//evil.example` 은
 * 스킴 상대 URL 이라 `/` 로 시작하면서도 «다른 호스트»로 나간다 — 그래서 두 번째 문자까지 본다.
 *
 * @returns {string|null}
 */
export function safePath(url) {
    if (typeof url !== 'string') return null;
    if (! url.startsWith('/')) return null;
    if (url.startsWith('//')) return null;

    return url;
}

/**
 * 포그라운드 푸시 → 배너에 필요한 값만. 표시할 게 없으면 null.
 *
 * FCM 은 포그라운드에서 notification 블록을 그대로 넘겨준다. 다만 데이터 전용
 * 메시지도 있을 수 있어 제목이 없으면 data.title 로 물러난다.
 *
 * @returns {{title: string, body: string, url: string|null}|null}
 */
export function toForegroundBanner(event) {
    const n = event?.notification ?? event;
    const title = n?.title ?? n?.data?.title ?? null;

    // 제목도 본문도 없으면 «보여줄 것이 없다». 빈 배너를 띄우면 더 나쁘다.
    if (typeof title !== 'string' || title === '') return null;

    return {
        title,
        body: typeof n?.body === 'string' ? n.body : (n?.data?.body ?? ''),
        url: safePath(n?.data?.url),
    };
}

/** 배너 하나만 유지한다 — 연달아 오면 «대체»한다(알림 tag 와 같은 사고방식). */
const BANNER_ID = 'gps119-push-banner';

/**
 * 인앱 배너를 띄운다.
 *
 * 인라인 스타일을 쓰는 이유: 이 배너는 어느 페이지에서든 떠야 하는데, 페이지마다
 * 로드된 CSS 가 다르다. 클래스에 기대면 「어떤 화면에서만 안 보이는」 배너가 된다.
 *
 * 🔑 «자동으로 사라지지 않는다.» 배정 알림을 못 보고 지나치는 것보다 거슬리는 편이 낫다.
 */
export function showForegroundBanner({ title, body, url }, env = globalThis) {
    const doc = env.document;
    if (! doc?.createElement) return;

    doc.getElementById?.(BANNER_ID)?.remove?.();

    const box = doc.createElement('div');
    box.id = BANNER_ID;
    box.setAttribute('role', 'alert');
    box.style.cssText = [
        'position:fixed', 'top:0', 'left:0', 'right:0', 'z-index:2147483647',
        // iOS 노치·다이내믹 아일랜드 아래로 내린다. 안드로이드에서는 0 이라 영향이 없다.
        'margin:calc(8px + env(safe-area-inset-top)) 8px 8px',
        'padding:14px 16px', 'border-radius:16px',
        'background:#0E6E7C', 'color:#fff',
        'font-family:Pretendard,ui-sans-serif,system-ui,-apple-system,sans-serif',
        'box-shadow:0 8px 24px rgba(0,0,0,.28)',
        'display:flex', 'gap:12px', 'align-items:flex-start',
    ].join(';');

    const text = doc.createElement('div');
    text.style.cssText = 'flex:1;min-width:0';

    const h = doc.createElement('div');
    h.style.cssText = 'font-size:15px;font-weight:800;line-height:1.4;word-break:keep-all';
    h.textContent = title;

    const p = doc.createElement('div');
    p.style.cssText = 'margin-top:2px;font-size:13px;line-height:1.5;opacity:.9;word-break:keep-all';
    p.textContent = body;

    text.append(h, p);

    const close = doc.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', '알림 닫기');
    close.textContent = '✕';
    close.style.cssText = 'background:none;border:0;color:inherit;font-size:16px;padding:0 4px;cursor:pointer';
    close.addEventListener('click', (e) => {
        e.stopPropagation();
        box.remove();
    });

    box.append(text, close);

    if (url) {
        box.style.cursor = 'pointer';
        box.addEventListener('click', () => env.location.assign(url));
    }

    doc.body.appendChild(box);
}

/** 테스트 전용 — 모듈 상태를 비운다. */
export function __resetNativePushState() {
    lastToken = null;
}
