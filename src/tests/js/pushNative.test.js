import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    isNativePushSupported, nativePushStatus, enableNativePush, disableNativePush,
    initNativePushRouting, __resetNativePushState,
} from '../../resources/js/push-native.js';
import { pushStatus, enablePush } from '../../resources/js/push.js';

/**
 * 앱 푸시 (FCM/APNs).
 *
 * 🔴 앱 안에서는 웹 푸시가 «원리적으로» 불가능하다(M-24) — 앱 웹뷰에 서비스워커가 없어
 *    PushSubscription 을 만들 수 없다. 여기가 앱 사용자에게 닿는 유일한 길이다.
 *
 * 🔑 이 파일이 고정하는 계약 셋:
 *    ① 토큰 «등록»은 웹 JS 가 한다 — 셸이 직접 쏘면 세션 쿠키가 없어 401 이다
 *    ② 토큰을 «받은 뒤» 등록한다 — 실패하면 서버에 아무것도 보내지 않는다
 *    ③ 서버 등록이 실패하면 «켜짐»으로 표시하지 않는다 — 알림이 영영 안 온다
 */

/** Capacitor 셸이 주입한 전역을 흉내낸다. */
function nativeEnv({ receive = 'granted', token = 'fcm-tok-1', fail = false, platform = 'android' } = {}) {
    const listeners = {};
    const plugin = {
        checkPermissions: vi.fn(async () => ({ receive })),
        requestPermissions: vi.fn(async () => ({ receive })),
        addListener: vi.fn((name, cb) => { listeners[name] = cb; }),
        // FirebaseMessaging 은 getToken() 이 토큰을 «직접» 돌려준다.
        getToken: vi.fn(async () => {
            if (fail) throw new Error('no-network');

            return { token };
        }),
    };

    return {
        Capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => platform,
            isPluginAvailable: (n) => n === 'FirebaseMessaging',
            Plugins: { FirebaseMessaging: plugin },
        },
        axios: { post: vi.fn(async () => ({})), delete: vi.fn(async () => ({})) },
        location: { assign: vi.fn() },
        __plugin: plugin,
        __listeners: listeners,
    };
}

beforeEach(() => __resetNativePushState());

describe('앱 푸시 — 사용 가능 판정', () => {
    it('🔑 셸이 capabilities 를 «안 알려도» 플러그인이 있으면 쓸 수 있다', () => {
        // 셸은 window.__gps119Native 를 심은 적이 없었다(문서에만 있었다).
        // 선언에만 의존했다면 앱 푸시는 영원히 꺼진 채였다.
        const env = nativeEnv();
        expect(env.__gps119Native).toBeUndefined();

        expect(isNativePushSupported(env)).toBe(true);
    });

    it('웹 브라우저에서는 앱 푸시가 아니다', () => {
        expect(isNativePushSupported({})).toBe(false);
    });

    it('앱이라도 플러그인이 없으면 못 쓴다', () => {
        // 구버전 셸이다. 「앱이면 된다」로 짰다면 여기서 깨진다.
        const env = nativeEnv();
        env.Capacitor.isPluginAvailable = () => false;

        expect(isNativePushSupported(env)).toBe(false);
    });
});

describe('앱 푸시 — 켜기', () => {
    it('🔑 토큰을 받아 «웹 JS 가» 서버에 등록한다', async () => {
        const env = nativeEnv({ platform: 'android' });

        const result = await enableNativePush(env);

        expect(result).toEqual({ ok: true });
        expect(env.axios.post).toHaveBeenCalledWith('/api/devices', {
            platform: 'android',
            token: 'fcm-tok-1',
        });
    });

    it('iOS 는 platform 을 ios 로 보낸다', async () => {
        const env = nativeEnv({ platform: 'ios' });

        await enableNativePush(env);

        expect(env.axios.post.mock.calls[0][1].platform).toBe('ios');
    });

    it('권한이 없으면 «요청»한다', async () => {
        const env = nativeEnv({ receive: 'prompt' });

        await enableNativePush(env);

        expect(env.__plugin.requestPermissions).toHaveBeenCalled();
    });

    it('거부되면 등록하지 않는다', async () => {
        const env = nativeEnv({ receive: 'denied' });

        expect(await enableNativePush(env)).toEqual({ ok: false, reason: 'denied' });
        expect(env.axios.post).not.toHaveBeenCalled();
    });

    it('🔑 토큰 발급이 실패하면 서버에 아무것도 보내지 않는다', async () => {
        const env = nativeEnv({ fail: true });

        expect(await enableNativePush(env)).toEqual({ ok: false, reason: 'registration-failed' });
        expect(env.axios.post).not.toHaveBeenCalled();
    });

    it('🔑 서버가 거절하면 «켜짐»이 되지 않는다', async () => {
        // 서버가 모르면 알림은 영영 안 온다. 켜진 것처럼 보이면 안 된다.
        const env = nativeEnv();
        env.axios.post = vi.fn(async () => { throw new Error('422'); });

        expect(await enableNativePush(env)).toEqual({ ok: false, reason: 'server-rejected' });
        expect(await nativePushStatus(env)).toBe('default');
    });
});

describe('앱 푸시 — 상태와 끄기', () => {
    it('켜기 전에는 default, 켠 뒤에는 subscribed', async () => {
        const env = nativeEnv();

        expect(await nativePushStatus(env)).toBe('default');
        await enableNativePush(env);
        expect(await nativePushStatus(env)).toBe('subscribed');
    });

    it('OS 가 거부 상태면 denied — 앱 안에서 되돌릴 수 없다', async () => {
        expect(await nativePushStatus(nativeEnv({ receive: 'denied' }))).toBe('denied');
    });

    it('끄면 서버에서 통로를 지운다', async () => {
        const env = nativeEnv();
        await enableNativePush(env);

        expect(await disableNativePush(env)).toEqual({ ok: true });
        expect(env.axios.delete).toHaveBeenCalledWith('/api/devices/current', {
            data: { token: 'fcm-tok-1' },
        });
        expect(await nativePushStatus(env)).toBe('default');
    });

    it('서버 해제가 실패하면 «꺼짐»으로 치지 않는다', async () => {
        const env = nativeEnv();
        await enableNativePush(env);
        env.axios.delete = vi.fn(async () => { throw new Error('500'); });

        expect(await disableNativePush(env)).toEqual({ ok: false, reason: 'server-error' });
        expect(await nativePushStatus(env)).toBe('subscribed');
    });
});

describe('앱 푸시 — 알림 탭 착지(딥링크)', () => {
    it('🔑 payload.url 로 이동한다 — 웹 sw.js 와 같은 규약', async () => {
        const env = nativeEnv();
        initNativePushRouting(env);

        env.__listeners.notificationActionPerformed({
            notification: { data: { url: '/control?request=83' } },
        });

        expect(env.location.assign).toHaveBeenCalledWith('/control?request=83');
    });

    it('외부 주소로는 튀지 않는다', async () => {
        const env = nativeEnv();
        initNativePushRouting(env);

        env.__listeners.notificationActionPerformed({
            notification: { data: { url: 'https://evil.example/steal' } },
        });

        expect(env.location.assign).not.toHaveBeenCalled();
    });

    it('url 이 없으면 아무 데도 안 간다', async () => {
        const env = nativeEnv();
        initNativePushRouting(env);

        env.__listeners.notificationActionPerformed({ notification: { data: {} } });

        expect(env.location.assign).not.toHaveBeenCalled();
    });
});

describe('push.js 가 앱에서 네이티브로 갈라진다', () => {
    it('🔑 앱에서는 서비스워커를 «보지 않는다»', async () => {
        // 앱 웹뷰에는 서비스워커가 없다. 웹 경로로 가면 unsupported 로 떨어져
        // 「이 브라우저는 알림을 지원하지 않습니다」가 뜬다 — 앱에서는 틀린 말이다.
        const env = nativeEnv();

        expect(await pushStatus(env)).toBe('default');
    });

    it('🔑 켜기도 네이티브 경로로 간다', async () => {
        const env = nativeEnv();

        expect(await enablePush(env)).toEqual({ ok: true });
        expect(env.axios.post).toHaveBeenCalled();
    });

    it('웹 브라우저는 기존 경로 그대로다', async () => {
        // 네이티브 분기가 웹을 건드리면 «있던 기능»이 사라진다.
        expect(await pushStatus({})).toBe('unsupported');
    });
});
