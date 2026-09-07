import { describe, it, expect } from 'vitest';
import { deniedCopy } from '../../resources/js/push-toggle.js';

/**
 * 「알림 거부」 안내 문구 (2026-09-07 현장: 앱에서 「브라우저 설정에서 허용」이 떴다).
 */
describe('deniedCopy — 어디서 풀어야 하는지는 플랫폼마다 다르다', () => {
    it('iOS 는 설정 앱 경로 + 「설정 열기」 버튼', () => {
        const c = deniedCopy('ios');

        expect(c.text).toContain('iPhone 설정');
        expect(c.text).not.toContain('브라우저');
        expect(c.action).toBe('설정 열기');
        expect(c.failure).toContain('GPS119');
    });

    it('Android 는 설정 경로만(바로 여는 공개 URL 이 없다)', () => {
        const c = deniedCopy('android');

        expect(c.text).toContain('설정');
        expect(c.text).not.toContain('브라우저');
        expect(c.action).toBeNull();
    });

    it('웹은 예전 문구 그대로', () => {
        expect(deniedCopy('web').text).toContain('브라우저 설정');
        expect(deniedCopy(undefined).action).toBeNull();
    });
});
