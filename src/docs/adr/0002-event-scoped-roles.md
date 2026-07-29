# ADR-0002. 행사 스코프 역할을 `event_participants` pivot으로 분리

- 상태: Accepted
- 날짜: 2026-06-25
- 관련: `epics/realtime-dispatch-control/02-roles-and-access.md`, `03-data-model.md`

## 배경 (Context)

현재 권한은 spatie 전역 3역할(`user` / `rescuer` / `admin`)뿐이다(`RolePermissionSeeder`).
요구사항의 현장 역할은 행사마다 달라진다: 참가자 / 운영진 / 경찰 / 자원봉사자(코스) /
자원봉사자(구급) / 구급대 / 상황실.

핵심 제약: **같은 사람이 행사 A에선 구급대, 행사 B에선 참가자**일 수 있다.
전역 역할 한 벌로는 (사용자 × 행사)별 역할을 표현할 수 없다.

후보: (a) spatie `teams` 기능으로 행사를 team으로 매핑, (b) 전역 역할에 행사 접두사 부여,
(c) **행사 스코프 전용 pivot 테이블 신설**.

## 결정 (Decision)

**2계층 역할 모델**을 둔다.

- **시스템 역할(전역)**: 기존 spatie `model_has_roles`. 값은 `admin`(시스템관리자) / `user`(일반)만 유지.
- **행사 역할(스코프)**: 신규 `event_participants` pivot `(project_id, user_id, role, status, ...)`.
  값은 `App\Enums\EventRole` string enum(`participant`/`staff`/`police`/`volunteer_course`/`volunteer_medic`/`paramedic`/`controller`).

- 접근 제어는 신규 미들웨어 `event.role:...` + 행사 스코프 쿼리로 판정.
- `event_participants.status`(`pending`/`active`/`left`)로 권한 역할은 `active` 전까지 차단.

## 근거 (Rationale)

- pivot은 (사용자 × 행사 × 역할 × 상태 × 위치캐시)를 한 곳에 자연스럽게 모은다 — 위치 ping·온라인 판정·역할이 모두 행사 스코프라 응집도가 높다.
- spatie `teams`를 켜면 기존 전역 권한 의미가 흔들리고 마이그레이션 리스크가 크다. 전역 권한(`admin`/`user`)은 그대로 두는 편이 안전하다.
- enum 기반이라 별도 역할 시딩이 불필요(행사 역할은 데이터가 아니라 코드 상수).

## 결과 (Consequences)

**긍정**
- 한 사용자의 다중 행사 동시 소속이 자연스럽게 표현된다.
- 모든 실시간 데이터(위치/신고/지령)를 `project_id`로 일관되게 스코프할 수 있다.

**부정/비용**
- 권한 판정이 "전역 역할 OR 행사 역할" 2경로가 되어 가드 로직이 늘어난다.
- `RolePermissionSeeder`에 행사 역할을 **넣지 않도록** 규율 유지 필요(섞이면 의미 붕괴).

**필요 변경**
- `event_participants` 마이그레이션 + `EventParticipant` 모델 + `EventRole` enum.
- `bootstrap/app.php` `$middleware->alias()`에 `event.role` 등록(`admin` 옆).
- 관제 웹 진입은 `controller` 또는 시스템 `admin`만. 기존 `AdminMiddleware`는 시스템 관리자용으로 유지.
