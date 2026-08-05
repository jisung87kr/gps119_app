# 03. 푸시 알림

> 요구: **「푸시알림 기능 필요」**
> 이 문서의 결론: **푸시는 «앱 기능»이 아니라 «서버 기능»이고, 서버 쪽이 거의 비어 있다.**

## 1. 🔴 현재 상태 — 알림 발송 경로가 사실상 없다

`app/Listeners/NotifyRescuers.php` 가 유일한 알림 진입점인데:

```php
private function sendNotificationToRescuer(User $rescuer, $request): void
{
    Log::info('Notifying rescuer about new request', [...]);

    // TODO: Implement actual notification logic (email, SMS, push notification, etc.)
    // For now, we just log the notification
}
```

**로그만 남긴다.** 실제로 밖으로 나가는 유일한 경로는 같은 리스너의 **디스코드 웹훅** 하나뿐이다.

> 2026-08-05 에 «수신자 산정»은 고쳤다(행사 상황실·구급대를 포함하도록, `recipientsFor()`). 하지만 **발송기는 여전히 스텁이다.** 즉 푸시 작업은 「앱에 SDK 붙이기」가 아니라 **「서버에 발송 계층을 처음 만들기」** 다.

## 2. Reverb 와 푸시의 역할 분담 — 헷갈리기 쉬운 지점

이미 Reverb 실시간이 있으니 푸시가 필요 없다고 오해하기 쉽다. **둘은 대체재가 아니다.**

| | Reverb (WebSocket) | FCM/APNs (푸시) |
|---|---|---|
| 동작 조건 | **앱이 떠 있고 화면이 살아 있을 때** | 앱이 죽어 있어도 |
| 지연 | 매우 낮음 | 낮음(수 초) |
| 배터리 | 연결 유지 비용 | OS 가 관리 |
| 용도 | **화면 갱신** — 지도 마커 이동, 목록 실시간 반영 | **환기** — "새 신고가 떴다" |

🔑 **모바일 앱의 가치 제안은 «화면 보고 있기»가 아니라 «주머니 속에서 놓치지 않기»다.** 그건 Reverb 가 못 한다.

⚠️ 그리고 [`02`](02-location-accuracy.md) 와 같은 위험이 여기도 있다 — **푸시 없는 모바일 관제는 안전 자산이 아니라 안전 부채**다. 상황실이 "폰으로 보고 있다"고 믿는데 백그라운드에서 WS 가 끊겨 실제로는 못 받는 상태가 된다.

## 3. 설계

### 3-1. FCM 단일 창구

Android 는 FCM 직행, iOS 는 **FCM → APNs 경유**로 통일한다. 서버가 두 프로토콜을 각각 다룰 이유가 없다.

```
RequestCreated / DispatchAssigned / DispatchStatusUpdated
        │
        ▼
  NotifyRescuers (queue)          ← 이미 ShouldQueue
        │  recipientsFor()        ← 이미 있음 (2026-08-05 수정)
        ▼
  PushService::send(User, payload)   ← 🆕 신설
        │
        ▼
   device_tokens 조회 → FCM HTTP v1
```

### 3-2. 신설이 필요한 것

| 항목 | 내용 |
|---|---|
| **`device_tokens` 테이블** | `user_id`, `token`, **`token_hash`(unique — 조회·중복판정은 전부 이 컬럼으로)**, `platform(ios/android)`, `app_version`, `last_seen_at`, `revoked_at`<br>⚠️ **원문 `token` 에는 인덱스를 걸지 않는다** — 이유는 아래 |
| **토큰 등록/해제 API** | `POST /api/devices` (본문에 토큰) · `DELETE /api/devices/current` (본문 또는 인증 주체로 해석). 앱 실행 시·토큰 갱신 시 |
| **`PushService`** | 발송·실패 처리·**무효 토큰 정리**(FCM 이 `UNREGISTERED` 반환 시 `revoked_at`) |
| **알림 종류 정의** | 아래 §4 |
| **사용자별 수신 설정** | 역할에 따라 받고 싶은 알림이 다르다 |

🔴 **FCM 토큰을 URL path 에 넣지 않는다.** `DELETE /api/devices/{token}` 형태는 액세스 로그·리버스 프록시 로그·에러 리포트에 토큰이 그대로 남는다. 토큰은 그 자체로 **해당 기기에 푸시를 보낼 수 있는 자격증명**이다. 본문(body)으로 받거나, 저장 시 해시를 두고 그 id 로 지운다.

⚠️ 같은 이유로 **조회 인덱스는 `token_hash` 에만 건다**(원문 `token` 은 발송에 필요하므로 보관하되, 검색·중복판정·로그에는 해시를 쓴다). 위 표에 `token_hash` 를 명시해 둔 것은 구현자가 원문 컬럼에 unique 를 거는 실수를 막기 위해서다.

🔴 **`sendNotificationToRescuer` 의 TODO 를 `PushService` 호출로 바꾸는 것이 이 에픽의 실질적 산출물**이다. 앱 SDK 연동은 그 다음이다.

### 3-3. 웹 푸시도 같이 볼 것

`PushService` 를 만들 때 **웹 푸시(VAPID)** 도 같은 인터페이스로 태울 수 있다. 관제는 PC 웹에서 쓰므로([04](04-app-partitioning.md) §3) **상황실에게는 웹 푸시가 앱 푸시보다 실질적으로 더 중요할 수 있다.**

## 4. 알림 종류 (초안)

| 이벤트 | 수신자 | 긴급도 | 비고 |
|---|---|---|---|
| **신규 신고 접수** | 행사 상황실(controller) + 시스템 admin/rescuer | 🔴 최고 | 이게 없으면 서비스의 핵심 약속이 빈다 |
| **지령 배정됨** | 배정된 구급대원 본인 | 🔴 최고 | 현재 `DispatchAssigned` 가 개인 채널로만 감 |
| 지령 상태 변경 | 상황실 | 중 | 묶어서(digest) 보낼 여지 |
| 내 신고 상태 변경 | 신고자 본인 | 중 | `RequestStatusUpdated` 대응 |
| 행사 시작/종료 | 참가자 전체 | 낮 | 있으면 좋음 |

🔴 **연락처 노출 규칙(ADR-0004)이 푸시 페이로드에도 적용되어야 한다.** 잠금화면 알림에 신고자 전화번호가 뜨면 «잠긴 기기 밖으로 개인정보가 나가는» 새 경로가 생긴다. **푸시 본문에는 식별자와 요약만 싣고, 상세는 앱을 열어서 API 로 가져온다.**

## 5. 딥링크 — 알림의 착지점

푸시를 탭했을 때 «앱 홈»으로 떨어지면 알림의 값어치가 절반이 된다.

2026-08-05 에 관제 SPA 에 **`?request=123` 딥링크**를 이미 넣었다 — 해당 신고의 배정 화면이 바로 열린다. 앱은 이 규약을 그대로 쓴다.

| 알림 | 목적지 |
|---|---|
| 신규 신고 | `/control?project={id}&request={rid}` (상황실) |
| 지령 배정 | `/events/{id}/dispatch` (구급대원) |
| 내 신고 상태 | `/requests/{id}` (신고자) |

**웹뷰 + 원격 URL 방식([01](01-webview-strategy.md))이라 딥링크가 그냥 URL 이면 된다** — 네이티브 라우팅 테이블을 따로 만들 필요가 없다. A안의 부수 이점.

### 🔴 5-1. 그런데 이 규약이 **정작 상황실에게는 동작하지 않는다** (2026-08-05 검토 발견)

위 표의 첫 줄 — 수신자 1순위인 **행사 상황실** — 이 실제로는 깨진다. `?project=` 를 읽는 라우트가 **관리자용 하나뿐**이다.

| 라우트 | `?project=` 처리 | 실제 사용자 |
|---|---|---|
| `routes/web.php:207` (`/admin/control`) | ✅ `request('project')` → `selectedId` | 시스템 admin |
| `routes/web.php:99~122` (`/control`) | 🔴 **읽지 않는다.** `view()` 에 `selectedId` 를 넘기지 않음 | **행사 controller** |

`control/index.blade.php:23` 이 `data-selected="{{ $selectedId ?? '' }}"` 라 **조용히 빈 값**이 되고, `ControlApp.js` 는 `projects[0]` 을 자동 선택한다. 그리고 `_consumeDeepLink()` 는 `selectProject()` **안**에서 실행되므로:

- 행사를 **하나만** 맡은 상황실 → 우연히 맞는다
- 행사를 **둘 이상** 맡은 상황실 → 🔴 **엉뚱한 행사가 열리고, `?request=` 는 그 행사 목록에 없어 아무 일도 일어나지 않는다.** 실패가 화면에 드러나지도 않는다

🔑 **`EventRole::CONTROLLER` 는 시스템 롤이 보통 `user` 다**(`recipientsFor()` 가 바로 이 사실 때문에 만들어졌다). 즉 **푸시를 가장 먼저 받아야 할 사람이 정확히 딥링크가 없는 쪽**에 있다.

**조치 — N1 산출물에 포함한다.** `/control` 이 `/admin/control` 과 동일하게 `?project=` 를 해석하도록 맞춘다(권한 검사는 기존 로직 유지, 목록에 없는 id 면 무시). 앱 작업 전에, **웹 푸시 단계에서 이미 필요하다.**

## 6. 미결

| ID | 질문 |
|---|---|
| M-8 | FCM 프로젝트·APNs 인증서 **명의 주체** — 세이브미 vs 인디고404. 스토어 계정 명의와 함께 결정해야 한다([05](05-store-release.md)) |
| M-9 | 알림 수신 설정의 세분화 수준 (역할별 기본값 / 사용자 토글 범위) |
| M-10 | 웹 푸시(VAPID)를 같은 스프린트에 포함할 것인가 — 상황실이 PC 웹을 쓴다면 우선순위가 높다 |
| M-11 | 조용한 시간(야간) 정책 — 구조 도메인이라 «끄면 안 되는 알림»이 있다 |
