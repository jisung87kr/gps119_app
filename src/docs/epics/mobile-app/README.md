# Epic: 모바일 앱 (WebView 하이브리드 · App Store / Play Store 배포)

> 기존 GPS119 웹(Laravel + Blade/Vue + Reverb)을 **WebView 하이브리드 앱**으로 감싸 두 스토어에 배포한다.
> 목표는 "웹을 앱처럼 보이게" 가 아니라, **웹이 못 하는 세 가지 — 백그라운드 위치, 푸시 알림, 스토어 유통 — 를 얻는 것**이다.

- 작성일: 2026-08-05
- 선행 에픽: [`realtime-dispatch-control`](../realtime-dispatch-control/README.md) (M0~M4 코드분 완료, PWA 셸까지)
- 상태: **기획 — 착수 전**

---

## ⛔ 착수 전에 반드시 읽을 것: 지금 코드가 이미 깨져 있다

**요구사항 「위치정보 수집, 정확도 중요」는 앱 이전에 웹에서 이미 실패하고 있다.**

`routes/api.php:36` 의 위치 수신 엔드포인트는 이렇게 걸려 있다.

```php
Route::post('/events/{id}/location', [LocationApiController::class, 'store'])
    ->middleware(['event.member', 'throttle:2,1']);
```

`throttle:2,1` 은 Laravel 문법상 **`분당` 2회**다. 그런데,

| 곳 | 무엇이라 믿고 있나 | 실제 |
|---|---|---|
| `routes/api.php:34` 주석 | *"rate-limit(**초당** 1~2)"* | 분당 2회 |
| `public/js/components/locationShare.js:9` 주석 | *"백엔드 throttle(**2/s**)·sharing-off 스킵과 **정합**"* | 분당 2회 |
| `locationShare.js:14` 실제 전송 | 이동 중 **5초마다** = 분당 12회 | 분당 2회만 통과 |

**클라이언트와 서버가 120배 어긋나 있고, 클라이언트는 스스로 「정합」이라 적어놨다.**

### 실측 (2026-08-05, 로그인 세션에서 연속 호출)

```
0:422(limit 2) | 1:422(limit 2) | 2:429 | 3:429 | 4:429
                                   └── X-RateLimit-Limit: 2
```

(422 는 테스트 페이로드의 검증 실패 — 스로틀은 통과했다는 뜻. 3번째부터 **429**.)

**즉 참가자가 «움직이는 동안» 위치 핑 12건 중 10건이 429 로 버려진다.** 실패 버퍼는 `MAX_BUFFER = 2` 라 복구되지도 않는다. 정지 상태(30초 하트비트, 분당 2회)에서만 우연히 맞아떨어진다 — **가장 정확도가 필요한 «이동 중»에 정확히 실패하는 구조.**

> 🔴 **이 에픽의 0번 작업은 앱 껍데기가 아니라 이 스로틀이다.** 백그라운드 위치를 붙이면 전송량이 늘어나므로 지금 고치지 않으면 앱이 문제를 **키운다**.
> → [`02-location-accuracy.md`](02-location-accuracy.md)

---

## 두 번째 관문: 인증이 WebView 에서 통째로 끊긴다

현재 API 인증은 **Sanctum SPA 모드(세션 쿠키 + `stateful` 도메인 화이트리스트)** 하나뿐이다. 저장소 전체에 **`createToken` 호출이 0건** — 즉 **베어러 토큰 발급 경로 자체가 없다.**

```
config/sanctum.php  'stateful' => localhost, 127.0.0.1, app.gps119.co.kr:9050 ...
routes/api.php      Route::middleware('auth:sanctum')->group(...)   ← 전부
```

Capacitor 웹뷰는 로컬 자산을 `capacitor://localhost`(iOS) · `https://localhost`(Android) 오리진에서 띄운다. **다른 오리진이므로 세션 쿠키가 실리지 않는다.** 영향 범위:

- `/api/*` 전체 (신고·입장·위치·지령)
- `/broadcasting/auth` — **Reverb private/presence 채널 인가 실패 = 실시간이 통째로 죽는다**
- 관제 CSV 다운로드 (`ControlApp.js` 가 세션 쿠키 GET 에 의존)

**선택지는 둘뿐이고, 이게 웹뷰 방식 선택과 직결된다** → [`01-webview-strategy.md`](01-webview-strategy.md)

---

## 문서 색인

| 문서 | 내용 | 사용자 질문 대응 |
|------|------|------------------|
| [01-webview-strategy.md](01-webview-strategy.md) | 웹뷰 방식 3안 비교, 인증 오리진 문제, 권고안 | 웹뷰로 할 예정 |
| [02-location-accuracy.md](02-location-accuracy.md) | 스로틀 결함, 백그라운드 위치, 권한 3단계, 정확도 설계 | 위치정보 수집·정확도 |
| [03-push-notifications.md](03-push-notifications.md) | FCM/APNs, Reverb 와의 역할 분담, 서버 측 미비점 | 푸시알림 |
| [04-app-partitioning.md](04-app-partitioning.md) | 앱을 목적별로 나눌 것인가 — 판단과 근거 | 사용자 목적별 앱 분리 |
| [05-store-release.md](05-store-release.md) | 두 스토어 심사 리스크, 필수 제출물, 개인정보 고지 | appstore/playstore 배포 |
| [06-repo-policy.md](06-repo-policy.md) | 레포 분리 정책, 버전·릴리스 관리 | 레포 관리 정책 |
| [07-roadmap.md](07-roadmap.md) | 마일스톤 N0~N4, 선행조건, 미결 결정 | — |

---

## 결정 요약 (상세는 각 문서)

| # | 질문 | 잠정 결론 | 확신도 |
|---|------|-----------|--------|
| 1 | 웹뷰 방식 | **Capacitor + 원격 URL 로딩(서버 호스팅)** — 오리진이 웹과 같아져 인증·Reverb·쿠키가 그대로 산다 | 높음 |
| 2 | 위치 정확도 | 스로틀 선수정 → 포그라운드 서비스(Android) / `UIBackgroundModes: location`(iOS) + **네이티브 플러그인** | 높음 |
| 3 | 푸시 | **FCM 단일 창구**(iOS 는 FCM→APNs 경유). Reverb 는 «앱이 떠 있을 때»만 담당 | 높음 |
| 4 | 앱 분리 | 🔴 **나누지 않는다. 단일 앱 + 역할 기반 분기** | 중간 — [04](04-app-partitioning.md) 의 재검토 조건 참조 |
| 5 | 레포 | 🔴 **웹 레포는 그대로 두고, 앱 셸만 별도 레포 1개** (`gps119_app_mobile`) | 중간 |
| 6 | 배포 | 두 스토어 동시. **심사 리스크 1순위는 App Store 4.2(최소 기능)와 위치 상시수집 정당화** | — |

---

## 범위 밖 (이번 에픽 비대상)

- 오프라인 우선(offline-first) 데이터 동기화 — 현재 PWA `sw.js` 수준 유지
- 앱 내 결제 · 다국어
- 워치(WearOS/watchOS) · 태블릿 전용 레이아웃
- 관제(`/control`)의 앱 탑재 — **웹으로 유지**한다([04](04-app-partitioning.md) §3)
