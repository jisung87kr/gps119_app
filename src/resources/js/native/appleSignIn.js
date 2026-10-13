// Sign in with Apple — iOS 앱 셸 전용 (App Store 심사 4.8, 2026-10-13).
//
// 🔑 버튼은 «그 앱이 플러그인을 아는가»로만 보인다(bridge.js 의 기능 협상과 같은 원칙).
//    빌드 5 이하 셸에는 플러그인이 없으므로 버튼이 안 보이고, 웹·안드로이드도 마찬가지다.
//    네이버 로그인이 있는 «iOS 앱»에 Apple 로그인을 함께 두는 게 심사 요건이다.
//
// 흐름: POST /auth/apple/nonce → SignInWithApple.authorize({nonce}) → POST /auth/apple/native
//       → 서버가 identityToken(서명·aud·nonce)을 검증하고 세션을 만든다.

import { nativePlatform } from './bridge';

export const APPLE_PLUGIN = 'SignInWithApple';

/** 이 환경에서 Apple 로그인 버튼을 보여야 하는가. */
export function canOfferAppleSignIn(env = globalThis) {
    return nativePlatform(env) === 'ios' && Boolean(env.Capacitor?.isPluginAvailable?.(APPLE_PLUGIN));
}

/** 사용자가 시트를 닫았을 때 — 오류로 알리지 않는다. */
export function isUserCancel(error) {
    const message = String(error?.message ?? error ?? '');

    // ASAuthorizationError.canceled(1001)
    return /1001|cancel/i.test(message);
}

async function postJson(url, body, csrf, fetchImpl) {
    const response = await fetchImpl(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(body ?? {}),
    });
    const json = await response.json().catch(() => ({}));

    if (!response.ok || json.success === false) {
        throw new Error(json.message || 'Apple 로그인에 실패했습니다.');
    }

    return json.data ?? {};
}

/**
 * Apple 로그인 한 번. 성공하면 이동할 URL 을, 사용자가 취소하면 null 을 돌려준다.
 */
export async function signInWithApple({ env = globalThis, csrf, fetchImpl = env.fetch?.bind(env) } = {}) {
    const plugin = env.Capacitor.Plugins[APPLE_PLUGIN];
    const { nonce } = await postJson('/auth/apple/nonce', {}, csrf, fetchImpl);

    let result;
    try {
        // 네이티브 시트는 clientId·redirectURI 를 쓰지 않지만 플러그인 타입상 필수라 채운다.
        result = await plugin.authorize({
            clientId: 'kr.co.gps119.app',
            redirectURI: `${env.location?.origin ?? ''}/`,
            scopes: 'name email',
            nonce,
        });
    } catch (error) {
        if (isUserCancel(error)) return null;
        throw error;
    }

    const r = result?.response ?? {};
    const { redirect } = await postJson('/auth/apple/native', {
        identity_token: r.identityToken,
        given_name: r.givenName ?? null,
        family_name: r.familyName ?? null,
    }, csrf, fetchImpl);

    return redirect;
}

/** [data-apple-signin] 버튼을 붙인다. 조건이 안 맞으면 숨긴 채로 둔다. */
export function initAppleSignIn(env = globalThis) {
    const doc = env.document;
    if (!doc || !canOfferAppleSignIn(env)) return;

    const csrf = doc.querySelector('meta[name="csrf-token"]')?.content ?? '';

    doc.querySelectorAll('[data-apple-signin]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            if (button.disabled) return;
            button.disabled = true;
            try {
                const redirect = await signInWithApple({ env, csrf });
                if (redirect) env.location.href = redirect;
            } catch (error) {
                env.alert?.(error?.message || 'Apple 로그인에 실패했습니다.');
            } finally {
                button.disabled = false;
            }
        });
    });
}
