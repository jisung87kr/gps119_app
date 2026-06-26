# Epic: 실시간 위치·지령 관제 시스템 (Realtime Dispatch & Control)

> 행사 현장의 모든 인원(참가자·운영진·경찰·자원봉사자·구급대)을 실시간으로 지도에 표시하고,
> 신고 접수 → 지령 배정 → 출동/도착/완료까지의 전 과정을 실시간으로 관제하는 시스템.

기존 GPS119(단발성 위치 공유 신고)를 **실시간 다중역할 현장 안전관제 플랫폼**으로 확장한다.

## 구현 현황 (M0~M4 코드분 완료 ✅)

> 2026-06 기준. 백엔드+프론트 코드분 구현 완료, 131 테스트 그린, 브라우저 검증 완료.
> 계약/설계는 [`backlog/architecture-spec.md`](backlog/architecture-spec.md)·[ADR 0001~0004](../../adr/), 실행 단위는 [`backlog/impl-tasks.md`](backlog/impl-tasks.md), 미결 결정은 [`backlog/open-decisions.md`](backlog/open-decisions.md) 참조.

| 마일스톤 | 상태 | 핵심 산출물 (대표 파일) |
|----------|------|-------------------------|
| **M0 — 실시간 기반** | ✅ | Reverb 채널 인가 배선(`bootstrap/app.php` `withBroadcasting` + `routes/channels.php`), `RequestCreated` 채널 분기(control/`requests.global`), `NotifyRescuers` 큐 전환, Echo 클라이언트(`resources/js/echo.js`) |
| **M1 — 행사 입장·역할** | ✅ | `event_participants`/`projects.join_code` 마이그레이션, `EventRole`(7종)·`ParticipantStatus` enum, `EventParticipant` 모델, `EventParticipantService`, `EnsureEventRole`·`EnsureEventMember` 미들웨어, 입장 API(`EventApiController`) + 참가자 입장 화면(`event/join`, `event/active`) |
| **M2 — 실시간 위치 관제** | ✅ | `location_pings` + `LocationPing`/`PersistLocationPing`(큐) + `LocationService`, 위치/roster/sharing API(`LocationApiController`), `ParticipantLocationUpdated`(presence+control), **웹 관제 SPA**(`resources/js/control/`, `/control`), 참가자 자동 위치공유(`public/js/components/locationShare.js`) |
| **M3 — 신고 고도화·지령 상태머신** | ✅ | `requests.type`+`RequestType`, `dispatches`+`DispatchStatus`+`Dispatch`, `DispatchService`(전이검증·`requests.status` 동기화·활성지령 1건 락), `DispatchApiController`, 이벤트 3종(`DispatchAssigned`/`DispatchStatusUpdated`/`RequestStatusUpdated`), 관제 배정 패널·출동보드, 구급대원 앱(`dispatch/index`), 주소확인 모달(`request/_confirm-modal`), 신고자 상태추적(`RequestShowApp.js`) |
| **M4 — 마감·운영(코드분)** | ✅ | 기록 다운로드 CSV(`EventReportController`, requests/dispatches/tracks), **PWA 셸**(`public/manifest.webmanifest`·`sw.js`·`offline.html`·아이콘, `resources/js/pwa.js`) |

**남은 것 / 범위 밖:**
- **결정 대기(미구현)**: Q2/OI-7 위치이력 보존기간·자동파기 정책(개인정보·법무), Q4 동시 행사 규모·Reverb 스케일링(부하검증 후). `location_pings`는 적재만 하고 자동 파기 잡 없음.
- **범위 밖(이번 에픽 비대상)**: Capacitor 하이브리드 래핑, FCM 백그라운드 푸시(현재 Reverb 인앱 실시간만), 네이티브 스토어 배포, 결제/다국어.
- **이연 TODO(코드 주석)**: 출동대원→신고핀 폴리라인(`control-map-spec §3③`), `GET /dispatches/mine` 응답에 신고자 연락처 미포함, PWA 아이콘은 GD 생성 플레이스홀더(브랜드 교체 예정), 레거시 `POST /requests/{id}/assign`·`requests.assigned_rescuer_id` Deprecated.

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

> 이 에픽에서 확정된 되돌리기 어려운 결정은 [`../../adr/`](../../adr/)에 ADR로 기록한다
> (Reverb 채택·행사 스코프 역할·지령 상태머신·연락처 채널 한정).

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
