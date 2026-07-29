# 03. 데이터 모델

기존 테이블: `users`, `projects`(행사), `requests`(신고). 신규/변경분만 기술한다.

## ER 개요

```
projects(행사) 1 ─┬─ N event_participants(행사참가/역할) ─ N ─ 1 users
                  ├─ N location_pings(위치) ──────────────── N ─ 1 users
                  └─ N requests(신고) 1 ─ N dispatches(지령) ─ N ─ 1 users(구급대원)
```
> 한 신고는 거절·무응답 시 재지령되므로 `requests 1 ─ N dispatches`(한 시점에 활성 지령은 1건). `dispatches`는 `project_id`를 직접 보유한다.

## 변경: `projects` (행사)

```php
Schema::table('projects', function (Blueprint $table) {
    $table->string('join_code', 12)->nullable()->unique()->after('slug'); // 행사 입장 코드
});
```
- `Project::booted()`에 `join_code` 자동발급(중복 회피) — 기존 slug 생성 로직 옆에 추가.
- `Project`에 관계 추가: `participants()` (hasMany EventParticipant), `locationPings()` (hasMany), `dispatches()` (hasMany — `dispatches.project_id` 직결).

## 신규: `event_participants` (행사별 역할/소속)

```php
Schema::create('event_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role');                  // App\Enums\EventRole
    $table->string('status')->default('active'); // pending/active/left
    $table->boolean('sharing_location')->default(false); // 위치공유 on/off
    $table->timestamp('joined_at')->nullable();
    $table->timestamp('last_seen_at')->nullable(); // 최근 ping 시각(온라인 판정)
    $table->timestamps();
    $table->unique(['project_id', 'user_id']);
});
```
- 모델 `App\Models\EventParticipant` — `role`/`status`를 `EventRole`/enum 캐스팅.
- 온라인 판정: `last_seen_at`이 N초 이내면 online. 관제 지도 표시 대상.

## 신규: `location_pings` (실시간 위치 이력)

```php
Schema::create('location_pings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->unsignedSmallInteger('accuracy')->nullable();  // m
    $table->unsignedSmallInteger('heading')->nullable();   // 0-359
    $table->unsignedSmallInteger('speed')->nullable();     // m/s
    $table->timestamp('recorded_at');
    // timestamps() 생략 — append-only 이력이라 recorded_at 하나로 충분
    $table->index(['project_id', 'recorded_at']);
    $table->index(['user_id', 'recorded_at']);
});
```

> **현재 위치 vs 이력 분리 전략**: `location_pings`는 append-only 이력(행사 종료 후 동선 다운로드용). 실시간 관제 지도는 **이력 전체를 읽지 않고**, `event_participants.last_lat/last_lng`(아래)나 Reverb 메시지의 최신값만 사용. 고빈도 쓰기이므로 ping은 비동기 큐로 적재.

`event_participants`에 최신 위치 캐시 컬럼 추가(관제 초기 로드 1쿼리용):
```php
$table->decimal('last_lat', 10, 8)->nullable();
$table->decimal('last_lng', 11, 8)->nullable();
```

## 신규: `dispatches` (지령)

```php
Schema::create('dispatches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('users');   // 발령자(controller)
    $table->foreignId('paramedic_id')->constrained('users');  // 수령 구급대원
    $table->string('status')->default('assigned'); // App\Enums\DispatchStatus
    $table->text('note')->nullable();              // 지령 메모
    $table->timestamp('assigned_at')->useCurrent();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('en_route_at')->nullable();
    $table->timestamp('arrived_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamps();
    $table->index(['project_id', 'status']);
});
```

### `App\Enums\DispatchStatus`

```
assigned(배정) → accepted(수락) → en_route(출동) → arrived(도착) → completed(완료)
                         └→ rejected(거절)   [assigned/accepted 단계에서만]
```
- 라벨/뱃지/다음전이 헬퍼는 `RequestStatus` 패턴 그대로.
- 전이 검증은 `DispatchService`에서. 잘못된 전이는 도메인 예외.
- 지령 상태 변화는 연결된 `requests.status`와 동기화: `accepted/en_route/arrived` → request `in_progress`, `completed` → request `completed`.

## 변경: `requests` (신고)

```php
Schema::table('requests', function (Blueprint $table) {
    $table->string('type')->default('other')->after('description'); // App\Enums\RequestType
});
```

### `App\Enums\RequestType` (신규 — 사고/고장/기타/긴급)

```php
enum RequestType: string {
    case ACCIDENT  = 'accident';   // 사고  (빨강)
    case BREAKDOWN = 'breakdown';  // 고장  (주황)
    case OTHER     = 'other';      // 기타  (회색)
    case EMERGENCY = 'emergency';  // 긴급전화
    public function label(): string;
    public function defaultPriority(): RequestPriority; // 사고→HIGH, 긴급→CRITICAL 등
    public function markerIcon(): string;
}
```
- 기존 `priority`(low/medium/high/critical)는 **유지**하되, 신고 시 `type`에서 기본 우선순위 자동 매핑. 상황실이 수동 상향 가능.
- 마이그레이션 시 기존 행: `description` 텍스트로 사고/고장/기타 추정 매핑하는 일회성 데이터 보정 스크립트(선택).

### 스냅샷 불변성 (요구 8번)

신고 생성 시 `latitude/longitude/address`는 **그 순간값으로 고정**되며 이후 위치 ping과 독립. 코드상 이미 그러함(`RequestService::createRequest`는 입력 좌표만 저장). 위치추적 테이블(`location_pings`)과 물리적으로 분리되어 있으므로 추가 보호장치 불필요 — 단 `requests`의 좌표는 **갱신 API에서 수정 불가**하도록 `RequestService::updateRequest`에서 `latitude/longitude` 화이트리스트 제외.

## 신규 모델 요약

| 모델 | 테이블 | 핵심 관계 |
|------|--------|-----------|
| `EventParticipant` | event_participants | belongsTo project, user |
| `LocationPing` | location_pings | belongsTo project, user |
| `Dispatch` | dispatches | belongsTo request, project, assignedBy(user), paramedic(user) |

## 인덱스/성능

- `location_pings`는 고빈도 → 파티셔닝/주기적 아카이브 고려. 행사 종료 후 동선만 필요하면 `recorded_at` 기준 콜드 스토리지 이관.
- 관제 초기 로드: `event_participants` 1쿼리로 전 인원 최신위치(`last_lat/last_lng`) + 역할 + online 여부.
- `dispatches(project_id, status)` 인덱스로 출동 현황 보드 집계.

## 마이그레이션 순서

1. `add_join_code_to_projects`
2. `create_event_participants`
3. `create_location_pings`
4. `add_request_type_to_requests`
5. `create_dispatches`
