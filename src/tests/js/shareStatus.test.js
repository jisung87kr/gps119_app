import { describe, it, expect } from 'vitest';
import { shareStatus, shouldRestartTracking } from '../../resources/js/native/locationPermission.js';

/**
 * 참가자 화면의 「지금 내 위치가 어떻게 되고 있나」 한 줄 (N3 / 02 §4).
 *
 * 🔴 **고정하려는 것은 「권한이 없는데 초록 불이 켜지지 않는다」이다.**
 *    예전에는 상태가 sharing 만 보고 「위치 공유 중」이라 말했다 — 권한이 거부돼도
 *    초록 불이 켜졌고, 바로 아래에서는 「전혀 전달되지 않습니다」라고 말하고 있었다.
 *    M-5 가 관제에서 막으려던 «거짓 안심»을 참가자 본인 화면에서 하고 있었던 셈이다.
 *
 * 🔑 화면(blade)은 이 함수를 부르고 톤→클래스만 입힌다. 판정이 한 곳에 있으므로
 *    여기서 «진짜 함수»를 검사한다 — 규칙을 테스트에 옮겨 적으면 그 자체가 0-8 이다.
 */

describe('shareStatus — 상태가 «진실»을 말한다', () => {
    it('🔴 권한이 거부됐으면 «공유 중»이라고 말하지 않는다', () => {
        const s = shareStatus({ sharing: true, permissionStep: 'guide_settings', osPermission: 'denied' });

        expect(s.tone).toBe('danger');
        expect(s.label).not.toContain('공유 중');
        expect(s.action).toBe('settings');
    });

    it('기기 위치가 꺼진 경우와 권한 거부를 구분한다', () => {
        // 안내가 달라야 한다 — 앱 설정만 들여다보다 영영 못 고치는 일을 막는다.
        const off = shareStatus({ sharing: true, permissionStep: 'guide_settings', osPermission: 'services_off' });
        const denied = shareStatus({ sharing: true, permissionStep: 'guide_settings', osPermission: 'denied' });

        expect(off.label).not.toBe(denied.label);
    });

    it('「사용 중만」이면 한계를 말하고 승격을 권한다', () => {
        const s = shareStatus({ sharing: true, permissionStep: 'explain_always' });

        expect(s.tone).toBe('warning');
        expect(s.action).toBe('always');
    });

    it('공유를 껐으면 권한 상태와 무관하게 «중지됨»이다', () => {
        // 끈 사람에게 붉은 경고를 띄우면 진짜 이상이 묻힌다.
        const s = shareStatus({ sharing: false, permissionStep: 'guide_settings', osPermission: 'denied' });

        // 🔑 문구가 아니라 «톤과 조치»로 판정한다. 카피는 바뀌어도 계약은 그대로다.
        expect(s.tone).toBe('muted');
        expect(s.action).toBeNull();
    });

    it('웹에서 권한이 «허용»이면 그냥 공유 중이다 — 브라우저는 그게 정상이다', () => {
        const s = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'granted' });

        expect(s.tone).toBe('ok');
        expect(s.action).toBeNull();
    });

    it('🔴 웹에서 권한이 거부됐는데 «공유 중»이라고 말하지 않는다', () => {
        // PC 실측(2026-08-31): 초록 「위치 공유 중」과 빨간 「권한이 거부되었습니다」가
        // 같은 카드에 함께 떴다. decidePermissionStep 이 웹에서 늘 'none' 이라
        // 상태가 웹의 권한 신호를 아예 안 보고 있었다.
        const s = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'denied' });

        expect(s.tone).toBe('danger');
        expect(s.label).not.toContain('공유 중');
    });

    it('🔑 웹에서는 «설정 열기» 조치를 주지 않는다 — 열 방법이 없다', () => {
        // 누를 수 없는 버튼을 두면 사용자는 눌러보고 아무 일도 없는 것을 겪는다.
        const s = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'denied' });

        expect(s.action).toBeNull();
        expect(s.hint).toContain('주소창');
    });

    it('권한을 아직 못 받았으면 «공유 중»이 아니라 «기다리는 중»이다', () => {
        // 한 건도 못 보낸 상태에서 「공유 중」이라고 말하면 그것도 거짓이다.
        const s = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'prompt' });

        expect(s.tone).toBe('warning');
        expect(s.action).toBeNull();  // 기다리는 중에는 누를 것이 없다
    });

    it('위치를 지원하지 않는 기기도 구분한다', () => {
        const s = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'unsupported' });

        expect(s.tone).toBe('danger');
        expect(s.action).toBeNull();
    });

    it('🔴 앱에서는 「주소창」을 안내하지 않는다 — 웹뷰엔 그런 UI 가 없다', () => {
        // 앱에서 「브라우저 설정에서 허용해 주세요」가 떴다(2026-08-31 실기기).
        // 사용자는 있지도 않은 주소창을 찾게 된다.
        const app = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'denied', native: true });
        const web = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'denied', native: false });

        expect(app.hint).not.toContain('주소창');
        expect(app.hint).toContain('설정');
        expect(web.hint).toContain('주소창');
    });

    it('앱에서는 설정을 열 수 있으므로 조치를 준다', () => {
        const app = shareStatus({ sharing: true, permissionStep: 'none', webPermission: 'denied', native: true });

        expect(app.action).toBe('settings');
    });

    it('🔑 네이티브 판정이 웹 신호보다 «먼저»다', () => {
        // 앱에서도 webPermission 은 채워진다. 순서가 뒤집히면 앱에서 「설정 열기」
        // 조치를 못 주게 되고, 그러면 3단계 UX 가 사라진다.
        const s = shareStatus({
            sharing: true, permissionStep: 'guide_settings',
            osPermission: 'denied', webPermission: 'denied',
        });

        expect(s.action).toBe('settings');
    });

    it('🔑 조치 버튼은 «필요할 때만» 나온다', () => {
        // 항상 떠 있으면 토글과 다시 경쟁하게 된다.
        expect(shareStatus({ sharing: true, permissionStep: 'none' }).action).toBeNull();
        expect(shareStatus({ sharing: false, permissionStep: 'none' }).action).toBeNull();
    });
});

describe('shouldRestartTracking — 설정에서 고치고 돌아왔을 때', () => {
    it('🔴 막힘 → 허용으로 바뀌면 다시 시작한다', () => {
        // iOS 는 거부된 뒤 프롬프트를 못 띄운다. 사용자는 설정에서 고치고 돌아오는데,
        // 그때 스스로 다시 시작하지 않으면 「역시 안 되네」로 끝난다.
        expect(shouldRestartTracking('denied', 'always')).toBe(true);
        expect(shouldRestartTracking('denied', 'when_in_use')).toBe(true);
        expect(shouldRestartTracking('services_off', 'always')).toBe(true);
    });

    it('🔑 첫 보고(null)에는 재시작하지 않는다', () => {
        // 그 시점엔 이미 enable()/resume() 이 감시를 시작한 뒤다. 또 restart 하면
        // 불필요하게 끊었다 잇는다.
        expect(shouldRestartTracking(null, 'always')).toBe(false);
        expect(shouldRestartTracking(undefined, 'when_in_use')).toBe(false);
    });

    it('여전히 막혀 있으면 시작하지 않는다', () => {
        expect(shouldRestartTracking('denied', 'denied')).toBe(false);
        expect(shouldRestartTracking('when_in_use', 'denied')).toBe(false);
    });

    it('이미 쓸 수 있었으면 다시 시작하지 않는다 — 승격은 restart 가 따로 한다', () => {
        expect(shouldRestartTracking('when_in_use', 'always')).toBe(false);
    });
});
