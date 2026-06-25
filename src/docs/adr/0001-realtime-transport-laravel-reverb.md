# ADR-0001. 실시간 전송 계층으로 Laravel Reverb 채택

- 상태: Accepted
- 날짜: 2026-06-25
- 관련: `epics/realtime-dispatch-control/04-realtime-architecture.md`

## 배경 (Context)

실시간 위치·지령 관제 에픽은 신고 접수, 위치 ping 팬아웃, 지령 상태 전이를
관제·앱에 즉시 반영해야 한다. 현재 시스템은:

- `BROADCAST_CONNECTION=log` (실제 브로드캐스트 없음), `config/broadcasting.php`·`routes/channels.php` 미게시.
- `App\Events\RequestCreated`가 이미 `ShouldBroadcast` + `broadcastWith()` / `broadcastAs('request.created')`를 구현 → **전송 드라이버만 붙이면 즉시 동작**할 상태.
- 스택은 Laravel 12 / PHP 8.2 단일 인스턴스(Docker), 외부 SaaS 의존 최소화 선호.

후보: (a) Pusher SaaS, (b) Soketi(자체호스팅 OSS), (c) **Laravel Reverb**(1st-party WebSocket), (d) 폴링만.

## 결정 (Decision)

**Laravel Reverb**를 실시간 전송 계층으로 채택한다. 클라이언트는 Laravel Echo + `pusher-js` 프로토콜.

- 서버: `php artisan reverb:start` 데몬을 Docker 서비스로 추가, Apache에서 WS 프록시.
- 인증: private/presence 채널은 Sanctum 세션/토큰으로 `/broadcasting/auth` 인가.
- WS 연결 실패 시 **HTTP 폴링(10~15초) 폴백**으로 자동 degrade.

## 근거 (Rationale)

- 1st-party라 Laravel 12 이벤트 시스템·Echo와 무설정에 가깝게 통합. 기존 `RequestCreated`를 그대로 첫 사용처로 쓸 수 있다.
- Pusher 같은 외부 SaaS 의존/과금/쿼터 없이 자체 인프라에 둘 수 있다(국내 단일 행사 규모에 충분).
- Soketi 대비 유지보수 주체가 명확(Laravel 공식)하고 문서·버전 정합성이 좋다.

## 결과 (Consequences)

**긍정**
- 기존 이벤트 골격 재사용으로 PoC까지 거리가 짧다.
- 운영 비용이 인프라 비용으로 한정된다.

**부정/비용**
- Reverb 데몬이 **단일 장애점** → supervisor 재기동 정책 + 폴링 폴백 필수.
- 수평 확장 시 다중 인스턴스 동기화를 위해 **Redis 스케일링**이 필요(초기 단일 인스턴스로 시작).
- 큐 워커(`queue:work`) 상시 가동 필요(위치 ping 적재·브로드캐스트 비동기화).

**필요 변경**
- `composer require laravel/reverb` → `reverb:install`, `npm i laravel-echo pusher-js`.
- `bootstrap/app.php`에 `->withBroadcasting(channels: routes/channels.php)` 추가.
- `RequestCreated::broadcastOn()` 채널 교체는 ADR-0004 참조.
