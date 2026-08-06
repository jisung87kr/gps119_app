# 로컬 개발용 HTTPS

## 왜 필요한가

`navigator.geolocation`·서비스워커·웹푸시는 **보안 컨텍스트(secure context)에서만** 동작한다.
예외는 `localhost` 뿐이고 **사설 IP 는 예외가 아니다.**

```
http://localhost:9050        → localhost 예외 → 보안 컨텍스트 ✅
http://172.30.1.11:9050      → 그냥 http     → 위치·SW·푸시 전부 차단 🔴
https://<호스트>:9051         → ✅
```

🔑 **차단됐을 때 증상이 「미지원」이나 「권한 거부」로 나온다.** 권한 팝업이 아예 뜨지 않고
`code 1 (PERMISSION_DENIED)` 로 떨어지므로, 웹뷰나 OS 권한 문제로 오진하기 쉽다.
실제로 mobile-app N2 에서 이걸 「WKWebView 가 geolocation 미지원」으로 잘못 진단한 전례가 있다.

## 구성

| 파일 | 역할 |
|---|---|
| `common.conf` | 두 vhost 가 공유 — DocumentRoot + Reverb(`/app`,`/apps`) 역프록시 |
| `apache.conf` | http vhost (호스트 **9050** → 80). PC 개발용으로 그대로 유지 |
| `apache-ssl.conf` | https vhost (호스트 **9051** → 443) |
| `certs/` | mkcert 로 만든 인증서. **gitignore — 개인키가 들어 있다** |

WebSocket 도 같은 오리진(9051)으로 붙는다. Apache 가 TLS 를 끊고 내부는 평문 `ws://reverb:8080` 이다.
**`VITE_REVERB_HOST` 를 박지 않는다** — `resources/js/echo.js` 가 현재 페이지 주소에서 유도한다.
(LAN IP 가 바뀌어도 재빌드가 필요 없다. 검증: `tests/js/echoConfig.test.js`)

## 인증서 재발급 (네트워크가 바뀌어 IP 가 달라졌을 때)

호스트명(`*.local`)은 IP 가 바뀌어도 그대로지만, **IP 로 직접 붙으려면 그 IP 가 SAN 에 있어야 한다.**

```bash
cd docker/apache/certs
mkcert -cert-file server.crt -key-file server.key \
  "$(scutil --get LocalHostName | tr 'A-Z' 'a-z').local" \
  localhost 127.0.0.1 ::1 "$(ipconfig getifaddr en0)"
docker compose restart app
```

## 기기에 루트 CA 신뢰시키기

mkcert 는 **로컬 CA** 로 서명한다. 그 CA 를 모르는 기기는 인증서를 거부한다.
루트 CA 위치: `$(mkcert -CAROOT)/rootCA.pem`

### iOS 시뮬레이터

```bash
xcrun simctl keychain booted add-root-cert "$(mkcert -CAROOT)/rootCA.pem"
```

### iPhone 실기기

1. `rootCA.pem` 을 **AirDrop** 으로 폰에 보낸다
2. 설정 → 일반 → **VPN 및 기기 관리** → 프로파일 설치
3. 🔑 설정 → 일반 → 정보 → **인증서 신뢰 설정** → mkcert 항목 **완전 신뢰 켜기**

⚠️ **3번을 빼먹으면 여전히 거부된다.** 프로파일 설치만으로는 부족하다 — iOS 는 수동 설치
루트 CA 를 별도로 「완전 신뢰」해야 쓴다. 가장 흔한 실패 지점이다.

### Android

사용자 인증서로 설치해도 **앱의 WebView 는 기본적으로 사용자 CA 를 신뢰하지 않는다.**
셸(`gps119_app_mobile`)에서 디버그 빌드에 한해 `network_security_config.xml` 로
`<certificates src="user" />` 를 허용해야 한다. 크롬 브라우저는 설치만으로 동작한다.

## 진단 페이지

`/geo-check.html` (`src/public/geo-check.html`) — 로그인·카카오·Vue 를 전부 배제하고
**보안 컨텍스트만** 본다. `isSecureContext` / `geolocation` / `serviceWorker` 유무와
실제 위치 요청 결과(`code` 포함)를 한 화면에 띄운다.

`?auto=1` 이거나 앱 웹뷰(`window.Capacitor`) 안이면 로드 즉시 요청한다 —
시뮬레이터에서 탭 없이 판정하기 위한 것이다.

이 페이지로 확인된 것(2026-08-06, iOS 시뮬레이터):

| | 사파리 http LAN | 사파리 https | **앱 웹뷰 https** |
|---|---|---|---|
| `isSecureContext` | false | true | true |
| `geolocation` | — | 있음 | 있음 |
| `serviceWorker` | — | 있음 | 🔴 **없음** |

**앱 웹뷰에 서비스워커가 없다**는 게 여기서 나왔다 → 앱 안에서 웹 푸시는 불가능하다.

## 접속 주소

| 용도 | 주소 |
|---|---|
| PC 개발 | `http://localhost:9050` (그대로) |
| PC — https 확인 | `https://localhost:9051` |
| 폰·앱 | `https://<LocalHostName>.local:9051` ← IP 가 바뀌어도 안 변한다 |
| 폰 — mDNS 안 될 때 | `https://<LAN IP>:9051` (인증서 SAN 에 그 IP 가 있어야 함) |
