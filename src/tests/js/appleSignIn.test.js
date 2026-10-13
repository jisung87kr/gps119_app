import { describe, it, expect, vi } from 'vitest';
import { canOfferAppleSignIn, isUserCancel, signInWithApple } from '../../resources/js/native/appleSignIn.js';

/**
 * Sign in with Apple (App Store 심사 4.8, 2026-10-13).
 *
 * 버튼은 «iOS 앱 + 플러그인이 든 빌드»에서만 열린다. 빌드 5 이하 셸에는 플러그인이 없어서
 * 버튼을 열면 누르는 순간 깨진다 — 웹이 앱보다 먼저 배포되는 게 정상이므로 이게 기본 상황이다.
 */
function env({ platform = 'ios', plugin = true, authorize } = {}) {
    return {
        Capacitor: {
            isNativePlatform: () => platform !== 'web',
            getPlatform: () => platform,
            isPluginAvailable: (name) => plugin && name === 'SignInWithApple',
            Plugins: { SignInWithApple: { authorize } },
        },
        location: { origin: 'https://gps119.co.kr' },
    };
}

function jsonResponse(body, ok = true) {
    return { ok, json: async () => body };
}

describe('canOfferAppleSignIn', () => {
    it('iOS 앱 + 플러그인 있음 → 보인다', () => {
        expect(canOfferAppleSignIn(env())).toBe(true);
    });

    it('플러그인 없는 구버전 iOS 셸 → 숨긴다', () => {
        expect(canOfferAppleSignIn(env({ plugin: false }))).toBe(false);
    });

    it('안드로이드 앱 → 숨긴다 (4.8 은 iOS 심사 요건)', () => {
        expect(canOfferAppleSignIn(env({ platform: 'android' }))).toBe(false);
    });

    it('일반 웹 → 숨긴다', () => {
        expect(canOfferAppleSignIn({})).toBe(false);
    });
});

describe('signInWithApple', () => {
    it('서버가 발급한 nonce 를 시트에 넘기고, 받은 토큰을 서버로 보낸다', async () => {
        const authorize = vi.fn(async () => ({
            response: { identityToken: 'tok', givenName: '길동', familyName: '홍' },
        }));
        const fetchImpl = vi.fn()
            .mockResolvedValueOnce(jsonResponse({ success: true, data: { nonce: 'n-1' } }))
            .mockResolvedValueOnce(jsonResponse({ success: true, data: { redirect: '/consent' } }));

        const redirect = await signInWithApple({ env: env({ authorize }), csrf: 'c', fetchImpl });

        expect(redirect).toBe('/consent');
        expect(authorize.mock.calls[0][0].nonce).toBe('n-1');
        expect(fetchImpl.mock.calls[1][0]).toBe('/auth/apple/native');
        expect(JSON.parse(fetchImpl.mock.calls[1][1].body)).toEqual({
            identity_token: 'tok', given_name: '길동', family_name: '홍',
        });
        expect(fetchImpl.mock.calls[1][1].headers['X-CSRF-TOKEN']).toBe('c');
    });

    it('사용자가 시트를 닫으면 null — 오류로 다루지 않는다', async () => {
        const authorize = vi.fn(async () => { throw new Error('The operation couldn’t be completed. (com.apple.AuthenticationServices.AuthorizationError error 1001.)'); });
        const fetchImpl = vi.fn().mockResolvedValueOnce(jsonResponse({ success: true, data: { nonce: 'n' } }));

        await expect(signInWithApple({ env: env({ authorize }), csrf: 'c', fetchImpl })).resolves.toBeNull();
        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });

    it('서버가 거절하면 그 메시지로 실패한다', async () => {
        const authorize = vi.fn(async () => ({ response: { identityToken: 'tok' } }));
        const fetchImpl = vi.fn()
            .mockResolvedValueOnce(jsonResponse({ success: true, data: { nonce: 'n' } }))
            .mockResolvedValueOnce(jsonResponse({ success: false, message: '확인 실패' }, false));

        await expect(signInWithApple({ env: env({ authorize }), csrf: 'c', fetchImpl })).rejects.toThrow('확인 실패');
    });
});

describe('isUserCancel', () => {
    it('1001 / cancel 만 취소로 본다', () => {
        expect(isUserCancel(new Error('error 1001'))).toBe(true);
        expect(isUserCancel('User canceled')).toBe(true);
        expect(isUserCancel(new Error('error 1000'))).toBe(false);
    });
});
