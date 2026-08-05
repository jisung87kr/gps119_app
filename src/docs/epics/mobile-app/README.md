# Epic: 모바일 앱 (WebView 하이브리드 · App Store / Play Store 배포)

> 기존 GPS119 웹(Laravel + Blade/Vue + Reverb)을 **WebView 하이브리드 앱**으로 감싸 두 스토어에 배포한다.
> 목표는 "웹을 앱처럼 보이게" 가 아니라, **웹이 못 하는 세 가지 — 백그라운드 위치, 푸시 알림, 스토어 유통 — 를 얻는 것**이다.

- 작성일: 2026-08-05
- 선행 에픽: [`realtime-dispatch-control`](../realtime-dispatch-control/README.md) (M0~M4 코드분 완료, PWA 셸까지)
- 상태: **기획 — 착수 전**

---

## ✅ 기획 중 발견한 결함 2건 — 2026-08-05 수정 완료

> 아래는 **이 에픽을 기획하며 발견해 이미 고친 것**이다. 현재 코드에는 없는 문제다.
> 남아 있는 리스크는 [다음 절](#-남아-있는-관문-인증이-webview-에서-통째로-끊긴다)과 [`07-roadmap.md`](07-roadmap.md) 의 N0 를 볼 것.

**① 위치 스로틀이 «분당 2회»였다** — 요구사항 「정확도 중요」가 앱 이전에 웹에서 이미 실패하고 있었다.

`throttle:2,1` 은 Laravel 문법(`최대횟수,분`)상 분당 2회인데, 주석 두 곳이 "초당 2회"라고 적고 있었고 클라이언트는 이동 중 5초마다(분당 12회) 보냈다. **클라이언트와 서버가 120배 어긋난 채, 클라이언트는 스스로 「정합」이라 적어놨다.**

실측(연속 호출): `0:422 | 1:422 | 2:429 | 3:429 | 4:429`, 응답 헤더 `X-RateLimit-Limit: 2`.
→ **이동 중 핑 12건 중 10건(83%)이 버려졌다.** 정지 상태(분당 2회)에서만 우연히 맞아, 정확도가 가장 필요한 「이동 중」에 정확히 실패하는 구조였다.

**② accuracy 가 관제 화면까지 도달하지 않았다** — 브로드캐스트에는 실려 있었지만 참가자 캐시에 없어, 관제가 처음 켜질 때 받는 roster 에서 빠졌다. 오차 5m 인 사람과 500m 인 사람이 같은 점으로 찍혔다.

| 조치 | 산출물 |
|---|---|
| `throttle:2,1` → `throttle:30,1`, 주석 정정 | `routes/api.php` |
| 클라이언트 429 백오프(20초 창) + 버퍼 2 → 12 | `locationShare.js` |
| `event_participants.last_accuracy` + roster + 마커 인포윈도 오차 반경 | 마이그레이션·`LocationService`·`markerPool.js` |
| 회귀 테스트 | PHP 2건(`LocationPingApiTest`) + **Vitest 12건**(`tests/js/locationShare.test.js`) |

🔑 **교훈은 남는다: 백그라운드 위치를 붙이면 전송량이 늘어난다.** 스로틀·백오프 값은 앱 작업 중 다시 실측해야 한다(현재 `30/분`은 웹 기준 추정치).

---

## 🔴 남아 있는 관문: 인증이 WebView 에서 통째로 끊긴다

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

## 📝 문서 검토 반영 (2026-08-05) — 4건

기획 문서를 코드와 대조해 **틀렸거나 서로 어긋난 것** 4건을 고쳤다. 전략적 결론(웹뷰 방식·앱 분리·레포 정책)은 그대로다.

| # | 무엇이 문제였나 | 어디에 반영 |
|---|---|---|
| ① | **딥링크 규약이 정작 상황실에게 동작하지 않는다** — `?project=` 를 `/admin/control` 만 읽는다. 행사 controller 는 시스템 롤이 `user` 라 `/control` 로 가고, 행사를 2개 이상 맡으면 엉뚱한 행사가 열린다 | [03 §5-1](03-push-notifications.md) · [07](07-roadmap.md) N1 + 게이트 |
| ② | **`/control` 을 앱에 넣는지 문서 3개가 다르게 말했다** — 「전용 앱 ✗ / 앱에서 열기 ✓ / 상시 관제는 PC」 셋으로 갈라 확정. 딸려 나온 **CSV 다운로드 결함**(M-21) 신규 | [04 §3-3](04-app-partitioning.md) · 아래 «범위 밖» |
| ③ | **색상 미러 드리프트를 «전과»라 썼는데 현재진행형이었다** — 7개 중 4개가 아직 어긋나 있다. 앱·심사자료로 퍼지기 전에 고친다 | [06 §2-1](06-repo-policy.md) 실측표 · [07](07-roadmap.md) 0-8 |
| ④ | **보존기간을 «파기»로만 봤다** — 위치정보법은 제공사실 확인자료를 «남기라»고 한다. 파기 잡만 짜면 법이 남기라는 것까지 지운다 | [02 §6-1](02-location-accuracy.md) · [05 §1-2·§2](05-store-release.md) |

부수 정정: 웹 푸시(VAPID)를 N1 **필수**로 승격(선택이면 N1 을 앱 없이 검증할 수 없다), 위치정보사업 신고를 *"대상일 수 있다"* → **대상 전제**로.

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
- **관제 전용 앱**의 별도 출시 — 내지 않는다([04](04-app-partitioning.md) §3-3)
  - ⚠️ 「관제 화면을 앱에서 열지 않는다」는 뜻이 **아니다.** `/control` 은 단일 앱 안에서 열리되 «웹 화면 그대로»이고, **상시 관제의 주 무대는 PC 웹**이다. 셋의 구분은 [04 §3-3](04-app-partitioning.md) 표 참조
