// 위치 권한 3단계 UX (mobile-app N3 / 02 §4).
//
// 🔑 **한 번에 「항상 허용」을 요구하면 거절률이 급등한다.** 그래서 나눈다:
//
//      1단계  행사 입장 직후          「사용 중에만」 — 브라우저/OS 기본 프롬프트
//      2단계  공유를 켤 때            «왜 필요한지»를 우리 화면으로 설명한 뒤 「항상 허용」
//      3단계  거부됐을 때             기능 제한 안내 + 설정 앱 딥링크
//
// 🔑 **이 파일은 「지금 어느 단계인가」만 정한다.** 화면을 그리지도, 권한을 요청하지도
//    않는다. 순수 함수라 테스트가 지킬 수 있고, 화면이 바뀌어도 판정은 안 흔들린다.

import { isNativeApp } from './bridge';
import { readNativePermission } from './locationTracker';

/**
 * 지금 사용자에게 무엇을 보여야 하는가.
 *
 * @returns {'none'|'explain_always'|'guide_settings'}
 */
export function decidePermissionStep({ native = false, permission = null, sharing = false } = {}) {
    // 웹에는 「항상 허용」이라는 개념이 없다. 브라우저 프롬프트가 1단계이자 전부이고,
    // 탭이 죽으면 어차피 끊긴다 — 여기서 설명 화면을 띄우면 고칠 수 없는 것을 요구하게 된다.
    if (!native) return 'none';

    // 🔑 보고가 없으면(구버전 셸·읽기 실패) 아무것도 하지 않는다.
    //    「모른다」를 「거부됨」으로 읽고 설정 화면으로 보내면, 멀쩡한 사용자가
    //    고칠 것도 없는 설정을 뒤지게 된다.
    if (permission === null) return 'none';

    if (permission === 'always') return 'none';

    // 🔴 여기가 3단계다. 공유를 켰든 안 켰든 보여준다 — 「켜고 나서야 막힌 걸 아는」
    //    순서면 대원은 이미 현장에 있고, 그때는 고칠 시간이 없다.
    if (permission === 'denied' || permission === 'services_off') return 'guide_settings';

    // when_in_use · not_determined — 공유를 «켤 때»만 승격을 권한다.
    // 끄고 있는 사람에게 배경 권한을 조르는 것은 거절률만 올린다.
    return sharing ? 'explain_always' : 'none';
}

/**
 * 지금 권한 상태를 읽어 서버에 보고한다 (M-5 / ADR-0008).
 *
 * ⚠️ **공유가 꺼져 있어도 부른다.** 권한이 끊기면 위치도 끊기므로, 「추적 중인 사람」의
 *    상태만 알 수 있으면 정작 알아야 할 경우를 놓친다.
 *
 * ⚠️ **읽을 게 없으면 보내지 않는다.** null 을 보내면 서버가 「보고한 적 없음」과
 *    「모른다고 보고함」을 구분할 수 없다 — 전자는 웹 사용자이고 후자는 앱인데,
 *    관제 화면에서 둘은 다르게 읽혀야 한다.
 *
 * 실패를 삼킨다. 보고가 안 됐다고 위치 공유 자체를 막으면 안 된다 —
 * 구조 요청은 알림 부가기능보다 중요하다.
 *
 * @returns {Promise<string|null>} 보고한 값. 보내지 않았으면 null.
 */
export async function reportLocationPermission(projectId, env = globalThis) {
    const permission = await readNativePermission(env);
    if (permission === null) return null;

    try {
        await env.axios?.patch(
            `/api/events/${projectId}/location-permission`,
            { permission },
            { headers: { Accept: 'application/json' } },
        );
    } catch {
        // 무시 — 다음 포그라운드 복귀에서 다시 보낸다.
    }

    return permission;
}

/**
 * 앱 설정 화면을 연다 (3단계 딥링크).
 *
 * 🔑 배경 위치 플러그인이 `openSettings()` 를 제공한다. 웹에는 대응물이 없다 —
 *    브라우저 설정을 열 방법이 없어서 안내 문구로만 처리해야 한다.
 *
 * @returns {Promise<boolean>} 실제로 열었으면 true
 */
export async function openLocationSettings(env = globalThis) {
    const plugin = env.Capacitor?.Plugins?.BackgroundGeolocation;
    if (!plugin?.openSettings) return false;

    try {
        await plugin.openSettings();

        return true;
    } catch {
        return false;
    }
}

/**
 * 화면이 붙일 수 있는 «관측» 지점.
 *
 * 🔴 **포그라운드 복귀마다 다시 읽어야 한다.** iOS 는 「항상 허용」을 나중에 다시
 *    물어보고(재확인 프롬프트), 사용자가 거기서 「사용 중」으로 되돌리면 배경 추적이
 *    «조용히» 끊긴다. 앱 안에서는 아무 일도 일어나지 않으므로 복귀 시점에 읽지 않으면
 *    관제는 끊긴 사람을 계속 「추적 중」으로 본다.
 *
 * @returns {() => void} 해제 함수
 */
export function watchPermissionChanges(projectId, onChange, env = globalThis) {
    if (!isNativeApp(env)) return () => {};

    const handler = async () => {
        if (env.document?.visibilityState === 'hidden') return;
        onChange(await reportLocationPermission(projectId, env));
    };

    env.document?.addEventListener?.('visibilitychange', handler);

    return () => env.document?.removeEventListener?.('visibilitychange', handler);
}

/**
 * 참가자 화면의 「지금 내 위치가 어떻게 되고 있나」 한 줄.
 *
 * 🔴 **예전에는 상태가 `sharing` 만 보고 「위치 공유 중」이라 말했다.** 권한이 거부돼도
 *    초록 불이 켜졌고, 바로 아래에서는 「전혀 전달되지 않습니다」라고 말하고 있었다 —
 *    M-5 가 관제에서 막으려던 «거짓 안심»을 참가자 본인 화면에서 하고 있었던 셈이다.
 *
 * 🔑 **판정 표를 새로 만들지 않는다.** `decidePermissionStep` 의 결과와 `sharing` 만
 *    조합한다. 매트릭스를 두 벌로 만들면 0-8 처럼 어긋난다.
 *
 * 🔑 **조치는 «필요할 때만» 나온다.** 항상 떠 있으면 공유 토글과 다시 경쟁하게 되고,
 *    그게 정확히 이 화면이 혼란스러웠던 이유였다(의도 vs 권한이 동급 버튼이었다).
 *
 * @returns {{label: string, hint: string|null, action: 'settings'|'always'|null, tone: string}}
 */
export function shareStatus({
    sharing = false,
    permissionStep = 'none',
    osPermission = null,
    webPermission = null,
    native = false,
} = {}) {
    if (!sharing) {
        // 끈 사람에게 붉은 경고를 띄우면 진짜 이상이 묻힌다.
        return { label: '위치 공유 꺼짐', hint: null, action: null, tone: 'muted' };
    }

    if (permissionStep === 'guide_settings') {
        return {
            // 기기 위치가 꺼진 경우와 권한 거부는 «안내가 달라야» 한다 —
            // 앱 설정만 들여다보다 영영 못 고치는 일을 막는다.
            label: osPermission === 'services_off'
                ? '기기 위치 서비스가 꺼져 있습니다'
                : '위치 권한이 꺼져 있습니다',
            hint: '지금은 상황실에 위치가 전달되지 않습니다.',
            action: 'settings',
            tone: 'danger',
        };
    }

    // 🔴 **웹의 권한 신호도 봐야 한다.** decidePermissionStep 은 웹에서 항상 'none' 이다
    //    (브라우저엔 「항상 허용」이라는 개념이 없으니 의도한 동작이다). 그것만 보면
    //    브라우저가 위치를 «거부»했는데도 「위치 공유 중」이라고 말하게 된다 —
    //    실제로 PC 에서 초록 불과 빨간 오류가 함께 떴다(2026-08-31).
    //    앱에서도 이 값은 채워지므로, 위 네이티브 분기 «뒤»에 둬서 순서를 지킨다.
    if (webPermission === 'unsupported') {
        return {
            // 🔑 앱에는 «브라우저»가 없다. 앱에서 「다른 브라우저에서 열어 주세요」는
            //    있지도 않은 해결책이라, 사용자는 할 수 있는 게 없어진다.
            //    (같은 실수를 'denied' 분기의 「주소창」 안내에서 한 번 했다.)
            label: native ? '이 기기에서는 위치를 쓸 수 없습니다' : '위치를 쓸 수 없는 브라우저입니다',
            hint: native
                ? '기기의 위치 서비스를 켠 뒤 앱을 다시 열어 주세요.'
                : '다른 브라우저에서 열어 주세요.',
            action: native ? 'settings' : null,
            tone: 'danger',
        };
    }

    if (webPermission === 'denied') {
        return {
            label: '위치 권한이 꺼져 있습니다',
            // 🔴 **고치는 곳이 웹과 앱이 다르다.** 앱에서 「주소창」을 안내하면
            //    사용자는 있지도 않은 UI 를 찾는다 — 웹뷰에는 브라우저 UI 가 없다.
            hint: native
                ? '지금은 상황실에 위치가 전달되지 않습니다. 설정에서 허용해 주세요.'
                : '지금은 상황실에 위치가 전달되지 않습니다. 주소창의 위치 아이콘에서 허용해 주세요.',
            // 🔑 웹에는 설정을 열 방법이 없다. 누를 수 없는 버튼을 두지 않는다.
            action: native ? 'settings' : null,
            tone: 'danger',
        };
    }

    if (webPermission === 'prompt') {
        // 아직 한 건도 못 보낸 상태다. 「공유 중」이라고 말하면 그것도 거짓이다.
        return {
            label: '위치 허용을 기다리는 중입니다',
            hint: '위치 사용 안내가 뜨면 허용을 눌러 주세요.',
            action: null,
            tone: 'warning',
        };
    }

    if (permissionStep === 'explain_always') {
        return {
            label: '앱을 열어둔 동안만 공유됩니다',
            hint: '화면을 끄거나 다른 앱을 쓰면 위치가 멈춥니다.',
            action: 'always',
            tone: 'warning',
        };
    }

    // 🔴 **아직 모르면 «모른다»고 말한다.** 초기값이 'unsupported' 이던 시절, 화면이
    //    열리자마자 「위치를 쓸 수 없는 브라우저입니다」라는 붉은 단정이 떴다가 첫 판정이
    //    오면 사라졌다(실기기에서 약 10초, 2026-09-01).
    //
    // 🔑 **자리가 중요하다.** 네이티브 판정(permissionStep)보다 «뒤»에 있어야 한다 —
    //    앱에서는 webPermission 이 비어 있는 채로 permissionStep 만 채워지는 경우가
    //    정상이라, 앞에 두면 「항상 허용으로 바꾸기」 안내를 통째로 가린다.
    //    (같은 순서 문제를 'denied' 분기에서 한 번 겪었다 — 이 파일 위쪽 주석 참조.)
    //
    // 🔑 그렇다고 「공유 중」이라고 하지도 않는다. 한 건도 못 보낸 상태에서 초록 불을
    //    켜는 것이 M-5 가 막으려던 «거짓 안심»이다.
    if (webPermission == null) {
        return { label: '위치 확인 중', hint: null, action: null, tone: 'muted' };
    }

    return { label: '위치 공유 중', hint: null, action: null, tone: 'ok' };
}

/** 위치를 «전혀» 못 얻는 값들. LocationPermission::blocksTracking() 과 같은 목록이다. */
const BLOCKED = ['denied', 'services_off', 'not_determined'];

/**
 * 권한이 «막힘 → 쓸 수 있음»으로 바뀌었나 (= 지금 추적을 다시 시작해야 하나).
 *
 * 🔴 **iOS 는 한 번 거부되면 앱 안에서 프롬프트를 다시 못 띄운다.** 사용자는 설정으로
 *    가서 고치고 돌아오는데, 그때 앱이 «스스로» 다시 시작하지 않으면 화면은 그대로다 —
 *    고쳤는데도 아무 일이 없으니 「역시 안 되네」로 끝난다.
 *
 * 🔑 **첫 보고(prev === null)에는 재시작하지 않는다.** 그때는 이미 enable()/resume() 이
 *    감시를 시작한 뒤라, 여기서 또 restart 하면 불필요하게 끊었다 잇는다.
 */
export function shouldRestartTracking(prev, next) {
    if (!next || BLOCKED.includes(next)) return false;

    return prev !== null && prev !== undefined && BLOCKED.includes(prev);
}
