import { describe, it, expect, vi, beforeEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    isNativePushSupported, nativePushStatus, enableNativePush, disableNativePush,
    initNativePushRouting, __resetNativePushState, safePath,
    toForegroundNotification, needsForegroundNotification, notificationId, clearAppBadge,
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


/** showForegroundBanner 폴백 검증용 최소 DOM. Vitest 환경은 node 라 document 가 없다. */
function fakeDocument() {
    const make = () => ({
        style: { cssText: '' },
        __children: [],
        setAttribute() {},
        addEventListener() {},
        append(...kids) { this.__children.push(...kids); },
        appendChild(kid) { this.__children.push(kid); },
        remove() {},
        set textContent(v) { this.__text = v; },
        get textContent() { return this.__text; },
    });

    return { createElement: make, getElementById: () => null, body: make() };
}

/** Capacitor 셸이 주입한 전역을 흉내낸다. */
function nativeEnv({
    receive = 'granted', token = 'fcm-tok-1', fail = false, platform = 'android',
    localNotifications = true, scheduleFails = false, badge = true,
} = {}) {
    const listeners = {};
    const local = localNotifications ? {
        schedule: vi.fn(async () => {
            // OS 알림이 꺼져 있으면 플러그인이 이 문구로 «거부»한다.
            if (scheduleFails) throw new Error('Notifications not enabled on this device');

            return {};
        }),
        addListener: vi.fn((name, cb) => { listeners[name] = cb; }),
    } : null;
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

    const badgePlugin = badge ? { clear: vi.fn(async () => ({})) } : null;
    const appPlugin = { addListener: vi.fn((name, cb) => { listeners[name] = cb; }) };

    const plugins = { FirebaseMessaging: plugin, App: appPlugin };
    if (local) plugins.LocalNotifications = local;
    if (badgePlugin) plugins.Badge = badgePlugin;

    return {
        Capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => platform,
            isPluginAvailable: (n) => n === 'FirebaseMessaging',
            Plugins: plugins,
        },
        axios: { post: vi.fn(async () => ({})), delete: vi.fn(async () => ({})) },
        location: { assign: vi.fn() },
        document: fakeDocument(),
        __plugin: plugin,
        __local: local,
        __badge: badgePlugin,
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

    it('🔴 스킴 상대 URL(//다른호스트)도 막는다 — `/` 로 시작하지만 밖으로 나간다', () => {
        expect(safePath('//evil.example/steal')).toBeNull();
        expect(safePath('/control?request=83')).toBe('/control?request=83');
        expect(safePath('https://evil.example')).toBeNull();
        expect(safePath(undefined)).toBeNull();
    });
});

/**
 * 🔴 앱을 «보고 있을 때» 온 푸시.
 *
 * 실측(에뮬레이터 logcat): Android 는 포그라운드면 FCM 이 «자동 표시하지 않고» 앱에
 * 넘긴다. 리스너가 없으면 그대로 증발한다 —
 *   Notifying listeners for event notificationReceived
 *   No listeners found for event notificationReceived
 * 구조 앱에서 「앱을 켜 두고 있었더니 배정을 더 늦게 알았다」는 뒤집힌 결과다.
 *
 * 🔑 표시는 «로컬 알림»으로 한다(인앱 배너가 아니라). 인앱 배너는 DOM 이라
 *    페이지를 옮기면 흔적 없이 사라지지만, 로컬 알림은 iOS 처럼 알림 그늘에 남는다.
 */
describe('앱 푸시 — 포그라운드 수신', () => {
    it('🔑 Android 는 리스너를 «등록한다» — 없으면 포그라운드 푸시가 통째로 버려진다', () => {
        const env = nativeEnv({ platform: 'android' });
        initNativePushRouting(env);

        expect(env.__plugin.addListener).toHaveBeenCalledWith(
            'notificationReceived', expect.any(Function),
        );
    });

    it('🔑 iOS 는 손대지 «않는다» — OS 가 이미 띄우고, 그건 알림 센터에 남는다', () => {
        const env = nativeEnv({ platform: 'ios' });
        initNativePushRouting(env);

        const events = env.__plugin.addListener.mock.calls.map(([name]) => name);

        expect(events).toContain('notificationActionPerformed');   // 탭 착지는 양쪽 다 필요
        expect(events).not.toContain('notificationReceived');
    });

    it('웹에서는 아무것도 띄우지 않는다', () => {
        expect(needsForegroundNotification({})).toBe(false);
    });

    it('🔑 Android 포그라운드 푸시 → «로컬 알림»을 올린다', () => {
        const env = nativeEnv({ platform: 'android' });
        initNativePushRouting(env);

        env.__listeners.notificationReceived({
            notification: {
                title: '구조 배정', body: '즉시 출동',
                tag: 'request-9', data: { url: '/control?request=9' },
            },
        });

        expect(env.__local.schedule).toHaveBeenCalledTimes(1);
        const sent = env.__local.schedule.mock.calls[0][0].notifications[0];
        expect(sent.title).toBe('구조 배정');
        expect(sent.extra).toEqual({ url: '/control?request=9' });
        // 셸이 만든 heads-up 채널을 써야 한다. 어긋나면 조용히 기본 채널로 떨어진다.
        expect(sent.channelId).toBe('gps119-rescue-v1');
    });

    it('🔑 우리가 올린 알림의 «탭»도 딥링크로 간다 — FCM 이 아니라 로컬 알림 이벤트다', () => {
        const env = nativeEnv({ platform: 'android' });
        initNativePushRouting(env);

        env.__listeners.localNotificationActionPerformed({
            notification: { extra: { url: '/control?request=9' } },
        });

        expect(env.location.assign).toHaveBeenCalledWith('/control?request=9');
    });

    it('로컬 알림 탭에도 같은 경로 검사가 걸린다', () => {
        const env = nativeEnv({ platform: 'android' });
        initNativePushRouting(env);

        env.__listeners.localNotificationActionPerformed({
            notification: { extra: { url: '//evil.example' } },
        });

        expect(env.location.assign).not.toHaveBeenCalled();
    });

    it('🔑 플러그인 없는 «구버전 셸»에서는 인앱 배너로 떨어진다', () => {
        // 셸은 스토어 심사를 거쳐 천천히 갱신된다. 구버전 앱에서 아무것도 안 뜨면
        // 그 대원은 지령을 놓친다 — bridge.js 기능 협상과 같은 이유다.
        const env = nativeEnv({ platform: 'android', localNotifications: false });
        initNativePushRouting(env);

        env.__listeners.notificationReceived({
            notification: { title: '구조 배정', body: '즉시 출동', data: {} },
        });

        expect(env.document.body.__children).toHaveLength(1);
    });

    it('🔑 OS 알림이 «꺼져 있어» 로컬 알림이 거부되면 배너로 떨어진다', async () => {
        // 토큰은 알림 권한 없이도 발급되고 사용자가 나중에 끌 수도 있다 — 서버는 계속 보낸다.
        // 이걸 안 받으면 화면을 «보고 있는데도» 아무것도 안 뜬다(삼켜진 거부만 남는다).
        const env = nativeEnv({ platform: 'android', scheduleFails: true });
        initNativePushRouting(env);

        env.__listeners.notificationReceived({
            notification: { title: '구조 배정', body: '즉시 출동', data: {} },
        });

        await vi.waitFor(() => expect(env.document.body.__children).toHaveLength(1));
    });

    it('같은 tag 는 같은 id 로 접힌다 — 포그라운드에서만 알림이 쌓이지 않게', () => {
        expect(notificationId('request-9')).toBe(notificationId('request-9'));
        expect(notificationId('request-9')).not.toBe(notificationId('request-10'));
        expect(Number.isInteger(notificationId('x'))).toBe(true);
        expect(notificationId('x')).toBeGreaterThan(0);
    });

    it('제목·본문·딥링크를 뽑는다', () => {
        const spec = toForegroundNotification({
            notification: { title: '구조 배정', body: '즉시 출동', data: { url: '/dispatch/9' } },
        });

        expect(spec.title).toBe('구조 배정');
        expect(spec.body).toBe('즉시 출동');
        expect(spec.url).toBe('/dispatch/9');
    });

    it('제목이 없으면 «빈 알림»을 만들지 않는다', () => {
        expect(toForegroundNotification({ notification: { data: { url: '/x' } } })).toBeNull();
        expect(toForegroundNotification({})).toBeNull();
    });

    it('알림의 딥링크에도 같은 경로 검사가 걸린다', () => {
        expect(toForegroundNotification({
            notification: { title: 'x', data: { url: 'https://evil.example' } },
        }).url).toBeNull();
    });

    it('data 전용 메시지도 표시한다 — notification 블록 없이 올 수 있다', () => {
        const spec = toForegroundNotification({
            notification: { data: { title: '구조 배정', body: '즉시 출동' } },
        });

        expect(spec.title).toBe('구조 배정');
        expect(spec.url).toBeNull();
    });
});

/**
 * 앱 아이콘 뱃지 — 숫자는 서버가 정하고(`BadgeCounter` → `aps.badge`), 지우는 건 앱이다.
 *
 * 서버 값은 «보낸 시점» 기준이라, 다른 사람이 먼저 처리하거나 본인이 다 처리해도
 * 다음 푸시가 올 때까지 숫자가 남는다. 앱을 열었다는 것 자체가 「봤다」는 뜻이다.
 */
describe('앱 푸시 — 뱃지 지우기', () => {
    it('🔑 앱이 «열릴 때» 뱃지를 지운다', () => {
        const env = nativeEnv({ platform: 'ios' });
        initNativePushRouting(env);

        expect(env.__badge.clear).toHaveBeenCalledTimes(1);
    });

    it('🔑 백그라운드에서 «돌아올 때»도 지운다 — 페이지가 새로 뜨지 않는다', () => {
        // 앱 전환으로 복귀하면 웹뷰는 그대로다. 이게 없으면 「앱을 열었는데 숫자가 남아 있다」.
        const env = nativeEnv({ platform: 'ios' });
        initNativePushRouting(env);

        env.__listeners.appStateChange({ isActive: true });

        expect(env.__badge.clear).toHaveBeenCalledTimes(2);
    });

    it('백그라운드로 «나갈 때»는 지우지 않는다', () => {
        const env = nativeEnv({ platform: 'ios' });
        initNativePushRouting(env);

        env.__listeners.appStateChange({ isActive: false });

        expect(env.__badge.clear).toHaveBeenCalledTimes(1);
    });

    it('🔑 플러그인 없는 «구버전 셸»에서도 라우팅은 계속 붙는다', () => {
        // 뱃지 하나 때문에 푸시 라우팅 전체가 예외로 죽으면 지령을 놓친다.
        const env = nativeEnv({ platform: 'android', badge: false });

        expect(() => initNativePushRouting(env)).not.toThrow();
        expect(env.__plugin.addListener).toHaveBeenCalledWith(
            'notificationActionPerformed', expect.any(Function),
        );
    });

    it('웹 브라우저에서는 아무 일도 없다', () => {
        expect(() => clearAppBadge({})).not.toThrow();
    });
});

/**
 * 🔴 «어느 진입점이 이걸 부르는가»를 고정한다.
 *
 * 이 저장소에는 Vite 진입점이 둘이다 — `app.js`(Blade 전 화면)와
 * `control/main.js`(관제 SPA). control/index.blade.php 의 @vite 는 후자만 넣으므로
 * app.js 에만 배선하면 **관제 화면에서만 푸시 라우팅이 통째로 빠진다.**
 *
 * 실제로 그랬다(iOS 실기기 2026-08-09): 관제 화면을 열어 둔 상태에서는 인앱 배너도
 * 안 뜨고 알림을 «탭»해도 아무 일도 없었다. 상황실이 하루 종일 켜 두는 화면이 거기다.
 *
 * 함수 호출을 실행으로 검사할 수 없어(진입점은 import 만으로 DOM·Vue 를 건드린다)
 * 소스 텍스트로 고정한다 — roleMeta 의 hex 금지 스펙과 같은 방식이다.
 */
describe('푸시 라우팅이 «모든» 진입점에 배선돼 있다', () => {
    /**
     * ⚠️ 주석을 «반드시» 걷어내고 본다. 처음엔 원문에서 바로 찾았는데, 호출을
     *    `// initNativePushRouting();` 로 주석 처리해도 텍스트가 남아 테스트가
     *    통과했다 — 변이로 확인하지 않았으면 못 잡을 뻔했다. 게다가 그 파일의
     *    설명 주석 자체가 이 함수 이름을 담고 있다.
     */
    const codeOf = (path) => readFileSync(
        fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)),
        'utf8',
    )
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .split('\n')
        .map((line) => line.replace(/\/\/.*$/, ''))
        .join('\n');

    it.each(['app.js', 'control/main.js'])('%s 가 initNativePushRouting 을 부른다', (path) => {
        const code = codeOf(path);

        expect(code).toMatch(/import\s*\{[^}]*initNativePushRouting[^}]*\}\s*from/);
        expect(code).toMatch(/^\s*initNativePushRouting\(\s*\);/m);
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
