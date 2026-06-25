# Epic: 실시간 위치·지령 관제 시스템 (Realtime Dispatch & Control)

> 행사 현장의 모든 인원(참가자·운영진·경찰·자원봉사자·구급대)을 실시간으로 지도에 표시하고,
> 신고 접수 → 지령 배정 → 출동/도착/완료까지의 전 과정을 실시간으로 관제하는 시스템.

기존 GPS119(단발성 위치 공유 신고)를 **실시간 다중역할 현장 안전관제 플랫폼**으로 확장한다.

## 산출물 구성 (3 surface)

| # | 산출물 | 대상 | 형태 |
|---|--------|------|------|
| 1 | **참가자용 앱** | 참가자, 운영진, 경찰, 자원봉사자, 구급대 | PWA(+하이브리드 래핑) |
| 2 | **관리자·구급대원용 앱** | 상황실, 구급대원 | PWA(+하이브리드 래핑) |
| 3 | **웹 관제 시스템** | 관리자, 상황실 | PC 웹 (대화면) |

세 surface 모두 **하나의 Laravel 백엔드 + Reverb 실시간 채널**을 공유한다. 앱 1/2는 동일 PWA 셸에서 역할에 따라 화면이 갈린다.

## 확정된 기술 결정

- **앱 구현**: PWA 우선 + 추후 Capacitor 하이브리드 래핑 (기존 Laravel 12 / Blade+Vue 자산 재사용). `PROMPT.MD`의 "하이브리드앱" 방향과 일치.
- **실시간**: **Laravel Reverb** (1st-party WebSocket) + Laravel Echo 클라이언트. 이미 선언된 `RequestCreated implements ShouldBroadcast`를 실제 동작시키는 첫 사용처.
- **지도/내비**: 카카오맵 JS SDK(기존) + 카카오내비/카카오맵 길찾기 딥링크.
- **백엔드 유지**: Laravel 12 / MySQL / Sanctum. 기존 서비스레이어·Enum·응답매크로 패턴 계승.

## 문서 색인

| 문서 | 내용 |
|------|------|
| [01-gap-analysis.md](01-gap-analysis.md) | 기존 시스템 현황 ↔ 요구사항 격차 분석 (무엇이 있고 무엇이 없나) |
| [02-roles-and-access.md](02-roles-and-access.md) | 역할 체계 재설계, 행사 코드 입장, 역할 배정 |
| [03-data-model.md](03-data-model.md) | 신규/변경 테이블, 마이그레이션, Enum |
| [04-realtime-architecture.md](04-realtime-architecture.md) | Reverb/Echo 구조, 위치 추적 파이프라인, 채널 설계 |
| [05-participant-app.md](05-participant-app.md) | 앱1 — 참가자용 (입장·위치공유·신고) |
| [06-dispatch-app.md](06-dispatch-app.md) | 앱2 — 관리자·구급대원용 (지령 상태머신) |
| [07-web-control.md](07-web-control.md) | 앱3 — 웹 관제 (전 인원 실시간 지도) |
| [08-api-and-events.md](08-api-and-events.md) | REST API + 브로드캐스트 이벤트 명세 |
| [09-roadmap.md](09-roadmap.md) | 단계별 구현 로드맵, 마일스톤, 리스크 |

## 핵심 흐름 (요구사항 기준)

```
참가자앱: 앱 실행 → 위치공유 자동시작(주기 ping) → 사고/고장/기타 버튼
        → GPS 수집 → 주소 변환 → "이 위치가 맞습니까?" 팝업 → 확인 → 신고 전송
        → 신고 시점 위치 고정 저장(스냅샷)
                              │ (Reverb broadcast)
웹 관제:  신고 접수 알림 → 지도에 신고 위치 고정 표시 → 구급대원에게 지령 배정
                              │ (Reverb broadcast)
구급대앱: 지령 수신 → 수락 → 출동 → 도착 → 완료 (상태 전이마다 broadcast)
        → 카카오내비로 신고 위치 길안내
                              │
웹 관제:  출동 현황 실시간 추적 → 완료 기록 → 행사 종료 후 기록 다운로드
```

## 범위 밖 (Out of scope, 이번 에픽)

- 네이티브 스토어 심사·배포 (하이브리드 래핑은 후속 단계)
- 결제/정산, 다국어
- 참가자 본인 인증(실명/통신사) 고도화 — 기존 전화번호 기반 유지
