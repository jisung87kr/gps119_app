import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    urlBase64ToUint8Array, subscriptionToPayload, isSupported,
    pushStatus, enablePush, disablePush, vapidPublicKey,
} from '../../resources/js/push.js';

/**
 * 웹 푸시 구독 (mobile-app N1).
 *
 * 브라우저가 필요 없다 — 의존이 navigator.serviceWorker · Notification · axios
 * 셋뿐이라 스텁으로 충분하다. 여기서 고정하려는 것은 «두 쪽이 어긋나는» 경우다:
 * 브라우저 구독만 남거나 서버 등록만 남으면 **조용히 알림이 안 온다.**
 * 사람 눈으로는 「켜져 있는데 안 와요」로만 보이는 종류의 버그다.
 */

const REAL_ENDPOINT = 'https://fcm.googleapis.com/wp/abc123';

function makeSubscription(overrides = {}) {
    return {
        toJSON: () => ({
            endpoint: REAL_ENDPOINT,
            keys: { p256dh: 'BPublic', auth: 'AuthSecret' },
        }),
        unsubscribe: vi.fn().mockResolvedValue(true),
        ...overrides,
    };
}

function makeEnv({
    permission = 'default',
    existing = null,
    key = 'BNjoJG_test-key',
    axiosImpl = {},
} = {}) {
    const subscription = makeSubscription();

    const pushManager = {
        getSubscription: vi.fn().mockResolvedValue(existing),
        subscribe: vi.fn().mockResolvedValue(subscription),
    };

    return {
        subscription,
        pushManager,
        env: {
            PushManager: function () {},
            Notification: {
                permission,
                requestPermission: vi.fn().mockResolvedValue('granted'),
            },
            navigator: {
                serviceWorker: { ready: Promise.resolve({ pushManager }) },
            },
            document: {
                querySelector: () => (key ? { content: key } : null),
            },
            axios: {
                post: vi.fn().mockResolvedValue({}),
                delete: vi.fn().mockResolvedValue({}),
                ...axiosImpl,
            },
        },
    };
}

describe('urlBase64ToUint8Array', () => {
    it('패딩 없는 base64url 을 디코드한다', () => {
        // 'Hello' → base64 'SGVsbG8=' → base64url 'SGVsbG8'
        expect(Array.from(urlBase64ToUint8Array('SGVsbG8'))).toEqual([72, 101, 108, 108, 111]);
    });

    it('- 와 _ 를 + 와 / 로 되돌린다', () => {
        // 0xFB 0xFF 0xBF → base64 '+/+/' → base64url '-_-_'
        expect(Array.from(urlBase64ToUint8Array('-_-_'))).toEqual([251, 255, 191]);
    });
});

describe('subscriptionToPayload', () => {
    it('endpoint 를 토큰으로, keys 를 그대로 넘긴다', () => {
        expect(subscriptionToPayload(makeSubscription())).toEqual({
            platform: 'web',
            token: REAL_ENDPOINT,
            keys: { p256dh: 'BPublic', auth: 'AuthSecret' },
        });
    });
});

describe('pushStatus', () => {
    it('지원하지 않는 브라우저', async () => {
        expect(await pushStatus({ navigator: {} })).toBe('unsupported');
    });

    it('거부된 상태는 «켜기»를 제안하면 안 되므로 따로 구분한다', async () => {
        const { env } = makeEnv({ permission: 'denied' });
        expect(await pushStatus(env)).toBe('denied');
    });

    it('구독이 있으면 subscribed', async () => {
        const { env } = makeEnv({ existing: makeSubscription() });
        expect(await pushStatus(env)).toBe('subscribed');
    });

    it('구독이 없으면 default', async () => {
        const { env } = makeEnv();
        expect(await pushStatus(env)).toBe('default');
    });
});

describe('enablePush', () => {
    it('구독하고 서버에 등록한다', async () => {
        const { env, pushManager } = makeEnv();

        const result = await enablePush(env);

        expect(result.ok).toBe(true);
        expect(pushManager.subscribe).toHaveBeenCalled();
        expect(env.axios.post).toHaveBeenCalledWith('/api/devices', {
            platform: 'web',
            token: REAL_ENDPOINT,
            keys: { p256dh: 'BPublic', auth: 'AuthSecret' },
        });
    });

    it('VAPID 키가 없으면 구독조차 시도하지 않는다', async () => {
        // 키 없이 구독하면 브라우저에는 «켜진 것»으로 남는데 서버는 보낼 수 없다.
        const { env, pushManager } = makeEnv({ key: '' });

        expect(await enablePush(env)).toEqual({ ok: false, reason: 'not-configured' });
        expect(pushManager.subscribe).not.toHaveBeenCalled();
    });

    it('권한을 거부하면 서버에 등록하지 않는다', async () => {
        const { env } = makeEnv();
        env.Notification.requestPermission = vi.fn().mockResolvedValue('denied');

        expect(await enablePush(env)).toEqual({ ok: false, reason: 'denied' });
        expect(env.axios.post).not.toHaveBeenCalled();
    });

    it('이미 구독이 있으면 다시 구독하지 않는다', async () => {
        // 재구독하면 endpoint 가 바뀌어 서버에 죽은 통로가 하나 더 생긴다.
        const { env, pushManager } = makeEnv({ existing: makeSubscription() });

        await enablePush(env);

        expect(pushManager.subscribe).not.toHaveBeenCalled();
        expect(env.axios.post).toHaveBeenCalled();
    });

    it('서버가 거절하면 브라우저 구독을 되돌린다', async () => {
        // 구독만 남으면 사용자에게는 «켜진 것»으로 보이면서 영영 알림이 안 온다.
        const { env, subscription } = makeEnv({
            axiosImpl: { post: vi.fn().mockRejectedValue(new Error('422')) },
        });

        expect(await enablePush(env)).toEqual({ ok: false, reason: 'server-rejected' });
        expect(subscription.unsubscribe).toHaveBeenCalled();
    });
});

describe('disablePush', () => {
    it('서버 해제 «후에» 브라우저 구독을 푼다', async () => {
        const existing = makeSubscription();
        const { env } = makeEnv({ existing });
        const order = [];
        env.axios.delete = vi.fn(() => { order.push('server'); return Promise.resolve({}); });
        existing.unsubscribe = vi.fn(() => { order.push('browser'); return Promise.resolve(true); });

        expect(await disablePush(env)).toEqual({ ok: true });
        expect(order).toEqual(['server', 'browser']);
    });

    it('토큰은 본문으로 보낸다 (URL 에 넣으면 로그에 남는다)', async () => {
        const { env } = makeEnv({ existing: makeSubscription() });

        await disablePush(env);

        expect(env.axios.delete).toHaveBeenCalledWith('/api/devices/current', {
            data: { token: REAL_ENDPOINT },
        });
    });

    it('서버 해제가 실패하면 브라우저 구독을 유지한다', async () => {
        // 먼저 풀어버리면 endpoint 를 잃어 서버에 죽은 통로가 영구히 남는다.
        const existing = makeSubscription();
        const { env } = makeEnv({ existing });
        env.axios.delete = vi.fn().mockRejectedValue(new Error('500'));

        expect(await disablePush(env)).toEqual({ ok: false, reason: 'server-error' });
        expect(existing.unsubscribe).not.toHaveBeenCalled();
    });

    it('구독이 없으면 아무것도 하지 않는다', async () => {
        const { env } = makeEnv();

        expect(await disablePush(env)).toEqual({ ok: true });
        expect(env.axios.delete).not.toHaveBeenCalled();
    });
});

describe('vapidPublicKey', () => {
    it('meta 태그에서 읽는다', () => {
        expect(vapidPublicKey({ querySelector: () => ({ content: 'BKey' }) })).toBe('BKey');
    });

    it('없으면 빈 문자열', () => {
        expect(vapidPublicKey({ querySelector: () => null })).toBe('');
    });
});

describe('isSupported', () => {
    it('셋 중 하나라도 없으면 미지원', () => {
        expect(isSupported({ navigator: {}, PushManager: function () {}, Notification: {} })).toBe(false);
        expect(isSupported({ navigator: { serviceWorker: {} }, Notification: {} })).toBe(false);
        expect(isSupported({ navigator: { serviceWorker: {} }, PushManager: function () {} })).toBe(false);
    });
});
