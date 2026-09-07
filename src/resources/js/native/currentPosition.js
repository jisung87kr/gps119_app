// 신고 화면의 «1회» 위치 취득 — iOS 앱 전용 (field-feedback-2026-09 F-12).
//
// 🔴 WKWebView 는 navigator.geolocation 을 부를 때마다 OS 권한과 «별개로» 사이트 동의
//    (「gps119.co.kr 이(가) 위치를 사용하려고 합니다」)를 띄우고, 그 답을 페이지 너머로
//    기억하지 않는다. 이 앱은 페이지 단위로 이동하므로 신고 화면을 열 때마다 팝업이 떴다
//    (2026-09-07 고객 피드백). 공개 API 로 자동 승인할 수 없다. 그래서 iOS 앱에서는 이미
//    들어 있는 배경 위치 플러그인으로 1회 취득한다 — OS 권한만 보고, WebKit 프롬프트는 안 뜬다.
//
// 🔑 Android 는 하지 않는다. Capacitor Android 는 OS 권한을 받은 뒤 WebView 에 자동 승인하므로
//    문제가 없고, addWatcher 는 포그라운드 서비스 알림(「GPS119 위치 공유」)을 잠깐 띄운다 —
//    신고 한 번에 그 알림이 번쩍이면 「뭔가 켜졌다」로 읽힌다.
//
// 🔑 «권한이 이미 있을 때만» 쓴다. 없으면 null 을 돌려주고 웹 경로(navigator.geolocation)로
//    떨어진다 — 그쪽이 OS 프롬프트를 띄운다. «언제 물어볼지»는 웹의 권한 UX 가 정하고(02 §4),
//    신고 화면이 addWatcher 로 「항상 허용」 프롬프트를 선점하면 안 된다.

import { hasNativeCapability, isNativeApp, nativePlatform, NativeCapability } from './bridge';
import { nativeLocationPlugin, readNativePermission, toGeolocationPosition } from './locationTracker';

const DEFAULT_TIMEOUT_MS = 15000;
const DEFAULT_MAX_AGE_MS = 30000;

/** W3C GeolocationPositionError «모양». mapHelpers.showGeolocationError 가 code 로 분기한다. */
export function geoError(code, message = '') {
    return { code, message, PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 };
}

/**
 * 플러그인 watcher 로 위치 1건을 받고 바로 걷는다 (순수 로직 — 플러그인·타이머는 주입).
 *
 * - options.timeout: 이 시간 안에 못 받으면 TIMEOUT(3) 으로 거부
 * - options.maximumAge: 이보다 오래된 «캐시» 위치는 버리고 새 fix 를 기다린다
 *   (`stale: true` 로 캐시를 먼저 받되, 웹 getCurrentPosition 의 maximumAge 와 같은 뜻으로 거른다)
 *
 * @returns {Promise<GeolocationPosition>}
 */
export function acquireOnce(plugin, options = {}, env = globalThis) {
    const timeoutMs = Number.isFinite(options.timeout) ? options.timeout : DEFAULT_TIMEOUT_MS;
    const maxAgeMs = Number.isFinite(options.maximumAge) ? options.maximumAge : DEFAULT_MAX_AGE_MS;
    const now = () => (typeof env.Date?.now === 'function' ? env.Date.now() : Date.now());

    return new Promise((resolve, reject) => {
        let settled = false;
        let watcherId = null;
        let timer = null;

        const release = (id) => {
            if (id == null) return;
            Promise.resolve(plugin.removeWatcher({ id })).catch(() => {});
        };

        const finish = (fn, value) => {
            if (settled) return;
            settled = true;
            env.clearTimeout(timer);
            release(watcherId);
            watcherId = null;
            fn(value);
        };

        timer = env.setTimeout(() => finish(reject, geoError(3, 'timeout')), timeoutMs);

        Promise.resolve(plugin.addWatcher(
            {
                backgroundMessage: '구조 지원을 위해 위치를 확인하는 중입니다.',
                backgroundTitle: 'GPS119 위치 확인',
                requestPermissions: false,
                stale: true,
                distanceFilter: 0,
            },
            (location, error) => {
                if (error) {
                    finish(reject, geoError(error.code === 'NOT_AUTHORIZED' ? 1 : 2, error.message));

                    return;
                }
                if (!location) return;

                // 캐시가 너무 오래됐으면 새 fix 를 기다린다 — 30초 전 좌표는 «없는 것»보다 낫지만
                // 어제 좌표는 아니다.
                const time = Number.isFinite(location.time) ? location.time : null;
                if (time != null && now() - time > maxAgeMs) return;

                finish(resolve, toGeolocationPosition(location));
            },
        )).then((id) => {
            // 콜백이 id 보다 먼저 와서 이미 끝났을 수 있다 — 그때는 여기서 걷는다.
            if (settled) release(id);
            else watcherId = id;
        }, (e) => finish(reject, geoError(2, e?.message ?? 'addWatcher failed')));
    });
}

/**
 * 셸이 «1회 취득»을 할 수 있으면 그 함수를, 아니면 null.
 *
 * 돌려준 함수는 권한이 없으면 null 로 resolve 한다 — 호출부(mapHelpers)는 그때 웹 경로로 간다.
 *
 * @returns {null | ((options?: object) => Promise<GeolocationPosition|null>)}
 */
export function createNativeCurrentPosition(env = globalThis) {
    if (!isNativeApp(env)) return null;
    if (nativePlatform(env) !== 'ios') return null;
    if (!hasNativeCapability(NativeCapability.BACKGROUND_LOCATION, env)) return null;

    const plugin = nativeLocationPlugin(env);
    if (typeof plugin?.addWatcher !== 'function') return null;

    return async function getCurrentPosition(options = {}) {
        const permission = await readNativePermission(env);
        if (permission !== 'always' && permission !== 'when_in_use') return null;

        return acquireOnce(plugin, options, env);
    };
}
