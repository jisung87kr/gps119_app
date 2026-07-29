# 아키텍처 기술 스펙 (계약) — 실시간 위치·지령 관제

> 이 문서는 **구현자가 그대로 따라 만들 수 있는 계약(contract)** 이다. 코드는 포함하지 않고 시그니처·스키마·규칙만 확정한다.
> 상위 결정은 ADR 0001~0004, 설계 맥락은 동 에픽 01~09 문서. 기존 패턴(서비스레이어 · Enum 헬퍼 · `response()->success/error` 매크로 · 모델 `booted()` 훅)을 그대로 계승한다.
> ADR 결정은 **뒤집지 않는다.** 문서 간 모순은 본문 끝 `OPEN ISSUES`에 모았다(임의 변경 금지).

작성자: 나민식(SaaS 아키텍트) · 기준일 2026-06-25

---

## SPEC-01. 마이그레이션 최종본

03 문서를 기준으로 하되, 컬럼/인덱스/제약을 **구현 확정 수준**으로 못박는다. 03과 어긋나는 곳은 `정정` 표기 + 사유를 단다.

### 마이그레이션 순서 (생성 파일명 규약)

| # | 파일 | 대상 | 비고 |
|---|------|------|------|
| 1 | `..._add_join_code_to_projects` | `projects` | FK 의존 없음 |
| 2 | `..._add_request_type_to_requests` | `requests` | dispatches가 requests.id를 참조하므로 5번보다 먼저 |
| 3 | `..._create_event_participants` | 신규 | projects·users 선행 필요(둘 다 기존) |
| 4 | `..._create_location_pings` | 신규 | 동상 |
| 5 | `..._create_dispatches` | 신규 | requests·projects·users 선행 필요 |

> **정정 1 (순서)**: 03 문서는 `create_dispatches`를 4번 `add_request_type` 뒤(5번)에 둔다 — 동일. 다만 03은 `create_event_participants`(2)·`create_location_pings`(3)를 `add_request_type`(4) **앞**에 둔다. `dispatches`만 requests에 FK가 걸리고 type 컬럼과는 무관하므로, requests 변경(type)을 앞당겨 **2번**으로 재배치했다(type 관련 일회성 데이터 보정과 dispatches 생성이 섞이지 않게). 의존성 위반 없음.

### 1) `projects.join_code`

```php
Schema::table('projects', function (Blueprint $table) {
    $table->string('join_code', 12)->nullable()->unique()->after('slug');
});
```
- 제약: `unique` (행사당 유니크). nullable — 기존 행 보존용. 신규 행은 `Project::booted()`에서 발급.
- `Project` `$fillable`에 `'join_code'` 추가.
- 발급 규칙: **6자리 대문자 영숫자**(혼동문자 `0/O/1/I` 제외 권장), 충돌 시 재생성 루프(기존 slug 로직과 동형). 발급 위치는 `creating` 훅의 slug 생성 직후.

### 2) `requests.type`

```php
Schema::table('requests', function (Blueprint $table) {
    $table->string('type')->default('other')->after('description'); // App\Enums\RequestType
});
```
- `Request` `$fillable`에 `'type'` 추가, `$casts`에 `'type' => RequestType::class`.
- `priority`는 **유지**. 신고 생성 시 `type`에서 `defaultPriority()`로 자동 매핑하되 상황실 수동 상향 허용(SPEC-04).
- 데이터 보정(선택): 기존 행 `description` → type 추정 매핑은 **별도 일회성 커맨드**로 분리(마이그레이션 본문에 비즈니스 로직 금지). 미수행 시 기본값 `other`.

### 3) `event_participants`

```php
Schema::create('event_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role');                          // App\Enums\EventRole
    $table->string('status')->default('active');     // App\Enums\ParticipantStatus (pending/active/left)
    $table->boolean('sharing_location')->default(false);
    $table->decimal('last_lat', 10, 8)->nullable();  // 최신 위치 캐시(관제 초기 로드 1쿼리용)
    $table->decimal('last_lng', 11, 8)->nullable();
    $table->timestamp('joined_at')->nullable();
    $table->timestamp('last_seen_at')->nullable();   // 온라인 판정
    $table->timestamps();
    $table->unique(['project_id', 'user_id']);
    $table->index(['project_id', 'role', 'status']); // 가용 인력 조회(역할+active)
});
```
- **정정 2 (컬럼 통합)**: 03 문서는 `last_lat/last_lng`를 본 테이블 정의 *밖*에 별도 블록으로 적었다. 단일 `create` 마이그레이션 안에 포함해 확정한다(별도 alter 불필요). 의미 동일.
- **추가 인덱스** `(project_id, role, status)`: 06 "가용 구급대원 목록"·관제 인력현황 쿼리 가속(03엔 명시 안 됨 — 비기능 요구 반영).
- `status` 기본값 결정: 자가선택 참가자는 즉시 `active`. 권한 역할(paramedic/controller 등)을 사전명단·수동배정으로 부여할 때 `pending`으로 둘 수 있음(SPEC-04 `joinEvent` 규칙).

### 4) `location_pings`

```php
Schema::create('location_pings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->unsignedSmallInteger('accuracy')->nullable(); // m
    $table->unsignedSmallInteger('heading')->nullable();  // 0-359
    $table->unsignedSmallInteger('speed')->nullable();    // m/s
    $table->timestamp('recorded_at');
    // timestamps() 생략 — append-only 이력, recorded_at 단일 시각으로 충분
    $table->index(['project_id', 'recorded_at']);
    $table->index(['user_id', 'recorded_at']);
});
```
- append-only. UPDATE/DELETE 없음(아카이브 이관만). 고빈도 → 큐 적재(SPEC-04 `recordPing`).
- 보존기간 정책은 OPEN ISSUE(개인정보).

### 5) `dispatches`

```php
Schema::create('dispatches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('users');  // 발령자(controller)
    $table->foreignId('paramedic_id')->constrained('users'); // 수령 구급대원
    $table->string('status')->default('assigned');           // App\Enums\DispatchStatus
    $table->text('note')->nullable();
    $table->text('reject_reason')->nullable();               // 거절 사유(06: 사유 필수)
    $table->timestamp('assigned_at')->useCurrent();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('en_route_at')->nullable();
    $table->timestamp('arrived_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamps();
    $table->index(['project_id', 'status']);   // 출동 현황 보드 집계
    $table->index(['request_id', 'status']);   // 활성 지령 1건 검색(재지령)
    $table->index('paramedic_id');             // /dispatches/mine
});
```
- **정정 3 (컬럼 추가)**: 03 문서엔 `reject_reason` 없음. 06 문서가 "거절 사유 필수"를 요구하므로 컬럼을 추가(03 누락 보강). `note`는 지령 메모, `reject_reason`은 거절 전이 전용.
- **추가 인덱스** `(request_id, status)`·`paramedic_id`: 재지령 시 "해당 신고의 활성 지령" 조회 및 본인 지령 목록용(03엔 `(project_id,status)`만 명시).
- "한 시점 활성 지령 1건"은 DB 유니크 제약으로 강제 불가(거절 row가 남으므로) → **서비스 레이어 불변식**으로 보장(SPEC-04).

---

## SPEC-02. 신규 Enum 계약

기존 `RequestStatus`/`RequestPriority` 패턴 그대로: backed string enum + 뷰 헬퍼. 모델 캐스팅, 검증은 `Rule::enum(...)`.

### SPEC-02a. `App\Enums\EventRole`

```php
enum EventRole: string
{
    case PARTICIPANT      = 'participant';
    case STAFF            = 'staff';
    case POLICE           = 'police';
    case VOLUNTEER_COURSE = 'volunteer_course';
    case VOLUNTEER_MEDIC  = 'volunteer_medic';
    case PARAMEDIC        = 'paramedic';
    case CONTROLLER       = 'controller';

    public function label(): string;            // 한글: 참가자/운영진/경찰/자원봉사자(코스)/자원봉사자(구급)/구급대/상황실
    public function markerColor(): string;      // 관제 지도 마커 색(hex 또는 토큰)
    public function badgeClasses(): string;     // Tailwind 뱃지(기존 패턴 일관성)
    public function canReceiveDispatch(): bool;  // true: PARAMEDIC, VOLUNTEER_MEDIC
    public function canDispatch(): bool;         // true: CONTROLLER
    public function canViewControl(): bool;      // true: CONTROLLER (시스템 admin은 EventRole 밖에서 별도 허용)
}
```
- `canReceiveDispatch()` → `PARAMEDIC`, `VOLUNTEER_MEDIC` 만 `true`.
- `canDispatch()` / `canViewControl()` → `CONTROLLER` 만 `true`. 시스템 `admin`은 enum이 아닌 spatie 전역 역할이므로 채널/미들웨어에서 **별도 OR 조건**으로 통과(ADR-0002).

### SPEC-02b. `App\Enums\ParticipantStatus` (신규 — 03의 문자열 상수를 enum화)

```php
enum ParticipantStatus: string
{
    case PENDING = 'pending';  // 승인대기 — 권한 역할은 active 전까지 접근 차단
    case ACTIVE  = 'active';   // 활동중
    case LEFT    = 'left';     // 퇴장

    public function label(): string;
    public function isActive(): bool; // self::ACTIVE
}
```
- **정정 4**: 03/02 문서는 `status`를 평문 문자열로 둠. 코드 일관성·오타 방지를 위해 enum으로 승격(값 동일, 마이그레이션 영향 없음). `EventParticipant.status`에 캐스팅.

### SPEC-02c. `App\Enums\RequestType`

```php
enum RequestType: string
{
    case ACCIDENT  = 'accident';   // 사고
    case BREAKDOWN = 'breakdown';  // 고장
    case OTHER     = 'other';      // 기타
    case EMERGENCY = 'emergency';  // 긴급전화

    public function label(): string;                 // 사고/고장/기타/긴급전화
    public function defaultPriority(): RequestPriority; // 매핑 아래
    public function markerIcon(): string;            // 관제 지도 신고 핀 아이콘
    public function badgeClasses(): string;
}
```
- `defaultPriority()` 매핑(확정): `EMERGENCY → CRITICAL`, `ACCIDENT → HIGH`, `BREAKDOWN → MEDIUM`, `OTHER → LOW`.
- 신고 생성 시 `priority` 미지정이면 `type->defaultPriority()` 적용. 명시 지정 시 그 값 우선(상황실 수동 상향).

### SPEC-02d. `App\Enums\DispatchStatus` + 전이 계약

```php
enum DispatchStatus: string
{
    case ASSIGNED  = 'assigned';
    case ACCEPTED  = 'accepted';
    case EN_ROUTE  = 'en_route';
    case ARRIVED   = 'arrived';
    case COMPLETED = 'completed';
    case REJECTED  = 'rejected';

    public function label(): string;        // 배정/수락/출동/도착/완료/거절
    public function badgeClasses(): string;
    public function dotClass(): string;
    public function isActive(): bool;        // ASSIGNED,ACCEPTED,EN_ROUTE,ARRIVED (REJECTED/COMPLETED는 종료)
    public function isTerminal(): bool;      // COMPLETED, REJECTED

    /** 이 상태에서 허용되는 다음 액션→결과 상태 맵 */
    public function allowedTransitions(): array; // 아래 표 그대로

    /** 이 전이가 연결 requests에 강제할 상태(없으면 null) */
    public function syncsRequestStatus(): ?RequestStatus;
}
```

#### 액션 어휘 (PATCH body `status`로 전달되는 값)

| 액션 | 결과 상태 |
|------|-----------|
| `accept` | `accepted` |
| `en_route` | `en_route` |
| `arrive` | `arrived` |
| `complete` | `completed` |
| `reject` | `rejected` |

> API는 **결과 상태값**(`accepted`/`en_route`/`arrived`/`completed`/`rejected`)을 그대로 받는다(08 문서 `{status}` 규약). 서비스가 현재 상태 × 목표 상태가 허용 전이표에 있는지 검증.

#### DispatchStatus 허용 전이표 (현재상태 × 목표상태 → 결과)

| 현재\목표 | accepted | en_route | arrived | completed | rejected |
|-----------|:---:|:---:|:---:|:---:|:---:|
| **assigned** | OK | — | — | — | OK |
| **accepted** | — | OK | — | — | OK |
| **en_route** | — | — | OK | — | — |
| **arrived** | — | — | — | OK | — |
| **completed** | — | — | — | — | — (terminal) |
| **rejected** | — | — | — | — | — (terminal) |

- `—` = 불가 → `DispatchTransitionException`(도메인 예외) → API 422.
- `rejected`는 **`assigned`/`accepted` 단계에서만** 가능(ADR-0003, 06). `en_route`/`arrived`에서는 거절 불가(이미 현장 이동 중).
- 동일 상태로의 재전이(`accepted→accepted` 등)는 멱등 허용 여부 → OPEN ISSUE(기본: 불가로 두고 422).

#### requests.status 동기화 규칙 (ADR-0003 단일화)

| Dispatch 전이 결과 | requests.status | requests 타임스탬프 |
|--------------------|-----------------|---------------------|
| `accepted` | `in_progress` | `responded_at` 최초 1회 |
| `en_route` | `in_progress` | (유지) |
| `arrived` | `in_progress` | (유지) |
| `completed` | `completed` | `completed_at` |
| `rejected` | **변경 없음** | 신고는 `pending` 유지(재지령 대상) |

- 동기화 책임은 **`DispatchService` 단일**. 신고 status를 다른 경로로 직접 바꾸지 않는다(드리프트 방지).
- 거절 시 신고는 재지령 대기 상태로 남아야 하므로 requests를 건드리지 않는다.

---

## SPEC-03. 모델/관계 계약

### SPEC-03a. `App\Models\EventParticipant`

```php
protected $fillable = [
    'project_id', 'user_id', 'role', 'status',
    'sharing_location', 'last_lat', 'last_lng', 'joined_at', 'last_seen_at',
];
protected $casts = [
    'role'             => EventRole::class,
    'status'           => ParticipantStatus::class,
    'sharing_location' => 'boolean',
    'last_lat'         => 'decimal:8',
    'last_lng'         => 'decimal:8',
    'joined_at'        => 'datetime',
    'last_seen_at'     => 'datetime',
];

public function project(): BelongsTo;   // Project
public function user(): BelongsTo;      // User

public function scopeActive($q);                       // status = ACTIVE
public function scopeForProject($q, int $projectId);
public function scopeReceivers($q);                    // role ∈ [PARAMEDIC, VOLUNTEER_MEDIC]
public function isOnline(int $thresholdSeconds = 60): bool; // last_seen_at 기준
```

### SPEC-03b. `App\Models\LocationPing`

```php
public $timestamps = false; // recorded_at 단일 시각

protected $fillable = [
    'project_id', 'user_id', 'latitude', 'longitude',
    'accuracy', 'heading', 'speed', 'recorded_at',
];
protected $casts = [
    'latitude'    => 'decimal:8',
    'longitude'   => 'decimal:8',
    'recorded_at' => 'datetime',
];

public function project(): BelongsTo;
public function user(): BelongsTo;
```

### SPEC-03c. `App\Models\Dispatch`

```php
protected $fillable = [
    'request_id', 'project_id', 'assigned_by', 'paramedic_id',
    'status', 'note', 'reject_reason',
    'assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'completed_at', 'rejected_at',
];
protected $casts = [
    'status'       => DispatchStatus::class,
    'assigned_at'  => 'datetime',
    'accepted_at'  => 'datetime',
    'en_route_at'  => 'datetime',
    'arrived_at'   => 'datetime',
    'completed_at' => 'datetime',
    'rejected_at'  => 'datetime',
];

public function request(): BelongsTo;                          // Request
public function project(): BelongsTo;                          // Project
public function assignedBy(): BelongsTo;                       // User, 'assigned_by'
public function paramedic(): BelongsTo;                        // User, 'paramedic_id'

public function scopeActive($q);                              // status ∈ isActive()
public function scopeForProject($q, int $projectId);
public function isOwnedBy(User $user): bool;                  // paramedic_id === user->id
```
- `Dispatch`는 `RequestCreated`처럼 `booted()`에서 이벤트를 쏘지 않는다 — 전이 브로드캐스트는 **서비스가 명시적으로** 발행(SPEC-04). 모델 훅 남발 방지.

### SPEC-03d. 기존 모델에 추가할 관계

| 모델 | 추가 |
|------|------|
| `Project` | `participants(): HasMany EventParticipant` / `locationPings(): HasMany LocationPing` / `dispatches(): HasMany Dispatch` / `$fillable += 'join_code'` / `booted()`에 join_code 발급 |
| `Request` | `dispatches(): HasMany Dispatch` / `activeDispatch(): HasOne`(status active 최신 1건) / `$fillable += 'type'` / `$casts += type` |
| `User` | `eventParticipations(): HasMany EventParticipant` / `dispatches(): HasMany Dispatch('paramedic_id')` / `eventRoleIn(Project $p): ?EventRole`(active 참가만) |

- `User::eventRoleIn()`은 채널 인가·미들웨어가 공통으로 쓰는 단일 진입점(중복 쿼리 방지). active 아니면 `null`.

---

## SPEC-04. 서비스 계약

### SPEC-04a. `App\Services\DispatchService`

```php
/** 지령 배정. controller/admin만. 같은 신고에 활성 지령이 있으면 차단(재지령은 reassign로). */
public function assign(Request $request, User $paramedic, User $controller, ?string $note = null): Dispatch;

/** 상태 전이. paramedic 본인 또는 controller. 허용 전이표 검증 → 타임스탬프·requests 동기화·브로드캐스트. */
public function transition(Dispatch $dispatch, DispatchStatus $target, User $actor, ?string $note = null, ?string $rejectReason = null): Dispatch;

/** 재지령. 기존 활성 지령이 rejected이거나 무응답일 때 새 대원에게 새 row 생성. */
public function reassign(Request $request, User $newParamedic, User $controller, ?string $note = null): Dispatch;

/** 행사 출동 현황 보드 집계(상태별 카운트 + 활성 지령 목록). */
public function boardForProject(Project $project): array;

/** 본인 소유 지령 목록(행사 무관). */
public function myDispatches(User $paramedic): Collection;
```

**불변식·검증 규칙 (서비스가 강제)**
1. `assign` 권한: `$controller`가 해당 행사 `CONTROLLER`(active) **또는** 시스템 `admin`. 아니면 도메인 예외 → 403.
2. 대상 적격: `$paramedic`이 해당 행사에서 `canReceiveDispatch()`(PARAMEDIC/VOLUNTEER_MEDIC) **이고** `active`. 아니면 422.
3. **활성 지령 1건 불변식**: `assign` 시 동일 `request_id`에 `isActive()` 지령이 있으면 거부(재지령은 `reassign` 경로). `reassign`은 기존 활성 지령이 terminal(rejected) 또는 controller가 명시적 회수한 경우에만.
4. 행사 스코프 일치: `dispatch.project_id === request.project_id`. 신고에 `project_id`가 없으면(비행사 신고) 지령 배정 불가 → OPEN ISSUE 참조.
5. `transition` 권한: 목표가 `reject`/일반 전이면 **paramedic 본인**; controller는 전 전이 가능(현장 대리 처리). `reject` 시 `rejectReason` 필수(없으면 422).
6. 전이 검증: `DispatchStatus::allowedTransitions()` 위반 → `DispatchTransitionException` → 422.
7. 전이 부수효과(원자적, 1 트랜잭션): 타임스탬프 스탬프 → `requests.status` 동기화(SPEC-02d) → `DispatchStatusUpdated` 브로드캐스트(+ completed/accepted 시 `RequestStatusUpdated`도 신고자에게).
8. `assign` 성공 시 `DispatchAssigned` 브로드캐스트(해당 구급대원 채널).

> **정정 5 (RequestService::assignRescuer 폐기 경로)**: ADR-0003대로 신규 흐름으로 일원화. 기존 `assignRescuer`(즉시 in_progress)는 dispatch 안정화 후 `Deprecated`. 본 스펙에서는 신규 `DispatchService`를 정본으로 하고, `RequestService::assignRescuer`는 **신규 코드 호출 금지**(레거시 라우트만 잠정 유지).

### SPEC-04b. `App\Services\EventParticipantService` (신규 — 02 입장/배정 로직 응집)

```php
/** join_code로 입장. 미참가면 생성(자가선택 participant=active), 사전명단 매칭 시 해당 역할. 권한역할은 pending 가능. */
public function joinByCode(string $joinCode, User $user): EventParticipant;

/** controller가 입장자 역할 변경/승인(pending→active). */
public function assignRole(Project $project, User $target, EventRole $role, User $controller, ParticipantStatus $status = ParticipantStatus::ACTIVE): EventParticipant;

/** 위치공유 on/off. */
public function setSharing(Project $project, User $user, bool $on): EventParticipant;

/** 관제 초기 로드: 전 인원 최신위치+역할+online (event_participants 1쿼리). */
public function rosterForControl(Project $project): Collection;
```

### SPEC-04c. `App\Services\LocationService` (신규)

```php
/** 위치 ping 수신: (1) event_participants 캐시 즉시 갱신, (2) location_pings INSERT 큐 적재, (3) ParticipantLocationUpdated 브로드캐스트. */
public function recordPing(Project $project, User $user, array $ping): void;
```
- `recorded_at` 미래 거부, lat/lng 범위 검증은 **요청 검증(FormRequest)** 에서 1차, 서비스에서 2차 방어.
- rate-limit(초당 1)는 라우트 미들웨어(`throttle`)로 1차 차단(SPEC-06).

### SPEC-04d. `RequestService` 변경점

| 메서드 | 변경 |
|--------|------|
| `createRequest` | `type` 처리 추가: `priority` 미지정 시 `RequestType::from($data['type'])->defaultPriority()` 주입. `project_id` 행사 신고 시 필수(SPEC-07). |
| `updateRequest` | **좌표 불변**: `latitude`/`longitude`/`address`는 화이트리스트에서 제외(수정 시 무시·거부). SPEC-07 참조. status 직접 변경은 dispatch 동기화와 충돌하므로 비-dispatch 경로에서는 신중히(완료/취소 등 비전이 케이스만). |
| `assignRescuer` | Deprecated(레거시). 신규 흐름은 `DispatchService::assign`. |

---

## SPEC-05. 채널 인가 규칙 + 브로드캐스트 페이로드

`bootstrap/app.php`에 `->withBroadcasting(channels: __DIR__.'/../routes/channels.php')` 추가(ADR-0001). 4개 채널 인가는 `User::eventRoleIn($project)` 단일 헬퍼로 판정.

### SPEC-05a. 채널 인가표 (`routes/channels.php`)

| 채널 | 타입 | 인가 통과 조건 |
|------|------|----------------|
| `event.{projectId}.control` | private | `eventRoleIn == CONTROLLER` **OR** 시스템 `admin`. (구급대 불통과 — ADR-0004) |
| `event.{projectId}.locations` | presence | 해당 행사 `active` 참가자 전원. presence payload는 `{user_id, role}` 만(연락처 없음). |
| `event.{projectId}.dispatch.{userId}` | private | `auth()->id() === {userId}` **AND** 그 행사 `active` 참가자 **AND** `canReceiveDispatch()`. |
| `event.{projectId}.requester.{userId}` | private | `auth()->id() === {userId}` **AND** 그 행사에 신고 이력 보유(신고자 본인). |

- 공통 전제: 모든 채널 인가는 `$user`가 해당 `projectId`에 **active로 속해야** 통과(presence/locations 포함). 단 시스템 admin은 control에서 active 여부와 무관하게 통과(전역 권한).
- 인가 실패는 `/broadcasting/auth` 403 → 클라이언트는 폴링 폴백(04).

### SPEC-05b. 브로드캐스트 이벤트 페이로드 (`broadcastWith()` 최종)

ADR-0004: **연락처는 control·개인 dispatch 페이로드에만.** 나머지는 좌표/역할/상태/시각만.

| 이벤트 | broadcastAs | 채널 | 페이로드(키 확정) |
|--------|-------------|------|-------------------|
| `RequestCreated`(변경) | `request.created` | `event.{projectId}.control` | `request_id, project_id, type, priority, latitude, longitude, address, requester:{id,name,phone}, created_at` |
| `RequestStatusUpdated`(신규) | `request.status.updated` | `event.{projectId}.requester.{userId}` | `request_id, status, dispatch:{paramedic_name, paramedic_phone}, updated_at` |
| `DispatchAssigned`(신규) | `dispatch.assigned` | `event.{projectId}.dispatch.{userId}` | `dispatch_id, request:{id,type,latitude,longitude,address,requester_name,requester_phone}, note, assigned_at` |
| `DispatchStatusUpdated`(신규) | `dispatch.updated` | `event.{projectId}.control` | `dispatch_id, request_id, status, paramedic_id, occurred_at`(연락처 없음) |
| `ParticipantLocationUpdated`(신규) | `participant.location` | `event.{projectId}.locations` **및** `event.{projectId}.control` | `user_id, role, latitude, longitude, accuracy, recorded_at`(연락처 없음) |

- **정정 6 (RequestCreated 채널)**: 기존 `new Channel('requests')` + `PrivateChannel('rescuers')` → `PrivateChannel('event.{projectId}.control')` 단일로 교체(ADR-0004). `broadcastOn()`은 `request.project_id` 필요 → 비행사 신고 처리는 OPEN ISSUE.
- `ParticipantLocationUpdated`는 한 이벤트가 `broadcastOn()`에서 두 채널을 반환(locations + control). control 구독자는 동일 좌표를 받지만 별도 페이로드 분기 불필요(연락처 미포함이라 동일 payload 안전).
- 모든 이벤트 `ShouldBroadcast`. 큐 비동기는 `BROADCAST_CONNECTION=reverb` + `queue:work`(04 체크리스트). 현재 `NotifyRescuers`는 동기 — Discord/알림 부수효과는 기존대로 유지하되 control 채널 전환에 맞춰 수신자 산정 변경(전 rescuer → 해당 행사 controller).

---

## SPEC-06. API 계약

규약: `auth:sanctum`, 응답 `response()->success($data,$msg,$code)` / `response()->error($msg,$code)`. 검증은 `FormRequest` 또는 인라인 `validate()`(기존 패턴). 신규 미들웨어 `event.role`.

### SPEC-06a. 미들웨어 `EnsureEventRole`

`bootstrap/app.php` `$middleware->alias()`에 `'event.role' => App\Http\Middleware\EnsureEventRole::class` 추가(`admin` 옆).

- 사용: `event.role:controller` 또는 `event.role:paramedic,controller`(콤마=OR).
- 행사 id 해석(08 문서 그대로):
  - 라우트 파라미터가 project(`/api/events/{id}/...`) → `{id}` 직접.
  - 라우트 파라미터가 dispatch(`PATCH /api/dispatches/{id}/status`) → 바인딩 `Dispatch->project_id`. 추가로 "paramedic 본인 OR 그 행사 controller" 검사.
  - project 컨텍스트 없는 본인소유(`/api/dispatches/mine`) → `event.role` 미적용, `auth` + 소유자 스코프.
- 판정: `User::eventRoleIn($project)`가 허용 역할 목록에 포함되거나 시스템 `admin`이면 통과. 아니면 403 `response()->error('해당 행사에 대한 권한이 없습니다', 403)`.

### SPEC-06b. 엔드포인트별 계약

표기: `req` = 요청 body/param, `res.data` = 성공 페이로드 형태.

#### 행사 입장

| 경로 | 가드 | req | 검증 | res.data |
|------|------|-----|------|----------|
| `GET /api/events/{joinCode}` | auth | path joinCode | `exists:projects,join_code` | `{id,name,start_date,end_date,is_active}`(미리보기, 민감정보 없음) |
| `POST /api/events/{joinCode}/join` | auth | `{}` | joinCode 유효·행사 active | `{participant:{id,role,status},project:{id,name}}` |
| `GET /api/events/{id}/me` | auth+참가자 | path id | active 참가 | `{role,status,sharing_location,last_seen_at}` |

- 비활성 행사 join은 422 + 안내(03/Project `isActive()`).

#### 위치

| 경로 | 가드 | req | 검증 | res.data |
|------|------|-----|------|----------|
| `POST /api/events/{id}/location` | auth + active 참가자(`event.role` 전 역할 OR 단순 active 검사) | `{latitude,longitude,accuracy?,heading?,speed?,recorded_at}` | lat∈[-90,90], lng∈[-180,180], accuracy/heading/speed 정수≥0, heading≤359, `recorded_at` ≤ now, `throttle` 초당 1 | `null`(202/200, 비동기 적재) |
| `GET /api/events/{id}/participants` | `event.role:controller` | path id | — | `[{user_id,name,role,last_lat,last_lng,last_seen_at,online}]` |
| `PATCH /api/events/{id}/sharing` | auth+참가자 | `{sharing_location:bool}` | boolean | `{sharing_location}` |

- `POST .../location`은 `event.role`에 전 역할 나열 대신 **active 참가 여부만** 보는 경량 가드 권장(OPEN ISSUE: 미들웨어 시그니처). `sharing_location=false`면 ping 수신해도 브로드캐스트/캐시 갱신 스킵.

#### 신고 (기존 확장)

| 경로 | 가드 | 변경/검증 | res.data |
|------|------|-----------|----------|
| `POST /api/requests` | auth | body `type` 추가 `Rule::enum(RequestType::class)`. `project_id` 행사 신고 시 필수(`exists:projects,id`). 좌표 검증은 기존 store 계승. | `Request`(+user, type, priority) |
| `GET /api/events/{id}/requests` | `event.role:controller` | path id | `[Request 요약 + active dispatch]` |
| `GET /api/requests/{id}` | auth(소유/관계자) | — | `Request + dispatches` |

#### 지령

| 경로 | 가드 | req | 검증 | res.data |
|------|------|-----|------|----------|
| `POST /api/requests/{id}/dispatch` | `event.role:controller`(해당 신고 행사) | `{paramedic_id, note?}` | `paramedic_id exists:users,id`, 대상 적격(SPEC-04 규칙2), 활성지령 불변식 | `Dispatch` |
| `PATCH /api/dispatches/{id}/status` | paramedic 본인 OR 그 행사 controller | `{status, note?, reject_reason?}` | `status ∈ {accepted,en_route,arrived,completed,rejected}`, 전이표 검증, reject 시 `reject_reason` required | `Dispatch`(전이 후) |
| `GET /api/dispatches/mine` | auth(소유자 스코프) | — | — | `[Dispatch + request 요약]` |
| `GET /api/events/{id}/dispatches` | `event.role:controller` | path id | — | `{counts:{assigned,accepted,en_route,arrived,completed,rejected}, active:[...], history:[...]}` |

- 전이 위반 → 422 + `response()->error('허용되지 않은 상태 전이입니다: {from} → {to}', 422)`.
- `POST /requests/{id}/dispatch`가 기존 `GET /requests/{id}/assign`을 대체(ADR-0003). 레거시 `assign`은 잠정 유지 후 Deprecated.

#### 리포트

| 경로 | 가드 | 비고 |
|------|------|------|
| `GET /api/events/{id}/report/requests.csv` | `event.role:controller` | 동기 스트림 |
| `GET /api/events/{id}/report/dispatches.csv` | `event.role:controller` | 전이 타임라인 |
| `POST /api/events/{id}/report/tracks` | `event.role:controller` | 대용량 → 큐 생성 → 다운로드 링크 응답. 보존기간 정책 적용(OPEN ISSUE). |

---

## SPEC-07. 데이터 정합성 규칙

### SPEC-07a. 신고 스냅샷 불변

- `requests.latitude/longitude/address`는 **생성 시점 값으로 고정.** `RequestService::updateRequest`의 화이트리스트에서 **명시 제외**한다. PATCH/PUT body에 좌표가 와도 무시(또는 422). 갱신 가능 화이트리스트: `status`(비-dispatch 경로 한정), `priority`(수동 상향), `description`, `type`.
- 좌표 갱신이 필요한 "실시간 위치"는 `location_pings`/`event_participants.last_lat/lng`로 **물리 분리**(03·06). 길안내(06)는 신고 고정 좌표만 사용.
- 검증: 신고 생성 후 `dispatches`/`location_pings`가 `requests` 좌표를 절대 역으로 수정하지 않음(서비스 코드 리뷰 체크리스트).

### SPEC-07b. 행사 스코프 강제

- 모든 위치/신고/지령 쿼리는 `project_id` 스코프. 사용자는 자신이 `active`로 속한 행사 데이터만 접근(02·ADR-0002).
- `dispatch.project_id === dispatch.request.project_id` 일치 강제(SPEC-04 규칙4). 다른 행사 신고에 지령 배정 불가.
- 채널·미들웨어·서비스 **3중 방어**: 채널 인가(SPEC-05a) + `EnsureEventRole`(SPEC-06a) + 서비스 권한 검사(SPEC-04). 어느 하나라도 단독 의존 금지.
- `cascadeOnDelete`: 행사 삭제 시 participants/pings/dispatches 연쇄 삭제(03). 단 `projects`는 SoftDeletes이므로 실제 cascade는 force-delete 시에만 발동 → OPEN ISSUE.

---

## OPEN ISSUES (문서 간 모순·미결 — 임의 변경하지 않고 보고)

| # | 이슈 | 충돌 지점 | 권고(미반영, 결정 대기) |
|---|------|-----------|------------------------|
| OI-1 | **비행사 신고의 control 채널** | 기존 `/api/requests`는 `project_id` nullable. `RequestCreated`를 `event.{projectId}.control`로 바꾸면(ADR-0004) `project_id` 없는 신고는 broadcastOn 대상 채널이 없음. | 비행사 신고는 레거시 `requests`/`rescuers` 채널 유지 또는 전역 fallback control 채널 분기. 정책 결정 필요. |
| OI-2 | **활성 지령 1건 DB 강제 불가** | 03 "한 시점 활성 지령 1건"을 unique로 강제 못함(rejected row 잔존). | 서비스 불변식으로만 보장(SPEC-04 규칙3). 동시성(2 controller 동시 배정) 대비 `request_id` 락 또는 부분 유니크 인덱스 검토. |
| OI-3 | **SoftDelete vs cascadeOnDelete** | `projects` SoftDeletes인데 자식 FK는 `cascadeOnDelete`(hard). 소프트 삭제 시 자식 미정리, force-delete 시에만 cascade. | 소프트 삭제 행사의 participants/pings 보존·정리 정책 결정 필요. |
| OI-4 | **location POST 가드 시그니처** | 08은 `auth + active 참가자`라 했으나 `event.role`은 역할 목록을 받음. "역할 무관 active"를 표현하는 가드 형태 미정. | `event.role` 인자 없이 active만 검사하는 별도 미들웨어(`event.member`) 또는 `event.role:*` 와일드카드 도입. |
| OI-5 | **`requester` 채널 인가 — 신고 이력** | SPEC-05a는 "그 행사 신고 이력 보유"로 판정. 신고 직후 첫 구독 시 race(신고 커밋 전 구독) 가능. | 신고자 본인 판정은 `auth id == userId` + 행사 active 참가로 충분히 완화 가능. 신고 이력 조건 필수화 여부 결정. |
| OI-6 | **멱등 전이 처리** | 동일 상태 재전송(`accepted→accepted`) 허용/거부 미정. | 기본 거부(422)로 명세했으나 네트워크 재시도 고려 시 멱등 허용이 안전. 결정 대기. |
| OI-7 | **위치이력 보존기간** | 07/09는 행사 종료 후 자동 파기 "필요"라고만 언급, 기간·방식 미정. 개인정보 영향. | 보존기간(예: 종료 후 30일) + 자동 파기 잡 + tracks 리포트 만료 정책 확정 필요. 보안/법무 검토 대상. |
| OI-8 | **`requests.assigned_rescuer_id` 잔존** | dispatch 도입 후 `requests.assigned_rescuer_id`·`responded_at` 의미 중복. | dispatch로 일원화 후 레거시 컬럼 deprecate 시점·마이그레이션 계획 필요(현재 유지). |
