# 사용자 화면 디자인 리뉴얼 — 대상 라우터 목록

작성일: 2026-07-30 / 기준 브랜치: `feature/event-participant-management`

`admin` 미들웨어가 걸린 관리자 백오피스와 API 라우트는 제외하고, **최종 사용자(신고자) + 현장 운영 인력**이 브라우저로 보게 되는 화면만 정리했다.
GET(화면) 라우트만 대상이며, POST/PATCH/DELETE 는 해당 화면의 폼 액션이라 별도 항목으로 두지 않았다.

---

## Tier 1 — 신고 핵심 동선 (최우선)

QR 스캔 → 로그인 → 위치 전송 → 상태 추적. 앱의 존재 이유이자 트래픽이 몰리는 경로.

| # | URI | route name | View | 레이아웃 | 규모 | 비고 |
|---|-----|-----------|------|---------|-----|-----|
| 1 | `GET /requests/create/{slug}` | `request.create.project` | `request/create-project.blade.php` | app | 137줄 | **행사 QR 진입점.** 카카오맵 + Vue(CDN), 상황 버튼 4종 |
| 2 | `GET /requests/create` | `request.create` | `request/create.blade.php` | app | 122줄 | 상시(비행사) 신고. 지도 UI 동일 계열 |
| 3 | `GET /requests/{request}` | `request.show` | `request/show.blade.php` | app | 144줄 | 신고 상태 추적. 담당자 정보 실시간 갱신 |
| 4 | `GET /dashboard` | `dashboard` | `dashboard.blade.php` | app | 176줄 | 로그인 후 홈. 내 신고 현황 + 참가 행사 진입 |
| 5 | `GET /` | — | (리다이렉트 → `request.create`) | — | — | 화면 없음, 진입 정책만 검토 |

관련 부품 (Tier 1 과 함께 손대야 함):
- `resources/views/request/_confirm-modal.blade.php` (51줄) — 신고 전송 확인 모달
- `resources/views/components/layouts/app.blade.php` (143줄) — **공통 셸/헤더/네비. 디자인 시스템의 기준점**
- `public/js/components/` — `IntroScreen.js`, `LocationButton.js`, `LocationInfo.js`, `MapContainer.js`, `RequestMapApp.js`, `RequestShowApp.js`, `mapHelpers.js` (마크업이 JS 템플릿 안에 있어 Blade만 고쳐선 안 바뀜)

## Tier 2 — 인증 / 진입

| # | URI | route name | View | 규모 | 비고 |
|---|-----|-----------|------|-----|-----|
| 6 | `GET /login` | `login` | `auth/login.blade.php` | 90줄 | 전화번호 로그인 + 네이버/카카오 소셜 |
| 7 | `GET /register` | `register` | `auth/register.blade.php` | 83줄 | |
| 8 | `GET /forgot-password` | `password.request` | `auth/forgot-password.blade.php` | 51줄 | |
| 9 | `GET /reset-password/{token}` | `password.reset` | `auth/reset-password.blade.php` | 64줄 | |

⚠️ Fortify 가 뷰를 등록해 둔 **`auth/verify-email`, `auth/two-factor-challenge` 블레이드 파일이 실제로 없다** (`FortifyServiceProvider.php:78,82`). 해당 플로우 진입 시 뷰 없음 에러. 리뉴얼 때 만들지/라우트를 끊을지 결정 필요.

## Tier 3 — 현장 운영 인력 화면 (행사 참가자·구급)

| # | URI | route name | View | 규모 | 비고 |
|---|-----|-----------|------|-----|-----|
| 10 | `GET /events/join` | `events.join` | `event/join.blade.php` | 232줄 | 참가 코드 6자 입력 → 미리보기 → 입장 |
| 11 | `GET /events/join/{joinCode}` | `events.join.code` | 위와 동일 (prefill) | — | QR 딥링크. 같은 뷰 |
| 12 | `GET /events/{id}/active` | `events.active` | `event/active.blade.php` | 156줄 | 위치 자동공유 토글 + 역할 표시 |
| 13 | `GET /events/{id}/dispatch` | `events.dispatch` | `dispatch/index.blade.php` | **329줄** | 구급대원 지령 앱. 이 묶음 중 최대·최복잡 |

관련 부품: `public/js/components/locationShare.js`, `dispatchMeta.js`, `kakaoNavi.js`

## Tier 4 — 프로필 / 계정

| # | URI | route name | View | 규모 |
|---|-----|-----------|------|-----|
| 14 | `GET /profile` | `profile.show` | `profile/show.blade.php` | 167줄 |
| 15 | `GET /profile/edit` | `profile.edit` | `profile/edit.blade.php` | 145줄 |
| 16 | `GET /profile/password` | `profile.password.edit` | `profile/edit-password.blade.php` | 86줄 |
| 17 | `GET /profile/delete` | `profile.delete` | `profile/delete-account.blade.php` | 83줄 |

## Tier 5 — 에러 / 안내 화면

라우트가 아니라 컨트롤러/라우트에서 직접 `view()` 로 반환되거나 예외 핸들러가 띄우는 화면. 사용자가 실제로 자주 만난다.

| View | 규모 | 언제 뜨는지 |
|------|-----|-----------|
| `errors/require-phone.blade.php` | 66줄 | 전화번호 미등록 상태로 신고 시도 (`web.php:15,30`) — **핵심 동선의 이탈 지점** |
| `errors/project-inactive.blade.php` | 45줄 | 종료된 행사 QR 스캔 (`web.php:26`) |
| `errors/duplicate-user.blade.php` | 34줄 | 소셜 로그인 중복 계정 |
| `errors/layout.blade.php` | 53줄 | 401/402/403/404/419/429/500/503 공통 껍데기 (각 5줄 stub) |
| `errors/minimal.blade.php` | 34줄 | |

---

## 제외 대상 (이번 범위 밖으로 제안)

- **관리자 백오피스 전체** — `/admin/*` 17개 GET 라우트 + `components/layouts/admin.blade.php`. 사용자 화면과 별개 디자인 언어(사이드바 GNB), 운영자 전용.
- **웹 관제 SPA** — `GET /control`, `GET /admin/control`. `control/index.blade.php` 는 22줄 셸뿐이고 실제 UI 는 Vite 번들 Vue(`resources/js/control/ControlApp.js`). 상황실 대형 모니터용 풀블리드 레이아웃으로 별도 트랙이 맞다.
- **API 라우트** — `/api/*` 전부 (화면 없음).
- **`resources/views/home.blade.php` (122줄)** — 어디서도 `view('home')` 을 호출하지 않는 **데드 파일**. 리뉴얼 대상이 아니라 삭제 후보.

---

## 규모 요약

| 구분 | GET 화면 라우트 | Blade 파일 | 총 라인 |
|------|---------------|-----------|--------|
| Tier 1 신고 핵심 | 4 (+리다이렉트 1) | 5 (모달·레이아웃 포함) | ~651 |
| Tier 2 인증 | 4 | 4 | 288 |
| Tier 3 운영 인력 | 4 | 3 | 717 |
| Tier 4 프로필 | 4 | 4 | 481 |
| Tier 5 에러·안내 | — | 13 | ~272 |
| **합계** | **16** | **29** | **~2,400** |

여기에 마크업을 품고 있는 `public/js/components/` 11개 파일이 추가로 붙는다.

---

# 디자인 방향 — `src/tmp/` 레퍼런스 기준

레퍼런스 5개 파일 확인 완료. **"Ink + Brand" 디자인 시스템 v6** — 웜 뉴트럴 + 페트롤 틸 브랜드, 모바일 앱 셸(하단 탭 바) 형태.

## 레퍼런스 ↔ 대상 화면 매핑

| 레퍼런스 | 대응 대상 | 비고 |
|---------|---------|-----|
| `tmp/design-system.html` (24KB) | **전체 기준** | 컬러/타이포/아이콘 12종/버튼/배지/2x2 그리드/KPI 카드/목록 행/하단 탭 바 |
| `tmp/dashboard.html` | Tier 1 `dashboard` | 긴급 CTA + KPI 4칸 + 내 행사 + 처리중 요청 + 요청 내역 |
| `tmp/dispatch.html` | Tier 1 `request.create` / `request.create.project` | ⚠️ 파일명은 dispatch 지만 실제로는 **신고 생성 화면**(지도 + 바텀시트 + 상황 4버튼). 구급대원 지령 앱이 아님 |
| `tmp/login.html` | Tier 2 `login` | 중앙 정렬 카드리스, 네이버 버튼 |
| `tmp/profile.html` | Tier 4 `profile.show` | 프로필 카드 + 통계 + 최근 내역 + 설정 목록 + 계정 액션 |

**시안이 없는 화면 = 25개 뷰.** design-system.html 의 컴포넌트 어휘로 파생해야 한다. 특히 시안 없이 새로 설계가 필요한 건:
- `request.show` (신고 상태 추적 — 타임라인·담당자 카드)
- Tier 3 전체 (`events.join` 코드 입력, `events.active` 위치공유 토글, `events.dispatch` **지령 앱 329줄**)
- Tier 5 에러·안내 (특히 `require-phone`)

## 디자인 토큰 이식 — Tailwind 3 → 4 변환 필요

레퍼런스는 **Tailwind CDN v3 + `tailwind.config` JS 객체**로 작성돼 있다. 이 프로젝트는 **Tailwind 4 + Vite**이므로 `tailwind.config.js` 가 아니라 `resources/css/app.css` 의 `@theme` 블록으로 옮겨야 한다.

현재 `app.css` 상태 → 바꿔야 할 것:

| 항목 | 현재 | 레퍼런스 목표 |
|-----|-----|-------------|
| 폰트 | `Geist` (Google Fonts) | **Pretendard** (jsdelivr CDN) |
| 뉴트럴 | Tailwind `slate` | 커스텀 `ink` 11단계 (웜 뉴트럴 `#FAFAF9`~`#0E0C0A`) |
| 주요 색 | `blue-600` | **`brand-600` = `#0E6E7C`** (페트롤 틸) |
| 상태 색 | `amber`/`blue`/`emerald` | `warning-600` `#C4890F` / `danger-600` `#E32F28` / `success-600` `#187F49` |
| body | `bg-slate-50/50 text-slate-900` | `bg-ink-50 text-ink-950` |
| 기존 유틸 | `.card-compact` `.btn-compact` `.glass-compact` | 레퍼런스에 대응 없음 — **정리 또는 재정의 대상** |

`@theme` 로 정의하면 `bg-ink-50`, `text-brand-600` 같은 유틸이 자동 생성된다. 색 이름을 `ink/brand/danger/warning/success` 로 맞춰두면 이후 Blade 는 레퍼런스 클래스를 거의 그대로 복사할 수 있다.

## 가장 큰 작업 — 셸 구조 자체가 바뀐다

레퍼런스는 **모바일 앱 셸**이고 현재 `layouts/app.blade.php`(143줄)는 **웹사이트 셸**이다. 리스타일이 아니라 재작성:

| | 현재 | 레퍼런스 |
|--|-----|---------|
| 헤더 | sticky, 로고 + 데스크톱 nav + 유저 드롭다운(Alpine) + 모바일 햄버거 | 로고 + 알림 벨 버튼만 (h-16), 서브페이지는 뒤로가기 + 타이틀 |
| 하단 | 없음 | **고정 하단 탭 바 3개 (홈 / 구조요청 / 프로필)**, `env(safe-area-inset-bottom)` |
| 푸터 | 사업자정보 + 크레딧 (10줄 그리드) | **없음** |
| 컨테이너 | `max-w-7xl` | `max-w-2xl` (모바일 우선) |
| 의존성 | Alpine.js CDN (드롭다운·햄버거 전용) | 탭 바로 대체되면 **Alpine 제거 가능** |

→ 파생 결정 사항:
1. **하단 탭 3개에 Tier 3 행사 화면이 없다.** 운영 인력(구급대/관제)은 탭 바로 자기 화면에 못 간다. 레퍼런스 dashboard 는 "내 행사" 섹션의 `지령·출동` 버튼으로 진입시키는데, 이 동선만으로 충분한지 결정 필요.
2. **로그아웃 위치 이동** — 현재 헤더 드롭다운 → 레퍼런스는 프로필 화면 하단 버튼. 헤더 드롭다운이 사라지므로 필수 변경.
3. **알림 벨 기능이 없다** — 레퍼런스 헤더에 벨 + 빨간 점(미확인)이 있으나 현재 앱에 알림 목록 화면/API 가 없다. 장식으로 둘지, 범위에 넣을지 결정.
4. **푸터 사업자 정보 처리** — 법적 표기(업체명·사업자번호)라 삭제만 하면 안 된다. 프로필 → "이용약관" 항목 아래로 옮기는 게 자연스럽다.

## 함께 바뀌는 비-Blade 파일

디자인 토큰을 바꾸면 다음이 **같이 안 바뀌면 색이 깨진다**:

1. **`app/Enums/*.php` 뷰 헬퍼** — `RequestStatus::badgeClasses()` 가 `bg-amber-50 text-amber-700 ring-1 ring-amber-200` 처럼 하드코딩. 레퍼런스 배지는 `흰 배경 + border-ink-200 + 색은 아이콘·텍스트만`(긴급만 배경 채움)이라 **클래스 구조 자체가 다르다.** 대상: `RequestStatus`, `RequestPriority`, `RequestType`, `EventRole::markerColor()`, `ParticipantStatus`, `DispatchStatus`.
2. **`public/js/components/*.js` (11개)** — 지도 페이지 마크업이 JS 템플릿 문자열 안에 있다. Blade 만 고치면 Tier 1 화면이 안 바뀌는 함정. `dispatchMeta.js` 는 PHP enum 색상 맵을 JS 로 미러링하고 있어 이중 수정 필요.
3. **`public/manifest.webmanifest`** — `theme_color: "#2563EB"` (파랑) → `#0E6E7C`.
4. **`layouts/app.blade.php:11`** — `<meta name="theme-color" content="#2563EB">` 동일 변경.
5. **`public/icon-192.png` / `icon-512.png`** — 파랑 기반이면 브랜드 틸로 재생성 필요. 레퍼런스 로고는 `brand-600` 배경 + 번개 아이콘.
6. **`public/offline.html`** — PWA 오프라인 폴백, 별도 스타일.
7. **`resources/js/control/*`** — 관제 SPA. **범위 밖이지만** 토큰을 공유하면 색이 바뀐다. `@theme` 만 갈면 slate/blue 유틸을 쓰는 관제 화면은 영향 없으나, 기존 유틸을 지우면 깨진다. → **기존 slate/blue 유틸은 남겨두고 신규 토큰을 추가하는 방식**이 안전.

## 권장 작업 순서

1. **토큰 + 폰트** — `app.css` 에 `@theme` 로 ink/brand/danger/warning/success 추가, Pretendard 교체. 기존 slate/blue 는 관제·관리자 화면 때문에 유지.
2. **셸 재작성** — `layouts/app.blade.php`: 헤더 축소 + 하단 탭 바 + 푸터 제거. 서브페이지용 `<x-page-header>` 슬롯 추가.
3. **공통 컴포넌트 추출** — design-system.html 의 어휘를 Blade 컴포넌트로: 버튼 4종, 배지, 목록 행, KPI 카드, 2x2 액션 그리드, 아이콘 12종(SVG partial).
4. **Enum 뷰 헬퍼 재작성** — 배지 클래스 구조 변경 (배경 채움 → 테두리 + 텍스트 색).
5. **Tier 1** — dashboard → request.create(+project, JS 컴포넌트 포함) → request.show.
6. **Tier 2** — login → register → forgot/reset. (+ 없는 `verify-email`/`two-factor-challenge` 처리 결정)
7. **Tier 4** — profile 4화면. (푸터 사업자정보 이관 포함)
8. **Tier 3** — events.join → events.active → events.dispatch(최대 난이도, 시안 없음).
9. **Tier 5** — 에러·안내 13뷰.
