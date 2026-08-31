import './bootstrap';
import { isNativeApp } from './native/bridge';
import { initPwa } from './pwa';
import { initPushToggles } from './push-toggle';
import { initNativePushRouting } from './push-native';
import { createNativeLocationTracker } from './native/locationTracker';
import {
    decidePermissionStep,
    openLocationSettings,
    reportLocationPermission,
    shareStatus,
    shouldRestartTracking,
    watchPermissionChanges,
} from './native/locationPermission';

// PWA: 서비스워커 등록 + 설치 온보딩 (참가자 셸)
initPwa();

// 웹 푸시 「알림 받기」 토글 — [data-push-section] 이 있는 화면에서만 붙는다.
initPushToggles();

// 앱 푸시 알림을 «탭»했을 때의 착지(딥링크). 웹에서는 sw.js 의 notificationclick 이
// 같은 일을 한다 — 규약(payload.url)이 같아서 착지 처리를 두 벌로 짜지 않는다.
// 앱이 아니거나 플러그인이 없으면 아무것도 하지 않는다.
initNativePushRouting();

// 위치 «취득» 트래커를 전역으로 넘긴다 (N3 / 02 §3-3).
//
// 🔑 **번들과 public/js 는 서로 import 할 수 없다.** public/js/components/* 는 브라우저에
//    그대로 서빙되는 모듈이고, Vite 는 번들에서 public/ 의 JS 를 가져오는 것을 막는다
//    (vitest.config.js 주석 참조). 그래서 이 한 지점에서만 window 로 건네고, 받는 쪽은
//    «주입»으로 취급한다 — locationShare.js 는 Capacitor 를 영영 모른다.
//
// 🔑 **네이티브가 불가능하면 null 이다.** 그때 locationShare.js 는 원래 쓰던 웹 경로를
//    그대로 쓴다. 웹 구현을 여기에 또 만들지 않는 이유다.
window.__gps119Bridge = {
    ...(window.__gps119Bridge || {}),
    locationTracker: createNativeLocationTracker(),

    // 권한 3단계 UX (02 §4). 화면은 「지금 어느 단계인가」만 물어보고 그린다 —
    // 판정은 여기(순수 함수)에 있고 Vitest 가 지킨다.
    isNativeApp: isNativeApp(),
    decidePermissionStep,
    reportLocationPermission,
    openLocationSettings,
    watchPermissionChanges,
    shareStatus,
    shouldRestartTracking,
};
