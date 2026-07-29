# 05. 앱1 — 참가자용 (PWA)

대상: 참가자, 운영진, 경찰, 자원봉사자, 구급대 (현장에서 움직이는 전 인원). 역할에 따라 추가 화면(구급대=지령 수신)은 [06](06-dispatch-app.md)으로 분기.

## 화면/플로우

### 1. 입장
- **행사 코드 입력** 또는 **QR 스캔** → `GET /api/events/{joinCode}` 행사 정보 표시.
- 미로그인 → 로그인/소셜로그인(기존 `SocialController` 재사용) 후 복귀.
- `POST /api/events/{joinCode}/join` → `event_participants` 등록. 전화번호 없으면 입력 유도(기존 `errors.require-phone` 정책 계승).
- **역할 표시/선택**: 기본 참가자. 사전명단 매칭 시 자동 역할, 권한 역할은 승인대기(`pending`) 안내.

### 2. 위치 공유 (자동 시작)
- 입장 후 **위치 공유 자동 시작**(요구 흐름 2번). 권한 팝업 → 허용.
- `navigator.geolocation.watchPosition` → 적응형 주기로 `POST /api/events/{id}/location`(04 문서 파이프라인).
- 상단에 공유 상태 토글(개인정보: 끄면 `event_participants.sharing_location=false`, 신고 시에는 일시 강제 on).
- 기존 `public/js/components/mapHelpers.js`(역지오코딩 등) 재사용.

### 3. 신고 (사고/고장/기타)
요구 핵심 흐름 3~8을 그대로 구현. 기존 `RequestMapApp.js`를 확장.

```
[사고🔴 / 고장🟡 / 기타⚫ / 긴급전화🔴] 버튼
   │ 클릭
   ▼
GPS 현재 위치 수집(getCurrentPositionOnce) → 역지오코딩(주소 변환)
   ▼
"이 위치가 맞습니까?" 확인 모달  ← 주소 + 지도 미리보기 표시 (기존 단순 confirm 대체)
   │  [확인]
   ▼
POST /api/requests { type, latitude, longitude, address, project_id, contact_phone }
   ▼
신고 시점 위치 고정 저장(스냅샷) + RequestCreated 브로드캐스트
   ▼
신고 접수 완료 화면 → 담당자 전화 연결 버튼(tel:) 노출
```

- **신고 유형**: 버튼이 `type`(`RequestType` enum)을 정식 전송. 기존엔 `description` free-text였음 → 변경.
- **주소 확인 팝업**: `RequestMapApp.js`의 `if(!confirm('위치공유를 하시겠습니까?'))`(146행)를 주소를 보여주는 모달로 교체. 지도에서 보정도 가능.
- **긴급전화**: 즉시 `tel:` 딥링크(행사 구조본부 번호, `projects.settings`에 저장).

### 4. 신고 후 담당자 전화 연결
- 신고 생성 응답 또는 `request.status.updated` 이벤트로 **담당 구급대원 배정** 수신 시, 상세 화면에 담당자 이름 + **전화 연결 버튼**(`tel:` 딥링크) 노출(요구: "신고 후 담당자 전화 연결").
- 배정 전에는 행사 상황실 대표번호로 연결.

### 5. 내 신고 상태 추적
- `event.{projectId}.requester.{userId}` 구독 → 대기→진행중→완료 실시간 갱신.
- 신고 위치를 카카오맵으로 보기 / 카카오내비 길안내(기존 show 화면 확장).

## 재사용 / 신규

| 항목 | 기존 자산 | 작업 |
|------|-----------|------|
| 지도·역지오코딩·주소검색 | `RequestMapApp.js`, `mapHelpers.js` | 확장 |
| 신고 전송 | `POST /api/requests`, `RequestApiController::store` | `type` 추가 |
| 주소 확인 팝업 | `confirm()` (146행) | 모달로 교체 |
| 위치 공유 | — | 신규(watchPosition + ping API) |
| 입장 | 프로젝트 slug 진입 | 코드/QR 입장 신규 |
| 실시간 상태 | — | Echo 구독 신규 |

## PWA 요건
- `manifest.json` + 서비스워커(설치형, 오프라인 셸). Vite PWA 플러그인 도입.
- 홈 화면 추가 유도. 위치권한·알림권한 온보딩.
- 푸시: 초기엔 앱 활성 시 Reverb 인앱 알림. 백그라운드 푸시는 하이브리드 단계(FCM)로.

## 수용 기준 (발췌)
- [ ] 코드/QR로 행사 입장, 역할이 부여된다.
- [ ] 입장 즉시 위치 공유가 시작되고 관제에 마커가 뜬다.
- [ ] 사고/고장/기타 신고 시 "이 위치가 맞습니까?"에 **주소가 표시**된다.
- [ ] 신고 좌표는 이후 이동과 무관하게 고정 저장된다.
- [ ] 신고 후 담당자(또는 상황실) 전화 연결 버튼이 동작한다.
