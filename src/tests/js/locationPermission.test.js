import { describe, it, expect, vi } from 'vitest';
import {
    decidePermissionStep,
    openLocationSettings,
    reportLocationPermission,
    watchPermissionChanges,
} from '../../resources/js/native/locationPermission.js';

/**
 * 위치 권한 3단계 UX (N3 / 02 §4).
 *
 * 🔑 여기서 고정하려는 것은 화면이 아니라 **「언제 무엇을 요구하는가」**다.
 *    한 번에 「항상 허용」을 요구하면 거절률이 급등하고, 반대로 아무 때나 설정
 *    화면으로 보내면 고칠 것도 없는 사용자가 설정을 뒤진다. 둘 다 사람 눈으로는
 *    «그럴듯해» 보여서 화면만 봐서는 안 걸린다.
 */

function nativeEnv({ status = 'when_in_use', patch = vi.fn().mockResolvedValue({}) } = {}) {
    return {
        Capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => 'ios',
            isPluginAvailable: () => true,
            Plugins: {
                Gps119LocationPermission: { check: vi.fn().mockResolvedValue({ status }) },
                BackgroundGeolocation: { openSettings: vi.fn().mockResolvedValue(undefined) },
            },
        },
        axios: { patch },
    };
}

describe('decidePermissionStep — 언제 무엇을 요구하는가', () => {
    it('웹에서는 아무것도 하지 않는다', () => {
        // 브라우저에는 「항상 허용」이 없다. 설명 화면을 띄우면 «고칠 수 없는 것»을
        // 요구하게 된다 — 탭이 죽으면 어차피 끊긴다.
        expect(decidePermissionStep({ native: false, permission: 'denied', sharing: true })).toBe('none');
    });

    it('🔴 보고가 없으면(null) 아무것도 하지 않는다', () => {
        // 구버전 셸이거나 읽기에 실패한 경우. 「모른다」를 「거부됨」으로 읽고 설정
        // 화면으로 보내면 멀쩡한 사용자가 고칠 것도 없는 설정을 뒤진다.
        expect(decidePermissionStep({ native: true, permission: null, sharing: true })).toBe('none');
    });

    it('항상 허용이면 더 요구하지 않는다', () => {
        expect(decidePermissionStep({ native: true, permission: 'always', sharing: true })).toBe('none');
    });

    it.each(['denied', 'services_off'])('%s 면 공유를 켜기 «전»에도 설정 안내를 띄운다', (permission) => {
        // 🔴 「켜고 나서야 막힌 걸 아는」 순서면 대원은 이미 현장에 있고,
        //    그때는 고칠 시간이 없다.
        expect(decidePermissionStep({ native: true, permission, sharing: false })).toBe('guide_settings');
        expect(decidePermissionStep({ native: true, permission, sharing: true })).toBe('guide_settings');
    });

    it.each(['when_in_use', 'not_determined'])('%s 는 공유를 «켤 때만» 승격을 권한다', (permission) => {
        // 끄고 있는 사람에게 배경 권한을 조르면 거절률만 올라간다.
        expect(decidePermissionStep({ native: true, permission, sharing: false })).toBe('none');
        expect(decidePermissionStep({ native: true, permission, sharing: true })).toBe('explain_always');
    });

    it('인자가 없어도 안전하다', () => {
        expect(decidePermissionStep()).toBe('none');
    });
});

describe('reportLocationPermission — M-5 보고', () => {
    it('읽은 값을 서버에 보낸다', async () => {
        const patch = vi.fn().mockResolvedValue({});
        const env = nativeEnv({ status: 'always', patch });

        const result = await reportLocationPermission(7, env);

        expect(result).toBe('always');
        expect(patch).toHaveBeenCalledWith(
            '/api/events/7/location-permission',
            { permission: 'always' },
            expect.anything(),
        );
    });

    it('🔴 읽을 게 없으면 «보내지 않는다»', async () => {
        // null 을 보내면 서버가 「보고한 적 없음」(웹)과 「모른다고 보고함」(앱)을
        // 구분할 수 없다. 관제 화면에서 둘은 다르게 읽혀야 한다.
        const patch = vi.fn();
        const env = { Capacitor: { Plugins: {} }, axios: { patch } };

        expect(await reportLocationPermission(7, env)).toBeNull();
        expect(patch).not.toHaveBeenCalled();
    });

    it('서버가 실패해도 삼킨다 — 공유 자체를 막지 않는다', async () => {
        // 보고는 부가기능이다. 구조 요청이 그것 때문에 멈추면 안 된다.
        const env = nativeEnv({ patch: vi.fn().mockRejectedValue(new Error('500')) });

        await expect(reportLocationPermission(7, env)).resolves.toBe('when_in_use');
    });
});

describe('openLocationSettings — 3단계 딥링크', () => {
    it('플러그인이 있으면 연다', async () => {
        const env = nativeEnv();

        expect(await openLocationSettings(env)).toBe(true);
        expect(env.Capacitor.Plugins.BackgroundGeolocation.openSettings).toHaveBeenCalled();
    });

    it('웹에서는 false — 브라우저 설정을 열 방법이 없다', async () => {
        expect(await openLocationSettings({})).toBe(false);
    });
});

describe('watchPermissionChanges — iOS 재확인 프롬프트 대응', () => {
    it('🔴 포그라운드 복귀마다 다시 읽는다', async () => {
        // iOS 는 「항상」을 나중에 다시 물어보고, 사용자가 「사용 중」으로 되돌리면
        // 배경 추적이 «조용히» 끊긴다. 앱 안에서는 아무 일도 안 일어나므로
        // 복귀 시점에 읽지 않으면 관제는 끊긴 사람을 계속 「추적 중」으로 본다.
        const listeners = {};
        const env = nativeEnv({ status: 'when_in_use' });
        env.document = {
            visibilityState: 'visible',
            addEventListener: (n, h) => { listeners[n] = h; },
            removeEventListener: vi.fn(),
        };
        const onChange = vi.fn();

        const stop = watchPermissionChanges(7, onChange, env);
        await listeners.visibilitychange();

        expect(onChange).toHaveBeenCalledWith('when_in_use');
        stop();
        expect(env.document.removeEventListener).toHaveBeenCalled();
    });

    it('화면이 숨겨진 상태에서는 읽지 않는다', async () => {
        const listeners = {};
        const env = nativeEnv();
        env.document = {
            visibilityState: 'hidden',
            addEventListener: (n, h) => { listeners[n] = h; },
            removeEventListener: vi.fn(),
        };
        const onChange = vi.fn();

        watchPermissionChanges(7, onChange, env);
        await listeners.visibilitychange();

        expect(onChange).not.toHaveBeenCalled();
    });

    it('웹에서는 아무것도 붙이지 않는다', () => {
        const addEventListener = vi.fn();

        watchPermissionChanges(7, vi.fn(), { document: { addEventListener } });

        expect(addEventListener).not.toHaveBeenCalled();
    });
});
