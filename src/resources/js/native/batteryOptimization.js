// 배터리 최적화 예외 (M-26).
//
// 🔴 **안드로이드는 이게 없으면 화면을 끄는 순간 위치 전송이 «완전히» 멈춘다.**
//    2026-09-01 실측(Galaxy A36 / Android 16): 화면을 끈 3분간 서버 수신 0건 →
//    배터리 최적화 예외를 넣자 9건(간격 10~32초).
//
// 🔑 **가장 나쁜 점은 「정상처럼 보인다」는 것이다.** 그 상태에서도 앱은 멀쩡히 돌고,
//    포그라운드 알림도 떠 있고, 화면을 켜면 위치가 다시 간다. 사용자도 관제도
//    끊긴 줄 모른다 — M-5 가 막으려던 «거짓 안심»과 같은 종류다.
//
// 🔑 iOS 에는 이 개념이 없다. 그래서 «안드로이드에서만» 묻는다.

import { isNativeApp, nativePlatform } from './bridge';

/**
 * 지금 배터리 최적화에서 제외돼 있는가.
 *
 * @returns {Promise<boolean|null>} 모르면 null (웹·구버전 셸·읽기 실패)
 */
export async function readBatteryOptimization(env = globalThis) {
    const plugin = env.Capacitor?.Plugins?.Gps119BatteryOptimization;
    if (!plugin?.check) return null;

    try {
        const res = await plugin.check();

        return typeof res?.ignoring === 'boolean' ? res.ignoring : null;
    } catch {
        return null;
    }
}

/**
 * 배터리 최적화 설정 화면을 연다.
 *
 * @returns {Promise<boolean>} 열지 못했으면 false
 */
export async function openBatteryOptimizationSettings(env = globalThis) {
    const plugin = env.Capacitor?.Plugins?.Gps119BatteryOptimization;
    if (!plugin?.openSettings) return false;

    try {
        const res = await plugin.openSettings();

        return res?.opened !== false;
    } catch {
        return false;
    }
}

/**
 * 지금 사용자에게 배터리 최적화를 «경고»해야 하는가. **순수 함수다.**
 *
 * 🔑 **「모른다」(null)로는 경고하지 않는다.** 구버전 셸이나 읽기 실패에서 경고를 띄우면,
 *    고칠 것이 없는 사람에게 고치라고 말하게 된다 — M-5 에서 같은 실수를 했다.
 *
 * 🔑 **공유를 켠 사람에게만 말한다.** 끄고 있는 사람에게 배터리 설정을 요구하는 것은
 *    이유 없는 요구다.
 *
 * 🔑 **안드로이드에만 해당한다.** iOS 에는 배터리 최적화라는 개념이 없어서, 거기서
 *    띄우면 있지도 않은 설정을 찾게 만든다.
 */
export function shouldWarnBatteryOptimization({
    native = false,
    platform = 'web',
    sharing = false,
    ignoring = null,
} = {}) {
    if (!native || platform !== 'android') return false;
    if (!sharing) return false;
    if (ignoring !== false) return false;   // true(제외됨) 도, null(모름) 도 경고하지 않는다

    return true;
}

/** 화면이 그대로 쓰는 안내 문구. 문구를 화면마다 다시 쓰면 어긋난다. */
export const BATTERY_WARNING = {
    title: '화면을 끄면 위치가 멈출 수 있습니다',
    body: '이 기기는 절전을 위해 앱을 재웁니다. 배터리 최적화에서 GPS119 를 제외해 두면 '
        + '화면이 꺼져 있어도 상황실에 위치가 계속 전달됩니다.',
    action: '배터리 설정 열기',
};

/** 셸이 이 기능을 아는가 (구버전 앱 대비). */
export function supportsBatteryOptimization(env = globalThis) {
    return Boolean(env.Capacitor?.Plugins?.Gps119BatteryOptimization?.check);
}

/** 지금 이 화면에서 물어볼 조건이 갖춰졌는지 한 번에 판정한다. */
export async function checkBatteryOptimization(sharing, env = globalThis) {
    if (!isNativeApp(env) || nativePlatform(env) !== 'android') {
        return { ignoring: null, warn: false };
    }

    const ignoring = await readBatteryOptimization(env);

    return {
        ignoring,
        warn: shouldWarnBatteryOptimization({
            native: true,
            platform: 'android',
            sharing,
            ignoring,
        }),
    };
}
