# 02. 역할 체계 · 행사 코드 입장 · 배정

## 문제

현재 역할은 spatie 전역 3종(`user`/`rescuer`/`admin`, `RolePermissionSeeder`)뿐이다. 요구사항의 역할은 **행사마다 달라지는 현장 역할**이다:

- 참가자 / 운영진 / 경찰 / 자원봉사자(코스) / 자원봉사자(구급) / 구급대 / 관리자(상황실)

같은 사람이 행사 A에선 구급대, 행사 B에선 참가자일 수 있으므로 **전역 역할로 표현 불가**. → 행사 스코프 역할(pivot)로 분리한다.

## 2계층 역할 모델

| 계층 | 저장소 | 용도 | 값 |
|------|--------|------|-----|
| **시스템 역할** (전역) | spatie `model_has_roles` (기존) | 플랫폼 권한 | `admin`(시스템관리자), `user`(일반) — 기존 유지 |
| **행사 역할** (스코프) | 신규 `event_participants` pivot | 현장 작전 역할 | `participant`, `staff`, `police`, `volunteer_course`, `volunteer_medic`, `paramedic`, `controller` |

`App\Enums\EventRole` (신규, string enum) — 라벨·지도 마커 색상·아이콘을 `RequestStatus` 패턴대로 헬퍼로 보유.

```php
enum EventRole: string {
    case PARTICIPANT      = 'participant';       // 참가자
    case STAFF            = 'staff';             // 운영진
    case POLICE           = 'police';            // 경찰
    case VOLUNTEER_COURSE = 'volunteer_course';  // 자원봉사자(코스)
    case VOLUNTEER_MEDIC  = 'volunteer_medic';   // 자원봉사자(구급)
    case PARAMEDIC        = 'paramedic';         // 구급대
    case CONTROLLER       = 'controller';        // 관리자/상황실

    public function label(): string;        // 한글 라벨
    public function markerColor(): string;  // 관제 지도 마커 색상
    public function canReceiveDispatch(): bool;  // 지령 수신 대상? (paramedic, volunteer_medic)
    public function canDispatch(): bool;         // 지령 발령 권한? (controller)
}
```

> 자원봉사자(구급)·구급대는 지령 수신 대상, 관리자(상황실/controller)는 지령 발령 권한. 권한 게이트는 `EventRole` 헬퍼 + 행사 스코프로 판정한다.

## 행사 코드 입장 (Event Code Join)

요구: "로그인 또는 **행사 코드 입장**". 기존엔 프로젝트 slug URL/QR만 있었음 → 짧은 입장 코드를 추가한다.

- `projects.join_code` (예: 6자리 영숫자, 행사당 유니크) 컬럼 추가. 생성 시 자동 발급(`Project::booted`에 slug와 함께).
- 입장 플로우:
  1. 앱에서 **행사 코드 입력** 또는 **QR 스캔**(QR은 `join_code` 또는 기존 slug 모두 허용).
  2. 미로그인 시 로그인/소셜로그인 → 복귀.
  3. `POST /api/events/{joinCode}/join` → `event_participants`에 (project, user, role) upsert.
  4. **역할 선택/배정**: 기본 `participant`로 입장하되, 운영진이 사전 명단을 올린 경우(전화번호 매칭) 해당 역할로 자동 배정. 미매칭 시 관리자 승인 대기 상태(`pending`) 가능.

### 역할 배정 규칙

| 방식 | 설명 |
|------|------|
| 자가선택 | 참가자는 코드 입장 시 즉시 `participant` 확정 |
| 사전명단 배정 | 운영진이 웹 관제에서 전화번호+역할 CSV 업로드 → 입장 시 자동 매칭 |
| 현장 수동배정 | 관리자(controller)가 웹 관제에서 입장자 역할 변경/승인 |

`event_participants.status`: `pending`(승인대기) / `active`(활동중) / `left`(퇴장). 구급대·경찰 등 권한 역할은 `active` 전까지 지령·관제 접근 불가.

## 접근 제어

- API: `auth:sanctum` + 신규 미들웨어 `event.role:paramedic,controller` (라우트 그룹에 적용). `bootstrap/app.php`의 `$middleware->alias()`에 등록(`admin` 옆에 `event.role`).
- 행사 스코프 강제: 모든 위치/신고/지령 쿼리는 `project_id`로 스코프. 사용자는 자신이 `active`로 속한 행사 데이터만 접근.
- 관제 웹: `controller` 또는 시스템 `admin`만 진입. 기존 `AdminMiddleware`는 시스템 관리자용으로 유지.

## 마이그레이션·시더 영향

- `RolePermissionSeeder`에 `controller`·`paramedic` 등 **행사역할은 넣지 않는다**(전역 역할 아님). 시스템 역할 `admin`/`user`만 유지.
- 신규 시더 `EventRoleSeeder`는 불필요(enum 기반). 대신 데모 행사 + 참가자 픽스처용 `EventParticipantSeeder`(개발용) 추가 권장.

자세한 스키마는 [03-data-model.md](03-data-model.md) 참조.
