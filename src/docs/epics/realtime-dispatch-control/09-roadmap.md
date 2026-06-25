# 09. 구현 로드맵 · 마일스톤 · 리스크

요구사항을 **점진 출시** 가능한 단위로 분할. 각 Phase 끝에 동작하는 산출물이 나오도록 구성(수직 슬라이스).

## Phase 0 — 실시간 기반 구축 (선행, 필수)
WebSocket 없이는 나머지가 무의미. 가장 먼저.
- [ ] Reverb 설치/구성, Echo 클라이언트, `channels.php`, 도커 WS 서비스/프록시 (04 문서 체크리스트)
- [ ] `bootstrap/app.php` `withBroadcasting` + 큐 워커 가동
- [ ] 기존 `RequestCreated` 채널을 `event.{id}.control`로 교체 → **신고 실시간 수신 PoC** (관리자 화면에 토스트)
- ✅ 산출: 신고가 디스코드뿐 아니라 실시간으로 관제에 뜬다.

## Phase 1 — 행사 입장 · 역할 (M1)
- [ ] `projects.join_code`, `event_participants` 마이그레이션 + 모델 + `EventRole` enum
- [ ] 코드/QR 입장 API + 참가자 앱 입장 플로우, 역할 배정(자가/사전명단/수동)
- [ ] `event.role` 미들웨어
- ✅ 산출: 코드로 입장해 역할이 부여된다.

## Phase 2 — 실시간 위치 추적 (M2)
- [ ] `location_pings` + `event_participants.last_*` , 위치 ping API, 큐 적재
- [ ] 참가자 앱 `watchPosition` 자동 공유(적응형 주기)
- [ ] `ParticipantLocationUpdated` 브로드캐스트
- [ ] **웹 관제 v1**: 카카오맵 + 전 인원 실시간 마커 + 역할 필터
- ✅ 산출: 관제 지도에서 전 인원이 실시간으로 움직인다. (요구 3번 surface의 핵심)

## Phase 3 — 신고 고도화 · 지령 (M3) ★ 핵심 가치
- [ ] `RequestType` enum + `requests.type`, 신고 버튼/주소확인 모달 개편(05 문서)
- [ ] `dispatches` + `DispatchStatus` + `DispatchService` + Dispatch API
- [ ] 구급대원 앱: 지령 수신·상태전이·카카오내비
- [ ] 관제: 지령 배정 UI + 출동 현황 보드
- [ ] 신고자: 상태 실시간 추적 + 담당자 전화 연결
- ✅ 산출: 신고→지령→출동→완료 전 과정이 실시간으로 돈다(요구 핵심 흐름 1~12 완성).

## Phase 4 — 마감/운영 (M4)
- [ ] 기록 다운로드 확장(신고·지령·동선)
- [ ] PWA 마감(매니페스트/서비스워커/설치 온보딩), 알림 권한
- [ ] **Capacitor 하이브리드 래핑**: 백그라운드 위치추적·푸시(FCM) 네이티브 보강
- [ ] 부하/배터리 튜닝, Reverb 스케일링(Redis), 위치이력 보존정책
- ✅ 산출: 스토어 배포 가능 하이브리드 앱 + 운영 안정화.

## 마일스톤 요약

| MS | 완료 기준 | 의존 |
|----|-----------|------|
| M0 | 실시간 신고 수신 동작 | — |
| M1 | 행사 코드 입장·역할 | M0 |
| M2 | 전 인원 실시간 관제 지도 | M1 |
| M3 | 지령 상태머신 end-to-end | M2 |
| M4 | 기록 다운로드 + 하이브리드 | M3 |

## 리스크 & 대응

| 리스크 | 영향 | 대응 |
|--------|------|------|
| PWA 백그라운드 위치 한계 | 화면 꺼지면 추적 끊김 | 적응형 주기·로컬큐 + Phase4 네이티브 플러그인 |
| 대규모 동시 ping 부하 | DB/WS 과부하 | ping 큐잉, 관제는 WS 최신값만, 클러스터링, Reverb+Redis |
| 위치 개인정보 | 법적/신뢰 | 공유 토글, 행사 스코프 한정, 종료 후 동선 보존기간·자동파기 정책 |
| 약전계/터널 구간 | 신고·위치 유실 | 오프라인 로컬큐 + `recorded_at` 보존, 복구 시 재전송 |
| Reverb 운영(단일장애점) | 실시간 중단 | supervisor 재기동, HTTP 폴링 폴백(04) |
| 신고 좌표 오염 | 출동 위치 오류 | 스냅샷 불변(03), 갱신 API 좌표 화이트리스트 제외 |
| 카카오 API 쿼터/키 | 지도·내비 실패 | 키 분리·쿼터 모니터링, 내비 웹 폴백 |

## 기존 코드 영향 체크리스트
- [ ] `RequestCreated::broadcastOn()` 채널 교체 (`requests`/`rescuers` → `event.{id}.control`)
- [ ] `NotifyRescuers` 큐 재활성(`ShouldQueue` 주석 해제) — 디스코드 동기 `file_get_contents` 비동기화
- [ ] `RequestApiController::store` `type` 추가 + `project_id` 처리
- [ ] `RequestService::updateRequest` 좌표 수정 차단
- [ ] `RolePermissionSeeder`는 시스템역할만(행사역할 미포함)
- [ ] `bootstrap/app.php` broadcasting + `event.role` 미들웨어

## 열린 결정사항 (착수 전 확인 권장)
- 사전명단 배정의 데이터 소스(전화번호 CSV vs 외부연동)
- 위치이력 보존기간(법무 검토)
- 구급대원 가용성 판단 기준(거리만 vs 현재 지령 보유수)
- 행사 동시 운영 규모(단일 Reverb로 충분한지)
