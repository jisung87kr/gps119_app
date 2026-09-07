import { describe, it, expect, vi } from 'vitest';
import {
    kakaoNaviTargets, nativeUrlOpener, openFirstAvailable, openByNavigation, openInBrowser, openKakaoNavi,
} from '../../public/js/components/kakaoNavi.js';

/**
 * 카카오 길안내 — field-feedback-2026-09 F-10.
 *
 * 🔴 아이폰에서 「길안내를 눌러도 아무 일도 안 일어난다」였다. 앱 안에서는 셸의 App.openUrl 이
 *    성공 여부를 돌려주므로 내비 → 카카오맵 → 웹 순으로 «확정적으로» 폴백해야 한다.
 *    브라우저 경로는 예전 동작(스킴 + 전환 감지 + 타이머)을 그대로 지킨다.
 */

const LAT = 37.5665;
const LNG = 126.978;

/** AppLauncher 가 «있는» 셸(미래). */
function nativeEnv(openUrl) {
    return { Capacitor: { isNativePlatform: () => true, Plugins: { AppLauncher: { openUrl } } } };
}

/** 지금 셸 — 플러그인 없음. 이동·타이머·visibilitychange 를 손으로 돌린다. */
function shellEnv() {
    const listeners = new Set();
    const env = {
        Capacitor: { isNativePlatform: () => true, Plugins: { App: {} } },
        document: {
            addEventListener: vi.fn((type, fn) => { if (type === 'visibilitychange') listeners.add(fn); }),
            removeEventListener: vi.fn((type, fn) => { listeners.delete(fn); }),
        },
        location: { href: '' },
        open: vi.fn(),
        hrefs: [],
        timers: [],
        setTimeout: vi.fn((fn) => { env.timers.push(fn); return env.timers.length; }),
    };
    Object.defineProperty(env.location, 'href', {
        set(v) { env.hrefs.push(v); },
        get() { return env.hrefs[env.hrefs.length - 1] ?? ''; },
    });

    return {
        env,
        hide: () => listeners.forEach((fn) => fn()),
        tick: () => { const fn = env.timers.shift(); if (fn) fn(); },
        listening: () => listeners.size,
    };
}

/** completed 를 주는 후보 집합으로 opener 를 만든다. 호출 순서를 기록한다. */
function openerThatCompletes(okSet) {
    const calls = [];
    const opener = vi.fn(async (url) => {
        calls.push(url);
        const key = url.startsWith('kakaonavi:') ? 'navi' : url.startsWith('kakaomap:') ? 'map' : 'web';

        return { completed: okSet.has(key) };
    });

    return { opener, calls };
}

describe('kakaoNaviTargets — 주소 세 가지', () => {
    it('내비·카카오맵·웹 주소를 만들고 라벨은 인코딩한다', () => {
        const t = kakaoNaviTargets(LAT, LNG, '신고 위치');

        expect(t.navi).toBe(`kakaonavi://navigate?ep=${LAT},${LNG}&name=%EC%8B%A0%EA%B3%A0%20%EC%9C%84%EC%B9%98&coord_type=wgs84`);
        expect(t.map).toBe(`kakaomap://route?ep=${LAT},${LNG}&by=CAR`);
        expect(t.web).toBe(`https://map.kakao.com/link/to/%EC%8B%A0%EA%B3%A0%20%EC%9C%84%EC%B9%98,${LAT},${LNG}`);
    });
});

describe('nativeUrlOpener — 「그 앱이 그 기능을 아는가」', () => {
    it('Capacitor 가 없으면(브라우저) null', () => {
        expect(nativeUrlOpener({})).toBeNull();
    });

    it('네이티브가 아니라고 답하면 null', () => {
        expect(nativeUrlOpener({ Capacitor: { isNativePlatform: () => false, Plugins: { AppLauncher: { openUrl() {} } } } })).toBeNull();
    });

    it('🔴 AppLauncher 가 없는 «지금» 셸이면 null — App.openUrl 은 @capacitor/app 8 에 없다', () => {
        expect(nativeUrlOpener({ Capacitor: { isNativePlatform: () => true, Plugins: { App: { getInfo() {} } } } })).toBeNull();
    });

    it('있으면 url 을 {url} 로 감싸 넘기는 함수', async () => {
        const openUrl = vi.fn(async () => ({ completed: true }));
        const open = nativeUrlOpener(nativeEnv(openUrl));

        await open('kakaonavi://x');
        expect(openUrl).toHaveBeenCalledWith({ url: 'kakaonavi://x' });
    });
});

describe('openFirstAvailable — 내비 → 카카오맵 → 웹', () => {
    const targets = kakaoNaviTargets(LAT, LNG);

    it('카카오내비가 있으면 거기서 멈춘다', async () => {
        const { opener, calls } = openerThatCompletes(new Set(['navi', 'map', 'web']));

        expect(await openFirstAvailable(targets, opener)).toBe('navi');
        expect(calls).toEqual([targets.navi]);
    });

    it('🔴 내비가 없고 카카오맵만 있으면 카카오맵 — 아이폰 「연동 안 됨」의 그 경우', async () => {
        const { opener, calls } = openerThatCompletes(new Set(['map', 'web']));

        expect(await openFirstAvailable(targets, opener)).toBe('map');
        expect(calls).toEqual([targets.navi, targets.map]);
    });

    it('둘 다 없으면 웹 링크(브라우저)', async () => {
        const { opener } = openerThatCompletes(new Set(['web']));

        expect(await openFirstAvailable(targets, opener)).toBe('web');
    });

    it('전부 실패하면 null — 예외를 던지지 않는다', async () => {
        const { opener } = openerThatCompletes(new Set());

        expect(await openFirstAvailable(targets, opener)).toBeNull();
    });

    it('플러그인이 예외를 던지거나 completed 를 안 주면 다음 후보로 간다', async () => {
        const opener = vi.fn()
            .mockRejectedValueOnce(new Error('plugin exploded'))
            .mockResolvedValueOnce(undefined)
            .mockResolvedValueOnce({ completed: true });

        expect(await openFirstAvailable(targets, opener)).toBe('web');
        expect(opener).toHaveBeenCalledTimes(3);
    });
});

describe('openByNavigation — 지금 셸: 이동 + 앱 전환 감지 체인', () => {
    const targets = kakaoNaviTargets(LAT, LNG);

    it('카카오내비로 이동했고 앱 전환(숨김)이 오면 거기서 끝', async () => {
        const s = shellEnv();
        const p = openByNavigation(targets, s.env);

        expect(s.env.hrefs).toEqual([targets.navi]);
        s.hide();
        s.tick();

        expect(await p).toBe('navi');
        expect(s.env.hrefs).toEqual([targets.navi]);
        expect(s.listening()).toBe(0);
    });

    it('🔴 내비가 없으면(전환 없음) 1.2초 뒤 카카오맵 스킴 — 아이폰 「연동 안 됨」의 그 경우', async () => {
        const s = shellEnv();
        const p = openByNavigation(targets, s.env);

        s.tick();                                   // 내비 대기 만료
        expect(s.env.hrefs).toEqual([targets.navi, targets.map]);

        s.hide();                                   // 카카오맵이 열렸다
        s.tick();
        expect(await p).toBe('map');
    });

    it('둘 다 없으면 웹 링크를 location.href 로 보낸다 — window.open 이 아니다(제스처 밖에서 막힌다)', async () => {
        const s = shellEnv();
        const p = openByNavigation(targets, s.env);

        s.tick();
        s.tick();

        expect(await p).toBe('web');
        expect(s.env.hrefs).toEqual([targets.navi, targets.map, targets.web]);
        expect(s.env.open).not.toHaveBeenCalled();
        expect(s.listening()).toBe(0);
    });

    it('대기 시간과 순서를 바꿀 수 있다', async () => {
        const s = shellEnv();
        const p = openByNavigation(targets, s.env, ['map', 'web'], 500);

        expect(s.env.setTimeout).toHaveBeenCalledWith(expect.any(Function), 500);
        s.tick();
        expect(await p).toBe('web');
        expect(s.env.hrefs).toEqual([targets.map, targets.web]);
    });
});

describe('openInBrowser — 예전 동작 유지', () => {
    function browserEnv() {
        const listeners = {};
        const env = {
            document: {
                addEventListener: vi.fn((type, fn) => { listeners[type] = fn; }),
                removeEventListener: vi.fn(),
            },
            location: { href: '' },
            open: vi.fn(),
            setTimeout: vi.fn((fn) => { env._timer = fn; return 1; }),
        };

        return { env, fire: (type) => listeners[type]?.() };
    }

    it('스킴을 찌르고, 전환이 없으면 타이머 뒤 웹 링크를 새 창으로 연다', () => {
        const { env } = browserEnv();
        const targets = kakaoNaviTargets(LAT, LNG);

        expect(openInBrowser(targets, env)).toBe('navi');
        expect(env.location.href).toBe(targets.navi);
        expect(env.open).not.toHaveBeenCalled();

        env._timer();
        expect(env.open).toHaveBeenCalledWith(targets.web, '_blank');
    });

    it('앱 전환(페이지 숨김)이 일어나면 웹 폴백을 취소한다', () => {
        const { env, fire } = browserEnv();

        openInBrowser(kakaoNaviTargets(LAT, LNG), env);
        fire('visibilitychange');
        env._timer();

        expect(env.open).not.toHaveBeenCalled();
    });
});

describe('openKakaoNavi — 진입점', () => {
    it('좌표가 없으면 아무것도 하지 않는다', async () => {
        const openUrl = vi.fn();

        expect(await openKakaoNavi(null, LNG, '신고 위치', nativeEnv(openUrl))).toBeNull();
        expect(openUrl).not.toHaveBeenCalled();
    });

    it('AppLauncher 가 있는 셸이면 확정 경로', async () => {
        const openUrl = vi.fn(async ({ url }) => ({ completed: url.startsWith('kakaomap:') }));

        expect(await openKakaoNavi(LAT, LNG, '신고 위치', nativeEnv(openUrl))).toBe('map');
    });

    it('플러그인 없는 지금 셸이면 이동 체인', async () => {
        const s = shellEnv();
        const p = openKakaoNavi(LAT, LNG, '신고 위치', s.env);

        expect(s.env.hrefs[0]).toMatch(/^kakaonavi:/);
        s.hide(); s.tick();
        expect(await p).toBe('navi');
    });

    it('브라우저면 예전 경로(window.open 폴백)', async () => {
        const s = shellEnv();
        delete s.env.Capacitor;

        expect(await openKakaoNavi(LAT, LNG, '신고 위치', s.env)).toBe('navi');
        s.tick();
        expect(s.env.open).toHaveBeenCalledWith(expect.stringMatching(/^https:\/\/map\.kakao\.com/), '_blank');
    });
});
