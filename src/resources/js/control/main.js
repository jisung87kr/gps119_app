// 관제 SPA 엔트리 (FE-2.1, Vite 번들).
// bootstrap 먼저 import → window.axios + window.Echo(Reverb) 가 마운트 전에 준비됨.
import '../bootstrap';
import { createApp } from 'vue/dist/vue.esm-browser.prod.js';
import ControlApp from './ControlApp';

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
    createApp(ControlApp).mount(el);
}
