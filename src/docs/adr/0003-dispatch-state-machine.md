# ADR-0003. 신고 배정을 지령(Dispatch) 상태머신으로 전환

- 상태: Accepted
- 날짜: 2026-06-25
- 관련: `epics/realtime-dispatch-control/03-data-model.md`, `06-dispatch-app.md`, `08-api-and-events.md`

## 배경 (Context)

현재 신고 배정은 `requests.assigned_rescuer_id` 단일 필드 + 전역 `RequestStatus`
(`pending`/`in_progress`/`completed`/`cancelled`)뿐이다. 한계:

- "수락 → 출동 → 도착 → 완료"의 현장 진행 단계를 표현 못 한다(전부 `in_progress`로 뭉개짐).
- 거절·무응답 시 **다른 대원에게 재지령**한 이력이 남지 않는다(필드 덮어쓰기).
- 배정자(상황실)·각 전이 시각·지령 메모를 담을 곳이 없다.

또한 기존 API `GET /api/requests/{id}/assign`은 (a) 컨트롤러 메서드명 불일치(`assign` ≠ `assignRescuer`)로 호출 시 에러나는 죽은 라우트이고, (b) 본문(`rescuer_id`)을 받는 GET이라 비표준이다.

## 결정 (Decision)

신규 **`dispatches` 테이블 + `App\Enums\DispatchStatus` 상태머신**을 도입한다.

```
assigned → accepted → en_route → arrived → completed
              └→ rejected   [assigned/accepted 단계에서만]
```

- `requests 1 ─ N dispatches`(한 시점 활성 지령 1건, 거절 시 재지령으로 새 row).
- 전이 검증은 `App\Services\DispatchService`(잘못된 전이는 도메인 예외 → 422).
- 지령 전이는 연결 신고 상태와 동기화: `accepted/en_route/arrived` → `in_progress`, `completed` → `completed`.
- 신규 API: `POST /api/requests/{id}/dispatch`, `PATCH /api/dispatches/{id}/status`, `GET /api/dispatches/mine`, `GET /api/events/{id}/dispatches`.

**기존 `assign` 라우트 처리**: 즉시 폐기하지 않는다. dispatch 도메인이 들어오기 전까지의
공백을 막기 위해 동작 가능한 표준형(`POST /requests/{id}/assign` → `assignRescuer`)으로 **교정**해 두고,
지령 API가 안정화되면 `Deprecated` → 제거한다.

## 근거 (Rationale)

- 현장 작전은 단계가 핵심 정보다(상황실은 "지금 출동 중/도착"을 봐야 함) → 단일 status로는 불가.
- 재지령 이력·전이 타임라인은 사후 기록 다운로드(요구 마지막) 및 책임 추적에 필요.
- 신고(`requests`)는 스냅샷 불변 자산으로 유지하고, 가변적인 작전 상태는 `dispatches`로 분리해 관심사를 가른다.

## 결과 (Consequences)

**긍정**
- 출동 현황 보드·완료 타임라인·재지령이 1급 데이터로 표현된다.
- 신고 도메인은 단순하게(스냅샷) 유지된다.

**부정/비용**
- 신고 status와 지령 status **2개를 동기화**해야 한다 → 동기화 책임을 `DispatchService`에 단일화해 드리프트 방지.
- 기존 `RequestService::assignRescuer`(즉시 `in_progress`)와 신규 흐름(수락 시 `in_progress`)의 의미가 달라짐 → 신규 흐름으로 일원화.

**필요 변경**
- `dispatches` 마이그레이션 + `Dispatch` 모델 + `DispatchStatus` enum + `DispatchService` + `DispatchApiController`.
- `routes/api.php`의 `assign` 라우트: 교정 완료(POST + `assignRescuer`), 추후 `Deprecated` 예정.
