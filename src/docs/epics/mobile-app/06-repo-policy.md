# 06. 레포 관리 정책

> 요구: **「레포 관리 정책 수립 — 웹 / iOS / Android 개별 레포로 나눌 것인가」**

## 1. 결론

🔴 **2개 레포. 웹은 지금 그대로, 앱 셸만 새로 하나.**

```
gps119_app          (기존, 유지)   Laravel + Blade/Vue + Reverb — 화면·API·실시간 전부
gps119_app_mobile   (신설)         Capacitor 셸 + ios/ + android/ — 네이티브 껍데기만
```

**iOS 와 Android 를 따로 나누지 않는다.**

## 2. 왜 iOS·Android 를 나누지 않나

Capacitor 프로젝트에서 `ios/` 와 `android/` 는 **같은 `capacitor.config.ts` 와 같은 플러그인 목록에서 «생성»되는 산출물**이다. 나누면:

- `capacitor.config.ts`·플러그인 버전·브리지 규약이 **두 곳에 복제**된다
- 플러그인 하나를 올릴 때 두 레포에 PR 을 내고 버전을 맞춰야 한다
- 🔑 **이 코드베이스에는 미러 드리프트가 «지금도» 있다.** 회고가 아니라 열려 있는 증거다.

### 2-1. ✅ 실측 — 색상 미러가 어긋나 있었다 → **미러를 없앴다** (2026-08-05 해소)

`roleMeta.js` 와 `EventRole::markerColor()` 는 *"양쪽 같이 수정"* 주석을 달고도 **7개 중 4개**가 달랐다. 아래가 발견 당시의 실측표다.

| 역할 | `resources/js/control/roleMeta.js` | `app/Enums/EventRole.php::markerColor()` | |
|---|---|---|---|
| participant | `#6B7280` | `#6b7280` | 일치 |
| **staff** | `#2563EB` | `#0ea5e9` | 🔴 |
| **police** | `#1E3A8A` | `#1d4ed8` | 🔴 |
| **volunteer_course** | `#16A34A` | `#14b8a6` | 🔴 |
| volunteer_medic | `#F59E0B` | `#f59e0b` | 일치 |
| **paramedic** | `#DC2626` | `#ef4444` | 🔴 |
| controller | `#7C3AED` | `#7c3aed` | 일치 |

🔍 **정본은 [`control-map-spec §2`](../realtime-dispatch-control/backlog/design/control-map-spec.md) 표였고, 어긋난 쪽은 PHP 였다.** 더 나쁜 것은 `markerColor()` 의 **사용처가 0건**이었다는 점이다 — "PHP 가 단일 출처"라는 주석과 달리, 화면을 그린 실질 출처는 JS 사본이었다.

✅ **해소 방식(0-8)** — 값 4건을 정본에 맞추는 데서 그치지 않고 **JS 에서 역할 색·라벨 사본을 삭제**했다.

```
EventRole::markerColor()  ← 정본(control-map-spec §2)
   └ mapMeta() → #control-app[data-role-meta] → initRoleMeta() → ROLE_META
```

JS 에 남은 것은 PHP 가 알 수 없는 **아이콘 형태(SVG path 이름)** 뿐이다. 자동 판정도 남겼다 — `EventRoleMapMetaTest`(주입 경로까지 검사) + `tests/js/roleMeta.test.js`(JS 에 hex 가 다시 생기면 실패).

**규율로 두 벌을 맞추는 건 이 팀 규모에서 작동하지 않는다** — 이 절의 논거가 바로 그것이고, 그래서 «맞추기»가 아니라 «없애기»로 갔다.

플랫폼 분리가 정당한 경우는 «네이티브 코드가 각각 크고 팀이 나뉜 경우»인데, 여기는 **셸이 거의 비어 있다**(URL 열고 플러그인 3개).

## 3. 왜 웹과 앱은 나누나 (모노레포로 안 합치나)

합치는 쪽도 근거는 있다 — 브리지 규약이 한 커밋에 들어가고, 버전 정합이 쉽다. 그런데 **나누는 이유가 더 크다.**

| 이유 | 설명 |
|---|---|
| **릴리스 주기가 근본적으로 다르다** | 웹은 하루 여러 번 배포. 앱은 스토어 심사 때문에 주~월 단위. 한 레포면 태그·CHANGELOG·릴리스 노트가 서로를 오염시킨다 |
| **CI 가 완전히 다르다** | 웹 = PHP/Node 테스트 + Docker. 앱 = Xcode/Gradle 빌드 + 서명 + 스토어 업로드. 한 레포에 두면 대부분의 커밋에서 무거운 모바일 CI 가 헛돈다 |
| **비밀 관리** | 서명 키·`.p8`·keystore 는 **웹 개발자 전원이 접근할 필요가 없다.** 레포가 나뉘면 접근 권한이 자연히 나뉜다 |
| **저장소 무게** | `ios/`·`android/`·Pods·Gradle 캐시가 웹 클론을 무겁게 한다 |
| **웹뷰 방식이라 결합이 애초에 얕다** | 앱은 URL 을 열 뿐. 공유하는 건 **브리지 규약 문서 하나**지 코드가 아니다 |

🔑 **판단 기준은 «코드를 공유하는가»가 아니라 «같이 배포되는가»다.** 웹과 앱은 같이 배포되지 않는다.

## 4. 두 레포 사이의 계약

레포를 나누면 **끊긴 규약**이 위험해진다(이 코드베이스의 알려진 실패 유형). 방어:

### 4-1. 브리지 규약의 정본은 «웹 레포»에 둔다

```
gps119_app/resources/js/native/bridge.js       ← 정본. 웹이 호출하는 인터페이스
gps119_app/docs/epics/mobile-app/bridge-contract.md  ← 규약 문서(신설 예정)
```

앱 셸은 이 규약을 **구현**한다. 웹이 «주인»인 이유는 화면 로직이 웹에 있고, 앱은 그걸 서빙하는 껍데기이기 때문이다.

### 4-2. 버전 협상 — 코드가 아니라 런타임에서 맞춘다

```js
// 웹이 앱에게 물어본다
bridge.getNativeCapabilities()
// → { appVersion: '1.2.0', features: ['bg-location', 'push'] }
```

웹이 **기능 존재 여부를 런타임에 확인**하고 없으면 우아하게 폴백한다(예: 백그라운드 위치 없으면 `watchPosition`). 이러면 **구버전 앱이 깔려 있어도 웹 배포가 앱을 깨뜨리지 않는다.**

🔑 **웹뷰 + 원격 URL 방식의 최대 함정이 이것이다** — 웹은 즉시 갱신되는데 앱은 사용자 기기에 구버전이 남는다. **웹이 항상 «앱이 구버전일 수 있다»를 전제로 짜여야 한다.**

### 4-3. 최소 지원 앱 버전은 서버가 내려준다

```
GET /api/app/config → { min_supported_version: '1.0.0', force_update: false }
```

브리지 규약을 깨는 변경이 있을 때만 올린다.

## 5. 신설 레포 구조

```
gps119_app_mobile/
├── README.md                 # 빌드·서명·배포 절차 (이게 사실상 이 레포의 본체)
├── capacitor.config.ts
├── package.json
├── src/
│   └── offline.html          # 유일한 로컬 웹 자산
├── ios/                      # 커밋함 (CocoaPods 산출물은 제외)
├── android/                  # 커밋함 (build/ 제외)
├── fastlane/  (선택)         # 스토어 업로드 자동화
└── .github/workflows/
    ├── build-ios.yml
    └── build-android.yml
```

**서명 키·인증서는 레포에 넣지 않는다.** CI Secrets 또는 별도 보안 저장소.

## 6. 브랜치·릴리스

두 레포 모두 기존 관행을 따른다 — `main` 보호, 기능 브랜치 → PR → 머지.

| | 웹 (`gps119_app`) | 앱 (`gps119_app_mobile`) |
|---|---|---|
| 태그 | 필요 시 | `v1.2.0` 필수 (스토어 버전과 1:1) |
| CHANGELOG | 선택 | **필수** — 심사 «What's New» 원문 |
| 릴리스 트리거 | 머지 → 배포 | 태그 → CI 빌드 → TestFlight/내부테스트 |

## 7. 미결

| ID | 질문 |
|---|---|
| M-18 | 신설 레포 소유 계정 (개인 vs 조직) — 스토어 명의(M-8)와 함께 결정 |
| M-19 | CI 러너 — GitHub Actions macOS 러너 비용 vs 로컬 빌드 |
| M-20 | `bridge-contract.md` 작성 시점 — N1 착수 전 |
