# 01. 기존 시스템 ↔ 요구사항 격차 분석

요구사항 항목별로 **현재 코드에 있는 것 / 없는 것 / 바꿀 것**을 정리한다. 모든 경로는 `src/` 기준.

## 요약 매트릭스

| 요구 기능 | 현재 상태 | 판정 | 핵심 작업 |
|-----------|-----------|------|-----------|
| 로그인 / 회원 | Fortify(전화/이메일) + 소셜(카카오·네이버) | ✅ 있음 | 재사용 |
| **행사 코드 입장** | 없음 (프로젝트 slug URL/QR만) | ❌ 신규 | `join_code` + 입장 플로우 |
| **역할 선택/배정** | 전역 3역할(user/rescuer/admin)뿐 | ⚠️ 부족 | 행사별 역할 pivot 신설 |
| **실시간 위치 공유(지속)** | 신고 시점 1회 좌표 저장만 | ❌ 신규 | 위치 ping + Reverb |
| 사고/고장/기타 신고 | 버튼은 있으나 `description` free-text | ⚠️ 부족 | `RequestType` enum 정식화 |
| 신고 전 주소 확인 팝업 | `confirm('위치공유를 하시겠습니까?')` | ⚠️ 부분 | "이 위치가 맞습니까?" 주소 포함 팝업 |
| 신고 시점 위치 고정 저장 | 생성 시 lat/lng 저장(불변) | ✅ 있음 | 스냅샷 컬럼 명시 강화 |
| 신고 후 담당자 전화 연결 | 없음 | ❌ 신규 | `tel:` 딥링크 + 담당자 노출 |
| **신고 접수 알림** | `RequestCreated`→디스코드/로그만 | ⚠️ 부분 | Reverb 실시간 푸시로 전환 |
| **지령 전송/상태머신** | `assigned_rescuer_id` + status뿐 | ❌ 신규 | `dispatches` 테이블 + 상태전이 |
| 카카오맵/내비 연결 | 카카오맵 표시 | ⚠️ 부분 | 내비 길찾기 딥링크 추가 |
| **웹 관제 실시간 지도** | 관리자 목록 화면뿐 | ❌ 신규 | 관제 SPA + 실시간 마커 |
| 역할별 필터 | 없음 | ❌ 신규 | 관제 필터 UI |
| 기록 다운로드 | 프로젝트 CSV 내보내기 존재 | ⚠️ 부분 | 위치/지령 이력 포함 확장 |

✅ 재사용 · ⚠️ 부분구현(확장 필요) · ❌ 신규개발

## 있는 것 (재사용 자산)

- **인증/회원**: `app/Providers/FortifyServiceProvider.php`(전화/이메일 분기 로그인), `SocialController`(카카오·네이버), Sanctum API 토큰(`User` `HasApiTokens`).
- **프로젝트(행사)**: `app/Models/Project.php` — slug 자동생성, 기간기반 status(pending/active/completed), 종료일 자동 비활성, QR코드(`endroid/qr-code`), 복제, CSV 내보내기(`ProjectController`).
- **신고 도메인**: `app/Models/Request.php`(좌표 decimal, 상태/우선순위 Enum 캐스팅, 생성 시 `RequestCreated` 디스패치), `app/Services/RequestService.php`(생성/수정/취소/배정 로직), `app/Enums/RequestStatus.php`(라벨/뱃지 헬퍼 포함).
- **신고 UI**: `public/js/components/RequestMapApp.js`(카카오맵 위치선택·역지오코딩·주소검색·신고전송), `mapHelpers.js`, `RequestShowApp.js`.
- **API 골격**: `routes/api.php` + `app/Http/Controllers/Api/RequestApiController.php` + `response()->success()/error()` 매크로(`AppServiceProvider`).
- **이벤트 골격**: `app/Events/RequestCreated.php`가 이미 `ShouldBroadcast` 구현 + `broadcastWith()`/`broadcastAs('request.created')` 정의됨 → **Reverb만 붙이면 즉시 동작**.

## 없는 것 (신규 개발)

1. **실시간 인프라**: `config/broadcasting.php` 미게시, Reverb/Echo/Pusher 미설치, `BROADCAST_CONNECTION=log`. WebSocket 자체가 없음.
2. **지속 위치 추적**: 위치 이력/현재위치 테이블 없음. 신고와 무관한 평상시 위치 공유 개념 자체가 없음.
3. **지령(Dispatch) 도메인**: 구급대원에게 "지령"을 보내고 수락/출동/도착/완료로 전이하는 상태머신 없음. 현재는 `assigned_rescuer_id` 단일 필드 + 전체 status뿐.
4. **행사별 역할 체계**: 한 사람이 행사 A에선 구급대, 행사 B에선 참가자일 수 있어야 함. 현재 spatie 전역 역할은 이를 표현 못 함.
5. **웹 관제 화면**: 전 인원 실시간 마커, 역할 필터, 지령 배정 UI, 출동 현황 보드. 현재 관리자 화면은 정적 목록(`resources/views/admin/*`).
6. **신고 유형 정식화**: 사고/고장/기타가 `description` 텍스트에만 존재 → 통계·필터 불가.

## 바꿀 것 (마이그레이션/리팩터)

- `requests`: `type`(신규 enum), `dispatch` 관계 추가. 기존 `priority`(low~critical)는 유형과 분리 유지하되 자동 매핑(사고→high 등) 고려.
- `RequestCreated` 알림 경로: 디스코드 동기 호출(`NotifyRescuers`의 `file_get_contents`)은 유지하되, **실시간 푸시는 Reverb 채널**로 이동. 큐(`ShouldQueue`)는 현재 주석처리됨 → 재활성 필요.
- `bootstrap/app.php`: 브로드캐스트 라우트(`channels.php`) 등록, `withBroadcasting` 추가.
- 신고 확인 팝업: `RequestMapApp.js`의 단순 `confirm()` → 주소를 보여주는 "이 위치가 맞습니까?" 모달로 교체.

## 데이터 정합성 주의

- 신고 **스냅샷 불변성**: 신고 당시 lat/lng/address는 이후 위치 ping과 무관하게 고정 보존돼야 함(요구 8번). 현재 `requests.latitude/longitude`가 그 역할 → 위치추적 테이블과 **물리적으로 분리** 유지.
- 한 사용자가 여러 행사에 동시 소속 가능 → 위치 ping·역할은 **행사(project) 스코프**로 키잉.
