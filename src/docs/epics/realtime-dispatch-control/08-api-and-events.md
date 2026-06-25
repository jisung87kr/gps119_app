# 08. API · 브로드캐스트 이벤트 명세

기존 규약 준수: `auth:sanctum`, 응답은 `response()->success($data,$msg,$code)` / `response()->error($msg,$code)` 매크로(`AppServiceProvider`). 모든 신규 API는 **행사(project) 스코프** + 역할 가드.

## REST API (신규/변경)

### 행사 입장
| 메서드 | 경로 | 설명 | 가드 |
|--------|------|------|------|
| GET | `/api/events/{joinCode}` | 행사 정보(입장 전 미리보기) | auth |
| POST | `/api/events/{joinCode}/join` | 입장(역할 부여) | auth |
| GET | `/api/events/{id}/me` | 내 참가정보/역할/상태 | auth + 참가자 |

### 위치
| 메서드 | 경로 | 설명 | 가드 |
|--------|------|------|------|
| POST | `/api/events/{id}/location` | 위치 ping 전송 | auth + active 참가자 |
| GET | `/api/events/{id}/participants` | 전 인원 최신위치/역할/online (관제 초기로드·폴백) | event.role:controller |
| PATCH | `/api/events/{id}/sharing` | 위치공유 on/off | auth + 참가자 |

### 신고 (기존 확장)
| 메서드 | 경로 | 변경 |
|--------|------|------|
| POST | `/api/requests` | body에 `type`(RequestType) 추가, `project_id` 필수화(행사 신고) |
| GET | `/api/events/{id}/requests` | 행사별 신고 목록(관제) — 신규 |
| GET | `/api/requests/{id}` | 담당 지령 정보 포함 |

> 기존 `routes/api.php`의 `/api/requests/{id}/assign`(GET)은 비표준 → 아래 dispatch로 대체/폐기.

### 지령 (신규)
| 메서드 | 경로 | 설명 | 가드 |
|--------|------|------|------|
| POST | `/api/requests/{id}/dispatch` | 지령 배정 `{paramedic_id, note}` | event.role:controller |
| PATCH | `/api/dispatches/{id}/status` | 전이 `{status, note?}` (accept/en_route/arrived/completed/reject) | 해당 구급대원 또는 controller |
| GET | `/api/dispatches/mine` | 내 지령 목록 | event.role:paramedic |
| GET | `/api/events/{id}/dispatches` | 출동 현황 보드 | event.role:controller |

### 리포트
| 메서드 | 경로 | 설명 |
|--------|------|------|
| GET | `/api/events/{id}/report/requests.csv` | 신고 기록 |
| GET | `/api/events/{id}/report/dispatches.csv` | 지령 타임라인 |
| POST | `/api/events/{id}/report/tracks` | 동선(대용량) 비동기 생성 → 다운로드 링크 |

## 브로드캐스트 이벤트

| 이벤트 | broadcastAs | 채널 | 페이로드(요약) |
|--------|-------------|------|----------------|
| `RequestCreated`(변경) | `request.created` | `event.{id}.control` | request_id, type, lat/lng, address, 신고자(이름·연락처), 시각 |
| `RequestStatusUpdated` | `request.status.updated` | `event.{id}.requester.{userId}` | request_id, status, 담당자(이름·연락처) |
| `DispatchAssigned` | `dispatch.assigned` | `event.{id}.dispatch.{userId}` | dispatch_id, request 요약, 메모 |
| `DispatchStatusUpdated` | `dispatch.updated` | `event.{id}.control` | dispatch_id, status, 시각, paramedic_id |
| `ParticipantLocationUpdated` | `participant.location` | `event.{id}.locations`/`control` | user_id, role, lat/lng, recorded_at |

채널 인가(`routes/channels.php`): 구독자가 행사에 `active`로 속하고 채널 역할조건 충족 시 허용.

## 검증 규칙 (요지)
- 위치 ping: lat∈[-90,90], lng∈[-180,180], `recorded_at` 미래 불가, rate-limit(예: 초당 1).
- 신고 `type`: `Rule::enum(RequestType::class)`. 좌표는 기존 store 규칙 계승(`RequestApiController::store`).
- 지령 전이: `DispatchStatus`의 허용 전이표 기반. 위반 시 422 + 명확 메시지.

## 미들웨어
- `bootstrap/app.php` `$middleware->alias()`에 `'event.role' => EnsureEventRole::class` 추가(기존 `admin` 옆).
- `EnsureEventRole`은 라우트의 `{id}`(project)와 인증 사용자의 `event_participants`(active) 역할을 대조.

## OpenAPI/문서화
- `PROMPT.MD` 규약대로 Swagger 자동생성 도입(`darkaonline/l5-swagger` 등) — 신규 API 어노테이션. (현재 미설치, 도입 권장)
