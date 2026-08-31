import { describe, it, expect, vi } from 'vitest';
import { createNativeLocationTracker, toLocationPermission } from '../../resources/js/native/locationTracker.js';

/**
 * 위치 «취득» 어댑터 (N3 / 02 §3-3).
 *
 * 🔑 여기서 고정하려는 것은 플러그인 호출이 아니라 **「언제 null 을 돌려주는가」**다.
 *    이 함수의 절반은 «안 하는 것»이다 — 웹이거나 그 앱이 백그라운드 위치를 모르면
 *    조용히 null 을 주고 웹 경로로 떨어져야 한다. 여기서 잘못 true 를 주면
 *    **구버전 앱이 없는 플러그인을 부르다 깨진다.**
 */

function capacitorEnv({ platform = 'ios', plugins = {}, available = [] } = {}) {
    return {
        Capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => platform,
            isPluginAvailable: (name) => available.includes(name),
            Plugins: plugins,
        },
    };
}

describe('createNativeLocationTracker — 언제 null 인가', () => {
    it('웹에서는 null 이다', () => {
        expect(createNativeLocationTracker({})).toBeNull();
    });

    it('앱이지만 플러그인이 없으면 null 이다 (구버전 셸)', () => {
        // 🔴 웹이 앱보다 최신인 상태가 «정상»이다. 여기서 트래커를 주면
        //    그 앱은 없는 플러그인을 부르다 깨진다.
        const env = capacitorEnv({ available: [] });

        expect(createNativeLocationTracker(env)).toBeNull();
    });

    it('기능은 있다는데 플러그인 객체가 없으면 null 이다', () => {
        // isPluginAvailable 이 참인데 Plugins 에 없는 조합. 「있다고 했으니 있겠지」로
        // 진행하면 undefined.addWatcher 로 죽는다.
        const env = capacitorEnv({ available: ['BackgroundGeolocation'], plugins: {} });

        expect(createNativeLocationTracker(env)).toBeNull();
    });

    it('플러그인이 실제로 있으면 트래커를 준다', () => {
        const env = capacitorEnv({
            available: ['BackgroundGeolocation'],
            plugins: { BackgroundGeolocation: { addWatcher: vi.fn(), removeWatcher: vi.fn() } },
        });

        const t = createNativeLocationTracker(env);

        expect(t).not.toBeNull();
        expect(t.kind).toBe('native');
        expect(t.supportsBackground).toBe(true);
    });
});

describe('createNativeLocationTracker — 위치를 안쪽 계약대로 넘긴다', () => {
    function scene() {
        let cb = null;
        const plugin = {
            addWatcher: vi.fn(async (_opts, callback) => { cb = callback; return 'watch-1'; }),
            removeWatcher: vi.fn(async () => {}),
        };
        const env = capacitorEnv({ available: ['BackgroundGeolocation'], plugins: { BackgroundGeolocation: plugin } });

        return { tracker: createNativeLocationTracker(env), plugin, fire: (...a) => cb(...a) };
    }

    it('🔑 플러그인 payload 를 GeolocationPosition 모양으로 바꿔 넘긴다', async () => {
        // 안쪽(locationShare)이 「앱인지 웹인지」를 몰라도 되게 하는 것이 요점이다.
        const { tracker, fire } = scene();
        const onFix = vi.fn();
        await tracker.start(onFix, vi.fn());

        fire({ latitude: 37.5, longitude: 127.1, accuracy: 8, bearing: 90, speed: 3, time: 1700000000000 }, null);

        expect(onFix).toHaveBeenCalledWith({
            coords: { latitude: 37.5, longitude: 127.1, accuracy: 8, heading: 90, speed: 3 },
            timestamp: 1700000000000,
        });
    });

    it('권한 오류는 PERMISSION_DENIED 코드로 접어 넘긴다', async () => {
        // locationShare 의 handleError 가 err.code === err.PERMISSION_DENIED 로 판정한다.
        // 그 계약을 여기서 맞춰 주지 않으면 권한 거부가 「알 수 없는 오류」로 표시된다.
        const { tracker, fire } = scene();
        const onError = vi.fn();
        await tracker.start(vi.fn(), onError);

        fire(null, { code: 'NOT_AUTHORIZED', message: '거부됨' });

        const err = onError.mock.calls[0][0];
        expect(err.code).toBe(err.PERMISSION_DENIED);
    });

    it('🔴 기본으로는 권한을 «요청하지 않는다»', async () => {
        // 언제 물을지는 웹의 3단계 UX 가 정한다(02 §4). 감시를 시작할 때마다 물으면
        // 입장 직후에 「항상 허용」이 튀어나와 거절률이 급등한다.
        const { tracker, plugin } = scene();

        await tracker.start(vi.fn(), vi.fn());

        expect(plugin.addWatcher.mock.calls[0][0].requestPermissions).toBe(false);
    });

    it('🔴 승격할 때는 권한을 요청한다 — 이게 «유일한» 프롬프트 통로다', async () => {
        // 이 플러그인에는 「권한만 요청」하는 API 가 없다. addWatcher 의 이 옵션을
        // 넘길 수단이 없으면 3단계 UX 가 아무 일도 못 한다 — 실제로 그래서
        // 「항상 허용으로 바꾸기」 버튼이 눌러도 먹통이었다(2026-08-31 실기기).
        const { tracker, plugin } = scene();

        await tracker.start(vi.fn(), vi.fn(), { requestPermissions: true });

        expect(plugin.addWatcher.mock.calls[0][0].requestPermissions).toBe(true);
    });

    it('start 를 두 번 불러도 watcher 는 하나다', async () => {
        const { tracker, plugin } = scene();
        await tracker.start(vi.fn(), vi.fn());
        await tracker.start(vi.fn(), vi.fn());

        expect(plugin.addWatcher).toHaveBeenCalledTimes(1);
    });

    it('stop 은 멱등하다 — watcher 없이 불러도 안전하다', async () => {
        const { tracker, plugin } = scene();

        await tracker.stop();
        await tracker.start(vi.fn(), vi.fn());
        await tracker.stop();
        await tracker.stop();

        expect(plugin.removeWatcher).toHaveBeenCalledTimes(1);
    });
});

describe('toLocationPermission — 경계에서 한 번만 접는다 (M-5)', () => {
    it.each([
        ['authorizedAlways', 'always'],
        ['always', 'always'],
        ['authorizedWhenInUse', 'when_in_use'],
        ['granted', 'when_in_use'],
        ['denied', 'denied'],
        ['restricted', 'denied'],
        ['notDetermined', 'not_determined'],
        ['prompt', 'not_determined'],
        ['disabled', 'services_off'],
    ])('%s → %s', (raw, expected) => {
        expect(toLocationPermission(raw)).toBe(expected);
    });

    it('🔴 모르는 값은 null 이다 — denied 로 접지 않는다', () => {
        // 「모른다」를 「거부됨」으로 바꾸면 관제에 붉은 배지가 뜨고,
        // 사람은 실제로 없는 문제를 쫓는다. ADR-0008 이 null 을 따로 둔 이유다.
        expect(toLocationPermission('whatever')).toBeNull();
        expect(toLocationPermission(null)).toBeNull();
        expect(toLocationPermission(undefined)).toBeNull();
    });
});
