import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { acquireOnce, createNativeCurrentPosition, geoError } from '../../resources/js/native/currentPosition.js';
import { getCurrentPositionOnce } from '../../public/js/components/mapHelpers.js';

/**
 * iOS 신고 화면의 1회 위치 취득 — field-feedback-2026-09 F-12.
 *
 * 🔴 WKWebView 가 navigator.geolocation 마다 사이트 동의를 다시 물어서 「위치 팝업이 계속 뜬다」.
 *    iOS 앱에서는 배경 위치 플러그인으로 1회 취득하고, 그 외(웹·Android·권한 없음)는 원래
 *    경로로 «떨어져야» 한다 — 떨어지지 않으면 웹·Android 신고 화면이 통째로 죽는다.
 */

const FIX = { latitude: 37.5, longitude: 127.0, accuracy: 8, bearing: 90, speed: 1.5, time: 1_000_000 };

/** 가짜 플러그인. addWatcher 콜백을 잡아 두고, 테스트가 fix 를 흘린다. */
function fakePlugin({ idDelay = 0 } = {}) {
    let callback = null;
    const removeWatcher = vi.fn(async () => {});
    const addWatcher = vi.fn((opts, cb) => {
        callback = cb;

        return new Promise((resolve) => setTimeout(() => resolve('w1'), idDelay));
    });

    return {
        plugin: { addWatcher, removeWatcher },
        addWatcher,
        removeWatcher,
        feed: (location, error = null) => callback?.(location, error),
        options: () => addWatcher.mock.calls[0]?.[0],
    };
}

describe('acquireOnce — watcher 로 1건 받고 바로 걷는다', () => {
    beforeEach(() => { vi.useFakeTimers(); vi.setSystemTime(1_000_000); });
    afterEach(() => { vi.useRealTimers(); });

    it('첫 fix 를 W3C 모양으로 돌려주고 watcher 를 제거한다', async () => {
        const f = fakePlugin();
        const p = acquireOnce(f.plugin, { timeout: 15000, maximumAge: 30000 });

        await vi.advanceTimersByTimeAsync(0); // addWatcher id 도착
        f.feed(FIX);

        const pos = await p;
        expect(pos.coords).toEqual({ latitude: 37.5, longitude: 127.0, accuracy: 8, heading: 90, speed: 1.5 });
        expect(pos.timestamp).toBe(FIX.time);
        expect(f.removeWatcher).toHaveBeenCalledWith({ id: 'w1' });
    });

    it('🔴 권한 프롬프트를 띄우지 않는다(requestPermissions: false) — 언제 물을지는 웹 UX 가 정한다', () => {
        const f = fakePlugin();
        acquireOnce(f.plugin, {});

        expect(f.options().requestPermissions).toBe(false);
    });

    it('maximumAge 보다 오래된 캐시는 버리고 다음 fix 를 기다린다', async () => {
        const f = fakePlugin();
        const p = acquireOnce(f.plugin, { timeout: 15000, maximumAge: 30000 });
        await vi.advanceTimersByTimeAsync(0);

        f.feed({ ...FIX, time: 1_000_000 - 60_000 }); // 60초 전 캐시
        f.feed({ ...FIX, time: 1_000_000 - 5_000, latitude: 38 });

        expect((await p).coords.latitude).toBe(38);
    });

    it('시간 안에 못 받으면 TIMEOUT(3) 으로 거부하고 watcher 를 걷는다', async () => {
        const f = fakePlugin();
        const p = acquireOnce(f.plugin, { timeout: 15000 });
        await vi.advanceTimersByTimeAsync(0);

        const failed = p.catch((e) => e);
        await vi.advanceTimersByTimeAsync(15000);

        const e = await failed;
        expect(e.code).toBe(3);
        expect(e.code).toBe(e.TIMEOUT);
        expect(f.removeWatcher).toHaveBeenCalledWith({ id: 'w1' });
    });

    it('플러그인 오류는 W3C 코드로 접는다 — NOT_AUTHORIZED → 1, 그 외 → 2', async () => {
        const f = fakePlugin();
        const p = acquireOnce(f.plugin, {}).catch((e) => e);
        await vi.advanceTimersByTimeAsync(0);
        f.feed(null, { code: 'NOT_AUTHORIZED', message: 'nope' });

        const e = await p;
        expect(e.code).toBe(1);
        expect(e.message).toBe('nope');
    });

    it('콜백이 watcher id 보다 먼저 와도 id 가 오면 걷는다(누수 없음)', async () => {
        const f = fakePlugin({ idDelay: 50 });
        const p = acquireOnce(f.plugin, {});

        f.feed(FIX);             // id 는 아직 없다
        await p;
        expect(f.removeWatcher).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(50);
        expect(f.removeWatcher).toHaveBeenCalledWith({ id: 'w1' });
    });

    it('addWatcher 자체가 실패하면 POSITION_UNAVAILABLE(2)', async () => {
        const plugin = { addWatcher: vi.fn(async () => { throw new Error('boom'); }), removeWatcher: vi.fn() };

        const e = await acquireOnce(plugin, {}).catch((x) => x);
        expect(e.code).toBe(2);
    });
});

describe('createNativeCurrentPosition — iOS 앱에서만, 권한이 있을 때만', () => {
    function env({ platform = 'ios', permission = 'when_in_use', plugin = true } = {}) {
        const plugins = {};
        if (plugin) plugins.BackgroundGeolocation = fakePlugin().plugin;
        plugins.Gps119LocationPermission = { check: async () => ({ status: permission }) };

        return {
            Capacitor: {
                isNativePlatform: () => true,
                getPlatform: () => platform,
                isPluginAvailable: (name) => Object.prototype.hasOwnProperty.call(plugins, name),
                Plugins: plugins,
            },
            setTimeout, clearTimeout, Date,
        };
    }

    it('브라우저면 null', () => {
        expect(createNativeCurrentPosition({})).toBeNull();
    });

    it('🔑 Android 는 null — 문제가 없고 포그라운드 서비스 알림이 번쩍인다', () => {
        expect(createNativeCurrentPosition(env({ platform: 'android' }))).toBeNull();
    });

    it('플러그인이 없는 구버전 셸이면 null', () => {
        expect(createNativeCurrentPosition(env({ plugin: false }))).toBeNull();
    });

    it('iOS 인데 권한이 없으면 함수는 있되 null 로 resolve — 웹 경로가 OS 프롬프트를 띄운다', async () => {
        const fn = createNativeCurrentPosition(env({ permission: 'not_determined' }));

        expect(typeof fn).toBe('function');
        expect(await fn({})).toBeNull();
    });

    it('iOS + 권한 있음이면 플러그인으로 1건', async () => {
        const e = env({ permission: 'always' });
        const fn = createNativeCurrentPosition(e);
        const p = fn({ timeout: 15000 });

        // readNativePermission → addWatcher 등록까지 마이크로태스크 몇 번
        await new Promise((r) => setTimeout(r, 0));
        const cb = e.Capacitor.Plugins.BackgroundGeolocation.addWatcher.mock.calls[0][1];
        // 이 블록은 실제 시계라 «지금» 찍힌 fix 여야 maximumAge 에 안 걸린다.
        cb({ ...FIX, time: Date.now() }, null);

        expect((await p).coords.latitude).toBe(37.5);
    });
});

describe('mapHelpers.getCurrentPositionOnce — 주입이 있으면 먼저, 없거나 null 이면 웹', () => {
    const webPos = { coords: { latitude: 1, longitude: 2, accuracy: 50 }, timestamp: 0 };
    const webEnv = (extra = {}) => ({
        navigator: { geolocation: { getCurrentPosition: vi.fn((ok) => ok(webPos)) } },
        ...extra,
    });

    it('주입이 없으면(웹) navigator.geolocation', async () => {
        const e = webEnv();

        expect(await getCurrentPositionOnce({ timeout: 100 }, e)).toBe(webPos);
        expect(e.navigator.geolocation.getCurrentPosition).toHaveBeenCalled();
    });

    it('주입이 null 로 답하면(권한 없음) 웹으로 떨어진다', async () => {
        const e = webEnv({ __gps119Bridge: { getCurrentPosition: vi.fn(async () => null) } });

        expect(await getCurrentPositionOnce({ timeout: 100 }, e)).toBe(webPos);
    });

    it('주입이 위치를 주면 그걸 쓰고 navigator 는 부르지 않는다', async () => {
        const nativePos = { coords: { latitude: 9, longitude: 9, accuracy: 5 }, timestamp: 1 };
        const e = webEnv({ __gps119Bridge: { getCurrentPosition: vi.fn(async () => nativePos) } });

        expect(await getCurrentPositionOnce({ timeout: 100 }, e)).toBe(nativePos);
        expect(e.navigator.geolocation.getCurrentPosition).not.toHaveBeenCalled();
    });

    it('주입이 위치 오류(code)로 거부하면 그대로 전파 — 웹으로 다시 묻지 않는다', async () => {
        const e = webEnv({ __gps119Bridge: { getCurrentPosition: vi.fn(async () => { throw geoError(3, 'timeout'); }) } });

        const err = await getCurrentPositionOnce({ timeout: 100 }, e).catch((x) => x);
        expect(err.code).toBe(3);
        expect(e.navigator.geolocation.getCurrentPosition).not.toHaveBeenCalled();
    });

    it('주입이 «배선» 예외를 던지면 웹 경로로 살린다', async () => {
        const e = webEnv({ __gps119Bridge: { getCurrentPosition: vi.fn(async () => { throw new TypeError('plugin missing'); }) } });

        expect(await getCurrentPositionOnce({ timeout: 100 }, e)).toBe(webPos);
    });
});
