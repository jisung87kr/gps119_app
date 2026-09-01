import { describe, it, expect, vi } from 'vitest';
import {
    BATTERY_WARNING,
    checkBatteryOptimization,
    openBatteryOptimizationSettings,
    readBatteryOptimization,
    shouldWarnBatteryOptimization,
    supportsBatteryOptimization,
} from '../../resources/js/native/batteryOptimization';

/**
 * 배터리 최적화 예외 (M-26).
 *
 * 🔴 이게 없으면 안드로이드에서 화면을 끄는 순간 위치 전송이 «완전히» 멈춘다
 *    (실측 0건). 그런데 앱은 멀쩡히 돌고 알림도 떠 있어서 **사람 눈으로는 안 걸린다.**
 */

function env({ ignoring, platform = 'android', throws = false, opened = true } = {}) {
    return {
        Capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => platform,
            Plugins: {
                Gps119BatteryOptimization: {
                    check: throws
                        ? () => Promise.reject(new Error('nope'))
                        : () => Promise.resolve({ ignoring }),
                    openSettings: () => Promise.resolve({ opened }),
                },
            },
        },
    };
}

describe('shouldWarnBatteryOptimization — 판정', () => {
    const base = { native: true, platform: 'android', sharing: true, ignoring: false };

    it('🔴 안드로이드에서 제외돼 있지 않고 공유 중이면 경고한다', () => {
        expect(shouldWarnBatteryOptimization(base)).toBe(true);
    });

    it('🔴 «모른다»(null)로는 경고하지 않는다', () => {
        // 구버전 셸·읽기 실패에서 경고하면 고칠 것 없는 사람에게 고치라고 말하게 된다.
        expect(shouldWarnBatteryOptimization({ ...base, ignoring: null })).toBe(false);
    });

    it('이미 제외돼 있으면 경고하지 않는다', () => {
        expect(shouldWarnBatteryOptimization({ ...base, ignoring: true })).toBe(false);
    });

    it('🔴 공유를 끈 사람에게는 요구하지 않는다', () => {
        expect(shouldWarnBatteryOptimization({ ...base, sharing: false })).toBe(false);
    });

    it('🔴 iOS 에는 이 개념이 없다 — 띄우지 않는다', () => {
        expect(shouldWarnBatteryOptimization({ ...base, platform: 'ios' })).toBe(false);
    });

    it('웹에서도 띄우지 않는다', () => {
        expect(shouldWarnBatteryOptimization({ ...base, native: false, platform: 'web' })).toBe(false);
    });

    it('인자가 없어도 깨지지 않는다', () => {
        expect(shouldWarnBatteryOptimization()).toBe(false);
    });
});

describe('readBatteryOptimization — 경계', () => {
    it('플러그인이 답하면 그대로 읽는다', async () => {
        await expect(readBatteryOptimization(env({ ignoring: true }))).resolves.toBe(true);
        await expect(readBatteryOptimization(env({ ignoring: false }))).resolves.toBe(false);
    });

    it('🔑 셸에 기능이 없으면 «모른다»(null)', async () => {
        await expect(readBatteryOptimization({})).resolves.toBeNull();
    });

    it('🔑 읽기에 실패해도 «모른다»(null) — 던지지 않는다', async () => {
        await expect(readBatteryOptimization(env({ throws: true }))).resolves.toBeNull();
    });

    it('불리언이 아닌 응답은 믿지 않는다', async () => {
        const e = env({});
        e.Capacitor.Plugins.Gps119BatteryOptimization.check = () => Promise.resolve({ ignoring: 'yes' });

        await expect(readBatteryOptimization(e)).resolves.toBeNull();
    });
});

describe('openBatteryOptimizationSettings', () => {
    it('열면 true', async () => {
        await expect(openBatteryOptimizationSettings(env({ ignoring: false }))).resolves.toBe(true);
    });

    it('🔑 못 열면 false — 화면이 「직접 바꿔 주세요」로 안내할 수 있다', async () => {
        await expect(openBatteryOptimizationSettings(env({ ignoring: false, opened: false })))
            .resolves.toBe(false);
    });

    it('셸에 기능이 없으면 false', async () => {
        await expect(openBatteryOptimizationSettings({})).resolves.toBe(false);
    });
});

describe('checkBatteryOptimization — 화면이 쓰는 입구', () => {
    it('안드로이드·공유 중·제외 안 됨 → 경고', async () => {
        await expect(checkBatteryOptimization(true, env({ ignoring: false })))
            .resolves.toEqual({ ignoring: false, warn: true });
    });

    it('제외돼 있으면 경고하지 않는다', async () => {
        await expect(checkBatteryOptimization(true, env({ ignoring: true })))
            .resolves.toEqual({ ignoring: true, warn: false });
    });

    it('🔴 iOS 에서는 플러그인을 부르지도 않는다', async () => {
        const e = env({ ignoring: false, platform: 'ios' });
        const spy = vi.spyOn(e.Capacitor.Plugins.Gps119BatteryOptimization, 'check');

        await expect(checkBatteryOptimization(true, e)).resolves.toEqual({ ignoring: null, warn: false });
        expect(spy).not.toHaveBeenCalled();
    });

    it('웹에서는 조용하다', async () => {
        await expect(checkBatteryOptimization(true, {}))
            .resolves.toEqual({ ignoring: null, warn: false });
    });
});

describe('안내 문구', () => {
    it('셸 지원 여부를 판별한다', () => {
        expect(supportsBatteryOptimization(env({ ignoring: false }))).toBe(true);
        expect(supportsBatteryOptimization({})).toBe(false);
    });

    it('🔑 문구는 한 곳에만 있다 — 화면마다 다시 쓰면 어긋난다', () => {
        expect(BATTERY_WARNING.title).toContain('화면을 끄면');
        expect(BATTERY_WARNING.body).toContain('배터리 최적화');
        expect(BATTERY_WARNING.action).toBeTruthy();
    });
});
