// 웹 푸시 구독 (mobile-app 에픽 N1).
//
// 서버(PushService)는 «닿는 통로»만 알면 되고, 그 통로를 만드는 게 여기다.
// 브라우저 구독 → POST /api/devices 로 등록. 해제는 역순.
//
// 🔑 브라우저 구독과 서버 등록은 «둘 다» 성공해야 의미가 있다.
//    한쪽만 남으면 조용히 알림이 안 온다 — 구독은 있는데 서버가 모르거나,
//    서버는 아는데 브라우저가 구독을 지웠거나. 그래서 실패 시 되돌린다.

// 🔑 앱 안에서는 «전부» 네이티브 경로로 넘긴다. 앱 웹뷰에는 서비스워커가 없어
//    아래 웹 경로가 전부 불가능하기 때문이다(M-24). 분기를 UI 가 아니라 여기서 하는 이유는
//    push-toggle.js 가 「상태 판단은 pushStatus() 하나로 모은다」를 전제로 쓰이기 때문 —
//    UI 가 자체 판단을 갖기 시작하면 «브라우저는 거부인데 화면은 켜짐»이 생긴다.

import {
    isNativePushSupported, nativePushStatus, enableNativePush, disableNativePush,
    syncNativePushOwner, currentUserId,
} from './push-native';

/**
 * 이 브라우저의 구독을 «누구» 계정으로 등록했는가 — 사용자 id.
 *
 * 🔴 브라우저 구독은 계정이 아니라 브라우저에 묶인다 (2026-09-07 현장). A 가 켠 PC 에서 B 가
 *    로그인하면 구독은 그대로 있어 화면은 「알림 받는 중」인데 서버 등록은 A 소유다 —
 *    B 의 지령이 A 에게 간다. 그래서 주인을 적어 두고, 다르면 같은 구독을 B 로 다시 등록한다
 *    (POST /api/devices 는 token_hash 기준 멱등이라 주인이 넘어온다). 기록이 없는데 구독이 있으면
 *    (이 변경 전에 켠 브라우저) 한 번 다시 등록해 주인을 채운다.
 */
const WEB_OWNER_KEY = 'gps119.push.web.owner';

function readWebOwner(env) {
    try {
        return env.localStorage?.getItem(WEB_OWNER_KEY) ?? null;
    } catch (e) {
        return null;
    }
}

function writeWebOwner(env, owner) {
    try {
        if (owner) env.localStorage?.setItem(WEB_OWNER_KEY, String(owner));
        else env.localStorage?.removeItem(WEB_OWNER_KEY);
    } catch (e) {
        // 저장소가 막힌 환경. 다음 페이지에서 한 번 더 등록될 뿐이다(멱등).
    }
}

/** VAPID 공개키는 서버가 meta 태그로 심는다(공개키라 노출되어도 무방). */
export function vapidPublicKey(doc = document) {
    return doc.querySelector('meta[name="vapid-public-key"]')?.content || '';
}

/**
 * base64url → Uint8Array. applicationServerKey 가 요구하는 형식.
 */
export function urlBase64ToUint8Array(base64Url) {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);

    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

/**
 * 구독 객체 → 서버가 받는 형태.
 * endpoint 가 곧 «토큰»이고, keys 가 암호화용 공개키다.
 */
export function subscriptionToPayload(subscription) {
    const json = subscription.toJSON();

    return {
        platform: 'web',
        token: json.endpoint,
        keys: {
            p256dh: json.keys?.p256dh,
            auth: json.keys?.auth,
        },
    };
}

/**
 * 현재 상태. UI 가 «무엇을 보여줄지» 정하는 단일 판단 지점.
 *
 * @returns {Promise<'unsupported'|'denied'|'subscribed'|'default'>}
 */
export async function pushStatus(env = globalThis) {
    if (isNativePushSupported(env)) return nativePushStatus(env);

    if (!isSupported(env)) return 'unsupported';

    // denied 는 페이지에서 되돌릴 수 없다(브라우저 설정에서 직접 풀어야 한다).
    // 그 상태에서 «알림 켜기» 버튼을 보여주면 눌러도 아무 일이 없는 것처럼 보인다.
    if (env.Notification.permission === 'denied') return 'denied';

    const registration = await env.navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    if (!existing) return 'default';

    // 구독이 있어도 «다른 사람» 것으로 등록돼 있으면 이 사람에게는 꺼진 것이다.
    // 「켜기」를 누르면 enablePush 가 같은 구독을 이 사람으로 다시 등록한다.
    const owner = readWebOwner(env);
    const me = currentUserId(env);
    if (owner && me && owner !== me) return 'default';

    return 'subscribed';
}

/**
 * 로그인한 사람이 바뀌었으면 이 브라우저의 구독을 «그 사람» 것으로 다시 등록한다.
 * 앱 안에서는 네이티브 쪽(syncNativePushOwner)으로 넘긴다. 페이지마다 한 번 부른다(app.js).
 *
 * 🔑 구독이 없으면 아무것도 하지 않는다 — 켤지는 사용자가 정한다. 권한이 없어도 마찬가지.
 *
 * @returns {Promise<{ok: boolean, changed: boolean, reason?: string}>}
 */
export async function syncPushRegistration(env = globalThis) {
    if (isNativePushSupported(env)) return syncNativePushOwner(env);

    if (!isSupported(env)) return { ok: false, changed: false, reason: 'unsupported' };
    if (env.Notification.permission !== 'granted') return { ok: true, changed: false };

    const me = currentUserId(env);
    if (!me) return { ok: true, changed: false };

    const registration = await env.navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
        writeWebOwner(env, null);

        return { ok: true, changed: false };
    }

    if (readWebOwner(env) === me) return { ok: true, changed: false };

    try {
        await env.axios.post('/api/devices', subscriptionToPayload(subscription));
    } catch (e) {
        return { ok: false, changed: false, reason: 'server-rejected' };
    }

    writeWebOwner(env, me);

    return { ok: true, changed: true };
}

export function isSupported(env = globalThis) {
    return Boolean(
        env.navigator?.serviceWorker
        && env.PushManager
        && env.Notification
    );
}

/**
 * 알림 켜기. 권한 요청 → 브라우저 구독 → 서버 등록.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
export async function enablePush(env = globalThis) {
    if (isNativePushSupported(env)) return enableNativePush(env);

    if (!isSupported(env)) {
        return { ok: false, reason: 'unsupported' };
    }

    const key = vapidPublicKey(env.document);
    if (!key) {
        // 서버에 VAPID 키가 없으면 구독은 성공해도 발송이 불가능하다.
        // 「켰는데 안 온다」로 가지 않도록 여기서 멈춘다.
        return { ok: false, reason: 'not-configured' };
    }

    const permission = await env.Notification.requestPermission();
    if (permission !== 'granted') {
        return { ok: false, reason: permission === 'denied' ? 'denied' : 'dismissed' };
    }

    const registration = await env.navigator.serviceWorker.ready;

    // 이미 구독이 있으면 그대로 쓴다. 다시 구독하면 endpoint 가 바뀌어
    // 서버에 죽은 통로가 하나 더 생긴다.
    const subscription = await registration.pushManager.getSubscription()
        ?? await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(key),
        });

    try {
        await env.axios.post('/api/devices', subscriptionToPayload(subscription));
    } catch (e) {
        // 서버가 거절했는데 브라우저 구독만 남기면, 사용자에게는 «켜진 것처럼»
        // 보이면서 영영 알림이 오지 않는다. 되돌린다.
        await subscription.unsubscribe().catch(() => {});

        return { ok: false, reason: 'server-rejected' };
    }

    writeWebOwner(env, currentUserId(env));

    return { ok: true };
}

/**
 * 알림 끄기. 서버 해제 → 브라우저 구독 해제.
 *
 * 순서가 반대면(브라우저 먼저) 서버 해제가 실패했을 때 endpoint 를 잃어버려
 * 서버에 죽은 통로가 영구히 남는다.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
export async function disablePush(env = globalThis) {
    if (isNativePushSupported(env)) return disableNativePush(env);

    if (!isSupported(env)) {
        return { ok: false, reason: 'unsupported' };
    }

    const registration = await env.navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
        return { ok: true };
    }

    try {
        await env.axios.delete('/api/devices/current', {
            data: { token: subscription.toJSON().endpoint },
        });
    } catch (e) {
        return { ok: false, reason: 'server-error' };
    }

    await subscription.unsubscribe();
    writeWebOwner(env, null);

    return { ok: true };
}
