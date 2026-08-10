// 관제 SPA 엔트리 (FE-2.1, Vite 번들).
// bootstrap 먼저 import → window.axios + window.Echo(Reverb) 가 마운트 전에 준비됨.
import '../bootstrap';
import { createApp } from 'vue/dist/vue.esm-browser.prod.js';
import ControlApp from './ControlApp';
import { initRoleMeta } from './roleMeta';
import { initNativePushRouting } from '../push-native';

// 🔴 이 페이지는 `app.js` 를 «로드하지 않는다» — control/index.blade.php 의 @vite 는
//    control/main.js 하나만 넣는다. 그래서 app.js 가 부르던 initNativePushRouting()
//    이 관제 화면에서만 통째로 빠져 있었다.
//
//    결과(iOS 실기기 실측 2026-08-09): 관제 화면을 띄워 둔 상태에서 푸시가 오면
//    ① 인앱 배너가 안 뜨고 ② 알림을 «탭»해도 아무 일도 일어나지 않는다.
//    상황실 담당자가 하루 종일 켜 두는 화면이 정확히 여기다 — 가장 오래 열려 있는
//    화면에서만 딥링크가 죽어 있었다.
//
//    앱 웹뷰가 아니면(웹 브라우저) 아무것도 하지 않으므로 웹 동작은 그대로다.
initNativePushRouting();

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
