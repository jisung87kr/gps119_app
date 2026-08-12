import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 관제 지도의 «전체보기» — 중심·배율을 핀이 그리는 경계에 맞춘다.
 *
 * 고쳐진 것 둘:
 *  1. 경계에 인원핀만 넣었다. 인원이 아직 아무도 위치를 공유하지 않은 행사(초기 상태의
 *     대부분)면 경계가 아예 안 잡혀, 신고가 강원도에 찍혀 있어도 지도는 서울시청
 *     기본 좌표에 머물렀다.
 *  2. 초기 조망을 roster 직후에 잡아서, 그 뒤에 올라오는 신고핀은 계산에 못 들어갔다.
 *
 * kakao 전역을 최소한으로 흉내 낸다 — 여기서 볼 것은 «어떤 점들을 경계에 넣고
 * 지도에 무엇을 시키는가»이지 지도의 렌더링이 아니다.
 */

/** setBounds/setLevel 호출을 기록하는 가짜 지도 + kakao 전역 */
function installKakao({ levelAfterFit = 6 } = {}) {
    const calls = { setBounds: [], setLevel: [], setCenter: [] };
    let level = levelAfterFit;

    global.kakao = {
        maps: {
            LatLng: class {
                constructor(lat, lng) { this.lat = lat; this.lng = lng; }
                getLat() { return this.lat; }
                getLng() { return this.lng; }
            },
            LatLngBounds: class {
                constructor() { this.points = []; }
                extend(p) { this.points.push([p.getLat(), p.getLng()]); }
            },
        },
    };

    const map = {
        setBounds: (...args) => calls.setBounds.push(args),
        setLevel: (l) => { level = l; calls.setLevel.push(l); },
        getLevel: () => level,
        setCenter: (c) => calls.setCenter.push(c),
    };

    return { map, calls };
}

/** extendBounds 만 흉내 낸 레이어 — 좌표 목록을 경계에 밀어 넣는다. */
function layer(points) {
    return {
        extendBounds(bounds) {
            points.forEach(([lat, lng]) => bounds.extend(new kakao.maps.LatLng(lat, lng)));
            return points.length > 0;
        },
    };
}

describe('관제 — 전체보기 경계', () => {
    let map, calls;

    beforeEach(() => {
        ({ map, calls } = installKakao());
    });

    afterEach(() => {
        delete global.kakao;
    });

    function ctx(overrides = {}) {
        return {
            map,
            isMobile: false,
            sheetSnap: 'peek',
            pool: null,
            requestPins: null,
            _mapPadding: ControlApp.methods._mapPadding,
            ...overrides,
        };
    }

    it('🔑 신고핀도 경계에 들어간다 (인원이 한 명도 없어도)', () => {
        // 예전에는 인원핀만 봐서 여기서 아무 일도 일어나지 않았다 — 지도는 기본 좌표에 머문다.
        const c = ctx({ requestPins: layer([[37.88, 127.73]]) });

        ControlApp.methods.recenter.call(c);

        expect(calls.setBounds).toHaveLength(1);
        expect(calls.setBounds[0][0].points).toEqual([[37.88, 127.73]]);
    });

    it('🔑 인원핀과 신고핀을 «같은» 경계에 넣는다', () => {
        const c = ctx({
            pool: layer([[37.50, 127.00], [37.55, 127.05]]),
            requestPins: layer([[37.88, 127.73]]),
        });

        ControlApp.methods.recenter.call(c);

        expect(calls.setBounds[0][0].points).toEqual([
            [37.50, 127.00], [37.55, 127.05], [37.88, 127.73],
        ]);
    });

    it('핀이 하나도 없으면 지도를 건드리지 않는다 (기본 중심 유지)', () => {
        const c = ctx({ pool: layer([]), requestPins: layer([]) });

        ControlApp.methods.recenter.call(c);

        expect(calls.setBounds).toHaveLength(0);
        expect(calls.setLevel).toHaveLength(0);
    });

    it('지도가 없으면 조용히 지나간다 (카카오 실패 시 관제는 계속 돈다)', () => {
        const c = ctx({ map: null, requestPins: layer([[37.88, 127.73]]) });

        expect(() => ControlApp.methods.recenter.call(c)).not.toThrow();
    });

    it('핀이 한 점뿐이라 최대 배율까지 붙으면 배율을 되돌린다', () => {
        // setBounds 가 level 1 까지 파고들면 주변 지형이 사라져 «여기가 어디인지»를 잃는다.
        ({ map, calls } = installKakao({ levelAfterFit: 1 }));
        const c = ctx({ requestPins: layer([[37.88, 127.73]]) });

        ControlApp.methods.recenter.call(c);

        expect(calls.setLevel).toEqual([4]);
    });

    it('충분히 벌어진 핀들은 배율을 건드리지 않는다', () => {
        ({ map, calls } = installKakao({ levelAfterFit: 7 }));
        const c = ctx({ requestPins: layer([[37.5, 127.0], [37.9, 127.8]]) });

        ControlApp.methods.recenter.call(c);

        expect(calls.setLevel).toHaveLength(0);
    });
});

describe('관제 — 전체보기 여백', () => {
    it('PC 는 사방 균등 여백', () => {
        const c = { isMobile: false };

        expect(ControlApp.methods._mapPadding.call(c)).toEqual({
            top: 24, right: 24, bottom: 24, left: 24,
        });
    });

    it('🔑 모바일은 바텀시트가 덮는 만큼 아래를 비운다', () => {
        // 안 그러면 핀이 시트 밑에 깔린 채 "전체 보기"라고 말하게 된다.
        // vitest environment 가 node 라 window 가 없다 — 높이만 흉내 낸다.
        const original = global.window;
        global.window = { innerHeight: 800 };
        try {
            const peek = ControlApp.methods._mapPadding.call({ isMobile: true, sheetSnap: 'peek' });
            const half = ControlApp.methods._mapPadding.call({ isMobile: true, sheetSnap: 'half' });
            const full = ControlApp.methods._mapPadding.call({ isMobile: true, sheetSnap: 'full' });

            expect(peek.bottom).toBe(96 + 24);
            expect(half.bottom).toBe(360 + 24);   // 45dvh
            expect(full.bottom).toBe(720 + 24);   // 90dvh
            expect(peek.bottom).toBeLessThan(half.bottom);
            expect(half.bottom).toBeLessThan(full.bottom);
        } finally {
            if (original === undefined) delete global.window;
            else global.window = original;
        }
    });
});

describe('관제 — 초기 조망 시점', () => {
    it('🔑 신고 목록을 불러온 «뒤에» 조망을 잡는다', async () => {
        // 순서가 뒤집히면 신고핀이 아직 없어서 경계에 못 들어간다 — 고치기 전의 버그.
        const order = [];
        const c = {
            selectedProjectId: null,
            projects: [{ id: 8, name: '상시 운영' }],
            projectName: '',
            requests: [], requestCount: 0, requestStatusMap: {},
            mapReady: false, map: null, pool: null, requestPins: null,
            _teardownRealtime: vi.fn(),
            closeAssign: vi.fn(),
            cancelRecallConfirm: vi.fn(),
            _ensureMap: vi.fn(async () => {}),
            _applyFilterToPool: vi.fn(),
            fetchRoster: vi.fn(async () => { order.push('roster'); }),
            fetchRequests: vi.fn(async () => { order.push('requests'); }),
            loadBoard: vi.fn(async () => { order.push('board'); }),
            recenter: vi.fn(() => { order.push('recenter'); }),
            _subscribeRealtime: vi.fn(),
            _consumeDeepLink: vi.fn(),
        };

        await ControlApp.methods.selectProject.call(c, 8);

        expect(order.indexOf('recenter')).toBeGreaterThan(order.indexOf('requests'));
    });
});
