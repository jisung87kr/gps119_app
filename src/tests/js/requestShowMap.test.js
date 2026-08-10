import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import createRequestShowApp from '../../public/js/components/RequestShowApp.js';

/**
 * 요청 상세(/requests/{id}) 지도 축척·중심 계산 단위 테스트.
 *
 * 실기기에서 보고된 두 증상을 고정한다(2026-08-10):
 *   1. 「지도가 강원↔제주를 다 담는 축척으로 열린다」
 *      → 내 위치를 «모르는» 동안에도 myMarker(기본값 제주 좌표)를 bounds 에 넣고 있었다.
 *   2. 「핸들을 내리면 엉뚱한 곳을 보고 있다」
 *      → 펼침 상태에서 넣은 시트 보정(panBy)을 접을 때 «빼지 않았다».
 *
 * 둘 다 «계산»이라 화면은 멀쩡해 보인다 — 값이 틀려도 지도는 정상적으로 그려진다.
 * 그래서 browse 로 눈으로 보는 대신 여기서 값으로 못박는다.
 *
 * Vue 를 띄우지 않고 options.methods 를 가짜 this 에 bind 해 «계산만» 돌린다.
 * 카카오 SDK 와 document 는 필요한 표면만 스텁한다.
 */

const REQ = { lat: 37.8228, lng: 128.1555 };  // 강원 평창 근처
const ME = { lat: 37.9200, lng: 128.2400 };  // 같은 권역이지만 «떨어진» 지점
const JEJU = { lat: 33.450701, lng: 126.570667 };  // 예전 기본값(가짜 내 위치)

function makeLatLng(lat, lng) {
    return { lat, lng, getLat: () => lat, getLng: () => lng };
}

function makeMarker(lat, lng) {
    let pos = makeLatLng(lat, lng);
    return {
        getPosition: () => pos,
        setPosition: (p) => { pos = p; },
        setMap: vi.fn(),
    };
}

/** setBounds 가 «맞춰 주는» 레벨. 실제 SDK 처럼 두 점의 거리에 따라 달라지는 걸 흉내낸다. */
function fittedLevelFor(extended) {
    if (extended.length < 2) return 4;
    const dLat = Math.abs(extended[0].lat - extended[1].lat);
    const dLng = Math.abs(extended[0].lng - extended[1].lng);
    const span = Math.max(dLat, dLng);
    if (span > 3) return 13;      // 강원↔제주 급
    if (span > 0.05) return 6;
    return 2;                     // 거의 겹침 → 과하게 확대
}

function makeMap() {
    const extended = [];
    const map = {
        level: 8,
        _extended: extended,
        calls: { setCenter: [], setLevel: [], setBounds: 0, panBy: [], relayout: 0 },
        getLevel: () => map.level,
        setLevel: (l) => { map.level = l; map.calls.setLevel.push(l); },
        setCenter: (c) => { map.calls.setCenter.push(c); },
        panBy: (x, y) => { map.calls.panBy.push([x, y]); },
        relayout: () => { map.calls.relayout += 1; },
        setBounds: () => {
            map.calls.setBounds += 1;
            map.level = fittedLevelFor(extended);
        },
    };
    return map;
}

/** options.methods 를 ctx 에 bind 한 «호출 가능한 메서드 묶음»을 만든다. */
function bindMethods(ctx) {
    const app = createRequestShowApp({ request: {} });
    for (const [name, fn] of Object.entries(app.methods)) {
        ctx[name] = fn.bind(ctx);
    }
    return ctx;
}

const SHEET_H = 400;

beforeEach(() => {
    globalThis.kakao = {
        maps: {
            LatLng: function (lat, lng) { return makeLatLng(Number(lat), Number(lng)); },
            LatLngBounds: function () {
                this.points = [];
                this.extend = (p) => { this.points.push(p); };
            },
        },
    };
    // setBounds 가 만든 bounds 의 점들을 map 이 볼 수 있게 연결하는 건 번거로우므로,
    // LatLngBounds 를 map._extended 에 직접 밀어 넣는 방식으로 단순화한다.
    globalThis.document = {
        getElementById: (id) => (id === 'bottom-sheet' ? { offsetHeight: SHEET_H } : null),
    };
});

afterEach(() => {
    delete globalThis.kakao;
    delete globalThis.document;
    vi.restoreAllMocks();
});

describe('setBounds — 아는 점만 담는다', () => {
    it('내 위치를 모르면 요청지 «한 점»만 쓴다 (제주 기본값이 끼어들지 않는다)', () => {
        const map = makeMap();
        const ctx = bindMethods({
            mapObject: map,
            requestMarker: makeMarker(REQ.lat, REQ.lng),
            myMarker: makeMarker(JEJU.lat, JEJU.lng),   // 자리표시 좌표
            myLocated: false,
            sheetExpanded: false,
        });

        ctx.setBounds();

        expect(map.calls.setBounds).toBe(0);                 // 두 점이 아니므로 bounds 를 안 쓴다
        expect(map.calls.setCenter).toHaveLength(1);
        expect(map.calls.setCenter[0].lat).toBeCloseTo(REQ.lat, 4);
        expect(map.level).toBe(4);
    });

    it('내 위치를 알면 두 점을 담되, «맞춘 레벨을 덮어쓰지 않는다»', () => {
        const map = makeMap();
        const ctx = bindMethods({
            mapObject: map,
            requestMarker: makeMarker(REQ.lat, REQ.lng),
            myMarker: makeMarker(ME.lat, ME.lng),
            myLocated: true,
            sheetExpanded: false,
        });
        map._extended.push(makeLatLng(REQ.lat, REQ.lng), makeLatLng(ME.lat, ME.lng));

        ctx.setBounds();

        expect(map.calls.setBounds).toBe(1);
        // 예전 코드는 여기서 항상 setLevel(7) 을 불러 맞춘 축척을 풀어 버렸다.
        expect(map.calls.setLevel).not.toContain(7);
        expect(map.level).toBe(6);
    });

    it('두 점이 거의 겹치면 과도한 확대만 막는다 (레벨 3 하한)', () => {
        const map = makeMap();
        const ctx = bindMethods({
            mapObject: map,
            requestMarker: makeMarker(REQ.lat, REQ.lng),
            myMarker: makeMarker(REQ.lat + 0.0001, REQ.lng),
            myLocated: true,
            sheetExpanded: false,
        });
        map._extended.push(makeLatLng(REQ.lat, REQ.lng), makeLatLng(REQ.lat + 0.0001, REQ.lng));

        ctx.setBounds();

        expect(map.level).toBe(3);
    });
});

describe('toggleSheet — 넣은 보정은 뺀다', () => {
    it('펼침 → 접힘이면 보정을 «되돌린다»', async () => {
        vi.useFakeTimers();
        const map = makeMap();
        const ctx = bindMethods({ mapObject: map, sheetExpanded: true });

        ctx.toggleSheet();
        expect(ctx.sheetExpanded).toBe(false);

        vi.advanceTimersByTime(400);
        expect(map.calls.panBy).toEqual([[0, -SHEET_H / 2]]);
        vi.useRealTimers();
    });

    it('접힘 → 펼침이면 보정을 «다시 넣는다»', () => {
        vi.useFakeTimers();
        const map = makeMap();
        const ctx = bindMethods({ mapObject: map, sheetExpanded: false });

        ctx.toggleSheet();
        vi.advanceTimersByTime(400);

        expect(map.calls.panBy).toEqual([[0, SHEET_H / 2]]);
        vi.useRealTimers();
    });

    it('토글을 왕복하면 순이동이 0 이다 (보정이 누적되지 않는다)', () => {
        vi.useFakeTimers();
        const map = makeMap();
        const ctx = bindMethods({ mapObject: map, sheetExpanded: true });

        ctx.toggleSheet();
        vi.advanceTimersByTime(400);
        ctx.toggleSheet();
        vi.advanceTimersByTime(400);

        const net = map.calls.panBy.reduce((sum, [, dy]) => sum + dy, 0);
        expect(net).toBe(0);
        vi.useRealTimers();
    });
});
