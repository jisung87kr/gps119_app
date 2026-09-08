# App Review 회신 — Guideline 2.1 「Information Needed」 (2026-09-08)

**반려가 아니라 «정보 요청»이다.** 기능·정책 위반 지적이 하나도 없다. 심사 이력이 짧은 개발자 계정에
Apple 이 정형으로 보내는 6문항이고, 답변 + 실기기 녹화를 보내면 심사가 이어진다.
답은 **Resolution Center 회신**과 **App Review Information → Notes** 두 곳에 같은 내용을 넣는다.

관련: [`listing-ko.md`](./listing-ko.md) · [`05-store-release.md` §2-1](../epics/mobile-app/05-store-release.md)

---

## 0. 순서 (사용자 콘솔 작업)

| # | 무엇 | 비고 |
|---|---|---|
| 1 | **iPhone 을 최신 iOS 로 올린다** | Apple 이 "latest operating system" 을 명시했다. 기록상 시험기(iPhone 16 Pro)는 8월에 iOS 18.7 이었다 — 그대로면 결격 |
| 2 | **TestFlight 로 빌드 5 를 설치**한다 | 녹화는 심사받을 빌드로. 빌드 5 의 네이버 로그인(R-1)도 이때 실기기 확인이 된다 |
| 3 | ✅ **배포 + `review:demo` 완료 (09-08 20:19)** (§2) | 데모 계정 3종 + 심사용 행사를 만든다. 지금까지는 참가자 1개(`01012345678`)만 냈다. Apple 이 «계정 종류별» 자격증명을 요구한다 |
| 4 | **녹화** (§1 대본) | iOS 화면 기록(제어 센터)이면 된다 — 권한 프롬프트·푸시 배너도 찍힌다 |
| 5 | Notes 갱신 → Resolution Center 회신(녹화 첨부) → **제출 빌드를 2 → 5 로 교체** → 제출 | 빌드를 바꾸면 재제출이 된다. 회신만 하고 빌드 2 를 둘 이유가 없다(사이렌·새로고침·네이버 로그인 전부 빌드 5) |

✅ **계정 삭제 결함(§4)은 고쳤다(ADR-0010).** 단, **운영에 배포돼야** 심사자가 밟지 않는다 — 3번 전에 배포가 먼저다.

---

## 1. 녹화 대본 (실기기 · 앱 실행부터 · 삭제까지 한 번에)

Apple 요구: **앱 실행으로 시작**, 일반 흐름, **가입·로그인·계정 삭제** 포함. UGC 는 없다(§3-1).

🔑 **삭제는 데모 계정으로 하지 않는다.** 녹화 첫머리에 가입한 «일회용» 계정을 마지막에 지운다 —
가입과 삭제가 같은 계정이라 흐름도 자연스럽다. 전화번호는 실제 번호가 아닌 것(예 `01000000xxx`)을 쓴다.

| 컷 | 화면 | 보여줄 것 |
|---|---|---|
| 1 | 홈 화면 → 앱 아이콘 탭 | 콜드 스타트, 스플래시 → 로그인 화면 |
| 2 | 회원가입 | 전화번호 + 비밀번호 + 필수 동의 2종(개인정보처리방침·위치정보 이용약관) 체크 → 가입 |
| 3 | 구조요청 화면 도착 | 일반 사용자의 랜딩. 큰 버튼과 상황 3종(사고·고장·기타) |
| 4 | 행사 참가 | 6자리 코드 입력 → 심사용 행사 입장 |
| 5 | 위치 공유 | 입장하면 공유는 켜진 채로 시작한다 → iOS 위치 권한 프롬프트(앱 사용 중) → 「항상 허용」 안내 카드. **토글을 껐다가 다시 켜는 것**을 보여준다 — 「언제든 끌 수 있다」의 증거 |
| 6 | 구조요청 | 「사고」 탭 → 접수 → 상태 화면(접수됨) |
| 7 | *(선택, 2번째 기기·PC)* 상황실 `/control` | 지도에 신고 마커 → 데모 구급대원 배정 → 참가자 폰에 상태 변경·푸시 배너 |
| 8 | 프로필 → 계정 삭제 | 비밀번호 입력 → 동의 체크 → 「계정 영구 삭제」 → 확인 → 로그인 화면으로 |

⚠️ 실명·실번호가 화면에 보이면 안 된다. 심사용 행사의 참가자는 가명만.
⚠️ 컷 7 을 넣을 거면 **상황실 계정으로 삭제를 시연하지 말 것**(§4).

---

## 2. 데모 계정 — 심사자에게 «계정 종류별»로

Apple 문구: *"If the app has multiple account types, provide credentials for each type."*

**`php artisan review:demo` 가 만든다** (`app/Console/Commands/ReviewDemoSetup.php`, 멱등, 테스트 `ReviewDemoSetupTest`).
계정 3종을 만들고 실제 입장 경로(`joinByCode`)로 심사용 행사에 넣은 뒤 역할을 올린다. 동의 2종을 기록하므로 위치 공유 게이트에
막히지 않는다. 비밀번호는 **stdout 에 한 번만** 나온다.

| 종류 | ID(전화번호) | 행사 역할 | 랜딩 |
|---|---|---|---|
| 참가자 | `01000000001` | participant | `/requests/create` |
| 구급대원 | `01000000002` | paramedic | `/events/{id}/dispatch` |
| 상황실 | `01000000003` | controller | `/control?project={id}` |

심사용 행사: `App Review 데모 행사` (slug `app-review-demo`), 종료일 = 실행일 + `--days`(기본 30). 다시 돌리면 종료일만 늘어난다.
`010-0000-xxxx` 는 통신사가 배정하지 않는 대역이다. 기존 `01012345678`(상시운영)은 건드리지 않았다.

운영에서(배포 뒤, **실서버 DB 에 쓴다**):

```bash
ssh gps119
cd ~/gps119_app && docker compose --env-file .env.deploy -f docker-compose.prod.yml exec app php artisan review:demo --days=30
#   → 표의 비밀번호와 입장 코드를 App Store Connect 에 옮겨 적는다
# 종료일 연장(심사가 길어지면):        같은 명령을 다시
# 비밀번호를 잃었으면:                 ... review:demo --reset-password
```

🔴 **자격증명은 이 저장소에 적지 않는다.** 콘솔에만 입력한다(`listing-ko.md` 의 규칙 그대로).

---

## 3. 회신 본문 (영문 — Resolution Center 와 Notes 에 동일하게)

`[ ]` 는 제출 시 채운다. 심사자는 한국어 UI 를 보게 되므로 화면 라벨을 영문 병기했다.

```
Thank you for reviewing GPS119. Answers to items 1–6 follow, and the same text has been added to
App Review Information > Notes. We have also replaced the build under review with build 5.

1. SCREEN RECORDING
Attached: [file name]. Recorded on an iPhone [model] running iOS [version], with the submitted build
installed via TestFlight. It starts from launching the app and shows the typical flow:
sign-up (phone number + password + the two required consents), the rescue-request screen, joining an
event with a 6-character join code, turning location sharing on (including the iOS location permission
prompts), filing a rescue request, [the control room assigning a medic and the resulting status update /
push notification on the participant's phone,] and finally account deletion
(Profile > 계정 삭제 "Delete account" > password > confirm) performed on the account created at the
start of the recording. The app has no user-generated content (see item 6).

2. PURPOSE AND TARGET AUDIENCE
GPS119 is a safety-operations app for outdoor events in Korea — marathons, trail runs, hiking events,
cycling races and festivals. When a participant is injured on a mountain trail or at the 15 km mark of
a course, the biggest delay is that the person cannot describe where they are. GPS119 removes that step:
the participant taps one "rescue request" (구조요청) button, the request is sent together with GPS
coordinates, and the event's own control room sees the exact spot on a map and dispatches the nearest
medic, who navigates there with a map link. The app supplements — it never replaces — the national
119 emergency service, and it links directly to calling 119.

Audience: (a) event participants — the general public; anyone can register in the app and join an
event by scanning the organizer's QR code or entering the join code; (b) the organizer's safety staff:
medics and the control room. The app is open to the public and is not limited to the employees of a
single organization: any organizer can create an event, and any participant can join one.

3. SETUP AND ACCESS
Login differs by account type: participants, medics and control-room staff sign in with a mobile
phone number (digits only) + password. Only the system administrator uses an email address; that
account is not needed for review.

Demo accounts (kept active for the duration of the review):
  Participant   phone: [ ]   password: [ ]
  Medic         phone: [ ]   password: [ ]
  Control room  phone: [ ]   password: [ ]
Demo event: "[event name]", join code: [ ]  (active until [date]).

Typical flow:
  1) Sign in as the participant → the rescue-request screen (구조요청) opens.
  2) Events tab → enter the join code → the event screen opens.
  3) Allow location when iOS asks. Location sharing (위치 공유) starts when a participant joins an
     event and can be turned off — and back on — at any time from the event screen.
  4) Tap a situation button (사고 accident / 고장 breakdown / 기타 other) → the request is filed
     with your coordinates; its status is shown on the dashboard.
  5) Sign in as the control room on another device or in Safari → the map at
     https://gps119.co.kr/control shows the request → assign the demo medic.
  6) Sign in as the medic → the dispatch screen shows the assignment (also delivered as a push
     notification) → accept / en route / arrived / completed.
Account deletion: Profile (프로필) > 계정 삭제 > enter password > tick the confirmation > 계정 영구 삭제.
Please test deletion with a newly registered account rather than the demo accounts above, so the
demo accounts stay available for the rest of the review. Sign-up is open in the app
(phone number + password + two consents).

4. EXTERNAL SERVICES
  - Kakao Maps JavaScript API — map display and coordinates-to-address lookup.
  - Kakao Map / Kakao Navi apps (deep link) — optional turn-by-turn navigation for medics; falls back
    to the web map when the apps are not installed.
  - Naver Login (OAuth) — optional alternative to phone-number login.
  - Firebase Cloud Messaging + APNs — push notifications (new request / dispatch assigned). Push
    payloads never contain phone numbers.
  - Our own server on AWS Lightsail (Seoul region): the web application, a WebSocket server for
    real-time map updates, and a MySQL database. TLS by Let's Encrypt.
  - An optional Discord webhook can post new-request alerts to the organizer's own channel; it is off
    unless the operator configures it.
No payment processor, no in-app purchases, no advertising, no analytics SDK, no AI services and no
third-party data providers are used.

5. REGIONAL DIFFERENCES
The app functions identically in every region; there is no region-based gating of features or
content. The service is designed for events held in South Korea: the UI is in Korean, the map
provider (Kakao Maps) covers Korea, and phone-number login expects Korean mobile numbers.
[We intend to make the app available on the Korea storefront only.]

6. REGULATED INDUSTRY / THIRD-PARTY MATERIAL
GPS119 is not a medical, healthcare or emergency-services provider and gives no medical advice or
treatment. It is a coordination tool an event organizer uses for its own on-site safety staff,
alongside the public 119 service. No license or credential is required to operate it. It contains
no protected third-party material; map data is displayed through Kakao's public developer API under
its terms of use. There is no user-generated content: users cannot post, share or comment; the only
free text in the app is a medic's optional reason when declining a dispatch, visible only to that
event's control room.
```

### 3-1. 답에 깔린 판단 (심사자에겐 안 보내는 것)

- **UGC 없음** — 신고에는 자유 입력 칸이 없다(상황 버튼 3종 + 좌표). `dispatches.reject_reason` 만 자유 텍스트이고 상황실 전용이라 UGC(1.2) 범주가 아니다. `listing-ko.md` 의 연령 등급 답과 일치시켰다.
- **3.2(특정 조직 전용 앱) 방어** — 참가자는 일반 대중이고 가입이 열려 있다는 문장을 일부러 넣었다. 「주최측 직원용」으로 읽히면 Custom App 배포로 보내진다.
- **6번(규제 산업)** — «규제 산업 아님, 119 보조» 로 답한다. ⚠️ 국내 **위치기반서비스사업 신고**는 Apple 이 묻는 «규제 산업 인가»와 다른 축(국내법)이고 아직 미완이다. 회신에 끌어들이지 않는다 — 단, Play 는 이 축이 실제로 막고 있으니 별도로 계속 진행한다(`05-store-release.md` §2 한국 특수사항).
- **디스코드 웹훅** — 운영 설정 여부를 이 세션에서 확인하지 못했다(운영 DB·env 조회 차단). 꺼져 있으면 그 줄을 지운다.
- **Korea storefront only** — 대괄호 문장은 사용자 결정. 제품 성격과 맞고 심사 질문(5번)이 단순해진다.

---

## 4. ✅ 계정 삭제 결함 — 고쳤다 (ADR-0010, 2026-09-08)

**발견(실측):** 지령 이력이 있는 구급대원·상황실 계정은 「계정 삭제」가 `FOREIGN KEY constraint failed` 로 500 이었다.
`dispatches.paramedic_id` / `assigned_by` 가 RESTRICT 이고 `users` 가 물리 삭제였다. 계정 삭제 테스트가 없어 안 드러났다.
심사자가 구급대원 데모 계정으로 삭제를 눌렀다면 그 자리에서 반려(2.1 + 5.1.1(v))였다.

**결정:** 탈퇴 = **익명화** ([ADR-0010](../adr/0010-account-deletion-as-anonymization.md)). `AccountDeletionService` 하나가 진입점.
사람(이름·전화·이메일·소셜·비밀번호·2FA·세션·토큰·참가·위치 이력·역할)은 지우고, 신고·지령 행은 연락처를 비운 채 행사 운영 기록으로 남긴다.
`users.account_deleted_at` 표식 + `phone` nullable 마이그레이션. 탈퇴 화면·개인정보처리방침 §5 문구를 실제 동작에 맞췄다
(방침 판은 올리지 않았다 — 올리면 전원 재동의 게이트가 걸린다. 결정 필요).

**검증:** `AccountDeletionTest` 6건(구급대원·상황실·신고자·재가입·오답 비밀번호·멱등) + 전체 552건 통과.

⚠️ 운영 반영은 배포(`deploy.sh` 가 마이그레이션 실행)로. 배포 전까지 운영은 여전히 500 이다.

---

## 5. 기록

- 2026-09-08 반려 수신 — 빌드 2, 2.1 Information Needed. 기능 지적 없음.
- 2026-09-08 계정 삭제 익명화(ADR-0010) + `review:demo` 명령 구현 → PR #60 병합 → **운영 배포 `2e2da7f` 20:17 KST** (마이그레이션 실행 확인, `/up` 200).
- 2026-09-08 20:19 운영에서 `review:demo --days=30` 실행 완료 — 심사용 행사 **#5** (`App Review 데모 행사`, 종료 2026-10-08), 계정 3종 생성. 비밀번호·입장 코드는 콘솔에만(사용자에게 채팅으로 전달).
- 회신·재제출: [ ] (제출 후 여기와 `05-store-release.md` §2-1 에 날짜·빌드를 적는다)
