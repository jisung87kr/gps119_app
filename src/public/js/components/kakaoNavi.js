// 카카오 길안내 (FE-3.2, DS-3.3 §4).
//
// "출동" 시 신고 **고정 스냅샷 좌표**로 길안내(신고자 실시간 위치 아님 — 06/03 강조).
//
// 🔴 2026-09-07 재작성 (field-feedback-2026-09 F-10). 예전 구현은 `location.href = 'kakaonavi://…'`
//    를 찌른 뒤 1.2초 안에 앱 전환이 없으면 `window.open(웹 링크)` 으로 떨어졌다. 앱(WKWebView)
//    에서는 그 폴백이 «사용자 제스처 밖»이라 팝업으로 막혔고, 카카오내비가 없는 아이폰에서는
//    길안내 버튼이 아무 일도 안 했다. 카카오맵 스킴은 시도조차 하지 않았다.
//
// 앱 안에서는 세 경로다 — 위에서부터 «되는 것»을 쓴다:
//   ① 셸에 AppLauncher(openUrl)가 있으면 성공 여부(completed)를 받아 내비 → 카카오맵 → 웹 순으로
//      «확정적으로» 폴백한다. ⚠️ 지금 셸(@capacitor/app 8.1.1)에는 openUrl 이 없다 —
//      @capacitor/app-launcher 를 넣은 셸이 나가면 자동으로 이 경로가 된다.
//   ② 그 전까지는 «이동 + 앱 전환 감지» 체인이다. `location.href = 스킴` 은 Capacitor 가 가로채
//      OS 에 넘기고(iOS UIApplication.open / Android ACTION_VIEW), 앱이 열리면 이 페이지가
//      숨겨진다(visibilitychange). 1.2초 안에 안 숨겨지면 다음 후보로 간다. 마지막 웹 링크도
//      `location.href` 로 보낸다 — Capacitor 가 외부 https 를 «페이지 이동이 아니라 외부 브라우저»
//      로 여니 지령 화면은 그대로 남고, 제스처가 없어도 막히지 않는다(window.open 과 다른 점).
//   ③ 브라우저(웹)에서는 예전 동작 그대로 — 스킴 → 1.2초 → window.open(웹 링크).
//      여기서 location.href 로 웹 링크를 보내면 지령 화면을 «떠나» 버린다.
//
// 🔑 이 파일은 Capacitor 를 «직접» 알지 않는다 — `env.Capacitor` 의 존재와 플러그인 이름만 본다.
//    public/js 는 번들(resources/js/native/bridge.js)을 import 할 수 없어서(vitest.config.js 주석)
//    판정을 여기서 한 번 더 한다. 「그 앱이 그 기능을 아는가」로 판정하는 규칙은 같다.

/**
 * 좌표 → 세 가지 길안내 주소 (순수 함수).
 *
 * - navi: 카카오내비 앱 (kakaonavi 스킴)
 * - map:  카카오맵 앱 길찾기 (kakaomap 스킴, 출발지는 현재 위치)
 * - web:  카카오맵 웹 길찾기 (앱이 하나도 없을 때)
 */
export function kakaoNaviTargets(lat, lng, name = '신고 위치') {
    const label = encodeURIComponent(name);

    return {
        navi: `kakaonavi://navigate?ep=${lat},${lng}&name=${label}&coord_type=wgs84`,
        map: `kakaomap://route?ep=${lat},${lng}&by=CAR`,
        web: `https://map.kakao.com/link/to/${label},${lat},${lng}`,
    };
}

/** 후보를 여는 순서. 내비가 있으면 내비, 없으면 카카오맵, 둘 다 없으면 웹. */
export const OPEN_ORDER = ['navi', 'map', 'web'];

/** 다음 후보로 넘어가기 전에 앱 전환을 기다리는 시간. */
export const SWITCH_WAIT_MS = 1200;

/** Capacitor 웹뷰 안인가. */
export function isNativeApp(env = globalThis) {
    const cap = env.Capacitor;
    if (!cap) return false;

    return typeof cap.isNativePlatform === 'function' ? cap.isNativePlatform() : true;
}

/**
 * 앱 셸이 주는 «확정적» 열기 수단(AppLauncher.openUrl). 없으면 null.
 *
 * @returns {null | ((url: string) => Promise<{completed?: boolean}>)}
 */
export function nativeUrlOpener(env = globalThis) {
    if (!isNativeApp(env)) return null;

    const launcher = env.Capacitor.Plugins?.AppLauncher;
    if (typeof launcher?.openUrl !== 'function') return null;

    return (url) => launcher.openUrl({ url });
}

/**
 * ① 후보를 순서대로 열어 «처음 성공한» 것을 돌려준다. 전부 실패하면 null.
 *
 * 🔑 `completed === true` 만 성공이다. iOS 는 앱이 없으면 false 를 주고, Android 는
 *    ActivityNotFound 를 false 로 접는다. 플러그인이 예외를 던져도 다음 후보로 간다 —
 *    길안내가 실패했다고 지령 화면이 죽으면 안 된다.
 *
 * @returns {Promise<'navi'|'map'|'web'|null>}
 */
export async function openFirstAvailable(targets, opener, order = OPEN_ORDER) {
    for (const key of order) {
        const url = targets[key];
        if (!url) continue;

        try {
            const res = await opener(url);
            if (res && res.completed === true) return key;
        } catch (e) {
            // 다음 후보
        }
    }

    return null;
}

/**
 * ② 앱 안, 플러그인 없음 — 이동 + 앱 전환 감지 체인.
 *
 * 스킴으로 이동시키고 waitMs 안에 페이지가 숨겨지면(앱이 열림) 거기서 끝. 아니면 다음 후보.
 * 마지막(web)은 확인할 것이 없으므로 보내고 바로 끝낸다.
 *
 * @returns {Promise<'navi'|'map'|'web'|null>} 열린(또는 마지막으로 시도한) 것
 */
export function openByNavigation(targets, env = globalThis, order = OPEN_ORDER, waitMs = SWITCH_WAIT_MS) {
    const doc = env.document;

    return new Promise((resolve) => {
        let hidden = false;
        let i = 0;

        const onHide = () => { hidden = true; };
        const done = (value) => {
            doc.removeEventListener('visibilitychange', onHide);
            resolve(value);
        };

        doc.addEventListener('visibilitychange', onHide);

        const tryNext = () => {
            if (hidden) { done(order[i - 1] ?? null); return; }
            if (i >= order.length) { done(null); return; }

            const key = order[i];
            i += 1;

            if (!targets[key]) { tryNext(); return; }

            env.location.href = targets[key];

            if (key === 'web') { done('web'); return; }

            env.setTimeout(tryNext, waitMs);
        };

        tryNext();
    });
}

/**
 * ③ 브라우저 경로 — 예전 동작 그대로. 스킴을 찌르고, 앱 전환(페이지 숨김)이 없으면 웹 폴백.
 *
 * @returns {'navi'} 시도했다는 뜻이지 성공을 보장하지 않는다(브라우저는 알 길이 없다).
 */
export function openInBrowser(targets, env = globalThis, waitMs = SWITCH_WAIT_MS) {
    const doc = env.document;

    let fellBack = false;
    const fallback = () => {
        if (fellBack) return;
        fellBack = true;
        env.open(targets.web, '_blank');
    };

    // 앱 전환이 일어나면 페이지가 백그라운드로 → visibilitychange 로 폴백 취소
    const onHide = () => { fellBack = true; };
    doc.addEventListener('visibilitychange', onHide, { once: true });

    env.location.href = targets.navi;

    env.setTimeout(() => {
        doc.removeEventListener('visibilitychange', onHide);
        fallback();
    }, waitMs);

    return 'navi';
}

/**
 * 고정좌표로 길안내를 연다.
 *
 * @param {number} lat  신고 고정 위도
 * @param {number} lng  신고 고정 경도
 * @param {string} name 목적지 라벨
 * @returns {Promise<'navi'|'map'|'web'|null>}
 */
export function openKakaoNavi(lat, lng, name = '신고 위치', env = globalThis) {
    if (lat == null || lng == null) return Promise.resolve(null);

    const targets = kakaoNaviTargets(lat, lng, name);

    const opener = nativeUrlOpener(env);
    if (opener) return openFirstAvailable(targets, opener);

    if (isNativeApp(env)) return openByNavigation(targets, env);

    return Promise.resolve(openInBrowser(targets, env));
}

export default openKakaoNavi;
