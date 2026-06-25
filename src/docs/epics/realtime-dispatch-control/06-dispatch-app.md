# 06. 앱2 — 관리자·구급대원용 (PWA)

대상: 관리자/상황실(controller), 구급대원(paramedic·volunteer_medic). 앱1과 동일 PWA 셸, 역할로 화면 분기.

## 구급대원 화면

### 1. 지령 수신
- `event.{projectId}.dispatch.{userId}` 구독 → `dispatch.assigned` 수신 시 **풀스크린 알림 + 알림음/진동**.
- 카드 정보: 신고 유형(사고/고장/기타), 신고 위치(주소+지도), 신고자 정보(이름·연락처), 상황실 메모.

### 2. 지령 상태 변경 (상태머신)
요구: "수락 / 출동 / 도착 / 완료 상태 변경". `DispatchStatus` 전이를 버튼으로.

```
[수락]  → accepted   (accepted_at)
[출동]  → en_route   (en_route_at)   + 카카오내비 자동 안내 제안
[도착]  → arrived    (arrived_at)
[완료]  → completed  (completed_at)  + 처리 메모 입력
[거절]  → rejected   (배정/수락 단계에서만, 사유 필수)
```
- 각 전이: `PATCH /api/dispatches/{id}/status { status, note? }` → `DispatchService`가 전이 검증 → `DispatchStatusUpdated` 브로드캐스트 → 관제·신고자 동기화.
- 연결 신고 상태 자동 동기화: accepted/en_route/arrived→`in_progress`, completed→`completed`(03 문서).

### 3. 길안내 (카카오내비/카카오맵)
- "출동" 시 신고 **고정 좌표**로 카카오내비 딥링크(`kakaonavi://navigate?...`) 또는 카카오맵 길찾기 웹 링크. 앱 미설치 시 웹 폴백.
- 신고자 실시간 위치가 아니라 **신고 시점 스냅샷 좌표**로 안내(요구 8·11번 — 현장 고정).

### 4. 내 위치 공유
- 구급대원도 참가자처럼 위치 ping 송신(관제가 가용 인력 위치 파악). 앱1 위치공유 모듈 공유.

## 관리자/상황실 화면 (모바일)
- 웹 관제([07](07-web-control.md))의 축약형. 신고 목록·지령 배정·출동 현황을 모바일에서.
- 데스크톱 부재 시 현장 지휘용. 핵심: 신고 접수 알림 + 빠른 지령 배정.

## 지령 배정 흐름 (상황실 관점)
```
신고 접수(request.created) → 관제/앱 알림
   → 가용 구급대원 목록(역할=paramedic/volunteer_medic, online, 거리순)
   → 대상 선택 + 메모 → POST /api/requests/{id}/dispatch { paramedic_id, note }
   → Dispatch 생성(status=assigned) → DispatchAssigned 브로드캐스트(해당 구급대원)
```
- 권한: `controller` 또는 시스템 `admin`만 배정(미들웨어 `event.role:controller`).
- 재배정/회수: 거절(rejected) 또는 무응답 시 다른 대원에게 재지령.

## 신규 서비스/컨트롤러
- `App\Services\DispatchService` — 배정/전이/재배정/검증(`RequestService` 패턴).
- `App\Http\Controllers\Api\DispatchApiController` — `assign`, `updateStatus`, `index`(내 지령).
- 응답은 `response()->success()/error()` 매크로 사용(기존 규약).

## 수용 기준 (발췌)
- [ ] 구급대원이 지령을 실시간(WS)으로 받고 알림이 울린다.
- [ ] 수락→출동→도착→완료 전이가 검증되며 잘못된 전이는 거부된다.
- [ ] 전이 시 관제와 신고자 화면이 실시간 갱신된다.
- [ ] "출동"에서 카카오내비로 신고 고정좌표 길안내가 열린다.
- [ ] 거절/무응답 시 재지령이 가능하다.
