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

    // 앱을 열었다는 것 자체가 「봤다」는 뜻이다. 여기서 안 지우면 숫자가 영영 남는다.
    clearAppBadge(env);
    env.Capacitor?.Plugins?.App?.addListener?.('appStateChange', ({ isActive }) => {
        if (isActive) clearAppBadge(env);
    });

    p.addListener('notificationActionPerformed', (action) => {
        const url = safePath(action?.notification?.data?.url);

        if (url) env.location.assign(url);
    });

    // 🔴 Android 는 앱이 «떠 있으면» FCM 이 알림을 자동 표시하지 않는다. 실측 로그:
    //    `Notifying listeners for event notificationReceived` 직후
    //    `No listeners found …` — 리스너가 없으면 그대로 증발한다.
    //    「대원이 앱을 켜 두고 있었더니 배정을 못 받았다」는 뒤집힌 결과다.
    if (needsForegroundNotification(env)) {
        p.addListener('notificationReceived', (event) => {
            const spec = toForegroundNotification(event);

            if (spec) presentForeground(spec, env);
        });

        // 우리가 «올린» 알림의 탭은 FCM 이 아니라 로컬 알림 쪽으로 온다.
        // 이걸 빠뜨리면 「포그라운드에서 온 알림만 눌러도 안 움직인다」가 된다.
        localNotifications(env)?.addListener?.('localNotificationActionPerformed', (action) => {
            const url = safePath(action?.notification?.extra?.url);

            if (url) env.location.assign(url);
        });
    }
}

/**
 * 이 플랫폼에서 포그라운드 알림을 «우리가» 띄워야 하는가.
 *
 * 🔑 규칙: **OS 가 하는 일은 OS 에 맡기고, 안 하는 것만 보완한다.**
 *
 * - iOS: `presentationOptions` 에 `alert` 가 있어 OS 가 띄운다 → 손대지 않는다
 * - Android: FCM 이 포그라운드에서 «자동 표시하지 않는다» → 앱이 직접 올린다
 *
 * ⚠️ 「Android 는 대안이 없다」가 아니다. FCM 이 표시 여부를 앱에 맡기는 설계일 뿐,
 *    앱이 알림을 올리면 iOS 와 똑같이 «알림 그늘에 남는다». 네이티브 개발도
 *    필요 없다 — LocalNotifications 플러그인으로 웹 JS 에서 올린다.
 */
export function needsForegroundNotification(env = globalThis) {
    return nativePlatform(env) === 'android';
}

function localNotifications(env = globalThis) {
    return env.Capacitor?.Plugins?.LocalNotifications ?? null;
}

/**
 * 앱 아이콘 뱃지를 지운다.
 *
 * 🔑 **숫자를 정하는 건 서버, 지우는 건 앱이다.**
 *    서버는 푸시를 보낼 때마다 그 사람의 「봐야 할 미처리 신고」 수를 `aps.badge` 로
 *    찍는다(`BadgeCounter`). 그런데 그 값은 **보낸 시점 기준**이라, 다른 사람이 먼저
 *    처리하거나 본인이 다 처리해도 **다음 푸시가 올 때까지 숫자가 그대로 남는다.**
 *    앱을 열었다는 것 자체가 「봤다」는 뜻이므로 여기서 0 으로 되돌린다.
 *
 * ⚠️ 플러그인이 없는 «구버전 셸»에서는 조용히 아무것도 하지 않는다. 뱃지 하나 때문에
 *    푸시 라우팅 전체가 예외로 죽으면 지령을 놓친다 — 우선순위가 비교가 안 된다.
 */
export function clearAppBadge(env = globalThis) {
    env.Capacitor?.Plugins?.Badge?.clear?.();
}

/**
 * 셸이 만들어 둔 구조 알림 채널.
 *
 * ⚠️ **셸의 `MainActivity.createRescueNotificationChannel()` 과 문자열이 같아야 한다.**
 *    (`android/app/src/main/res/values/notification.xml`) 어긋나면 안드로이드가 조용히
 *    기본 채널로 떨어뜨려 heads-up 이 사라진다 — 오류는 나지 않는다.
 */
const RESCUE_CHANNEL_ID = 'gps119-rescue-v1';

/**
 * 포그라운드 알림을 실제로 띄운다.
 *
 * 🔑 로컬 알림을 «먼저» 시도하고, 안 되면 인앱 배너로 떨어진다. 못 뜨는 길이 둘이다:
 *
 *    ① **플러그인이 없는 구버전 셸.** 셸은 스토어 심사를 거쳐 천천히 갱신되므로
 *       현장에 남아 있다(bridge.js 의 기능 협상과 같은 이유).
 *    ② **OS 알림이 꺼져 있음.** 그러면 `schedule()` 이 «거부»한다
 *       (`"Notifications not enabled on this device"`). 토큰은 알림 권한 없이도
 *       발급되고 사용자가 나중에 권한을 끌 수도 있어서, 서버는 계속 보낸다.
 *
 *    ②를 안 받으면 **화면을 «보고 있는데도» 아무것도 안 뜨고** 삼켜진 거부만 남는다.
 *    배너는 DOM 이라 OS 권한과 무관하게 뜬다 — 정확히 이 경우에 유일하게 남는 수단이다.
 */
function presentForeground(spec, env = globalThis) {
    const ln = localNotifications(env);

    if (! ln?.schedule) {
        showForegroundBanner(spec, env);

        return;
    }

    Promise.resolve(ln.schedule({
        notifications: [{
            id: spec.id,
            title: spec.title,
            body: spec.body,
            channelId: RESCUE_CHANNEL_ID,
            extra: { url: spec.url },
        }],
    })).catch(() => showForegroundBanner(spec, env));
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
 * 포그라운드 푸시 → 띄울 알림에 필요한 값만. 표시할 게 없으면 null.
 *
 * FCM 은 포그라운드에서 notification 블록을 그대로 넘겨준다. 다만 데이터 전용
 * 메시지도 있을 수 있어 제목이 없으면 data.title 로 물러난다.
 *
 * @returns {{id: number, title: string, body: string, url: string|null}|null}
 */
export function toForegroundNotification(event) {
    const n = event?.notification ?? event;
    const title = n?.title ?? n?.data?.title ?? null;

    // 제목도 본문도 없으면 «보여줄 것이 없다». 빈 알림을 띄우면 더 나쁘다.
    if (typeof title !== 'string' || title === '') return null;

    // 서버가 보낸 tag 를 그대로 «대체 키»로 쓴다(PushMessage::$tag). tag 가 없으면
    // 딥링크 → 제목 순으로 물러난다. 셋 다 없을 일은 위 검사에서 걸러진다.
    const tag = (typeof n?.tag === 'string' && n.tag) ? n.tag : (n?.data?.url ?? title);

    return {
        id: notificationId(tag),
        title,
        body: typeof n?.body === 'string' ? n.body : (n?.data?.body ?? ''),
        url: safePath(n?.data?.url),
    };
}

/**
 * tag → 안정적인 32비트 양의 정수.
 *
 * 🔑 LocalNotifications 는 «id» 가 같으면 대체한다. 서버의 tag 규약(같은 신고 =
 *    같은 tag)을 그대로 잇기 위해 tag 를 id 로 접는다 — 안 그러면 포그라운드에서만
 *    같은 신고 알림이 쌓이고, 쌓이면 안드로이드가 그룹으로 묶어 «그룹 요약 줄»이
 *    생긴다. 그 줄에는 extra 가 없어서 누르면 딥링크가 사라진다(2026-08-09 실측).
 *
 * 0 은 피한다 — 일부 안드로이드 버전이 id=0 알림을 취소 대상으로 다룬다.
 */
export function notificationId(tag) {
    let hash = 0;

    for (let i = 0; i < tag.length; i += 1) {
        hash = (Math.imul(hash, 31) + tag.charCodeAt(i)) | 0;
    }

    return Math.abs(hash) || 1;
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
