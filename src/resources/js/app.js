import './bootstrap';
import { initErrorReporting } from './errorReport';
import {
    BATTERY_WARNING,
    checkBatteryOptimization,
    openBatteryOptimizationSettings,
} from './native/batteryOptimization';
import { isNativeApp, nativeInfo, nativePlatform } from './native/bridge';
import { initPwa } from './pwa';
import { initPushToggles } from './push-toggle';
import { syncPushRegistration } from './push';
import { initNativePushRouting } from './push-native';
import { createNativeLocationTracker } from './native/locationTracker';
import { createNativeCurrentPosition } from './native/currentPosition';
import {
    decidePermissionStep,
    openLocationSettings,
    reportLocationPermission,
    shareStatus,
    shouldRestartTracking,
    watchPermissionChanges,
} from './native/locationPermission';

// 웹뷰 JS 에러 수집 (M-16) — **가장 먼저 붙인다.**
//
// 🔴 앱은 원격 URL 을 띄우므로 콘솔을 볼 방법이 없다. 이게 없으면 실기기에서 난
//    에러는 «증상»으로만 남고, 낮에 그랬듯 화면에 진단 문자열을 그려서 쫓게 된다.
// ⚠️ 이 줄보다 먼저 난 에러는 못 잡는다(레이아웃 인라인 모듈 등). 전부는 아니다.
initErrorReporting({
    context: () => ({
        platform: nativePlatform(),
        appVersion: nativeInfo().version,
    }),
});

// PWA: 서비스워커 등록 + 설치 온보딩 (참가자 셸)
initPwa();

// 웹 푸시 「알림 받기」 토글 — [data-push-section] 이 있는 화면에서만 붙는다.
initPushToggles();

// 앱 푸시 알림을 «탭»했을 때의 착지(딥링크). 웹에서는 sw.js 의 notificationclick 이
// 같은 일을 한다 — 규약(payload.url)이 같아서 착지 처리를 두 벌로 짜지 않는다.
// 앱이 아니거나 플러그인이 없으면 아무것도 하지 않는다.
initNativePushRouting();

// 🔴 푸시 등록을 «로그인한 사람»에게 맞춘다 (2026-09-07 현장). 같은 기기·브라우저를 다른
//    계정이 쓰면 토큰·구독이 이전 사람 것으로 남아 새 사람은 알림을 못 받는다. 페이지마다
//    주인을 비교해 다를 때만 다시 등록한다 — 대부분의 로드에서는 아무 요청도 안 나간다.
//    /control 은 서비스워커를 등록하지 않아(ready 가 영영 안 풀린다) 여기서만 부른다;
//    그쪽은 initNativePushRouting 안의 네이티브 동기화만 탄다.
syncPushRegistration().catch(() => {});

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

    // 신고 화면의 1회 위치 취득 (F-12). iOS 앱에서만 함수이고 그 외엔 null —
    // mapHelpers.getCurrentPositionOnce 가 null 이면 원래의 navigator.geolocation 을 쓴다.
    getCurrentPosition: createNativeCurrentPosition(),

    // 권한 3단계 UX (02 §4). 화면은 「지금 어느 단계인가」만 물어보고 그린다 —
    // 판정은 여기(순수 함수)에 있고 Vitest 가 지킨다.
    isNativeApp: isNativeApp(),
    decidePermissionStep,
    reportLocationPermission,
    openLocationSettings,
    watchPermissionChanges,
    shareStatus,
    shouldRestartTracking,

    // 배터리 최적화 (M-26). 안드로이드는 이게 없으면 화면을 끄는 순간
    // 위치 전송이 «완전히» 멈추는데, 앱은 멀쩡해 보인다.
    checkBatteryOptimization,
    openBatteryOptimizationSettings,
    BATTERY_WARNING,
};
