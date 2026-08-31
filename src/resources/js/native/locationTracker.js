// 위치 «취득» 어댑터 (mobile-app N3 / 02 §3-3).
//
// 🔑 **이 파일은 「어떻게 얻는가」만 안다.** 「언제 보내는가」(적응형 주기·버퍼·429
//    백오프)는 locationShare.js 가 갖는다. 그렇게 나눠야 셸을 바꿔도 전송 로직이
//    안 흔들리고, 반대로 전송 정책을 고쳐도 플러그인을 안 건드린다.
//
// 🔑 **웹 구현이 여기 없다.** 이 파일은 «네이티브가 가능할 때만» 트래커를 만들고
//    아니면 null 을 돌려준다. 웹 경로는 locationShare.js 안에 이미 있으므로,
//    여기에 또 만들면 같은 로직이 두 벌이 된다 — N0 의 0-8 이 그 실패였다.
//
// ⚠️ **플러그인 이름을 이 파일 밖에서 부르지 않는다.** M-4 로 고른
//    @capacitor-community/background-geolocation 이 언젠가 멈추면(ADR 에 적은
//    되돌릴 조건) 갈아끼울 곳이 여기 하나여야 한다.

import { hasNativeCapability, isNativeApp, NativeCapability } from './bridge';

/** 셸이 노출하는 플러그인 객체 이름. 갈아끼울 때 바뀌는 유일한 문자열. */
const PLUGIN = 'BackgroundGeolocation';

/**
 * 권한 «상태»를 읽는 셸 자체 플러그인 (M-5).
 *
 * 🔴 **배경 위치 플러그인에는 `checkPermissions()` 가 없다.** watcher 가
 *    NOT_AUTHORIZED 로 실패하는지만 알 수 있어서 **「항상 허용」과 「사용 중만」을
 *    구분하지 못한다.** 그러면 M-5 의 tracking 과 foreground_only 가 합쳐지고,
 *    관제는 «화면을 닫으면 끊기는 사람»을 「추적 중」으로 본다 — M-5 가 막으려던
 *    바로 그 오판이다. 그래서 셸에 읽기 전용 메서드를 따로 뒀다.
 */
const PERMISSION_PLUGIN = 'Gps119LocationPermission';

/**
 * 앱/OS 가 주는 권한 값을 «우리 값»으로 접는다 (M-5 / ADR-0008).
 *
 * 🔑 경계에서 한 번만 매핑한다. 안쪽에서는 이 값만 믿는다 — 화면마다 iOS/Android
 *    문자열을 알아보게 하면 신뢰 경계가 사라진다.
 *
 * 모르는 값은 `null` 을 돌려준다. **`denied` 로 접지 않는다** — 「모른다」를
 * 「거부됨」으로 바꾸면 관제에 붉은 배지가 뜨고, 사람은 실제로 없는 문제를 쫓는다.
 */
export function toLocationPermission(raw) {
    switch (raw) {
        case 'always':
        case 'authorizedAlways':
        case 'granted_background':
            return 'always';

        case 'when_in_use':
        case 'authorizedWhenInUse':
        case 'granted':
            return 'when_in_use';

        // iOS restricted(스크린타임·MDM)는 denied 로 접는다 — 우리가 할 수 있는
        // 안내가 같다(ADR-0008 D2).
        case 'denied':
        case 'restricted':
            return 'denied';

        case 'services_off':
        case 'disabled':
            return 'services_off';

        case 'not_determined':
        case 'notDetermined':
        case 'prompt':
            return 'not_determined';

        default:
            return null;
    }
}

/**
 * 셸에게 지금 권한 상태를 묻는다. 못 물으면 `null`.
 *
 * 🔑 **트래커와 «따로» 부를 수 있어야 한다.** 권한 보고는 공유가 꺼져 있어도, 추적을
 *    시작하지 않아도 해야 하기 때문이다(ADR-0008 D3) — 권한이 끊기면 위치도 끊기므로
 *    「추적 중인 사람」의 상태만 알 수 있으면 정작 알아야 할 경우를 놓친다.
 *
 * ⚠️ **실패를 삼키고 null 을 돌려준다.** 「모른다」와 「거부됨」은 다르다. 구버전 셸에는
 *    이 플러그인이 아예 없고, 그때도 null 이다 — 서버는 그 참가자를 unknown 으로 둔다.
 *    호출 실패를 denied 로 접으면 관제에 붉은 배지가 뜨고 사람이 없는 문제를 쫓는다.
 */
export async function readNativePermission(env = globalThis) {
    const plugin = env.Capacitor?.Plugins?.[PERMISSION_PLUGIN];
    if (!plugin?.check) return null;

    try {
        const res = await plugin.check();

        return toLocationPermission(res?.status ?? null);
    } catch {
        return null;
    }
}

/**
 * 네이티브 백그라운드 위치 트래커. **이 셸이 못 하면 `null`.**
 *
 * 🔑 null 을 돌려주는 것이 이 함수의 절반이다. 「앱이면 네이티브」가 아니라
 *    「그 앱이 그 기능을 아는가」로 판정하므로(bridge.js), 플러그인이 없는 구버전
 *    앱에서는 조용히 웹 경로로 떨어져야 한다 — 부르다 깨지면 안 된다.
 *
 * @returns {null | {
 *   kind: 'native',
 *   supportsBackground: boolean,
 *   supported: boolean,
 *   start(onFix: Function, onError: Function): Promise<void>,
 *   stop(): Promise<void>,
 *   readPermission(): Promise<string|null>,
 * }}
 */
export function createNativeLocationTracker(env = globalThis) {
    if (!isNativeApp(env)) return null;
    if (!hasNativeCapability(NativeCapability.BACKGROUND_LOCATION, env)) return null;

    const plugin = env.Capacitor?.Plugins?.[PLUGIN];
    if (!plugin) return null;

    let watcherId = null;

    return {
        kind: 'native',
        supportsBackground: true,
        supported: true,

        /**
         * 🔑 **onFix 에 W3C GeolocationPosition «모양»으로 넘긴다.**
         *    그게 이미 locationShare.js 안쪽의 계약이라, 여기서 맞춰 주면 웹 경로는
         *    무변환이고 적응은 네이티브 쪽에서만 일어난다. 안쪽 코드가 「앱인지
         *    웹인지」를 영영 몰라도 되는 것이 요점이다.
         */
        async start(onFix, onError) {
            if (watcherId != null) return;

            watcherId = await plugin.addWatcher(
                {
                    // 백그라운드 상시 알림 문구(Android 포그라운드 서비스 요건).
                    // 없으면 플러그인이 백그라운드 취득을 시작하지 못한다.
                    backgroundMessage: '구조 지원을 위해 위치를 공유하는 중입니다.',
                    backgroundTitle: 'GPS119 위치 공유',
                    requestPermissions: false, // 권한 요청은 UX 단계에서 따로 한다
                    stale: false,
                    distanceFilter: 0,         // 전송 판정은 locationShare 가 한다
                },
                (location, error) => {
                    if (error) {
                        onError({
                            code: error.code === 'NOT_AUTHORIZED' ? 1 : 2,
                            PERMISSION_DENIED: 1,
                            POSITION_UNAVAILABLE: 2,
                            TIMEOUT: 3,
                            message: error.message,
                        });

                        return;
                    }
                    if (!location) return;

                    onFix({
                        coords: {
                            latitude: location.latitude,
                            longitude: location.longitude,
                            accuracy: location.accuracy,
                            heading: location.bearing ?? null,
                            speed: location.speed ?? null,
                        },
                        timestamp: location.time ?? Date.now(),
                    });
                },
            );
        },

        async stop() {
            if (watcherId == null) return;

            const id = watcherId;
            watcherId = null;
            await plugin.removeWatcher({ id });
        },

        /** 지금 OS 권한이 어떤 상태인가 (M-5 보고용). → readNativePermission */
        async readPermission() {
            return readNativePermission(env);
        },
    };
}
