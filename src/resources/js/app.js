import './bootstrap';
import { initPwa } from './pwa';
import { initPushToggles } from './push-toggle';
import { initNativePushRouting } from './push-native';

// PWA: 서비스워커 등록 + 설치 온보딩 (참가자 셸)
initPwa();

// 웹 푸시 「알림 받기」 토글 — [data-push-section] 이 있는 화면에서만 붙는다.
initPushToggles();

// 앱 푸시 알림을 «탭»했을 때의 착지(딥링크). 웹에서는 sw.js 의 notificationclick 이
// 같은 일을 한다 — 규약(payload.url)이 같아서 착지 처리를 두 벌로 짜지 않는다.
// 앱이 아니거나 플러그인이 없으면 아무것도 하지 않는다.
initNativePushRouting();
