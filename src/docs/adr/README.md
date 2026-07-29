# 아키텍처 결정 기록 (ADR)

이 디렉터리는 GPS119의 **되돌리기 어려운/논쟁적인 기술 결정**을 한 건당 한 파일로 기록한다.
형식은 Michael Nygard 표준(Title / Status / Context / Decision / Consequences)을 따른다.

- 새 결정은 다음 번호(`NNNN-제목.md`)로 추가한다. 번호는 재사용하지 않는다.
- 결정을 뒤집을 때는 기존 ADR을 지우지 말고 `Status`를 `Superseded by ADR-XXXX`로 바꾸고 새 ADR을 만든다.

## 상태값

`Proposed`(제안) · `Accepted`(채택) · `Superseded`(대체됨) · `Deprecated`(폐기)

## 목록

| # | 제목 | 상태 |
|---|------|------|
| [0001](0001-realtime-transport-laravel-reverb.md) | 실시간 전송 계층으로 Laravel Reverb 채택 | Accepted |
| [0002](0002-event-scoped-roles.md) | 행사 스코프 역할을 `event_participants` pivot으로 분리 | Accepted |
| [0003](0003-dispatch-state-machine.md) | 신고 배정을 지령(Dispatch) 상태머신으로 전환 | Accepted |
| [0004](0004-contact-channel-scoping.md) | 실시간 채널의 신고자 연락처 노출 최소화 | Accepted |

> 배경 설계는 [`../epics/realtime-dispatch-control/`](../epics/realtime-dispatch-control/) 참조.
