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
