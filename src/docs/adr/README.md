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
| [0005](0005-all-requests-belong-to-event.md) | 모든 신고는 행사에 소속 — "상시 운영" 기본 행사 흡수 | Accepted |
| [0006](0006-production-hosting-aws-seoul.md) | 운영 배포 대상 — AWS 서울 단일 VM (공공 진입 시 NCP 이전) | Accepted |
| [0007](0007-dispatch-recall-and-request-cancellation.md) | 지령 회수를 독립 상태로, 신고 취소를 단일 진입점으로 | Accepted |
| [0008](0008-location-permission-as-separate-axis.md) | OS 위치 권한을 «공유 의도»와 분리된 축으로 — 관제 상태는 서버 파생 | Accepted |
| [0009](0009-admin-issued-operator-accounts.md) | 운영진 계정을 관리자가 초기 비밀번호로 일괄 발급 — 첫 로그인에서 변경+동의 강제 | Accepted |

> 배경 설계는 [`../epics/realtime-dispatch-control/`](../epics/realtime-dispatch-control/) 참조.
