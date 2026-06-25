# impl-tasks — 실시간 위치·지령 관제 (BE/FE 구현 백로그)

> 풀스택(kim-balsu) 실행 단위. **설계는 다시 열지 않는다.** 모든 태스크는
> [`architecture-spec.md`](./architecture-spec.md)의 계약(SPEC-01~07)과 ADR 0001~0004,
> [`00-master-plan.md`](./00-master-plan.md)의 lane/의존을 그대로 코드 태스크로 변환한 것이다.
> 스펙과 어긋나는 지점은 임의로 바꾸지 않고 본문 끝 **OPEN**에 모았다(스펙의 OPEN ISSUES와 연동).
>
> - 번호: `BE-x.x` / `FE-x.x`, `x` = Phase. master-plan의 lane 번호와 1:1 정렬.
> - 규모: **S** ≈ 1주 미만 · **M** ≈ 1~2주 · **L** ≈ 2주+ (상대값).
> - 테스트: 전부 컨테이너 안 `docker exec gps119_app-app-1 php artisan test --filter=...` 전제.
> - 기존 패턴 계승: 로직은 서비스레이어, 응답은 `response()->success/error` 매크로, 도메인 문자열·색상은 Enum 헬퍼,
>   Vue는 페이지별 마운트(단 관제 SPA만 Vite 번들 예외 — 07/master-plan).

---

## Phase 0 — 실시간 기반 (M0)

> 크리티컬 패스의 머리. **OPS-0.1(Reverb/도커 WS) 선행** 후 BE-0.1 → FE-0.1 직렬.
> Phase 0 태스크는 신규 도메인을 만들지 않고 **기존 `RequestCreated` 한 건을 실시간으로 흐르게** 하는 수직 슬라이스.

### BE-0.1 — withBroadcasting 배선 + channels.php 스캐폴드 + RequestCreated 채널 교체
- **건드릴 파일**
  - 수정: `src/bootstrap/app.php` (`->withBroadcasting(channels: __DIR__.'/../routes/channels.php')`)
  - 신규: `src/routes/channels.php` (Phase0은 `event.{projectId}.control` 인가만 스캐폴드 — controller/admin)
  - 수정: `src/app/Events/RequestCreated.php` (`broadcastOn` 교체, `broadcastWith` 페이로드 SPEC-05b로 정정)
  - 수정: `src/app/Listeners/NotifyRescuers.php` (수신자 산정: 전 rescuer → 해당 행사 controller/admin)
  - 수정(설정 전제): `src/config/broadcasting.php`·`src/.env` (`BROADCAST_CONNECTION=reverb`) — OPS-0.1과 경계, 코드측만.
- **작업 요약**: ADR-0001대로 broadcasting 라우트 등록. `RequestCreated::broadcastOn()`을
  `new Channel('requests') + PrivateChannel('rescuers')` → **`PrivateChannel("event.{$this->request->project_id}.control")` 단일**로 교체(ADR-0004, SPEC-05b 정정6).
  `broadcastWith()`를 SPEC-05b 키(`request_id, project_id, type, priority, latitude, longitude, address, requester:{id,name,phone}, created_at`)로 맞춤.
  `channels.php`에 `event.{projectId}.control` private 인가(`eventRoleIn==CONTROLLER OR admin`)를 Phase0 한정으로 스캐폴드(나머지 채널은 BE-1.2/2.2/3.3에서 추가).
- **수용 기준**
  - [ ] `php artisan tinker`/테스트에서 `RequestCreated` dispatch 시 control 채널로만 나간다(`requests`/`rescuers` 미사용).
  - [ ] `broadcastWith()` 키가 SPEC-05b와 일치, 연락처는 control 페이로드에만.
  - [ ] control 채널 인가: controller/admin 통과, 그 외 403.
  - [ ] `NotifyRescuers`의 Discord/로그 부수효과는 유지하되 수신자는 해당 행사 controller/admin로 축소.
- **테스트**: `--filter=RequestCreatedBroadcastTest` — (1) 이벤트가 `event.{id}.control` 채널을 반환(`Event::fake`+`assertDispatched` 또는 `broadcastOn` 단위검증), (2) 페이로드 키 단언, (3) `Broadcast::channel` 인가 콜백: controller 통과 / paramedic·일반 거부.
- **의존**: SPEC-05a/05b, ADR-0001·0004. OPEN: OI-1(비행사 신고는 `project_id` null → broadcastOn 대상 없음).
- **규모**: S · **Phase 0**
- **구현 메모(완료)**: OI-1 확정 반영 — `project_id` 있으면 `PrivateChannel("event.{id}.control")`, 없으면 `PrivateChannel("requests.global")`. `channels.php`는 **Phase 0 잠정 규칙**으로 두 채널 모두 `hasRole('admin'|'rescuer')` 통과. **TODO(Phase 1, BE-1.2)**: `event.{id}.control` 인가를 `EventParticipant` active + `EventRole::CONTROLLER`(시스템 admin OR) 기반으로 강화. 테스트(`RequestCreatedBroadcastTest`/`BroadcastAuthTest`)는 sqlite 인메모리·`tests/bootstrap.php`(컨테이너 OS env override)·null 브로드캐스터 전제로 작성.

### FE-0.1 — Echo 클라이언트 초기화 + 폴링 폴백 + 관제 토스트 PoC
- **건드릴 파일**
  - 수정: `src/resources/js/bootstrap.js` (Echo + pusher-js 초기화, `window.Echo`)
  - 신규: `src/resources/js/echo.js` (Echo 팩토리·재연결·폴백 헬퍼 분리)
  - 신규: `src/public/js/components/realtimeFallback.js` (WS 실패 시 10~15초 HTTP 폴링 degrade)
  - 수정: `src/resources/views/admin/requests/index.blade.php` (control 구독 + 신규 신고 토스트 PoC)
  - 수정(전제): `src/package.json` (`laravel-echo`, `pusher-js`) — OPS-0.1 npm 설치와 경계.
- **작업 요약**: ADR-0001대로 Echo(Reverb 브로드캐스터) 초기화, `/broadcasting/auth` 인가 통과 확인.
  관리자 신고 목록에서 `event.{id}.control` 구독 → `request.created` 수신 시 토스트. WS 끊기면 폴링으로 자동 degrade(R5).
- **수용 기준**
  - [ ] 관리자가 화면을 열면 control 채널 구독에 성공한다(인가 통과).
  - [ ] 새 신고 생성 시 디스코드뿐 아니라 관제 화면에 실시간 토스트가 뜬다(M0 부록 산출물).
  - [ ] WS 연결 실패를 강제하면 HTTP 폴링 폴백으로 목록이 갱신된다.
- **테스트(php): 직접 단위테스트 대상 아님(JS).** `--filter=BroadcastAuthTest`로 `/broadcasting/auth` 인가 응답(200/403)만 백엔드에서 검증. 프론트는 수동 QA(브라우저)로 토스트/폴백 확인 후 보고.
- **의존**: BE-0.1, OPS-0.1(npm/Reverb 데몬). DS: 토스트 비주얼은 PoC 수준(DS-0 없음).
- **규모**: M · **Phase 0**

---

## Phase 1 — 행사 입장·역할 (M1)

> BE-1.1(스키마)은 M0와 **병렬 선행 가능**(Reverb 비의존). BE-1.2 가드부터 BE-0.1 채널 패턴 사용.

### BE-1.1 — projects.join_code + event_participants 마이그레이션 + EventRole/ParticipantStatus enum + 모델
- **건드릴 파일**
  - 신규: `src/database/migrations/..._add_join_code_to_projects.php` (SPEC-01 #1)
  - 신규: `src/database/migrations/..._create_event_participants.php` (SPEC-01 #3, `last_lat/lng` 포함·인덱스 `(project_id,role,status)`)
  - 신규: `src/app/Enums/EventRole.php` (SPEC-02a)
  - 신규: `src/app/Enums/ParticipantStatus.php` (SPEC-02b)
  - 신규: `src/app/Models/EventParticipant.php` (SPEC-03a)
  - 수정: `src/app/Models/Project.php` (`$fillable += join_code`, `booted()` join_code 발급, `participants()/locationPings()/dispatches()` 관계)
  - 수정: `src/app/Models/User.php` (`eventParticipations()`, `eventRoleIn(Project): ?EventRole` 단일 진입점)
  - 신규(테스트용): `src/database/factories/EventParticipantFactory.php`
- **작업 요약**: SPEC-01/02/03 그대로. join_code는 6자리 대문자 영숫자(혼동문자 제외), 충돌 시 재생성 루프, slug 발급 직후 `creating` 훅. `EventRole`은 `label/markerColor/badgeClasses/canReceiveDispatch/canDispatch/canViewControl` 헬퍼 구현(기존 `RequestStatus` 패턴). `User::eventRoleIn()`은 active 참가만 반환(채널/미들웨어 공용).
- **수용 기준**
  - [ ] 마이그레이션이 SPEC-01 컬럼/제약/인덱스와 정확히 일치(`(project_id,user_id)` unique, `(project_id,role,status)` index).
  - [ ] `Project` 생성 시 join_code 자동 발급·유니크, 기존 행 영향 없음(nullable).
  - [ ] `EventRole::canReceiveDispatch()`는 PARAMEDIC/VOLUNTEER_MEDIC만 true, `canDispatch/canViewControl`은 CONTROLLER만 true.
  - [ ] `User::eventRoleIn($p)`가 active 참가만 역할 반환, 비참가/pending는 null.
- **테스트**: `--filter=EventParticipantModelTest` — (1) join_code 발급·유니크 충돌 재생성, (2) enum 헬퍼 boolean 매트릭스, (3) `eventRoleIn` active/pending/미참가 분기, (4) 캐스팅(role/status enum) 라운드트립.
- **의존**: SPEC-01·02·03, ADR-0002. OPEN: OI-3(SoftDelete vs cascadeOnDelete) — cascade 정의만 두고 정리정책 미구현.
- **규모**: M · **Phase 1**

### BE-1.2 — 입장 API + EnsureEventRole 미들웨어 + EventParticipantService(joinByCode/assignRole) + locations/control 채널 인가 보강
- **건드릴 파일**
  - 신규: `src/app/Http/Middleware/EnsureEventRole.php` (SPEC-06a)
  - 수정: `src/bootstrap/app.php` (`$middleware->alias(['event.role' => ...])` `admin` 옆)
  - 신규: `src/app/Services/EventParticipantService.php` (SPEC-04b: joinByCode/assignRole/setSharing/rosterForControl)
  - 신규: `src/app/Http/Controllers/Api/EventApiController.php` (show/join/me)
  - 수정: `src/routes/api.php` (`/api/events/{joinCode}`, `/join`, `/api/events/{id}/me`)
  - 수정: `src/routes/channels.php` (`requester.{userId}` 인가 추가 — 신고자 본인; locations/control은 BE-2.2와 분담)
- **작업 요약**: SPEC-06b 행사 입장 3엔드포인트. `EnsureEventRole`은 행사 id 해석 3경로(project 파라미터/dispatch 바인딩/본인소유 미적용)와 `User::eventRoleIn()` 또는 admin OR 판정(SPEC-06a). 비활성 행사 join은 422(Project `isActive()`). 자가선택 participant는 즉시 active, 권한역할은 pending 가능(SPEC-04b).
- **수용 기준**
  - [ ] `GET /api/events/{joinCode}`가 민감정보 없는 미리보기(`id,name,start_date,end_date,is_active`)만 반환.
  - [ ] `POST /join`이 미참가 시 participant=active 생성, 재호출 멱등(중복 row 없음, unique).
  - [ ] 비활성 행사 join → 422 + 안내.
  - [ ] `event.role:controller` 가드가 비-controller 403, controller/admin 통과.
  - [ ] 전화번호 없는 사용자 join 시 기존 `errors.require-phone` 정책 계승(05) — 라우트/응답 레벨 처리.
- **테스트**: `--filter=EventJoinApiTest` — (1) show 미리보기 키, (2) join 생성+멱등, (3) 비활성 행사 422, (4) `EnsureEventRoleTest`: controller/admin 통과·일반 403·pending 차단, (5) joinCode 미존재 404/422.
- **의존**: BE-1.1, BE-0.1(채널 패턴), SPEC-06a/06b. OPEN: Q1(사전명단 데이터소스 미정 → 자가선택+수동만 구현), OI-4(active-only 가드 시그니처).
- **규모**: M · **Phase 1**

### FE-1.1 — 참가자 앱 입장 플로우(코드/QR 입력 → 로그인 복귀 → 역할 표시)
- **건드릴 파일**
  - 신규: `src/resources/views/event/join.blade.php` (코드 입력/QR 진입 화면)
  - 신규: `src/public/js/components/EventJoinApp.js` (Vue 페이지 마운트, 기존 per-page 패턴)
  - 수정: `src/routes/web.php` (`/events/join`, `/events/join/{joinCode}` 진입 — QR 딥링크)
  - 수정: 소셜/폼 로그인 복귀 경로(기존 `SocialController` 재사용, intended URL 보존)
- **작업 요약**: 05 입장 플로우. 코드 입력 또는 QR(`/events/join/{joinCode}`) → `GET /api/events/{joinCode}` 미리보기 → 미로그인 시 로그인 후 복귀 → `POST /join` → 역할/승인대기(pending) 표시. 전화번호 없으면 입력 유도.
- **수용 기준**
  - [ ] 코드/QR로 행사 입장, 역할이 화면에 표시된다(M1 산출물).
  - [ ] 미로그인 진입 시 로그인 후 같은 입장 지점으로 복귀한다.
  - [ ] 권한역할 pending은 "승인대기" 안내, participant는 즉시 활동.
- **테스트(php)**: `--filter=EventJoinWebTest` — 입장 라우트가 미인증 시 로그인 리다이렉트, 인증 후 join 호출 경로 정상(웹 레벨). UI 상호작용은 수동 QA.
- **의존**: BE-1.2, FE-0.1, DS-1.1(입장/역할 표시 UX·역할 색상 토큰).
- **규모**: M · **Phase 1**

---

## Phase 2 — 실시간 위치 관제 지도 (M2)

### BE-2.1 — location_pings 마이그레이션 + LocationService + 위치 ping API(큐 적재) + sharing/participants 엔드포인트
- **건드릴 파일**
  - 신규: `src/database/migrations/..._create_location_pings.php` (SPEC-01 #4, append-only, `timestamps` 없음)
  - 신규: `src/app/Models/LocationPing.php` (SPEC-03b, `$timestamps=false`)
  - 신규: `src/app/Services/LocationService.php` (SPEC-04c: recordPing — 캐시갱신+큐 INSERT+브로드캐스트 트리거)
  - 신규: `src/app/Http/Requests/StoreLocationPingRequest.php` (lat/lng/accuracy/heading/speed/recorded_at 검증)
  - 신규: `src/app/Http/Controllers/Api/LocationApiController.php` (store/participants/sharing)
  - 신규: `src/app/Jobs/PersistLocationPing.php` (location_pings INSERT 큐 잡)
  - 수정: `src/routes/api.php` (`POST /api/events/{id}/location` `throttle:60,1` 초당1, `GET /participants` `event.role:controller`, `PATCH /sharing`)
- **작업 요약**: SPEC-04c/06b. ping 수신 시 (1) `event_participants.last_lat/lng/last_seen_at` 즉시 갱신, (2) `location_pings` INSERT는 큐(PersistLocationPing) 적재, (3) `ParticipantLocationUpdated` 발행(BE-2.2). `sharing_location=false`면 캐시갱신/브로드캐스트 스킵(05). `recorded_at` 미래 거부·lat/lng 범위는 FormRequest 1차·서비스 2차. rate-limit `throttle` 라우트 1차.
- **수용 기준**
  - [ ] ping 1건 수신 시 `last_lat/lng/last_seen_at` 갱신, `location_pings` 큐 잡 1건 dispatch.
  - [ ] 미래 `recorded_at`·범위초과 좌표 422.
  - [ ] `sharing_location=false`면 캐시/브로드캐스트 스킵(잡도 생략).
  - [ ] `GET /participants`는 controller만, `[{user_id,name,role,last_lat,last_lng,last_seen_at,online}]` 반환(rosterForControl 1쿼리).
- **테스트**: `--filter=LocationPingApiTest` — (1) 정상 ping → 캐시 갱신+`Queue::assertPushed(PersistLocationPing)`, (2) 미래/범위초과 422, (3) sharing off 스킵, (4) throttle 초과 429, (5) participants controller-only 403.
- **의존**: BE-1.1, OPS-0.2(큐 워커). SPEC-04c/06b. OPEN: OI-4(active-only 가드), OI-7(보존기간 미정 → 파기잡은 OPS-4.1).
- **규모**: M · **Phase 2**

### BE-2.2 — ParticipantLocationUpdated 이벤트 + locations/control 채널 인가
- **건드릴 파일**
  - 신규: `src/app/Events/ParticipantLocationUpdated.php` (`ShouldBroadcast`, `broadcastAs=participant.location`)
  - 수정: `src/routes/channels.php` (`event.{projectId}.locations` presence + control에도 동일 이벤트 수신)
  - 수정: `src/app/Services/LocationService.php` (recordPing 말미에서 이벤트 발행 연결)
- **작업 요약**: SPEC-05a/05b. `broadcastOn()`이 두 채널 반환(`locations` presence + `control` private), 페이로드 `{user_id, role, latitude, longitude, accuracy, recorded_at}`(연락처 없음, 두 채널 동일 안전). presence 인가 payload는 `{user_id, role}`만. locations 인가는 해당 행사 active 참가자 전원.
- **수용 기준**
  - [ ] `ParticipantLocationUpdated`가 locations+control 두 채널 반환, 페이로드에 연락처 없음.
  - [ ] locations presence 인가: active 참가자 통과, 비참가 403, payload는 `{user_id,role}`.
  - [ ] control은 controller/admin만(BE-0.1 인가 재사용).
- **테스트**: `--filter=ParticipantLocationEventTest` — (1) broadcastOn 2채널·broadcastWith 키 단언(연락처 부재), (2) locations presence 인가 콜백 active/비active, (3) control 인가 재확인.
- **의존**: BE-2.1, BE-0.1. SPEC-05a/05b.
- **규모**: S · **Phase 2**

### FE-2.1 — 웹 관제 SPA v1 (Vite 번들 1개): 카카오맵 + 전 인원 마커 풀 + 역할 필터 + online
- **건드릴 파일**
  - 신규: `src/resources/js/control/main.js` (관제 SPA 엔트리 — **Vite 번들 예외**, vite.config input 추가)
  - 신규: `src/resources/js/control/ControlApp.vue` 외 컴포넌트(MapPane/RoleFilter/RequestList/DispatchBoard 스텁)
  - 신규: `src/resources/js/control/markerPool.js` (마커 풀 재사용·좌표만 이동)
  - 수정: `src/vite.config.js` (`input`에 `resources/js/control/main.js` 추가)
  - 신규: `src/resources/views/admin/control.blade.php` (`#control-app` 마운트, controller/admin 가드)
  - 수정: `src/routes/web.php` (`/admin/control` 라우트, `admin` 또는 event.role 가드)
- **작업 요약**: 07 레이아웃. 초기 `GET /api/events/{id}/participants`로 마커 일괄 생성(이력 미조회), Echo `participant.location`으로 **해당 마커 좌표만 이동**(전체 리렌더 금지, markerPool 재사용). 역할 필터 체크박스로 마커 토글, `last_seen_at` 타임아웃 online/offline. 대규모 시 클러스터링(DS-2.1). 관제만 Vite 번들(07/master-plan 예외).
- **수용 기준**
  - [ ] 한 지도에서 전 인원이 역할 색상(EventRole::markerColor) 마커로 표시·실시간 이동.
  - [ ] 역할 필터가 즉시 마커 표시를 토글.
  - [ ] 마커 갱신이 좌표 이동만(리렌더/재생성 없음, 풀 재사용).
  - [ ] online/offline이 `last_seen_at` 기준으로 반영.
- **테스트(php)**: `--filter=ControlPageAccessTest` — `/admin/control` 접근 가드(controller/admin 200, 그 외 403/리다이렉트). 지도/마커 동작은 수동 QA(브라우저)로 검증·보고.
- **의존**: BE-2.2, FE-0.1, DS-2.1. master-plan 최대 병목(L).
- **규모**: L · **Phase 2**

### FE-2.2 — 참가자 앱 watchPosition 적응형 공유 + 토글 + 로컬 큐 배칭
- **건드릴 파일**
  - 신규: `src/public/js/components/locationShare.js` (watchPosition 적응형 주기·로컬큐·재전송)
  - 신규: `src/public/js/components/SharingToggle.js` (공유 on/off, `PATCH /sharing`)
  - 수정: 입장 후 화면(FE-1.1 `event/*`)에 공유 모듈 자동 시작 연결
- **작업 요약**: 05 위치공유. 입장 후 `navigator.geolocation.watchPosition` 적응형 주기 → `POST /api/events/{id}/location`. 약전계/터널 대비 로컬 큐 배칭+`recorded_at` 보존, 복구 시 재전송(R4). 상단 공유 토글(끄면 sharing_location=false, 신고 시 일시 강제 on). mapHelpers 재사용.
- **수용 기준**
  - [ ] 입장 즉시 위치 공유 자동 시작, 관제 마커에 반영.
  - [ ] 공유 토글 off 시 ping 중단·`sharing_location=false`.
  - [ ] 오프라인 구간 좌표가 로컬 큐에 보존되고 복구 시 `recorded_at` 순서대로 재전송.
- **테스트(php)**: `--filter=SharingToggleApiTest` — `PATCH /sharing` boolean 반영. watchPosition/배칭은 수동 QA.
- **의존**: BE-2.1, FE-1.1.
- **규모**: M · **Phase 2**

---

## Phase 3 — 신고 고도화·지령 상태머신 (M3) ★ 핵심 가치

### BE-3.1 — RequestType enum + requests.type 마이그레이션 + store 확장 + 좌표 불변
- **건드릴 파일**
  - 신규: `src/database/migrations/..._add_request_type_to_requests.php` (SPEC-01 #2, `type` default `other`)
  - 신규: `src/app/Enums/RequestType.php` (SPEC-02c: label/defaultPriority/markerIcon/badgeClasses)
  - 수정: `src/app/Models/Request.php` (`$fillable += type`, `$casts += type`, `dispatches()/activeDispatch()` 관계 — BE-3.2와 분담)
  - 수정: `src/app/Http/Controllers/Api/RequestApiController.php` (store에 `type` `Rule::enum`, project_id 행사 필수)
  - 수정: `src/app/Services/RequestService.php` (createRequest: priority 미지정 시 `type->defaultPriority()`; updateRequest: 좌표/주소 화이트리스트 제외)
- **작업 요약**: SPEC-02c/04d/07a. `defaultPriority` 매핑(EMERGENCY→CRITICAL, ACCIDENT→HIGH, BREAKDOWN→MEDIUM, OTHER→LOW). `updateRequest`는 `latitude/longitude/address`를 화이트리스트에서 **명시 제외**(좌표 스냅샷 불변, R6). 기존 행 보정은 마이그레이션 본문 금지(별도 일회성 커맨드, 미수행 시 default other).
- **수용 기준**
  - [ ] `POST /api/requests`가 `type` enum 검증, 미지정 priority는 type 기본값 자동.
  - [ ] updateRequest에 좌표/주소가 와도 무시/거부(불변).
  - [ ] 행사 신고 시 project_id 필수(`exists:projects,id`).
- **테스트**: `--filter=RequestTypeTest` — (1) type별 defaultPriority 매핑, (2) priority 명시 우선, (3) update 시 좌표 변경 차단(생성값 유지), (4) type enum 검증 실패 422.
- **의존**: BE-1.1. SPEC-02c/04d/07a, ADR-0003. OPEN: OI-8(`assigned_rescuer_id` 잔존).
- **규모**: S · **Phase 3**

### BE-3.2 — dispatches 마이그레이션 + Dispatch 모델 + DispatchStatus enum + DispatchService + DispatchApiController + 라우트
- **건드릴 파일**
  - 신규: `src/database/migrations/..._create_dispatches.php` (SPEC-01 #5, `reject_reason` 포함·인덱스 3종)
  - 신규: `src/app/Enums/DispatchStatus.php` (SPEC-02d: label/badge/dot/isActive/isTerminal/allowedTransitions/syncsRequestStatus)
  - 신규: `src/app/Models/Dispatch.php` (SPEC-03c, booted에서 이벤트 미발행)
  - 신규: `src/app/Services/DispatchService.php` (SPEC-04a: assign/transition/reassign/boardForProject/myDispatches)
  - 신규: `src/app/Exceptions/DispatchTransitionException.php` (도메인 예외 → 422)
  - 신규: `src/app/Http/Controllers/Api/DispatchApiController.php` (dispatch/updateStatus/mine/board)
  - 수정: `src/routes/api.php` (`POST /requests/{id}/dispatch`, `PATCH /dispatches/{id}/status`, `GET /dispatches/mine`, `GET /events/{id}/dispatches`)
  - 신규(테스트용): `src/database/factories/DispatchFactory.php`
- **작업 요약**: SPEC-02d/04a/06b. `allowedTransitions()`는 전이표 그대로, `syncsRequestStatus()`로 신고 status 동기화를 **DispatchService 단일화**(R9 드리프트 방지). 불변식: assign 권한(controller/admin), 대상 적격(canReceiveDispatch+active), **활성 지령 1건**, 행사 스코프 일치, reject는 assigned/accepted 단계만+reason 필수, 전이 부수효과 1트랜잭션. 전이 위반 → 422 명확 메시지.
- **수용 기준**
  - [ ] assign: controller/admin만, 대상이 PARAMEDIC/VOLUNTEER_MEDIC active일 때만, 동일 신고 활성지령 있으면 거부.
  - [ ] transition: 전이표 위반 422, reject는 assigned/accepted에서만+reason 필수, en_route/arrived에서 reject 불가.
  - [ ] 동기화: accepted→requests in_progress(responded_at 1회), completed→completed(completed_at), rejected→requests 무변경.
  - [ ] `GET /dispatches/mine` 본인 소유만, `GET /events/{id}/dispatches` boardForProject 카운트+active+history.
- **테스트**: `--filter=DispatchServiceTest` 및 `--filter=DispatchApiTest` — (1) 전이표 전 케이스(OK/422 매트릭스), (2) reject 단계·reason 강제, (3) requests status 동기화 4케이스, (4) 활성지령 1건 불변식, (5) 권한/적격/스코프 거부, (6) board 집계 정확.
- **의존**: BE-3.1, BE-2.2. SPEC-02d/04a/06b, ADR-0003. OPEN: OI-2(활성지령 동시성 락), OI-6(멱등 전이).
- **규모**: L · **Phase 3** (BE 최대 병목)

### BE-3.3 — DispatchAssigned/DispatchStatusUpdated/RequestStatusUpdated 이벤트 + 채널 분리(ADR-0004) + reassign 경로
- **건드릴 파일**
  - 신규: `src/app/Events/DispatchAssigned.php` (`dispatch.assigned`, `event.{id}.dispatch.{userId}`)
  - 신규: `src/app/Events/DispatchStatusUpdated.php` (`dispatch.updated`, `event.{id}.control`)
  - 신규: `src/app/Events/RequestStatusUpdated.php` (`request.status.updated`, `event.{id}.requester.{userId}`)
  - 수정: `src/routes/channels.php` (`dispatch.{userId}`·`requester.{userId}` 인가 — SPEC-05a)
  - 수정: `src/app/Services/DispatchService.php` (assign/transition에서 이벤트 명시 발행 — 모델 훅 아님)
- **작업 요약**: SPEC-05a/05b. 이벤트 발행은 **서비스가 명시적으로**(SPEC-03c). `dispatch.{userId}` 인가: 본인 id AND active AND canReceiveDispatch. `requester.{userId}` 인가: 본인 id AND 행사 신고 이력(OI-5 완화 검토). 페이로드 ADR-0004: 연락처는 control·개인 dispatch에만. completed/accepted 시 신고자에게 `RequestStatusUpdated`(담당자 이름·연락처 포함).
- **수용 기준**
  - [ ] `DispatchAssigned`는 본인 dispatch 채널로만, 신고자 연락처 포함.
  - [ ] `DispatchStatusUpdated`는 control로만, 연락처 미포함(`dispatch_id,request_id,status,paramedic_id,occurred_at`).
  - [ ] 구급대원은 control 미구독(인가 거부), 배정건 연락처는 dispatch 채널로만.
  - [ ] reassign이 terminal(rejected)/회수 시에만 새 row 생성+DispatchAssigned 발행.
- **테스트**: `--filter=DispatchEventChannelTest` — (1) 세 이벤트 broadcastOn/As/With 키, (2) dispatch 채널 인가 본인/타인/비적격, (3) requester 채널 인가, (4) control 채널에 paramedic 거부(R8), (5) reassign 흐름.
- **의존**: BE-3.2. SPEC-05a/05b, ADR-0004. OPEN: OI-5(requester 신고이력 조건).
- **규모**: M · **Phase 3**

### FE-3.1 — 신고 버튼(type) + "이 위치가 맞습니까?" 주소확인 모달(기존 confirm 교체)
- **건드릴 파일**
  - 수정: `src/public/js/components/RequestMapApp.js` (**146행 `confirm('위치공유를 하시겠습니까?')` → 주소+지도 미리보기 모달**, `description` free-text → `type` 전송)
  - 신규: `src/public/js/components/AddressConfirmModal.js` (주소·지도 미리보기·보정)
  - 신규(긴급전화): `projects.settings` 기반 구조본부 `tel:` 딥링크 처리(05)
- **작업 요약**: 05. 사고/고장/기타/긴급전화 버튼이 `type`(RequestType) 정식 전송(기존 description 대체). 주소확인 모달이 역지오코딩 주소+지도 미리보기 표시, 지도 보정 가능. 긴급전화는 즉시 `tel:`. 신고 좌표는 모달 확정 시점 스냅샷.
- **수용 기준**
  - [ ] 사고/고장/기타 신고 시 "이 위치가 맞습니까?"에 **주소가 표시**된다(05 수용기준).
  - [ ] 버튼이 `type`을 전송하고 백엔드 priority 자동 매핑과 일치.
  - [ ] 긴급전화는 구조본부 번호로 `tel:` 연결.
  - [ ] 신고 좌표가 이후 이동과 무관하게 고정 저장(BE-3.1 불변과 연동).
- **테스트(php)**: `--filter=RequestStoreTypeTest`(BE-3.1과 공유) — type 전송 시 저장 검증. 모달 UI는 수동 QA.
- **의존**: BE-3.1, DS-3.1, FE-2.2.
- **규모**: M · **Phase 3**

### FE-3.2 — 구급대 앱: 지령 수신(알림음/진동) + 상태 전이 + 카카오내비(고정좌표)
- **건드릴 파일**
  - 신규: `src/resources/views/dispatch/index.blade.php` (구급대 앱 화면)
  - 신규: `src/public/js/components/DispatchApp.js` (Vue 페이지 마운트)
  - 신규: `src/public/js/components/dispatchNotify.js` (풀스크린 알림·알림음·진동)
  - 신규: `src/public/js/components/kakaoNavi.js` (`kakaonavi://` 딥링크 + 카카오맵 웹 폴백)
  - 수정: `src/routes/web.php` (`/dispatch` 라우트, paramedic 가드)
- **작업 요약**: 06. `event.{id}.dispatch.{userId}` 구독 → `dispatch.assigned` 시 풀스크린+알림음/진동. 수락→출동→도착→완료 버튼이 `PATCH /api/dispatches/{id}/status`(결과 상태값). "출동" 시 신고 **고정 좌표**로 카카오내비 딥링크(미설치 웹 폴백). 거절은 사유 필수 입력.
- **수용 기준**
  - [ ] 지령을 실시간(WS) 수신하고 알림이 울린다.
  - [ ] 수락→출동→도착→완료 전이가 동작, 잘못된 전이는 거부 메시지.
  - [ ] "출동"에서 카카오내비로 신고 고정좌표 길안내가 열린다(실시간 위치 아님).
  - [ ] 거절 시 사유 입력 후 재지령 대상이 된다.
- **테스트(php)**: BE-3.2/3.3 API 테스트로 전이/페이로드 검증. 알림음/진동/내비 딥링크는 수동 QA.
- **의존**: BE-3.3, DS-3.1.
- **규모**: L · **Phase 3** (핵심 가치 완성점)

### FE-3.3 — 관제: 지령 배정 UI(가용 대원 거리순) + 출동 현황 보드
- **건드릴 파일**
  - 신규: `src/resources/js/control/DispatchAssignPanel.vue` (신고 클릭 → 가용 대원 거리순 → 배정)
  - 신규: `src/resources/js/control/DispatchBoard.vue` (배정/출동/도착/완료 실시간 집계)
  - 수정: `src/resources/js/control/ControlApp.vue` (FE-2.1 SPA에 패널/보드 통합, `dispatch.updated` 구독)
- **작업 요약**: 07. 신고 핀/목록 클릭 → 가용 구급대원(role=paramedic/volunteer_medic, online, 거리순) 선택+메모 → `POST /api/requests/{id}/dispatch`. `dispatch.updated` 실시간으로 보드 카운트/타임라인 갱신, 완료 지령은 이력 이동(접수→도착→완료 처리시간 표기).
- **수용 기준**
  - [ ] 신고에서 구급대원을 골라 지령을 배정할 수 있다.
  - [ ] 가용 대원이 거리순 정렬(Q3 정렬 기준 결정 반영).
  - [ ] 출동 현황(배정/출동/도착/완료)이 실시간 집계된다.
- **테스트(php)**: `--filter=DispatchBoardApiTest`(BE-3.2 공유) — board 집계·가용대원 조회. UI는 수동 QA.
- **의존**: BE-3.3, FE-2.1. OPEN: Q3(가용성 정렬 기준 — 거리만 vs 지령보유수).
- **규모**: M · **Phase 3**

### FE-3.4 — 신고자: 상태 실시간 추적 + 담당자 전화 연결(tel:)
- **건드릴 파일**
  - 수정: `src/public/js/components/RequestShowApp.js` (`event.{id}.requester.{userId}` 구독, 상태 실시간 갱신)
  - 신규: `src/public/js/components/RequesterStatusTracker.js` (대기→진행중→완료 표시 + 담당자 카드)
  - 수정: `src/resources/views/request/show.blade.php` (담당자 이름 + `tel:` 버튼)
- **작업 요약**: 05. `request.status.updated` 구독 → 상태 실시간 갱신. 담당 구급대원 배정 시 담당자 이름+`tel:` 버튼, 배정 전에는 행사 상황실 대표번호. 신고 위치 카카오맵/내비(기존 show 확장).
- **수용 기준**
  - [ ] 신고 상태가 대기→진행중→완료로 실시간 갱신.
  - [ ] 담당 배정 시 담당자 전화 연결 버튼, 배정 전 상황실 번호 연결.
- **테스트(php)**: BE-3.3 `RequestStatusUpdated` 페이로드 테스트로 담당자 정보 검증. UI는 수동 QA.
- **의존**: BE-3.3, FE-3.1.
- **규모**: S · **Phase 3**

---

## Phase 4 — 마감·운영 (M4)

### BE-4.1 — 기록 다운로드 확장(신고/지령 CSV) + 동선 비동기 생성 API
- **건드릴 파일**
  - 신규: `src/app/Http/Controllers/Api/EventReportController.php` (requests.csv/dispatches.csv/tracks)
  - 신규: `src/app/Jobs/GenerateTrackReport.php` (동선 대용량 비동기 생성 → 다운로드 링크)
  - 수정: 기존 `ProjectController::exportCsv` 패턴 계승/확장
  - 수정: `src/routes/api.php` (3 리포트 라우트, `event.role:controller`)
- **작업 요약**: 07/SPEC-06b 리포트. 신고 CSV(유형·시각·위치·처리시간·담당), 지령 CSV(전이 타임라인), 동선은 location_pings → 큐 생성 후 링크. 보존기간 정책 적용(OI-7 미정 → 정책 결정 후 만료 처리).
- **수용 기준**
  - [ ] 행사 종료 후 신고·지령·동선 기록을 controller가 내려받을 수 있다.
  - [ ] 동선은 비동기 생성 후 다운로드 링크 응답.
- **테스트**: `--filter=EventReportTest` — (1) requests.csv/dispatches.csv controller-only 403, (2) CSV 헤더/행 검증, (3) tracks `Queue::assertPushed(GenerateTrackReport)`.
- **의존**: BE-3.2, BE-2.1. OPEN: OI-7(보존기간).
- **규모**: M · **Phase 4**

### FE-4.1 — PWA 마감(manifest/SW/온보딩) + 리포트 다운로드 UI
- **건드릴 파일**
  - 신규: `src/public/manifest.json` (설치형 매니페스트)
  - 신규: 서비스워커(오프라인 셸) — Vite PWA 플러그인 도입(`vite.config.js` 수정, `package.json` 의존 추가)
  - 신규: `src/public/js/components/pwaOnboarding.js` (홈 화면 추가·위치/알림 권한 온보딩)
  - 신규: `src/resources/js/control/ReportPanel.vue` (리포트 다운로드 UI)
- **작업 요약**: 05 PWA 요건. manifest+SW(오프라인 셸), 설치 유도, 위치/알림 권한 온보딩. 푸시는 초기 Reverb 인앱(백그라운드 FCM은 OPS-4.2 하이브리드). 관제에 리포트 다운로드 패널.
- **수용 기준**
  - [ ] 홈 화면 추가가 가능하고 오프라인 셸이 뜬다.
  - [ ] 위치·알림 권한 온보딩이 노출된다.
  - [ ] 관제에서 리포트를 다운로드할 수 있다.
- **테스트(php)**: `--filter=ReportDownloadAccessTest`(BE-4.1 공유). PWA 설치/SW는 브라우저 수동 QA.
- **의존**: FE-2.1, BE-4.1. OPS-4.2(하이브리드)는 OPS lane.
- **규모**: M · **Phase 4**

---

## 테스트 인프라 메모 (선행 정리)

- 현재 `src/tests/Feature/`에는 `ExampleTest`만 존재 → 신규 도메인 테스트 전에 **factory/seeder 정비 필요**:
  `ProjectFactory`(join_code 포함), `EventParticipantFactory`, `DispatchFactory`, `RolePermissionSeeder`(admin/user/rescuer)와
  EventRole 픽스처. BE-1.1/3.2 태스크에 factory 신규를 포함했다.
- 브로드캐스트 테스트는 `Event::fake()`/`Broadcast::channel` 인가 콜백 직접 호출 + `Queue::fake()`(큐 적재) 조합으로
  WS 데몬 없이 단위 검증(데몬 통합은 OPS lane QA).

---

## OPEN (스펙과 어긋남·미결 — 임의 변경 금지, 보고만)

architecture-spec.md의 OPEN ISSUES(OI-1~8)와 master-plan의 결정사항(Q1~7)을 구현 태스크 관점에서 재확인. **임의로 코드에서 해소하지 않는다.**

| ref | 막는 태스크 | 구현 영향 |
|-----|------------|-----------|
| OI-1 비행사 신고 control 채널 | BE-0.1 | `RequestCreated::broadcastOn`이 `project_id` 필요. project_id null 신고는 broadcast 대상 채널이 없음 → 레거시 채널 유지 or 전역 fallback 분기 결정 전까지 **null 가드**(브로드캐스트 스킵)로 임시 처리, 정책 결정 후 확정. |
| OI-2 활성지령 1건 동시성 | BE-3.2 | 서비스 불변식만으로는 2 controller 동시 배정 경합 가능. `request_id` 락 or 부분 유니크 인덱스는 결정 대기 → 우선 트랜잭션+재조회로 방어, 부하검증(OPS) 후 강화. |
| OI-4 location 가드 시그니처 | BE-2.1 | "역할 무관 active"를 `event.role`이 표현 못함. `event.role:*` 와일드카드 or `event.member` 별도 미들웨어 결정 전까지, location 라우트는 **컨트롤러 내 active 참가 검사**로 임시 처리. |
| OI-5 requester 채널 신고이력 race | BE-3.3 | 신고 직후 첫 구독 race. 현재는 `auth id==userId`+행사 active만으로 완화, 신고이력 필수화 여부 결정 대기. |
| OI-6 멱등 전이 | BE-3.2 | 동일상태 재전송 기본 거부(422). 네트워크 재시도 고려한 멱등 허용은 결정 대기 — 테스트는 현행(거부)로 작성. |
| OI-7 위치이력 보존기간 | BE-2.1·BE-4.1·OPS-4.1 | 보존기간/자동파기 미정. tracks 리포트 만료·파기잡 설계 선행조건(법무). 보존정책 확정 전 M2 진입 시 R3 트리거. |
| OI-8 `assigned_rescuer_id` 잔존 | BE-3.1·BE-3.2 | dispatch 도입 후 `requests.assigned_rescuer_id`/`responded_at` 의미 중복. 현재 유지, deprecate 시점은 Q6과 연동. |
| Q1 사전명단 데이터소스 | BE-1.2 | 미정 → M1은 **자가선택+수동만** 구현(사전명단 매칭은 데이터소스 결정 후 추가). |
| Q3 가용성 정렬 기준 | FE-3.3 | 거리만 vs 지령보유수 미정 → 배정 UI 정렬 로직은 결정 후 확정(우선 거리순). |
| Q6 레거시 assign 폐기 | BE-3.2 | `POST /requests/{id}/assign`은 dispatch 안정화 후 Deprecated→제거. 현재 잠정 유지(신규 코드는 DispatchService만 호출). |
