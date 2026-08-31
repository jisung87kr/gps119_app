import { describe, it, expect } from 'vitest';
import { shareStatus } from '../../resources/js/native/locationPermission.js';

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

        expect(s.label).toBe('공유 중지됨');
        expect(s.action).toBeNull();
    });

    it('웹(step=none)에서는 그냥 공유 중이다 — 브라우저는 그게 정상이다', () => {
        const s = shareStatus({ sharing: true, permissionStep: 'none' });

        expect(s.tone).toBe('ok');
        expect(s.action).toBeNull();
    });

    it('🔑 조치 버튼은 «필요할 때만» 나온다', () => {
        // 항상 떠 있으면 토글과 다시 경쟁하게 된다.
        expect(shareStatus({ sharing: true, permissionStep: 'none' }).action).toBeNull();
        expect(shareStatus({ sharing: false, permissionStep: 'none' }).action).toBeNull();
    });
});
