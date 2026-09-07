# 현장 피드백 2026-09 — 판정과 작업 트래커

> 2026-09-07 에 고객(행사 주최 측)이 앱 QA 를 마치고 보낸 15건. 각 항목을 **웹(이 저장소) / 앱 셸 / 재확인**으로 갈라 판정했다.
> 작업은 **이 저장소에서** 진행한다. 셸 수정이 필요한 항목은 `~/Dev/gps119_app_mobile` 의 파일을 가리켜 두었다 — 그 저장소를 열어 고치고, 여기 표의 상태를 갱신한다.

- 작성일: 2026-09-07
- 운영에 떠 있는 것: 웹 `308da4d`(**2026-09-07 배포 8회**, PR #34~#55, `DEPLOY.md` §0) · iOS **빌드 5 업로드 2026-09-07 23:46**(빌드 3 사이렌 · 빌드 4 새로고침 실기기 확인 · App Store 는 빌드 2 제출 상태) · Android 는 아직 Play 미제출(사이드로드)
- 관련: [`mobile-app`](../mobile-app/README.md) · [ADR-0009](../../adr/0009-admin-issued-operator-accounts.md) · [ADR-0002](../../adr/0002-event-scoped-roles.md)

## 0. 한눈에

| ID | 고객 원문(요약) | 판정 | 저장소 | 상태 |
|---|---|---|---|---|
| F-01 | 앱 설치 시 실시간 관제에 위치 표시되나 | **된다** — 이미 있는 기능 | — | ✅ 09-07 답변 완료 |
| F-02 | 백그라운드 위치 공유되나 | **된다**, OS 별 전제 있음 — 이미 있는 기능 | — | ✅ 09-07 답변 완료 |
| F-03 | 아이폰 새로고침 방법 | 앱엔 양 OS 모두 없음. iOS 당겨서 새로고침 추가 | 셸 | ✅ 빌드 4 실기기 확인(09-07 23:15). 빌드 3 은 Capacitor 가 바운스를 꺼 둬 실패 |
| F-04 | 일괄 회원가입 ID·PW = 전화번호 | **09-04 배포로 이미 있음**. PW 만 `password`→전화번호 | 웹 | ✅ 09-07 구현 |
| F-05 | 역할 추가(구급대·회송팀·공무원·참가자·자원봉사자·경찰·상황실) | 회송팀·공무원 신설. 자원봉사자 통합은 **고객 확인 필요** | 웹 | ✅ 09-07 구현(신설 2종 + 「자원봉사자」 별칭) |
| F-06 | 역할 배정 시 이름 검색 | 관제 패널은 09-04 배포로 있음. **관리자 「참가자 추가」 셀렉트**가 문제였을 가능성이 큼 → 이름 검색 피커로 교체 | 웹 | ✅ 09-07 구현 |
| F-07 | 알람 소리 변경(안 들림) | ① 푸시음 교체(셸+웹 «한 쌍») ② 지령 화면 자체 알림음 | 셸+웹 | ①② ✅ · ① **빌드 3 실기기에서 사이렌 확인**(09-07 23:20) |
| F-08 | 역할 배정 정렬: 역할 → 가나다 | 관제 패널은 서버 순서 그대로. 정렬 추가 | 웹 | ✅ 09-07 구현(관제 패널 + 관리자 페이지) |
| F-09 | Android: 도착을 눌러야 카카오맵, 출동으로 바꿔달라 | 코드는 처음부터 «출동»에서 띄움 — **실기기 재현 필요** | 재확인 | ☐ |
| F-10 | iOS: 길안내 눌러도 카카오맵 안 됨 | **원인 확정** — 폴백 `window.open` 을 WKWebView 가 막음 | 웹 | ✅ 09-07 구현 · 실기기 QA 필요 |
| F-11 | iOS: 알림 켜도 계속 꺼짐으로 표기 | **09-04 배포(`eb2cf8b`)에서 수정 완료** — 피드백은 그 전 빌드 기준 | 웹 | ✅ 09-04 수정·배포 |
| F-12 | iOS: 신고자 입장일 때 위치 동의 팝업 반복 | **원인 확정** — 신고 화면이 브라우저 geolocation 을 써서 WKWebView 가 페이지마다 다시 묻는다 | 웹(+선택 셸) | ✅ 09-07 A안 구현 · 실기기 QA 필요 |
| F-13 | (현장 발견) 「알림 받는 중」인데 지령 안 옴 | 켜짐 기록·구독이 계정이 아니라 기기에 묶여 있었다 — 다른 계정이 먼저 쓴 기기 | 웹 | ✅ 09-07 구현·배포 `ea993dc` |
| F-14 | (현장 발견) 운영 iOS 앱 푸시 전부 실패 | FCM 401 `THIRD_PARTY_AUTH_ERROR` / APNs `BadEnvironmentKeyInToken` — Firebase 의 APNs 키가 sandbox 전용 | **콘솔**(Apple·Firebase) + 로깅 | ✅ 09-07 22:10 키 교체(`5YP6MW3RP8`) → 22:25 지령 #51 **실기기 수신·소리 확인** · 로깅 PR #39 |
| F-15 | (현장 발견) 소리·배너가 안 남 — 설정 → 알림 목록에 GPS119 만 「배지」 | **뱃지 플러그인 `clear()` 가 iOS 에서 `.badge` 만으로 권한을 요청**하고, 웹이 앱을 열 때마다 그걸 불러 새 설치의 첫 프롬프트가 배지뿐이 됐다. 그 뒤 alert·sound 요청은 iOS 가 무시 | 웹 | ✅ 09-07 PR #43 배포 `d5c8694` · 22:40 기기 재설치 후 「배너, 사운드, 배지」 확인, `default`·`rescue_alarm.caf` 둘 다 소리 남 |
| F-16 | (현장 발견) 푸시 아이콘이 프레임워크 기본값 | Android: 포그라운드 로컬 알림에 `smallIcon` 을 안 넘겼다. **iOS: 그 아이폰이 빌드 1**(아이콘 교체 09-01 이전) — 재설치 때 TestFlight 의 옛 빌드가 깔림 | 웹(+셸 기본값) · 기기 | ✅ 09-07 PR #46 `9f83a30` · iOS 는 빌드 2 설치로 해결 · 등록에 `app_version` 을 실어 이제 서버에서 빌드가 보인다(PR #48 `f45b913`) |

배포 단위로 다시 묶으면:

- **웹만 배포(즉시)**: F-04, F-05, F-06(관리자 페이지), F-07 ② 지령 화면 알림음, F-08, F-10, F-12 — ✅ **2026-09-07 전부 구현, 브랜치 `feat/field-feedback-2026-09`** (PHP 534 · JS 405 통과, Pint 통과, Vite 빌드 통과 — 실행은 전부 컨테이너 안). 실기기 QA 는 F-07 ②·F-10·F-12
- **셸 스토어 재배포**: F-03, F-07 ① — ✅ **2026-09-07 구현, 셸 브랜치 `feat/field-feedback-2026-09`**(versionCode 2 · iOS 빌드 3). 선택 항목 F-12 B안·F-10 `@capacitor/app-launcher` 는 남김
- **고객 재확인 먼저**: F-09, 그리고 F-05 자원봉사자 통합 여부

🔑 **웹은 배포하면 즉시 반영되고 셸은 심사를 거친다.** 웹만으로 되는 것을 먼저 내고, 셸 변경은 한 번에 묶어 낸다(Android Play 첫 제출과 iOS 빌드 3).

---

## 1. 문의 — 답변만 하면 되는 것

### F-01. 앱이 깔린 참가자의 위치가 관제에 실시간으로 뜨는가

**된다.** 조건 셋 — 입장 QR 로 행사에 들어오고, 위치정보 동의를 하고, 활동 화면에 머문다. 입장 시 `sharing_location` 이 기본 켜짐(`EventParticipantService::joinByCode`)이고 관제는 Reverb 로 즉시 갱신된다. 웹으로 들어와도 같지만 화면을 끄면 웹은 끊기고 앱만 이어진다(F-02).

### F-02. 백그라운드에서 위치가 공유되는가

**된다.** 단 OS 마다 전제가 있고, 그 전제를 앱이 화면에서 안내한다.

| OS | 전제 | 근거 |
|---|---|---|
| Android | 「항상 허용」 + **배터리 최적화 예외** | 09-01 Galaxy A36 실측: 예외 없이는 화면 끄고 3분간 수신 0건, 예외 후 9건. 앱이 안내 카드를 띄운다(M-26) |
| iOS | 「항상 허용」 | 09-01 iPhone 16 Pro 실측 통과(353m, 최대 42초 간격). 추적 중 파란 상태바가 뜨며 숨길 수 없다 |

「사용 중에만」을 고른 사람은 관제 역할 배정 패널에 **「위치 권한 없음」** 배지로 구분돼 보인다(ADR-0008).

### F-03. 아이폰에서 새로고침

**앱에는 양 OS 모두 당겨서 새로고침이 없다.** 갤럭시에서 되던 것은 Chrome 브라우저의 기능이다. 관제·지령 화면은 실시간 갱신이라 새로고침이 필요 없고, 나머지 화면용으로 iOS 에 붙인다.

- ✅ **2026-09-07 구현** — 셸 `ios/App/App/MainViewController.swift` `installPullToRefresh()`: 웹뷰 스크롤뷰에 `UIRefreshControl`, 당기면 `reload()` 후 0.8초 뒤 스피너 종료.
- 🔴 **빌드 3 실기기: 안 걸림.** Capacitor 가 `scrollView.bounces = false` 로 두어 당겨도 스크롤뷰가 안 움직인다(`CAPBridgeViewController`). **빌드 4**(`bf461b4`)에서 `bounces = true`. `alwaysBounceVertical` 은 일부러 기본(꺼짐) — 문서가 실제로 스크롤되는 목록에서만 걸리고, 높이 고정 지도·신고 화면에서는 안 걸린다(신고 입력 중 실수 새로고침 방지). 짧은 목록은 못 당긴다.
- ⚠️ 실기기 QA 필요: 지도 화면(카카오맵이 터치를 잡아 스크롤뷰가 안 당겨질 것으로 예상)과 `overflow:hidden` 화면(바운스가 없어 안 걸릴 것으로 예상). 예상이지 실측이 아니다. 잘못 걸리면 지도를 끌다 새로고침되는 사고가 된다.
- Android 도 맞추려면 `SwipeRefreshLayout` 로 WebView 를 감싸야 해서 더 크다. 요청이 iOS 뿐이므로 iOS 만 한다.

---

## 2. 기능 추가 · 관제

### F-04. 일괄 회원가입 — ID·PW 를 전화번호로

**이미 있다(ADR-0009, 09-04 배포).** ID 는 전화번호이고, 초기 비밀번호가 전원 `password` 인 것만 다르다. 첫 로그인에서 비밀번호 변경 + 위치정보 동의를 강제한다.

✅ **2026-09-07 구현.** 초기 비밀번호 = 그 사람의 전화번호(숫자만):

- `app/Services/AccountIssueService.php` — `INITIAL_PASSWORD` 상수 제거, `initialPasswordFor($phone)` 이 단일 출처. `issueRow()`·`reissuePassword()` 가 쓴다
- 발급 안내·확인창·재설정 버튼 문구(참가자 페이지·회원 목록·가입 화면 에러) 전부 「전화번호」로
- ADR-0009 D1 개정 기록, `CLAUDE.md` 갱신
- `tests/Feature/AccountIssueTest.php` — 전화번호로 로그인·재설정, 옛 `password` 로는 «못» 들어오는 것까지 판정
- 📌 보안 수준은 지금과 같은 급이다 — 둘 다 「번호를 아는 사람이 아는 값」이고, ADR 이 그 위험을 이미 감수하며 완화책(강제 변경·미로그인 배지·재설정)을 두었다.

⚠️ 「공무원·자원봉사자 일괄 가입」은 명단 CSV 의 역할 열에 그 이름이 **풀려야** 한다 → F-05.

### F-05. 역할 추가

현재 `EventRole` 7종: 참가자·운영진·경찰·자원봉사자(코스)·자원봉사자(구급)·구급대·상황실. 고객 표와의 차이:

| 고객 표 | 지금 | 판정 |
|---|---|---|
| 구급대 — 지령 수신·내비 | `paramedic` | 있음 |
| **회송팀** — 지령 수신·내비 | 없음 | **신설** `transport`. `canReceiveDispatch()`·`isDispatchCandidate()` 에 포함 → 후보 판정이 enum 단일 출처라 역할만 넣으면 지령을 받는다(`DispatchService`·`User::isDispatchCandidate`) |
| **공무원** — 사고신고 | 없음 | **신설** `official`. 권한은 `staff` 와 같음 |
| 참가자·경찰·상황실 | 있음 | 그대로 |
| **자원봉사자** — 사고신고 | 코스·구급 둘로 갈려 있음 | 🔴 **고객 확인 필요.** 명단 CSV 에 「자원봉사자」라고 쓰면 `ParticipantImportService::resolveRole()` 이 라벨 완전일치라 **「알 수 없는 역할」로 거절된다.** 선택지: ① 둘 유지 + 「자원봉사자」를 코스의 별칭으로 ② 하나로 통합(기존 행 데이터 이관 필요, `volunteer_medic` 의 지령 화면 접근권도 사라짐) |

✅ **2026-09-07 구현** — `official`(공무원, cyan-600, 건물 아이콘) · `transport`(회송팀, orange-600, 차량 아이콘):

- `app/Enums/EventRole.php` — 선언 순서: 참가자 → 운영진 → **공무원** → 경찰 → 자원봉사자(코스) → 자원봉사자(구급) → 구급대 → **회송팀** → 상황실. 회송팀은 `canReceiveDispatch`·`isDispatchCandidate` 둘 다 참
- 🔑 역할 목록 사본 둘을 없앴다 — `EventParticipant::scopeReceivers/scopeDispatchCandidates` 가 값을 직접 적고 있었고(`EventRole::dispatchReceiverValues()/dispatchCandidateValues()` 로), 관제의 「구급만」 칩이 JS 에 `['paramedic','volunteer_medic']` 을 갖고 있었다(`mapMeta()` 의 `receivesDispatch` 로). 둘 다 그대로 뒀으면 회송팀은 후보인데 목록·칩에서 빠졌다
- `roleMeta.js` `ROLE_ICONS`+`ICON_PATHS`, control-map-spec §2, `ADMIN_MANUAL.md` 색 표, `CLAUDE.md`, `02-roles-and-access.md` 주석
- **명단 CSV 별칭**(`ParticipantImportService::ROLE_ALIASES`): 「자원봉사자」「자원봉사」→ 코스, 「회송」「회송반」→ 회송팀, 「구급대원」→ 구급대. ⚠️ 「자원봉사자」= 코스는 **가정**이다(고객 표에서 자원봉사자는 사고신고만 하고, 그건 코스 쪽) — §5 확인 후 통합(②)으로 가면 데이터 이관 1건이 붙는다
- 테스트: `EventRoleMapMetaTest`(9종·`receivesDispatch` 주입), `DispatchCandidateTest`(회송팀이 후보 목록에 뜨고 배정된다), `AdminParticipantImportTest`(별칭), `roleMeta.test.js`
- 마이그레이션 없음(문자열 컬럼)

### F-06. 역할 배정 시 이름 검색

**관제 역할 배정 패널에는 09-04 배포로 이미 있다**(`ControlApp.js` `rosterQuery` → `rosterSearch.js`, 이름·역할 라벨 검색, 전화번호는 ADR-0004 로 제외). 고객 피드백이 배포 전 테스트였을 가능성이 크다 — 재확인.

✅ **2026-09-07 구현 — 관리자 페이지 「참가자 추가」.** 고객 원문이 「참가자 **추가**(역할배정 할 때)」라 관제 패널이 아니라 이 화면일 가능성이 크다. 회원 «전체» `<select>` 였던 것을 이름 검색 피커(Alpine, 페이지에 실린 데이터로 클라이언트 필터, 20명 초과 시 「더 입력」 안내)로 바꿨다. 실리는 데이터는 이름 + 전화 뒤 4자리뿐이다(`PhoneMaskingTest` 유지). 테스트: `AdminParticipantPageTest`.

### F-07. 알람 소리 — 안 들림

소리가 **둘**이다.

| # | 무엇 | 지금 | 왜 안 들리나 |
|---|---|---|---|
| ① | 푸시 알림음 | Android 채널 `gps119-rescue-v1` 기본음 · iOS `sound: default`(`PushMessage::toFcmPayload`) | 기본 알림음이 짧고 작다 |
| ② | 지령 화면 자체 알림음 | `dispatch/index.blade.php` 의 WebAudio 삐(880Hz·0.6초·gain 0.3) | 작고, **첫 터치 전엔 AudioContext 가 잠겨 아예 안 난다** — 푸시를 탭해 들어온 직후가 정확히 그 상태 |

① 은 **셸과 웹이 한 쌍**이다(time-sensitive 때와 같다 — 한쪽만 나가면 조용히 아무 일도 안 일어난다):

✅ **2026-09-07 구현(셸 + 웹).**
- 셸 Android: `res/raw/rescue_alarm.wav`(외부 음원 없이 합성한 1200/800Hz 2.5초 사이렌), `MainActivity.createRescueNotificationChannel()` 이 **`gps119-rescue-v2`** 를 `setSound(…, USAGE_ALARM)` 으로 만들고 v1 은 지운다. 알람 볼륨·진동 모드에서도 울린다 — 판단이며, 되돌리려면 v3 + usage 변경
- 셸 iOS: `ios/App/App/rescue_alarm.caf` 번들 + `project.pbxproj` 등록(BuildFile·FileReference·그룹·Resources 네 곳)
- 웹: `PushMessage::ANDROID_CHANNEL_ID`(v2)·`IOS_SOUND`(rescue_alarm.caf) 를 페이로드에 항상 싣는다(tag 블록이 android 를 통째 대입하던 것도 하위 키로). `push-native.js` 는 채널 id 를 적지 않고 `listChannels()` 로 **있는 것 중 최신**을 고른다 — 원격 URL 번들이라 v1 만 있는 구버전 앱에서도 돌기 때문
- 🔑 **따로 나가도 조용히 죽지 않게 했다.** 웹이 먼저 나가면 구버전 앱은 FCM 이 매니페스트 기본 채널(v1)로 떨어뜨리고 iOS 는 기본음 — heads-up 은 산다. 셸이 먼저 나가면 웹의 `listChannels` 가 v2 를 골라 로컬 알림은 맞고, FCM 만 v1(지워진 채널) → 기본 채널로 떨어져 heads-up 이 «그 사이» 약해진다. 그래서 **웹을 먼저** 배포한다
- 테스트: `FcmSenderTest`(채널 id·사운드), `pushNative.test.js`(v2/v1/실패 폴백). ⚠️ 실기기 QA 필요 — 소리 크기·진동 모드·잠금화면
- 📌 **실측(2026-09-07 22:40, iPhone 빌드 2)**: 번들에 없는 `rescue_alarm.caf` 를 보내도 iOS 가 **기본음으로 대체**한다(무음 아님). 그래서 웹이 먼저 나가도 구버전 iOS 는 기본음으로 울린다 — 「웹 먼저」 순서의 iOS 쪽 근거가 실측으로 확인됐다. 사이렌 자체는 빌드 3 부터
- 📌 iOS 무음 모드를 뚫는 「긴급 알림(critical alert)」은 Apple 별도 승인 항목이라 범위 밖. 요청이 오면 그때.

② ✅ **2026-09-07 구현(웹).** `dispatch/index.blade.php` — 1200/800Hz 사각파를 0.25초씩 8번(≈2초) 사이렌, 음량 0.6. 회수는 짧게 세 번으로 구분. 울리기 전 `resume()`, 첫 탭/터치에서 **무음**으로 잠금 해제(예전엔 첫 탭에 삐 소리). ⚠️ 실기기 QA 필요 — 잠금화면·무음 스위치에서는 OS 푸시음(①)만이 답이다.

### F-08. 역할 배정 정렬 — 1순위 역할, 2순위 가나다

관제 역할 배정 패널의 `filteredRoster` 는 서버가 준 순서 그대로다.

✅ **2026-09-07 구현.**
- `rosterSearch.js` `sortRoster()` — 서버가 준 `ROLE_ORDER` 인덱스 → 이름 `localeCompare('ko')`, 모르는 역할·이름 없는 행은 뒤로, 안정 정렬. `ControlApp.filteredRoster` 가 검색 뒤에 적용. Vitest 5건.
- 관리자 페이지 — `EventRole::orderByDeclarationSql()`(CASE, sqlite·MySQL 공용)로 참가자 목록·입장 대기 명단 모두 선언순 → 이름. `AdminParticipantPageTest`.

---

## 3. 버그

### F-09. Android — 「도착」을 눌러야 카카오맵이 뜬다

**코드로는 설명되지 않는다.** `dispatch/index.blade.php` `transition()` 은 처음 구현(146477c)부터 `en_route`(출동 버튼) 전이에서 `openKakaoNavi()` 를 부르고, 「길안내」 버튼도 거절 외 모든 카드에 있다.

다만 `public/js/components/kakaoNavi.js` 는 **카카오내비**(`kakaonavi://`)만 부르고, 1.2초 안에 앱 전환이 없으면 카카오맵 **웹 링크**로 떨어진다. 카카오내비가 없고 카카오맵만 있는 기기라면 「출동」 뒤 1.2초 지연 후 외부 브라우저/카카오맵으로 가는 경로다.

- ☐ 고객에게 확인: 그 기기에 카카오내비가 있는지, 카카오맵만 있는지
- ☐ 실기기 재현. 재현되면 원인 기록 후 F-10 과 같은 수정으로 흡수

### F-10. iOS — 길안내를 눌러도 카카오맵이 안 된다

**원인 확정.** `kakaoNavi.js` 는 `window.location.href = 'kakaonavi://…'` 를 찌른 뒤 1.2초 후 `window.open(map.kakao.com…, '_blank')` 로 폴백한다. Capacitor iOS 는 커스텀 스킴을 `UIApplication.open` 으로 넘기므로 **카카오내비가 깔린 아이폰은 된다.** 없으면 조용히 실패하고, 폴백 `window.open` 은 `setTimeout` 안(사용자 제스처 밖)이라 **WKWebView 가 팝업으로 막는다**(Capacitor 는 `javaScriptCanOpenWindowsAutomatically` 를 켜지 않는다). 카카오맵 스킴은 시도조차 안 한다. → 카카오내비 없는 아이폰에서 「아무 일도 안 일어남」.

✅ **2026-09-07 구현(웹만).** `public/js/components/kakaoNavi.js` 재작성 — 앱 안에서는 **카카오내비 → 카카오맵(`kakaomap://route?ep=lat,lng&by=CAR`) → 웹 링크** 체인.

🔴 **처음 계획했던 `App.openUrl` 은 셸의 `@capacitor/app` 8.1.1 에 없다**(그 메서드는 `@capacitor/app-launcher` 몫, 구현 중 확인). 그래서 지금 셸에서는 «`location.href = 스킴` + 1.2초 안에 앱 전환(visibilitychange) 감지» 체인으로 간다. Capacitor 가 스킴·외부 https 를 가로채 OS 에 넘기므로 지령 화면은 남고, 마지막 웹 링크도 `location.href` 라 제스처 없이도 안 막힌다(예전 `window.open` 이 막힌 지점). 셸에 `AppLauncher` 가 들어오면 `completed` 로 확정 폴백하는 경로가 자동으로 켜진다(코드에 이미 있음 — §4 선택 항목). 브라우저는 예전 동작 그대로. Vitest 20건.

- ⚠️ **실기기 QA 필요**: 아이폰에 카카오내비 없음 / 카카오맵만 / 둘 다 없음 세 경우. 특히 `kakaomap://route` 의 `ep`-only 형식과 `visibilitychange` 발화 시점은 실기기로만 확인된다. Android 도 같은 체인이라 F-09 가 같이 닫힐 가능성이 크다.

### F-11. iOS — 설정에서 알림을 켜도 계속 「꺼짐」

✅ **09-04 배포(`eb2cf8b`)에서 수정 완료 — 이 피드백은 그 전 빌드 기준이다.** 켜짐 기록이 모듈 변수뿐이라 화면을 옮기면 「알림 꺼짐」으로 읽혔다(원격 URL 셸은 이동마다 새 번들). 지금은 `localStorage` 에 남기고 다른 페이지에서 끌 때 토큰을 다시 받아 서버에 알린다.

- 📌 별건으로 남는 것: `DEPLOY.md` §5 「실기기 푸시 종단 검증(운영 서버 대상)」이 미완이다 — 앱 푸시는 개발 서버로만 검증됐다. §6-4 에 둔다.
- 다시 같은 말이 오면 용의자: iOS 에서 APNs 토큰 확보 전 `getToken()` 실패 → 화면엔 「알림 등록에 실패했습니다. 네트워크를…」 문구가 뜬다(꺼짐 표기와는 다르다).

### F-12. iOS — 신고자 입장일 때 위치 동의 팝업이 계속 뜬다

**원인 확정.** 신고 화면(`RequestMapApp.js`·`RequestShowApp.js`)이 `mapHelpers.getCurrentPositionOnce()` 로 브라우저 `navigator.geolocation` 을 쓴다. WKWebView 는 OS 권한과 **별개로** 사이트 단위 동의(「gps119.co.kr 이(가) 위치를 사용하려고 합니다」)를 **페이지를 열 때마다** 다시 묻고, 공개 API 로 자동 승인할 수 없다. 이 앱은 페이지 단위 이동이라 매번 뜬다. Android 는 Capacitor 가 OS 권한을 받은 뒤 WebView 에 자동 승인해서 안 뜬다. 활동 화면의 공유는 네이티브 플러그인(`locationTracker.js`)이라 안 뜨고 **신고 화면만** 문제다.

| 안 | 내용 | 배포 |
|---|---|---|
| **A ✅ 09-07 구현** | `resources/js/native/currentPosition.js` — iOS 앱 + 플러그인 + **OS 권한이 이미 있을 때만** `addWatcher`(requestPermissions:false, stale 허용하되 `maximumAge` 로 거름) → 첫 fix → `removeWatcher`. `app.js` 가 `__gps119Bridge.getCurrentPosition` 으로 건네고 `mapHelpers.getCurrentPositionOnce` 가 먼저 쓴다. null(권한 없음·해당 없음)이면 원래 `navigator.geolocation`. 권한이 없을 때 여전히 WebKit 프롬프트가 한 번 뜨는 건 의도다 — «언제 물을지»는 웹 UX 몫. Vitest 17건. ⚠️ 실기기 QA 필요 | 웹만 |
| B(다음 셸) | 🛠 셸 `package.json` 에 `@capacitor/geolocation` 추가(`ios/App/App/Info.plist` 문구는 이미 있음). 웹은 `Capacitor.Plugins.Geolocation` 이 있으면 우선 | 셸+웹 |

---

### F-13. (현장에서 새로 발견) 알림 받기가 「켜짐」인데 지령 알림이 안 온다 — 기기·브라우저를 다른 계정이 먼저 썼을 때

2026-09-07 21:27 KST 배포 후 테스트에서 발견. 계정 `01012345679`(user 204, 구급대)에게 배정했는데 웹·앱 모두 안 옴.

**원인 확정(서버 확인).** 배정 시점에 그 계정의 `device_tokens` 가 0건이라 발송이 없었다(리스너 14ms DONE). 그런데 화면은 「알림 받는 중」이었다 — 켜짐 기록(앱 `localStorage`)과 브라우저 구독이 **계정이 아니라 기기에** 묶여 있어서, 같은 아이폰·브라우저를 앞서 쓴 다른 계정의 것이 그대로 보였다. 서버의 토큰은 그 이전 계정 소유였고(토큰 #9, 09-03 생성), 12:32 UTC 에 토글을 다시 만지자 비로소 204 로 넘어왔다. **배포와 무관한 기존 결함**이고, 일괄 발급된 운영진이 공용 기기를 쓰면 똑같이 겪는다.

✅ **2026-09-07 구현(웹만, PR #37).** 켜짐 기록의 값을 «등록한 사용자 id» 로 바꾸고(앱 `gps119.push.enabled`, 웹 `gps119.push.web.owner`), 레이아웃이 `<meta name="gps119-user">` 로 로그인 사용자를 알린다. 페이지마다 `syncPushRegistration()` 이 주인과 현재 사용자를 비교해 다르면 같은 토큰·구독을 현재 사용자로 다시 `POST /api/devices` 한다 — 서버는 `token_hash` 기준이라 주인이 넘어오고 이전 사람은 더 못 받는다. 예전 기록('1')은 「누군지 모름」으로 한 번 재등록. 기록·구독이 없으면 건드리지 않는다(켤지는 사용자가 정한다). `/control` 은 서비스워커가 없어 웹 동기화를 부르지 않는다. 테스트: `pushNative.test.js` 9건·`push.test.js` 8건·`PushOwnerMetaTest`.

### F-14. (현장에서 새로 발견) 운영 서버의 iOS 앱 푸시가 «전부» 실패한다 — APNs 키 환경

F-13 을 고치고 다시 배정해도(21:51 KST, 지령 #45) 앱에 안 왔다. 이번엔 토큰이 있었고 리스너가 748ms 로 실제 발송을 했는데 `laravel-2026-09-07.log` 에 `failed:1` 집계만 남았다.

**원인 확정.** 같은 토큰으로 FCM 에 손으로 보내 응답 본문을 받았다:
`HTTP 401 UNAUTHENTICATED · errorCode THIRD_PARTY_AUTH_ERROR · "Invalid APNs credential." · ApnsError reason **BadEnvironmentKeyInToken**`.
FCM 이 APNs 에 넘길 때 Firebase 에 올려 둔 APNs 인증 키(.p8)가 **sandbox(개발) 전용**이라 App Store/TestFlight 빌드(production APNs)로는 못 보낸다. 8-31 실기기 검증은 Xcode 개발 빌드(sandbox)였고, 오늘이 운영 서버 + 스토어 빌드 조합의 첫 iOS 푸시다 — `DEPLOY.md` §5 「운영 대상 푸시 종단 검증 미완」이 정확히 이것이었다. `validate_only` 로는 200 이라(토큰·프로젝트·서비스 계정은 정상) 실제 발송으로만 드러난다. 오늘 iOS 대상 발송 16건이 전부 이 이유로 실패했다(웹은 정상 배달).

**고치는 곳 — 콘솔(코드 아님):**
1. Apple Developer → Certificates, Identifiers & Profiles → **Keys** → 사용 중인 APNs 키의 환경이 **Sandbox & Production** 인지 확인. Sandbox 전용이면 새 키를 «Sandbox & Production» 으로 만든다(.p8 은 생성 때 한 번만 받는다)
2. Firebase Console → 프로젝트 `gps119` → 프로젝트 설정 → **Cloud Messaging** → Apple 앱 구성 → **APNs 인증 키** 를 새 .p8(키 ID·팀 ID `KWL346JAR4`)로 교체
3. 확인: 관제에서 배정 1건 → 로그에 `FCM 발송 거절` 이 «안» 뜨고 `delivered:1`

✅ **2026-09-07 22:10 KST 해소.** 새 키 `5YP6MW3RP8`(Sandbox & Production, Team Scoped)을 만들어 Firebase 에 올렸고, 같은 토큰(#9)으로 다시 보낸 진단 발송이 **FCM 200 + message id** 로 바뀌었다. 키 파일은 `~/.gps119-keys/AuthKey_5YP6MW3RP8_apns_sandbox_production.p8`(셸 README 참조). **22:25 지령 #51 이 웹·iOS 양쪽 실기기에 도착했고 소리까지 확인** — `DEPLOY.md` §5 「운영 대상 푸시 종단 검증」 종료.

### F-15. (현장에서 새로 발견) 알림은 오는데 소리가 안 난다 — 설정 → 알림 → GPS119 에 「사운드」 행이 없음

F-14 해소 직후. 진단 발송 4건(`default` 2·`rescue_alarm.caf` 2)이 전부 배너만 뜨고 조용했다. 무음 스위치·잠금 상태와 무관.

**원인(확정, 22:27 스크린샷).** 설정 → 알림 목록에서 다른 앱은 「배너, 사운드, 배지」인데 **GPS119 만 「배지」** — iOS 가 이 앱에 배지만 허가한 것이다. 재설치해도 같았다. 이유는 **뱃지 플러그인**(`@capawesome/capacitor-badge`)이다: `clear()` 가 iOS 에서 `UNUserNotificationCenter.requestAuthorization(options: .badge)` 를 부르는데, 웹 `push-native.js` 가 앱을 열 때마다 `clearAppBadge()` 로 그걸 불렀다. 새 설치의 **첫 권한 프롬프트가 「배지만」**이 되고, 허용을 누르면 iOS 는 그 상태로 굳어 이후 FirebaseMessaging 의 alert·badge·sound 요청을 무시한다. 8-31 실기기 기록에 «소리 남» 확인이 없는 것도 이것으로 설명된다 — 처음부터 그랬다. 22:25 에 들린 「소리」는 OS 푸시음이 아니라 지령 화면의 자체 사이렌(F-07 ②, Reverb)이었을 가능성이 크다.

✅ **해소.** ① 코드(웹): `clearAppBadge()` 가 `FirebaseMessaging.checkPermissions()` 로 **이미 허용일 때만** 뱃지를 지운다 — 새 설치에는 지울 뱃지도 없다. PR #43, 22:30 배포 `d5c8694`. PR #41(켜기 때 항상 재요청)도 유지. ② 기기: 이미 배지만 허가된 아이폰은 **앱 삭제 → 아이폰 재시동 → 재설치 → 알림 받기 켜기**(iOS 는 같은 날 재설치하면 예전 권한을 캐시로 되살린다). 그러면 첫 프롬프트가 FirebaseMessaging 의 셋 요청이 되고 목록에 「배너, 사운드, 배지」가 뜬다.

📌 다음 셸 빌드에 「기기의 알림 권한 상세(소리 포함 여부)」를 읽는 메서드를 넣고 프로필에 표시하면 이 종류를 원격에서 바로 안다(선택, §6-5).

✅ **코드 쪽(PR)**: `FcmSender` 가 거절 응답의 status·errorCode·APNs reason 을 `FCM 발송 거절` 경고로 남긴다(토큰·본문 제외). 다음엔 로그 한 줄로 안다. `FcmSenderTest` 1건.

📌 Android 토큰 #4·#5(user 3, 09-01 생성)는 `UNREGISTERED` — 앱을 지웠거나 재설치한 기기. 다음 실제 발송에서 INVALID 로 폐기된다(정상 동작).

## 4. 앱 셸에서 고칠 것 — `~/Dev/gps119_app_mobile`

셸은 «할 수 있게 만드는 것»까지만 갖는다(플러그인 배선·권한 선언·알림 채널). 정책과 화면은 전부 이 저장소다. 아래가 이번 피드백에서 셸 몫이다.

| 항목 | 파일(셸 저장소 기준) |
|---|---|
| F-03 iOS 당겨서 새로고침 ✅ | `ios/App/App/MainViewController.swift` |
| F-07 푸시음 ✅ | `android/app/src/main/res/raw/rescue_alarm.wav`, `res/values/notification.xml`, `.../MainActivity.java`, `ios/App/App/rescue_alarm.caf` + `project.pbxproj` |
| F-12 B안 | `package.json`(`@capacitor/geolocation`), `npx cap sync` |
| F-10 선택 | `package.json` 에 `@capacitor/app-launcher` — 있으면 웹 `kakaoNavi.js` 가 `completed` 기반 확정 폴백으로 자동 전환(코드 준비됨). 없어도 동작은 한다 |
| 공통 ✅ | `versionCode` 1→2 · iOS 빌드 2→3 · 셸 `README.md` 기록. 마케팅 버전(1.0)은 그대로 — 올릴지는 결정 필요 |

⚠️ 셸 저장소의 `README.md` 「앱 내장 네이티브 플러그인 추가 시 관문 넷」과 「빌드한 뒤 번들 안을 눈으로 확인」을 그대로 따른다.

## 5. 고객에게 물을 것

1. F-05 자원봉사자 — 코스·구급 둘을 유지할지, 하나로 합칠지 (지금은 「자원봉사자」= 코스로 가정)
2. F-06 — 검색이 필요했던 화면이 관제 배정 패널인지 관리자 「참가자 추가」인지 (둘 다 넣었으니 확인만)
3. F-09 — 그 안드로이드 기기에 카카오내비가 있는지, 카카오맵만 있는지
4. F-07 — 합성 사이렌으로 갈지, 원하는 음원이 따로 있는지

## 6. 남은 작업 (2026-09-07 기준)

구현은 끝났고 **배포·확인**이 남았다. 순서에 뜻이 있다 — F-07 은 웹이 먼저다.

### 6-1. 고객 응답

- [x] F-01·F-02 답변 보냄 (2026-09-07) — 둘 다 이미 있는 기능
- [ ] F-11 은 09-04 에 이미 고쳤다고 답변
- [ ] §5 네 가지 확인 요청

### 6-2. 웹 배포 (즉시 가능)

- [x] PR #34 → `main` 병합 `6c2066a` (2026-09-07)
- [x] `./deploy.sh` 21:02 KST, 마이그레이션 없음, 헬스체크 통과 → `DEPLOY.md` §0 행 추가
- [x] 배포 후 확인(로그인 없이): `/up` 200 · `kakaoNavi.js` 에 `kakaomap://route` · `mapHelpers.js` 에 브리지 훅 · `push-native` 번들에 채널 v2/v1 · 관제 번들에 회송팀 아이콘 · 컨테이너 4개
- [x] 로그인 확인(사용자, 2026-09-07): 관제 `/control` 역할 필터에 공무원·회송팀 · 관리자 참가자 페이지 피커 · 지령 화면 알림음 — 이상 없음
- [ ] 🔴 **구버전 앱**으로 푸시 1건 — heads-up 이 살아 있는지(FCM 이 매니페스트 기본 채널로 떨어지는 경로)
- [x] PR #37 `ea993dc` 21:43 KST — F-13 푸시 주인 동기화. 배포 후 확인: `/up` 200, 앱 번들에 `gps119.push.web.owner`, 게스트 페이지엔 `gps119-user` meta 없음
- [x] PR #39·#41 `cb413bf` 22:20 KST — FCM 거절 로깅 + 알림 켜기 때 권한 항상 재요청(F-15). 마이그레이션 없음
- [x] F-13·F-14 현장 확인 22:25 KST — 204 계정 웹(토큰 #18)·iOS(재설치 후 토큰 #19) 양쪽에 지령 #51 발송, 로그에 실패 없음
- [x] PR #43 `d5c8694` 22:30 KST — 뱃지 플러그인이 첫 권한 요청을 「배지만」으로 만들던 것(F-15 진짜 원인). 마이그레이션 없음
- [x] PR #46 `9f83a30` 22:50 KST — Android 포그라운드 로컬 알림 상태바 아이콘·색(F-16). 마이그레이션 없음
- [x] PR #48 `f45b913` 23:00 KST — 기기 등록에 `app_version`. 마이그레이션 없음
- [ ] F-16 현장 확인: 갤럭시에서 앱을 «열어 둔 채» 배정 → 상태바에 GPS119 핀 아이콘·청록색
- [x] iOS 빌드 2 재설치 → 알림 아이콘 GPS119 핀 확인(사용자, 23:05 KST). `app_version` 은 다음 «켜기» 또는 주인 변경 때 채워진다 — 지금 토큰 #19 는 23:00 배포 전 등록이라 아직 null
- [x] F-15 현장 재확인 22:40 KST — 삭제 → 재시동 → 재설치 → 알림 받기 → 목록에 GPS119 「배너, 사운드, 배지」. 진단 푸시 E(`default`)·F(`rescue_alarm.caf`) **둘 다 소리 남** → 파일이 없는 빌드에서도 iOS 가 기본음으로 대체한다(F-07 ① 의 구버전 우려 해소). 운영 iOS 푸시 종단(배너·소리) 최종 확인

### 6-3. 셸 배포 (스토어 심사)

- [x] 셸 PR #6 → `main` `5dc08d2` (2026-09-07) — M-26·F-07 ①·F-03·아이콘 기본값·APNs 키 문서
- [x] iOS 빌드 3 업로드 2026-09-07 22:52 KST — CLI(`xcodebuild archive` → `-exportArchive` 업로드, 셸 README 절차). 아카이브 검증: 번들 URL 운영·`rescue_alarm.caf`·entitlements 3종. 마케팅 버전 1.0 유지
- [x] 빌드 3 실기기(23:20): 사이렌 ✅ · 당겨서 새로고침 ❌ → 빌드 4
- [x] iOS 빌드 4 업로드 2026-09-07 23:01 KST (셸 `bf461b4`, 같은 CLI 절차)
- [x] 빌드 4 실기기(23:15): 목록 화면 당겨서 새로고침 ✅ (사용자 확인). 지도 화면 오작동 여부와 `app_version` 갱신은 다음 사용 때
- [ ] App Store 심사 제출은 빌드 2 가 어떤 상태인지 보고 결정(빌드 3 로 교체 제출)
- [ ] Android: `npm run bundle:android:prod` → **Play 첫 제출은 법무 선행조건에 막혀 있다**(개인정보처리방침 URL·데이터 안전 섹션, `05-store-release.md` §2). 그 전까지는 사이드로드 APK(`build:android:prod`)로 현장 기기에 배포
- [ ] Play 에 올린 뒤 `assetlinks.json` 에 Play 앱 서명 키 지문 «추가» (`05 §밟기 쉬운 것 ③`)

### 6-4. 실기기 QA (자동 판정이 못 미치는 것)

| 항목 | 기기 | 확인할 것 |
|---|---|---|
| ~~F-03~~ | iPhone 빌드 4 | ✅ 목록 당겨서 새로고침 확인(23:15). 남은 것: **지도 화면을 끌 때 오작동하지 않는가** |
| F-07 ① | Galaxy + ~~iPhone(빌드 3)~~ ✅ 사이렌 확인 | Android: 잠금화면·진동 모드·주머니에서 들리는가 · 설정에 「구조 알림」이 하나만 보이는가 |
| F-07 ② | 둘 다 | 푸시를 탭해 지령 화면에 들어온 직후 Reverb 지령에 소리가 나는가 |
| F-10 | iPhone: 카카오내비 없음 / 카카오맵만 / 둘 다 없음 | 각각 어디로 가는가, 1.2초 뒤 폴백이 실제로 전환되는가 |
| F-12 | iPhone | 신고 화면을 두 번째 열 때 위치 팝업이 «안» 뜨는가(OS 권한 허용 후) |
| ~~푸시 종단~~ | iPhone, 운영 서버 | ✅ 09-07 22:25 — 웹·iOS 수신, iOS 소리 확인 (F-14·F-15 거쳐서) |

### 6-5. 선택 (이번엔 안 한 것)

- 셸 `@capacitor/app-launcher` — F-10 을 `completed` 기반 확정 폴백으로 (코드 준비됨)
- 셸에 알림 권한 상세(alert·sound·badge) 읽기 메서드 + 프로필 표시 — F-15 같은 «소리 없는 허가»를 원격에서 보이게
- 앱 빌드가 바뀌면 자동 재등록 — 켜짐 기록에 빌드를 같이 적어(`<user>@<build>`) 다르면 `syncNativePushOwner` 가 다시 POST. 지금은 «켜기»를 다시 눌러야 `app_version` 이 갱신된다
- 셸 `@capacitor/geolocation` — F-12 B안, Android 까지 같은 경로로
- F-05 자원봉사자 통합(②) — 고객 답에 따라 데이터 이관 1건
- Android 당겨서 새로고침 — 요청이 없어 두었다

---

## 7. 2차 목록 (2026-09-07 밤, 사용자 테스트 중 발견)

| ID | 원문 | 판정 | 저장소 | 상태 |
|---|---|---|---|---|
| R-1 | 앱에서 네이버 로그인 작동 안 함 | `/login/naver` → `nid.naver.com` 302 를 Capacitor 가 **외부 브라우저**로 넘겨 로그인이 앱 밖에서 끝난다. `server.allowNavigation` 에 네이버 호스트 | 셸 | ✅ 셸 `96ceece` · **빌드 5 업로드 23:46** · 실기기 확인 대기 |
| R-2 | 관제 역할 배정 목록에 참가 인원 전체가 안 나옴 | 서버는 active 전원을 준다(프로젝트 4: 19명 전원 active, pending·left 없음). 목록 상자가 `max-h-56`(224px)이라 7명 남짓만 보이고 시트 안 중첩 스크롤이라 더 있는 줄 모른다 — **가설**. 상자를 화면 절반까지 키움 | 웹 | ✅ 배포 `308da4d` · 고객 재확인 필요 |
| R-3 | 사고 접수되면 관제에 토스트 + 알림음 | `_onRequestCreated` 에 상단 토스트(8초, 누르면 그 신고 펼침) + 사이렌(`alertSound.js`, 첫 클릭·터치에서 무음 잠금 해제) + 진동 | 웹 | ✅ 배포 `308da4d` · 관제 화면을 한 번 클릭한 뒤부터 소리 |
| R-4 | 출동이력 시간이 현재 시간과 차이 | 앱 시간대가 **UTC**. 서버가 그리는 시각(출동이력·통계·CSV) 전부 9시간 차. 시각 열이 전부 MySQL TIMESTAMP 라 앱 `Asia/Seoul` + 세션 `+09:00` 설정만으로 저장값 변경 없이 KST — `TimezoneTest` 가 `dateTime()` 컬럼 유입을 막는다. 부수 효과: 스케줄 `dailyAt('04:40')` 이 이제 KST(문서와 일치) | 웹(설정) | ✅ 배포 `308da4d` · 운영에서 지령 #51 이 22:25 KST 로 읽히는 것 확인 |
| R-5 | 앱에서 알림 권한 끈 뒤 「브라우저 설정에서 허용」 문구 | 플랫폼별 문구(`deniedCopy`). iOS 는 「설정 열기」 버튼(`app-settings:`), Android 는 경로 안내 | 웹 | ✅ 배포 `308da4d` |
| R-6 | 관제 이동궤적이 선으로만 나와 의미 없어 보임 | 개선안 §7-1 | 웹 | ⏸ 보류 — 다음에 다시 논의(사용자, 09-07) |
| R-7 | 앱 화면을 열어 둔 채 배정받으면 푸시·웹 알림 둘 다 옴 | 「웹 알림」= 지령 화면의 **풀스크린 알림**으로 확인. 없앨 수 없으니 OS 배너 쪽을 조용히 하는 방향(§7-2 첫 항목) | 웹/셸 | ⏸ 보류 — 일단 둔다(사용자, 09-07) |

### 7-1. R-6 이동궤적 — 개선안

지금은 `trackLayer.js` 가 사람마다 역할색 폴리라인 하나를 얹는다. 방향·시간·현재 위치가 없어 「선」으로만 보인다. 후보:

1. **방향 화살표 + 시작·현재 점** — `kakao.maps.Polyline({ endArrow: true })` 와 양 끝 마커. 가장 싸다(반나절).
2. **시간에 따른 농도** — 오래된 구간은 옅게, 최근 구간은 진하게(구간별 폴리라인 분할). 어디로 «가고 있는지»가 보인다.
3. **시간 슬라이더 재생** — 특정 시각의 위치를 되감기. 사고 경위 확인용. 가장 비싸다(며칠).
4. **최근 N분만** — 기본 30분, 더 보려면 늘리기. 선이 얽히는 걸 줄인다.

권고: 1+2+4 를 한 번에(하루). 3 은 사고 조사 요구가 실제로 있을 때.

### 7-2. R-7 「푸시 + 웹 알림 둘 다」 — 확인할 것

앱을 열어 둔 채 배정받으면 지금은 이렇게 온다:

| 플랫폼 | OS 알림 | 앱 안 |
|---|---|---|
| iOS | OS 배너(포그라운드에도 띄우도록 설정) | 지령 화면이 열려 있으면 풀스크린 알림 + 사이렌(Reverb) |
| Android | 웹이 올리는 로컬 알림 | 위와 같음 |

「웹 알림」이 무엇인지에 따라 고칠 곳이 다르다:
- **지령 화면의 풀스크린 알림**을 말한다면 — 없앨 수 없다(수락·거절 버튼이 거기 있다). 대신 **OS 쪽을 조용히** 할 수 있다: 지령 화면이 열려 있을 때 Android 는 로컬 알림을 건너뛰고(웹만), iOS 는 포그라운드 배너를 끈다(셸 `presentationOptions` 에서 `alert` 제거 → 다른 화면에서는 웹이 인앱 배너로 대신 띄움).
- **같은 사람의 «브라우저» 웹 푸시**(맥·PC)를 말한다면 — 서버 정책: 앱 토큰이 살아 있는 사용자에게는 웹 구독으로 보내지 않는다(`PushService` 수신자 선별). 웹만 쓰는 상황실은 영향 없다.

→ **답(09-07 밤): 풀스크린 알림을 뜻함.** 방향은 첫 항목(지령 화면이 열려 있을 때 OS 배너를 조용히)이고, 일단 보류. 다시 다룰 때 Android 는 웹만으로(`presentForeground` 가 지령 화면이면 건너뜀), iOS 는 셸 `presentationOptions` 에서 `alert` 제거 + 다른 화면용 인앱 배너가 필요하다.
