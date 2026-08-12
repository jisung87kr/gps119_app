# ADR-0007. 지령 회수(recall)를 독립 상태로 두고, 신고 취소를 단일 진입점으로 모은다

- 상태: Accepted
- 날짜: 2026-08-12
- 관련: ADR-0003(지령 상태기계), ADR-0004(연락처 채널 스코핑), `app/Enums/DispatchStatus.php`, `app/Services/DispatchService.php`, `app/Services/RequestService.php`
- 계기: 2026-08-12 현장 피드백 — 「신고 취소방법」, 「출동 배정 후 회수방법」

## 배경 (Context)

현장에서 두 가지가 «아예 불가능»했다.

**1. 배정한 지령을 되돌릴 수 없다.** `DispatchStatus::allowedTransitions()` 에 역행 전이가 하나도 없었다. 관제사가 엉뚱한 대원에게 보냈거나 중복 신고임을 뒤늦게 알아도, API·UI 어디에도 회수 경로가 없다. 실제 운영에서는 무전으로 「그냥 무시하세요」라고 말하고, 지령은 그 대원의 화면에 영원히 «활성»으로 남았다. 그 신고는 활성 지령 1건 불변식에 걸려 **다른 사람에게 재배정할 수도 없다.**

**2. 신고 취소가 절반만 동작한다.** `RequestService::cancelRequest()` 는 `status` 와 `completed_at` 만 바꿨다. 그 결과:

- 활성 dispatch 가 **고아**로 남는다. 대원의 지령 화면은 신고 status 를 보지 않고 dispatch status 만 보므로, 취소된 신고가 계속 출동 목록에 떠 있고 대원은 현장으로 간다.
- 그 대원이 나중에 「완료」를 누르면 `syncRequestStatus()` 가 **취소를 완료로 덮어쓴다.** 취소 기록 자체가 사라지는 종류의 버그라 사후에 발견되지도 않는다.
- 브로드캐스트·푸시가 **전혀** 나가지 않는다. 신고자 화면은 15초 폴링이 돌 때까지 모르고, 관제 화면과 담당 대원은 끝까지 모른다.
- 관리자 화면(`AdminController::requestUpdate`)은 서비스를 안 거치고 `update()` 를 직접 호출해서, `canBeCancelled()` 검사조차 없이 완료된 건도 취소로 되돌릴 수 있었다. 같은 버튼처럼 보이는 **다른 동작**이 두 개였다.

## 결정 (Decision)

### D1. `DispatchStatus::CANCELLED` 를 새로 만든다 (REJECTED 재사용 금지)

- 전이: `assigned → cancelled`, `accepted → cancelled`, `en_route → cancelled`. **`arrived` 에서는 회수 불가.**
- `cancelled` 는 terminal. `syncsRequestStatus()` 는 `PENDING` 을 반환해 신고를 재배정 대기로 되돌린다.
- 권한: **상황실(controller)/admin 만.** 대원 본인은 회수할 수 없다.
- 전용 이벤트 `DispatchRecalled` → 대원 개인 채널 + 전용 리스너 `PushDispatchRecalled`.
- `dispatches.cancelled_at` 컬럼 추가.

### D2. 신고 취소는 `RequestService::cancelRequest()` 단 하나의 문으로만 들어온다

- 권한: **admin 항상 / 그 행사 controller 항상 / 신고자 본인은 «활성 지령이 없을 때만».**
- 취소는 활성 지령 회수와 **한 트랜잭션**이다.
- `RequestStatusUpdated` 를 발행하고, 그 이벤트의 `broadcastOn()` 에 **control 채널을 추가**한다.
- `requests.cancelled_by` / `cancel_reason` 컬럼 추가.
- `updateRequest()` 로 들어오는 `status=cancelled` 는 **던진다.** 관리자 화면도 서비스를 경유하도록 교체.

### D3. 신고 상태 동기화에 «종료상태 가드»를 둔다

`DispatchService::syncRequestStatus()` 는 신고가 이미 종결(`completed`/`cancelled`)이면 아무것도 하지 않는다. 짝으로 `assign()` 은 종결된 신고에 지령을 붙이지 않는다.

## 검토한 대안 (Alternatives)

- **A. 회수를 `REJECTED` 로 표현 (기각).** 마이그레이션이 필요 없어 가장 싸다. 그러나 거절은 「대원이 못 간다」(대원 책임, 사유 필수)이고 회수는 「관제가 뺀다」다. 한 값에 합치면 **행사 리포트의 거절률이 영구히 오염**되고, 이미 내보낸 CSV 는 되돌릴 수 없다. 사고 원인 분석에서 정확히 구분이 필요한 두 사건이다.
- **B. 회수 시 신고를 그대로 두기(`syncsRequestStatus() = null`) (기각).** 아무도 안 가는데 신고만 `in_progress` 로 남는 좀비가 된다. `PENDING` 복귀가 「다시 배정해야 한다」를 정확히 표현한다.
- **C. `arrived` 에서도 회수 허용 (기각).** 대원이 이미 현장에 도착했으면 오인신고라도 「도착해서 확인하고 종결했다」가 기록상 정확하다. 회수로 지우면 그 이동 자체가 없던 일이 된다.
- **D. 신고자가 언제든 취소 (기각).** 응급 도메인은 오탐(FP)보다 누락(FN)이 나쁘지만, 「출동 중인 지령을 신고자가 말없이 지우는 것」은 누락보다 나쁘다. 배정 후에는 상황실 판단을 거친다.
- **E. 신고자는 취소 불가, 전화만 (기각).** 현행 매뉴얼 방침이고 오취소 위험이 가장 낮다. 그러나 배정 «전» 오신고까지 전화로만 처리하게 만들면 상황실이 불필요한 통화에 묶인다.

## 결과 (Consequences)

**긍정**
- 관제사가 잘못 보낸 지령을 되돌리고 **다른 대원에게 재배정**할 수 있다.
- 취소된 신고로 대원이 출동하는 일이 없어진다. 회수 푸시가 「가지 마세요」를 손 안까지 전달한다.
- 「누가 왜 껐는가」가 데이터에 남는다.
- 관리자 화면 취소와 API 취소가 **같은 동작**이 된다.

**부정 / 비용**
- `cancelled` 로 기록된 행의 의미는 되돌릴 수 없다. 회수와 거절을 사후에 재분류하려면 수작업이다.
- 리포트 CSV 에 새 상태값이 등장한다 — 기존 집계 스크립트가 있다면 `cancelled` 를 모른다.
- 상태기계 케이스가 6 → 7 로 늘어 전이표 검증 부담이 커진다.

**검증**
- `tests/Feature/DispatchRecallTest.php`, `tests/Feature/RequestCancellationTest.php`, `tests/Feature/DispatchCandidateTest.php`.
- D3 의 종료상태 가드는 **변이 검증**으로 확인했다 — 가드를 제거하면 `test_the_sync_guard_alone_protects_a_cancelled_request` 가 정확히 실패한다.

## 함께 들어간 것 (별도 ADR 불필요)

**배정 후보를 구급대(PARAMEDIC)로 한정.** 피드백 #5. `EventRole::canReceiveDispatch()`(자격)와 `isDispatchCandidate()`(후보)를 **분리**했다. 전자를 그대로 좁혔다면 이미 지령을 받아 이동 중인 자원봉사(구급)가 자기 지령 화면에서 쫓겨나고(진행 중 지령이 즉시 고아가 된다) 활동화면에서도 참가자로 강등된다. 되돌리기 비용이 낮아(메서드 1개 + 호출 2곳) ADR 대상은 아니다.
