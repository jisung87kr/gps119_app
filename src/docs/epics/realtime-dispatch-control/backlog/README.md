# 실행 백로그 (Realtime Dispatch & Control)

에픽 설계 문서(`../01`~`09`)와 ADR(`../../../adr/`)를 **구현 가능한 태스크 단위로 분해**한 백로그.
담당 5인(PM·아키텍트·DevOps·풀스택·디자이너)이 lane별로 작성했다.

## 구성

| 문서 | 담당 | 내용 | Lane 접두사 |
|------|------|------|-------------|
| [00-master-plan.md](00-master-plan.md) | PM (cha-sunok) | 마일스톤 M0~M4, WBS·의존성, RACI, 크리티컬 패스, 리스크 | M0~M4 |
| [architecture-spec.md](architecture-spec.md) | 아키텍트 (na-minsik) | 마이그레이션·Enum·모델·서비스·채널·API 계약 (구현 계약) | SPEC-xx |
| [infra-tasks.md](infra-tasks.md) | DevOps (kang-mansu) | Reverb·WS 프록시·큐·배포·스케일링·보존정책 | OPS-xx |
| [impl-tasks.md](impl-tasks.md) | 풀스택 (kim-balsu) | 백엔드/프론트 구현 태스크(파일 단위·테스트) | BE-/FE-x.x |
| [ux-tasks.md](ux-tasks.md) | 디자이너 (lee-sunja) | 화면·플로우·마커 시스템·디자인 스펙 | DS-xx |
| [open-decisions.md](open-decisions.md) | (통합) | 전 lane이 올린 미결 결정·OPEN ISSUE 통합 등록부 | OI-/Q- |

## 마일스톤 한눈에

| MS | 완료 기준 | 의존 | 핵심 lane |
|----|-----------|------|-----------|
| **M0** | 기존 신고가 `event.{id}.control`로 관제에 실시간으로 뜬다 | — | OPS-01~08, BE-0.1, FE-0.1 |
| **M1** | 코드/QR 입장 → 행사 스코프 역할 부여 | M0 | BE-1.x, DS-1.x |
| **M2** | 웹 관제에서 전 인원이 역할 색상으로 실시간 이동 | M1 | BE-2.x, FE-2.x, DS-2.x, OPS-04 |
| **M3** ★ | 신고→지령→출동→완료 상태머신 end-to-end | M2 | BE-3.x, FE-3.x, DS-3.x |
| **M4** | 기록 다운로드 + PWA + 하이브리드·운영안정화 | M3 | OPS-09~13, BE-4.1, FE-3.4, DS-4.x |

## 진행 원칙
- 크리티컬 패스: `OPS-01(Reverb) → BE-0.1 → FE-0.1 → BE-1.2 → BE-2.1 → BE-2.2 → FE-2.1 → BE-3.2 → BE-3.3 → FE-3.2`.
- 각 태스크는 `architecture-spec.md`의 SPEC 계약을 단일 출처로 따른다. SPEC과 어긋나면 SPEC을 먼저 고친다.
- 미결(OI-/Q-)은 [open-decisions.md](open-decisions.md)에서 추적하며, **해당 미결이 막는 태스크는 결정 전 착수 금지**.
