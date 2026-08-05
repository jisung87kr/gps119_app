// 관제 SPA 엔트리 (FE-2.1, Vite 번들).
// bootstrap 먼저 import → window.axios + window.Echo(Reverb) 가 마운트 전에 준비됨.
import '../bootstrap';
import { createApp } from 'vue/dist/vue.esm-browser.prod.js';
import ControlApp from './ControlApp';
import { initRoleMeta } from './roleMeta';

// 신고 고정핀 점멸 keyframes 주입(번들 CSS 없이 런타임 1회).
const style = document.createElement('style');
style.textContent = `
@keyframes ctrlReqBlink {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%      { opacity: 0.45; transform: scale(0.88); }
}
.ctrl-req-pin { cursor: pointer; transform-origin: center bottom; }
`;
document.head.appendChild(style);

const el = document.getElementById('control-app');
if (el) {
    // 역할 라벨·마커색은 서버(EventRole::mapMeta())가 단일 출처다.
    // ControlApp 의 data() 가 ROLE_ORDER/ROLE_META 참조를 잡으므로 «마운트 전에» 채운다.
    try {
        initRoleMeta(JSON.parse(el.dataset.roleMeta || 'null'));
    } catch (e) {
        console.error('[control] data-role-meta 파싱 실패', e);
    }

    createApp(ControlApp).mount(el);
}
