# 04. 실시간 아키텍처 (Reverb + 위치 추적)

## 스택

- **서버**: Laravel Reverb (1st-party WebSocket). `php artisan reverb:start` 데몬. 도커 컴포즈에 서비스 추가(포트 노출).
- **클라이언트**: Laravel Echo + `pusher-js` 프로토콜(Reverb 호환). 기존 Vite(`resources/js/bootstrap.js`)에 Echo 초기화 추가.
- **인증**: private/presence 채널은 Sanctum 세션 또는 토큰으로 `/broadcasting/auth` 인가.

## 설치/구성 체크리스트

- [ ] `composer require laravel/reverb` → `php artisan reverb:install` (`config/broadcasting.php`, `config/reverb.php`, `.env` 생성)
- [ ] `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_*`, `REVERB_HOST/PORT/SCHEME`
- [ ] `npm i laravel-echo pusher-js` + `resources/js/echo.js` 초기화, `bootstrap.js`에서 import
- [ ] `routes/channels.php` 생성 + `bootstrap/app.php`에 `->withBroadcasting(channels: __DIR__.'/../routes/channels.php')`
- [ ] `docker-compose.yml`: `reverb` 서비스(또는 app 컨테이너에서 `reverb:start`) + 포트(예: 9055) 노출, Apache/Nginx WS 프록시
- [ ] 큐 워커 가동(`queue:work`) — ping 적재·브로드캐스트 비동기화 (현재 `QUEUE_CONNECTION=database`)

## 채널 설계

| 채널 | 타입 | 구독자 | 흐르는 이벤트 |
|------|------|--------|----------------|
| `event.{projectId}.control` | private | controller/admin(상황실)만 | `request.created`, `dispatch.*`, `participant.location` |
| `event.{projectId}.locations` | presence | active 참가자 전원 | `participant.location` (위치 ping 팬아웃) |
| `event.{projectId}.dispatch.{userId}` | private | 해당 구급대원 본인 | `dispatch.assigned`, `dispatch.updated` |
| `event.{projectId}.requester.{userId}` | private | 신고자 본인 | `request.status.updated`(대기→진행→완료) |

`routes/channels.php`에서 각 채널 인가: 구독자가 해당 행사에 `active`로 속하고 역할 조건을 만족하는지 `EventParticipant` 조회로 검증.

> **연락처 노출 최소화**: 신고자 연락처가 실리는 `control` 채널은 **상황실(controller)·시스템 admin 전용**이다. 구급대원은 control을 구독하지 않고, 배정받은 건만 `event.{id}.dispatch.{userId}` 채널로(연락처 포함, 본인 지령 한정) 받으며, 현장 지도 맥락은 연락처가 없는 `event.{id}.locations` presence 채널로 받는다. 이로써 배정되지 않은 신고의 신고자 연락처가 전체 구급대원에게 팬아웃되지 않는다.

> 기존 `RequestCreated`는 `new Channel('requests')` + `PrivateChannel('rescuers')`로 하드코딩됨. → **행사 스코프 채널**(`event.{projectId}.control`)로 변경 필요. `broadcastOn()` 수정.

## 브로드캐스트 이벤트 (신규/변경)

| 이벤트 클래스 | broadcastAs | 트리거 |
|---------------|-------------|--------|
| `RequestCreated` (변경) | `request.created` | 신고 생성 (기존 재사용, 채널만 교체) |
| `RequestStatusUpdated` (신규) | `request.status.updated` | 신고 상태 변경 → 신고자에게 |
| `DispatchAssigned` (신규) | `dispatch.assigned` | 지령 배정 → 구급대원 |
| `DispatchStatusUpdated` (신규) | `dispatch.updated` | 수락/출동/도착/완료 → 관제 |
| `ParticipantLocationUpdated` (신규) | `participant.location` | 위치 ping 수신 → 관제/locations |

모든 이벤트는 `ShouldBroadcast` + `broadcastWith()`로 **최소 페이로드**(id, 좌표, 상태, 역할, 시각)만. 민감정보(연락처)는 control 채널 한정.

## 위치 추적 파이프라인

```
[참가자 앱] navigator.geolocation.watchPosition
   │  (이동/시간 임계치 충족 시, 예: 5초 또는 10m)
   ▼
POST /api/events/{id}/location  { lat, lng, accuracy, heading, speed, recorded_at }
   │
[서버] 1) event_participants.last_lat/last_lng/last_seen_at 갱신 (즉시)
       2) location_pings INSERT (큐 디스패치, append-only)
       3) ParticipantLocationUpdated broadcast → event.{id}.locations / control
   │
[웹 관제] Echo.private('event.{id}.control').listen('participant.location') → 마커 이동
```

### 전송 빈도/배터리 정책

- 기본 ping 주기: **이동 시 5초 / 정지 시 30초**(적응형). 백그라운드 시 완화.
- 클라이언트 배칭: 오프라인/약전계 구간은 로컬 큐잉 후 복구 시 일괄 전송(`recorded_at` 보존 → 동선 정확).
- 서버 부하: ping은 **DB 직쓰기 대신 큐**. 관제 표시는 Reverb 메시지로 즉시, DB는 이력용.
- **PWA 백그라운드 한계 명시**: 브라우저는 백그라운드 지속 추적이 제한적. 정밀 백그라운드 추적은 Capacitor 하이브리드 단계에서 네이티브 geolocation 플러그인으로 해결(09 로드맵 Phase 4).

## 도커/배포 고려

- Reverb WS 포트는 Apache(`docker/apache/apache.conf`)에서 `/app`(WS 경로) 프록시 또는 별도 서브도메인. TLS(`9051`) 종단.
- 헬스체크 `/up`(기존) 외 Reverb 데몬 감시(supervisor/`php artisan reverb:start` 재기동 정책).
- 수평 확장 시 Reverb는 Redis 스케일링 옵션 필요(다중 인스턴스). 초기엔 단일 인스턴스로 충분.

## 폴백

- WS 연결 실패 시 관제/앱은 **HTTP 폴링(10~15초)** 으로 자동 degrade. 위치/지령 조회 API(08 문서) 재사용.
