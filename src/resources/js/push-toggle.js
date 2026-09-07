// 「알림 받기」 토글 (mobile-app N1).
//
// 사용자 셸에는 Alpine 이 없으므로 data 속성 기반 바닐라 컨트롤러로 붙인다.
// 마크업: <button data-push-toggle> + <span data-push-state> (같은 컨테이너 안)
//
// 상태 판단은 push.js 의 pushStatus() 하나로 모은다 — UI 가 자체 판단을 갖기
// 시작하면 «브라우저는 거부인데 화면은 켜짐» 같은 어긋남이 생긴다.

import { pushStatus, enablePush, disablePush } from './push';
import { nativePlatform } from './native/bridge';

/**
 * 「알림 거부」 안내 — 어디서 풀어야 하는지는 플랫폼마다 다르다 (2026-09-07 현장).
 *
 * 앱에서 알림 권한을 끈 뒤 프로필에 가면 「브라우저 설정에서 허용해 주세요」가 떴다. 앱에는
 * 브라우저 설정이 없다. iOS 는 `app-settings:` 로 설정 앱의 이 앱 페이지를 열 수 있어 버튼을 준다.
 * Android 는 알림 설정으로 바로 가는 공개 URL 이 없어 경로만 적는다.
 *
 * @param {'ios'|'android'|'web'} platform
 * @returns {{text: string, action: string|null, failure: string}}
 */
export function deniedCopy(platform) {
    if (platform === 'ios') {
        return {
            text: '알림이 꺼져 있습니다 — iPhone 설정 → 알림 → GPS119 에서 허용해 주세요',
            action: '설정 열기',
            failure: '알림 권한이 꺼져 있습니다. iPhone 설정 → 알림 → GPS119 에서 허용해 주세요.',
        };
    }
    if (platform === 'android') {
        return {
            text: '알림이 꺼져 있습니다 — 설정 → 애플리케이션 → GPS119 → 알림에서 허용해 주세요',
            action: null,
            failure: '알림 권한이 꺼져 있습니다. 설정 → 애플리케이션 → GPS119 → 알림에서 허용해 주세요.',
        };
    }

    return {
        text: '알림이 차단되어 있습니다 — 브라우저 설정에서 허용해 주세요',
        action: null,
        failure: '알림 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.',
    };
}

/** iOS 설정 앱의 이 앱 페이지. Capacitor 가 외부 스킴으로 넘겨 설정 앱이 열린다. */
const IOS_APP_SETTINGS_URL = 'app-settings:';

const LABELS = {
    unsupported: { text: '이 브라우저는 알림을 지원하지 않습니다', action: null, tone: 'muted' },
    // denied 는 플랫폼별 문구 — deniedCopy() 가 준다. 여기 값은 자리표시자다.
    denied: { text: '', action: null, tone: 'muted' },
    subscribed: { text: '알림 받는 중', action: '끄기', tone: 'on' },
    default: { text: '알림 꺼짐', action: '켜기', tone: 'off' },
};

const FAILURES = {
    'not-configured': '서버에 알림 설정이 되어 있지 않습니다.',
    // denied 는 플랫폼별 — 아래 toggle() 에서 deniedCopy() 로 바꿔 쓴다.
    denied: '알림 권한이 거부되었습니다.',
    dismissed: '알림 권한 요청이 취소되었습니다.',
    'server-rejected': '알림 등록에 실패했습니다. 잠시 후 다시 시도해 주세요.',
    'server-error': '알림 해제에 실패했습니다. 잠시 후 다시 시도해 주세요.',
    unsupported: '이 브라우저는 알림을 지원하지 않습니다.',
    // 앱 전용: 권한은 받았는데 FCM/APNs 토큰 발급이 실패했다(네트워크·설정).
    // 「권한 거부」와 섞으면 사용자가 OS 설정만 뒤지게 된다.
    'registration-failed': '알림 등록에 실패했습니다. 네트워크를 확인하고 다시 시도해 주세요.',
};

export function createPushToggle(root, env = globalThis) {
    const button = root.querySelector('[data-push-toggle]');
    const state = root.querySelector('[data-push-state]');

    if (!button) return null;

    function labelFor(status) {
        if (status !== 'denied') return LABELS[status] ?? LABELS.default;

        const copy = deniedCopy(nativePlatform(env));

        return { text: copy.text, action: copy.action, tone: 'muted' };
    }

    async function render() {
        const status = await pushStatus(env);
        const label = labelFor(status);

        if (state) {
            state.textContent = label.text;
            state.dataset.tone = label.tone;
        }

        // 되돌릴 수 없는 상태(미지원·거부)에서는 버튼을 숨긴다.
        // 눌러도 아무 일이 없는 버튼은 «고장난 것»으로 읽힌다.
        button.hidden = label.action === null;
        button.textContent = label.action ?? '';
        button.disabled = false;

        return status;
    }

    async function toggle() {
        button.disabled = true;
        button.textContent = '처리 중…';

        const status = await pushStatus(env);

        // 거부 상태의 버튼은 「설정 열기」다(iOS 만). 켜기를 다시 시도해도 OS 가 안 묻는다.
        if (status === 'denied') {
            if (nativePlatform(env) === 'ios') env.location.assign(IOS_APP_SETTINGS_URL);
            await render();

            return { ok: false, reason: 'denied' };
        }

        const result = status === 'subscribed' ? await disablePush(env) : await enablePush(env);

        if (!result.ok && state) {
            // 실패를 삼키지 않는다 — 조용히 원래 상태로 돌아가면 사용자는
            // 「눌렀는데 아무 일도 안 일어난다」로 겪는다.
            state.textContent = result.reason === 'denied'
                ? deniedCopy(nativePlatform(env)).failure
                : (FAILURES[result.reason] ?? '알림 설정에 실패했습니다.');
            state.dataset.tone = 'error';
            button.disabled = false;
            button.textContent = status === 'subscribed' ? '끄기' : '켜기';

            return result;
        }

        await render();

        return result;
    }

    button.addEventListener('click', toggle);
    render();

    return { render, toggle };
}

export function initPushToggles(env = globalThis) {
    env.document.querySelectorAll('[data-push-section]').forEach((root) => {
        createPushToggle(root, env);
    });
}

export default initPushToggles;
