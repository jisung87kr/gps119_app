// 「알림 받기」 토글 (mobile-app N1).
//
// 사용자 셸에는 Alpine 이 없으므로 data 속성 기반 바닐라 컨트롤러로 붙인다.
// 마크업: <button data-push-toggle> + <span data-push-state> (같은 컨테이너 안)
//
// 상태 판단은 push.js 의 pushStatus() 하나로 모은다 — UI 가 자체 판단을 갖기
// 시작하면 «브라우저는 거부인데 화면은 켜짐» 같은 어긋남이 생긴다.

import { pushStatus, enablePush, disablePush } from './push';

const LABELS = {
    unsupported: { text: '이 브라우저는 알림을 지원하지 않습니다', action: null, tone: 'muted' },
    denied: { text: '알림이 차단되어 있습니다 — 브라우저 설정에서 허용해 주세요', action: null, tone: 'muted' },
    subscribed: { text: '알림 받는 중', action: '끄기', tone: 'on' },
    default: { text: '알림 꺼짐', action: '켜기', tone: 'off' },
};

const FAILURES = {
    'not-configured': '서버에 알림 설정이 되어 있지 않습니다.',
    denied: '알림 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.',
    dismissed: '알림 권한 요청이 취소되었습니다.',
    'server-rejected': '알림 등록에 실패했습니다. 잠시 후 다시 시도해 주세요.',
    'server-error': '알림 해제에 실패했습니다. 잠시 후 다시 시도해 주세요.',
    unsupported: '이 브라우저는 알림을 지원하지 않습니다.',
};

export function createPushToggle(root, env = globalThis) {
    const button = root.querySelector('[data-push-toggle]');
    const state = root.querySelector('[data-push-state]');

    if (!button) return null;

    async function render() {
        const status = await pushStatus(env);
        const label = LABELS[status] ?? LABELS.default;

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
        const result = status === 'subscribed' ? await disablePush(env) : await enablePush(env);

        if (!result.ok && state) {
            // 실패를 삼키지 않는다 — 조용히 원래 상태로 돌아가면 사용자는
            // 「눌렀는데 아무 일도 안 일어난다」로 겪는다.
            state.textContent = FAILURES[result.reason] ?? '알림 설정에 실패했습니다.';
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
