import './bootstrap';
import { initPwa } from './pwa';
import { initPushToggles } from './push-toggle';

// PWA: 서비스워커 등록 + 설치 온보딩 (참가자 셸)
initPwa();

// 웹 푸시 「알림 받기」 토글 — [data-push-section] 이 있는 화면에서만 붙는다.
initPushToggles();
