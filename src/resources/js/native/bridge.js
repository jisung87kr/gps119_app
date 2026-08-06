// 네이티브 셸 브리지 (mobile-app 에픽 N2 / 01 §5).
//
// 웹 코드가 Capacitor 를 «직접 알지 않게» 하는 유일한 접점이다.
// 셸을 바꾸거나 걷어내도 교체할 곳은 이 파일 하나다.
//
// 🔑 이 파일의 존재 이유는 감지가 아니라 «기능 협상»이다.
//    웹은 배포하면 즉시 갱신되지만 **앱은 스토어 심사를 거쳐 천천히 갱신된다**
//    (01 §4-3: 웹 배포 = 앱 갱신, 단 네이티브 기능은 앱 버전에 묶인다).
//    즉 «웹이 앱보다 최신»인 상태가 정상이고, 그 상태에서 앱에 없는 기능을 부르면
//    구버전 앱이 깨진다. 그래서 **있는지 물어보고 없으면 웹 경로로 떨어진다.**
//    「앱이면 네이티브」가 아니라 「그 앱이 그 기능을 아는가」로 판정한다.

/**
 * Capacitor 웹뷰 안에서 실행 중인가.
 *
 * 셸은 원격 URL 을 그대로 띄우므로(01 A안) 오리진이 웹과 같다 —
 * User-Agent 나 도메인으로는 구분할 수 없고, 셸이 주입하는 전역으로만 안다.
 */
export function isNativeApp(env = globalThis) {
    const cap = env.Capacitor;
    if (!cap) return false;

    // isNativePlatform() 은 Capacitor 3+ 에서 제공. 없으면 전역 존재만으로 판정한다
    // (구버전 셸에서 «앱인데 아니라고» 답하면 SW 가 이중으로 붙는다).
    return typeof cap.isNativePlatform === 'function' ? cap.isNativePlatform() : true;
}

/**
 * @returns {'ios'|'android'|'web'} 셸이 보고하는 플랫폼
 */
export function nativePlatform(env = globalThis) {
    if (!isNativeApp(env)) return 'web';

    const platform = env.Capacitor?.getPlatform?.();

    return platform === 'ios' || platform === 'android' ? platform : 'web';
}

/**
 * 이 셸이 제공하는 기능 목록. 셸이 `window.__gps119Native` 로 심는다.
 *
 * 웹이 앱보다 최신일 수 있으므로 **없는 키는 «아직 모르는 기능»** 이지 오류가 아니다.
 *
 * @returns {{version: string|null, capabilities: string[]}}
 */
export function nativeInfo(env = globalThis) {
    const info = env.__gps119Native;

    if (!info || typeof info !== 'object') {
        return { version: null, capabilities: [] };
    }

    return {
        version: typeof info.version === 'string' ? info.version : null,
        capabilities: Array.isArray(info.capabilities) ? info.capabilities : [],
    };
}

/**
 * 이 앱이 그 기능을 아는가.
 *
 * 🔑 웹에서는 «항상 false» 다 — 네이티브 기능은 정의상 앱에만 있다.
 *    호출하는 쪽은 false 를 「없음」이 아니라 「웹 경로로 가라」로 읽어야 한다.
 */
export function hasNativeCapability(name, env = globalThis) {
    if (!isNativeApp(env)) return false;

    // ① 셸이 «명시적으로» 알린 기능. 플러그인으로 확인할 수 없는 것도 여기로 온다.
    if (nativeInfo(env).capabilities.includes(name)) return true;

    // ② 플러그인으로 «실측»되는 기능.
    //
    // 🔑 이쪽이 더 정확하다. 손으로 관리하는 목록은 «거짓말을 할 수 있다» —
    //    셸에서 플러그인을 빼고 목록을 안 고치면 웹은 있다고 믿고 부르다 깨진다.
    //    isPluginAvailable() 은 그 빌드에 «실제로 들어간» 네이티브 플러그인만 참이다.
    //
    //    ⚠️ 실제로 셸은 `window.__gps119Native` 를 심은 적이 «없었다»(문서에만 있었다).
    //       ① 만 있었다면 이 함수는 영원히 false 였다.
    const plugin = PLUGIN_FOR[name];

    return Boolean(plugin && env.Capacitor?.isPluginAvailable?.(plugin));
}

/**
 * 알려진 기능 이름. 셸과 웹이 같은 문자열을 써야 하므로 여기 모아 둔다.
 *
 * ⚠️ **값을 바꾸지 말 것.** 이미 배포된 앱이 옛 이름으로 신고하므로,
 *    이름을 바꾸면 그 앱들에서 기능이 통째로 사라진다. 새 이름은 추가만 한다.
 */
export const NativeCapability = {
    /** 백그라운드 위치 추적 (N3) */
    BACKGROUND_LOCATION: 'background-location',
    /** FCM 푸시 토큰 발급 (N3) */
    PUSH_TOKEN: 'push-token',
    /** 파일 다운로드 처리 — WKWebView 가 기본 처리하지 않는다 (M-21) */
    FILE_DOWNLOAD: 'file-download',
};

/**
 * 기능 ↔ Capacitor 플러그인 대응표.
 *
 * 여기 있는 기능은 셸의 «선언»이 없어도 플러그인 존재만으로 판정된다.
 * 대응 플러그인이 없는 기능(file-download 처럼 셸이 직접 구현하는 것)은 넣지 않는다 —
 * 넣을 게 없으면 ①(명시 선언)만으로 판정된다.
 */
const PLUGIN_FOR = {
    // ⚠️ '@capacitor/push-notifications' 가 아니라 FirebaseMessaging 이다.
    //    전자는 iOS 에서 «APNs 토큰»을 준다 — 그걸 FCM 에 보내면 INVALID_ARGUMENT 가
    //    오고, 서버는 그걸 「죽은 기기」로 읽어 **살아 있는 아이폰을 폐기**한다.
    //    PushPlatform::usesFcm() 이 iOS 도 FCM 경유를 전제하므로 토큰도 FCM 이어야 한다.
    [NativeCapability.PUSH_TOKEN]: 'FirebaseMessaging',
};
