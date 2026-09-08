# ADR-0010. 회원 탈퇴는 물리 삭제가 아니라 «익명화» — 사람은 지우고 행사 기록은 남긴다

- 상태: Accepted
- 날짜: 2026-09-08
- 관련: ADR-0003(지령 상태머신), ADR-0004(연락처 채널 스코프), `app/Services/AccountDeletionService.php`, `tests/Feature/AccountDeletionTest.php`, 개인정보처리방침 §5
- 계기: App Store 심사(2.1 Information Needed) 준비 중, 지령 이력이 있는 구급대원·상황실 계정의 「계정 삭제」가 500 으로 죽는 것을 실측.

## 배경 (Context)

`ProfileController::destroyAccount` 는 `$user->delete()` 로 users 행을 물리 삭제했다. 그런데
`dispatches.paramedic_id` / `assigned_by` 는 `constrained('users')` 만 있고 삭제 규칙이 없다(RESTRICT).
지령을 한 번이라도 받았거나 내린 사람은 삭제가 FK 위반으로 실패한다 — 스토어 심사자가 구급대원 데모 계정으로
「계정 삭제」를 누르면 그 자리에서 반려(2.1 버그 + 5.1.1(v) 계정 삭제 의무)다. 계정 삭제 테스트가 하나도 없어
지금까지 드러나지 않았다.

한편 신고자 쪽은 `requests.user_id` 가 cascade 라 «삭제는 됐지만», 신고와 그에 붙은 지령까지 통째로 사라졌다.
사고 이력은 행사 주최의 운영 기록(보고·보험·분쟁)인데, 피해자가 탈퇴하면 주최 측 기록이 지워지는 구조였다.

## 결정 (Decision)

### D1. users 행은 남기고 «사람»만 지운다

`AccountDeletionService::delete()` 하나가 탈퇴의 유일한 진입점이다. 트랜잭션 안에서:

| 지운다 | 남긴다 |
|---|---|
| 이름(→「탈퇴 회원」)·전화(→NULL)·이메일·소셜 연결·아바타 | users 행(id) |
| 비밀번호(무작위로 교체)·remember token·2FA | 신고 행 — 좌표·시각·상태·유형 |
| 세션(다른 기기 포함)·Sanctum 토큰·푸시 토큰 | 지령 행 — 누가·언제·어떤 상태로 |
| 행사 참가·위치 이력(`location_pings`)·역할·명단 연결 | 동의 기록(익명 id 에 붙은 «언제 어느 판») |
| 본인 신고의 `contact_phone`·`description`, 본인이 쓴 `cancel_reason` | |

`account_deleted_at` 이 탈퇴 표식이다. 위치 이력은 트랜잭션 «밖»에서 청크로 먼저 지운다 — 한 사람 것도
수십만 행일 수 있고, 중간에 실패해도 재실행이 나머지를 마저 지운다(멱등).

### D2. SoftDeletes 를 쓰지 않는다

`deleted_at` + 전역 스코프는 `$dispatch->paramedic` 을 null 로 만들어 과거 기록 화면이 줄줄이 깨진다.
탈퇴한 사람은 기록 위에 **「탈퇴 회원」으로 보여야** 한다. 그래서 컬럼 이름도 `account_deleted_at` 이다 —
`deleted_at` 이면 누군가 trait 를 붙이는 순간 D1 이 조용히 무너진다.

### D3. `users.phone` 을 nullable 로

탈퇴 시 전화번호를 비워야 재가입(같은 번호)이 되고 로그인 조회에 안 걸린다. NOT NULL 이면 빈 문자열을
넣어야 하는데 unique 라 두 번째 탈퇴자부터 충돌한다. NULL 은 unique 에 걸리지 않는다.
`User::setPhoneAttribute` 는 null 을 null 로 둔다(`preg_replace(null)` 은 `''`).

### D4. FK 는 그대로 둔다

`paramedic_id` / `assigned_by` 를 `nullOnDelete` 로 바꾸는 대안은 버렸다 — `paramedic_id` 가 null 인 지령은
`/dispatches/mine`·개인 채널·보고서의 「응답자」 가정을 깬다. 이제 users 는 물리 삭제되지 않으므로 RESTRICT 는
«기록을 지키는» 안전장치로 남는다.

## 결과 (Consequences)

- 탈퇴 화면과 개인정보처리방침 §5 의 문구를 실제 동작에 맞췄다: 개인정보는 파기, 구조요청·출동 기록은
  연락처가 지워진 형태로 행사 운영 기록에만 남는다. ⚠️ 방침의 판(`config/legal.php`)은 올리지 않았다 —
  올리면 전원 재동의 게이트가 즉시 걸린다. 올릴지는 사람이 정한다.
- 관리자 화면에 「탈퇴 회원」 행이 보일 수 있다(전화 없음). 숨길지·배지를 달지는 별건.
- `projects.created_by` cascade 는 이제 도달하지 않는다(users 를 지우지 않으므로). 관리자가 탈퇴해도 행사는 남는다.
- 위치정보법의 «제공사실 확인자료» 보존과는 별개다(02 §6-1). 탈퇴가 그 자료를 지우지는 않는다 — 애초에 아직 없다.
