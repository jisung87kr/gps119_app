import { describe, it, expect, vi } from 'vitest';
import {
    isNativeApp, nativePlatform, nativeInfo, hasNativeCapability, NativeCapability,
} from '../../resources/js/native/bridge.js';
import { registerServiceWorker } from '../../resources/js/pwa.js';

/**
 * 네이티브 셸 브리지 (mobile-app N2).
 *
 * 여기서 고정하는 것은 감지가 아니라 «기능 협상»이다.
 * 웹은 배포 즉시 갱신되지만 앱은 스토어 심사를 거쳐 천천히 갱신되므로,
 * **웹이 앱보다 최신인 상태가 정상**이다. 그 상태에서 앱에 없는 기능을 부르면
 * 이미 배포된 구버전 앱이 깨진다 — 사용자는 스토어 업데이트 전까지 복구할 수 없다.
 */

const webEnv = () => ({ navigator: {}, addEventListener: vi.fn() });

const appEnv = (overrides = {}) => ({
    navigator: { serviceWorker: { register: vi.fn() } },
    addEventListener: vi.fn(),
    Capacitor: {
        isNativePlatform: () => true,
        getPlatform: () => 'ios',
    },
    ...overrides,
});

describe('isNativeApp', () => {
    it('웹에서는 false', () => {
        expect(isNativeApp(webEnv())).toBe(false);
    });

    it('셸 안에서는 true', () => {
        expect(isNativeApp(appEnv())).toBe(true);
    });

    it('isNativePlatform() 이 없는 구버전 셸도 앱으로 본다', () => {
        // 「앱인데 아니라고」 답하면 SW 가 이중으로 붙는다 — 안전한 쪽으로 기운다.
        expect(isNativeApp({ Capacitor: {} })).toBe(true);
    });

    it('브라우저에서 실행된 Capacitor(웹 타깃)는 앱이 아니다', () => {
        expect(isNativeApp({ Capacitor: { isNativePlatform: () => false } })).toBe(false);
    });
});

describe('nativePlatform', () => {
    it.each([
        ['ios', 'ios'],
        ['android', 'android'],
    ])('%s 를 그대로 보고한다', (given, expected) => {
        const env = appEnv();
        env.Capacitor.getPlatform = () => given;
        expect(nativePlatform(env)).toBe(expected);
    });

    it('웹은 web', () => {
        expect(nativePlatform(webEnv())).toBe('web');
    });

    it('모르는 값은 web 으로 떨어진다', () => {
        const env = appEnv();
        env.Capacitor.getPlatform = () => 'harmonyos';
        expect(nativePlatform(env)).toBe('web');
    });
});

describe('nativeInfo', () => {
    it('셸이 심은 버전·기능 목록을 읽는다', () => {
        const env = appEnv({ __gps119Native: { version: '1.2.0', capabilities: ['push-token'] } });
        expect(nativeInfo(env)).toEqual({ version: '1.2.0', capabilities: ['push-token'] });
    });

    it('셸이 아무것도 안 심었어도 터지지 않는다', () => {
        // 가장 오래된 셸은 이 전역 자체를 모른다. 그 앱에서 웹이 죽으면 안 된다.
        expect(nativeInfo(appEnv())).toEqual({ version: null, capabilities: [] });
    });

    it('형태가 깨진 값도 빈 목록으로 떨어진다', () => {
        const env = appEnv({ __gps119Native: { version: 3, capabilities: 'push' } });
        expect(nativeInfo(env)).toEqual({ version: null, capabilities: [] });
    });
});

describe('hasNativeCapability — 구버전 앱 보호', () => {
    it('앱이 아는 기능은 true', () => {
        const env = appEnv({ __gps119Native: { capabilities: [NativeCapability.PUSH_TOKEN] } });
        expect(hasNativeCapability(NativeCapability.PUSH_TOKEN, env)).toBe(true);
    });

    it('🔑 앱이 «모르는» 기능은 false — 웹 경로로 떨어져야 한다', () => {
        // 웹이 새 기능을 배포해도, 이미 설치된 구버전 앱은 그것을 모른다.
        // 여기서 true 가 나오면 그 앱에서 없는 네이티브 호출이 터진다.
        const env = appEnv({ __gps119Native: { capabilities: [NativeCapability.PUSH_TOKEN] } });
        expect(hasNativeCapability(NativeCapability.BACKGROUND_LOCATION, env)).toBe(false);
    });

    it('기능 목록이 아예 없는 오래된 셸에서도 false', () => {
        expect(hasNativeCapability(NativeCapability.PUSH_TOKEN, appEnv())).toBe(false);
    });

    it('웹에서는 언제나 false', () => {
        expect(hasNativeCapability(NativeCapability.PUSH_TOKEN, webEnv())).toBe(false);
    });
});

describe('서비스워커 등록', () => {
    it('웹에서는 등록한다', () => {
        const env = { navigator: { serviceWorker: { register: vi.fn() } }, addEventListener: vi.fn() };

        registerServiceWorker(env);

        expect(env.addEventListener).toHaveBeenCalledWith('load', expect.any(Function));
    });

    it('🔑 셸 안에서는 등록하지 않는다', () => {
        // 셸이 자체 캐시·오프라인 폴백을 갖고 있어 두 겹이 된다.
        // 푸시도 네이티브와 SW 웹푸시가 동시에 와서 알림이 두 번 뜬다.
        const env = appEnv();

        registerServiceWorker(env);

        expect(env.addEventListener).not.toHaveBeenCalled();
        expect(env.navigator.serviceWorker.register).not.toHaveBeenCalled();
    });

    it('서비스워커를 지원하지 않는 브라우저에서는 조용히 지나간다', () => {
        const env = webEnv();

        expect(() => registerServiceWorker(env)).not.toThrow();
        expect(env.addEventListener).not.toHaveBeenCalled();
    });
});
