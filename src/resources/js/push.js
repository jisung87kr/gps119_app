// 웹 푸시 구독 (mobile-app 에픽 N1).
//
// 서버(PushService)는 «닿는 통로»만 알면 되고, 그 통로를 만드는 게 여기다.
// 브라우저 구독 → POST /api/devices 로 등록. 해제는 역순.
//
// 🔑 브라우저 구독과 서버 등록은 «둘 다» 성공해야 의미가 있다.
//    한쪽만 남으면 조용히 알림이 안 온다 — 구독은 있는데 서버가 모르거나,
//    서버는 아는데 브라우저가 구독을 지웠거나. 그래서 실패 시 되돌린다.

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
    if (!isSupported(env)) return 'unsupported';

    // denied 는 페이지에서 되돌릴 수 없다(브라우저 설정에서 직접 풀어야 한다).
    // 그 상태에서 «알림 켜기» 버튼을 보여주면 눌러도 아무 일이 없는 것처럼 보인다.
    if (env.Notification.permission === 'denied') return 'denied';

    const registration = await env.navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();

    return existing ? 'subscribed' : 'default';
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

    return { ok: true };
}
