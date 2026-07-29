# ADR-0004. 실시간 채널의 신고자 연락처 노출 최소화

- 상태: Accepted
- 날짜: 2026-06-25
- 관련: `epics/realtime-dispatch-control/04-realtime-architecture.md`, `08-api-and-events.md`

## 배경 (Context)

실시간 채널 설계 초안에서 `event.{projectId}.control` 채널의 구독자에 **구급대까지 포함**시키면서,
동시에 "민감정보(연락처)는 control 채널 한정"이라고 규정했다. 이 둘은 충돌한다:

- control 채널에는 `request.created` 페이로드로 **신고자 이름·연락처**가 흐른다.
- 거기에 구급대 전원이 구독하면, **배정받지도 않은 신고의 신고자 연락처가 모든 구급대원에게 팬아웃**된다.
- 한편 `02`/`07` 문서는 관제 화면 접근을 `controller`/시스템 `admin`으로 제한하고 있어 정책이 어긋난다.

## 결정 (Decision)

연락처가 실리는 채널을 **상황실(controller)·시스템 admin 전용**으로 좁힌다.

| 채널 | 구독자 | 연락처 포함 |
|------|--------|-------------|
| `event.{id}.control` | controller / admin 만 | 예 (`request.created` 등) |
| `event.{id}.dispatch.{userId}` | 해당 구급대원 본인 | 예 — **본인에게 배정된 건만** |
| `event.{id}.locations` | active 참가자 전원(presence) | 아니오 — 좌표/역할만 |
| `event.{id}.requester.{userId}` | 신고자 본인 | 담당자 정보만 |

- 구급대원은 control을 구독하지 않는다.
- 구급대원이 신고자에게 전화해야 하는 경우는 **자신이 배정받은 지령**뿐이므로, 연락처는 본인 `dispatch` 채널 페이로드로만 받는다.
- 현장 지도 맥락은 연락처 없는 `locations` presence 채널로 충족한다.

## 근거 (Rationale)

- 최소권한 원칙: 개인정보(전화번호)는 "그 정보가 업무상 필요한 주체"에게만 도달해야 한다.
- 상황실은 전체 인시던트를 봐야 하므로 연락처 접근이 정당하지만, 개별 구급대원은 배정 건에 한해서만 정당하다.
- 위치 좌표(`locations`)와 연락처(`control`/개인 `dispatch`)의 민감도를 분리해 채널 단위로 관리한다.

## 결과 (Consequences)

**긍정**
- 개인정보 노출 범위가 역할·배정 관계에 비례하도록 좁혀진다(감사·법무 리스크 감소).
- 채널 인가 규칙이 역할과 1:1로 단순해진다.

**부정/비용**
- 구급대원 앱은 "전체 신고 보드"를 실시간으로 보지 못한다(설계상 의도된 제약 — 인시던트 총괄은 상황실 책임).

**필요 변경**
- `RequestCreated::broadcastOn()`: 기존 `new Channel('requests')` + `PrivateChannel('rescuers')` → `event.{id}.control`로 교체.
- `routes/channels.php` 인가에서 `control`은 `controller`/`admin`만 통과.
- `broadcastWith()`는 채널별 최소 페이로드 — 연락처는 control·개인 dispatch 페이로드에만 포함.
