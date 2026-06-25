# 미결 결정 등록부 (Open Decisions)

전 lane이 올린 OPEN ISSUE(OI)·열린 결정(Q)을 통합. **막는 태스크가 착수되기 전**에 닫아야 한다.
`소유자`가 결정 주체, `권장안`은 미정 시 채택할 기본값(채택 시 상태를 Decided로).

## 사람(제품/정책) 결정 필요 — 우선

| ID | 이슈 | 막는 것 | 소유자 | 권장안 | 상태 |
|----|------|---------|--------|--------|------|
| **OI-1** | 행사 무관 일반 신고(`project_id=null`)의 실시간 채널. `RequestCreated`를 `event.{id}.control`로 옮기면 일반 신고는 채널이 없어짐 | **BE-0.1 (M0, 첫 태스크)** | 제품 | (확정) | **Decided: 일반 신고도 실시간 유지. `project_id` 있으면 `event.{id}.control`, 없으면 전용 전역 채널 `requests.global`(시스템 admin·rescuer 구독)로 브로드캐스트. `RequestCreated::broadcastOn()`이 project_id 유무로 분기.** |
| **Q2 / OI-7** | 위치이력(`location_pings`) 보존기간·자동파기 정책 (개인정보·법무) | OPS-11 자동파기, M2 상시수집 운영 | 제품/법무 | 행사 종료 +30일 후 자동 파기, 그 전 드라이런만 | **OPEN** |
| **Q1** | 사전명단 역할배정 데이터 소스 (전화번호 CSV vs 외부연동) | BE-1.2 사전명단 매칭 | 제품 | v1은 CSV 업로드만 | **OPEN** |
| **Q3** | 가용 구급대원 정렬 기준 (거리만 vs 현재 지령 보유수) | FE-3.3 배정 리스트, DS-3.4 | 제품/운영 | 거리 우선 + 보유 지령수 보조표시 | **OPEN** |
| **Q4** | 행사 동시 운영 규모 (단일 Reverb로 충분한가) | OPS-09 스케일링, 큐 드라이버 | 운영 | v1 단일 인스턴스, M2에서 부하검토 후 재판단 | **OPEN** |

## 기술 결정 (팀 자체 종결 가능) — 권장안대로 진행 예정

| ID | 이슈 | 막는 것 | 소유자 | 권장안(채택 예정) |
|----|------|---------|--------|-------------------|
| OI-2 | "활성 지령 1건"을 DB 유니크로 강제 불가 → 동시 배정 경합 | BE-3.2 | 아키텍트 | 트랜잭션 내 상태검사 + 비관적 락(`lockForUpdate`), 부분 유니크 인덱스 보조 |
| OI-3 | `projects` SoftDelete인데 자식 FK는 `cascadeOnDelete`(hard) 불일치 | BE-1.1 마이그레이션 | 아키텍트 | 자식 cascade 유지(물리삭제 시 정리), 소프트삭제는 스코프 쿼리로 가림 — 문서화 |
| OI-4 | 위치 POST의 "역할무관 active 참가자" 가드를 `event.role`로 표현 불가 | BE-2.1 | 아키텍트 | `event.member` 미들웨어 신설(active면 역할무관 통과) |
| OI-5 | requester 채널 인가 시 신고 이력 조건 race | BE-3.x | 아키텍트 | 인가 쿼리에서 소유 신고 존재만 확인(시점 race 무해) |
| OI-6 | 멱등 전이(동일 상태 재전송) 허용 여부 | BE-3.2 | 아키텍트 | 동일 상태 재전송은 no-op 200(중복 클릭 방어) |
| OI-8 | `requests.assigned_rescuer_id`/`responded_at` 의미가 dispatch와 중복 | BE-3.2 | 아키텍트 | dispatch 도입 후 단일 출처는 dispatch, 기존 필드는 파생/폐기 경로 |
| DS-color | 역할 7색 마커를 색만으로 구분 어려움(색맹) | DS-0, FE-2.1 | 디자이너 | 색 + 아이콘 형태 7종 병용(Heroicons 차용, 신규제작 최소화) |

## Phase 0 구현 메모

- **OI-1 적용 완료(BE-0.1)**: `RequestCreated::broadcastOn()`이 `project_id` 유무로 분기 — 있으면 `event.{id}.control`, 없으면 `requests.global`. 레거시 `requests`/`rescuers` 채널 제거.
- **채널 인가(Phase 0 잠정 → Phase 1 강화 완료)**: `event.{id}.control`을 `User::eventRoleIn(Project)` active+`EventRole::CONTROLLER`(시스템 admin OR)로 **BE-1.2에서 강화 완료**. `requests.global`은 그대로 admin/rescuer.

## Phase 1 구현 메모 (BE-1.1 / BE-1.2)

- **OI-3 적용**: `event_participants` 자식 FK는 `cascadeOnDelete` 유지. projects는 SoftDeletes라 소프트삭제 시 자식 cascade 안 됨(forceDelete 시에만) — 마이그레이션/모델 주석에 명시. 정리정책은 OPS lane.
- **OI-4 적용**: 위치/공용 라우트의 "역할무관 active 참가자" 가드를 위해 `EnsureEventMember`(`event.member`) 신설. 역할 가드는 `EnsureEventRole`(`event.role:controller` 등).
- **Q1 (사전명단 CSV)**: v1 풀 구현 안 함. `joinByCode`는 기본 `participant=active` + 수동배정(`PATCH /participants/{userId}`)으로 처리. 전화번호 기반 사전명단 CSV 매칭은 `EventParticipantService::joinByCode` 주석에 TODO stub만 남김(데이터소스 결정 후).
- **이연 TODO**: `EnsureEventRole`의 dispatch 라우트({id}=dispatch) 해석은 Phase 3(BE-3.x)에서 확장. Phase 1은 project 라우트만 사용. `EventParticipantService::rosterForControl`(관제 초기 로드 1쿼리)은 위치 캐시가 필요한 Phase 2(BE-2.x)에서 구현.

## 처리 규칙
- 사람 결정 5건은 소유자 합의 즉시 이 표에 `Decided: <결론>`으로 갱신하고, 막던 태스크를 해제한다.
- 기술 결정 7건은 아키텍트/디자이너가 권장안대로 종결하되, 결과를 `architecture-spec.md`/`ux-tasks.md`에 반영한다.
- OI-1은 M0 첫 태스크를 막으므로 **최우선**.
