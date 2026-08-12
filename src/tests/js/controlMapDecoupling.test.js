import { describe, it, expect, vi, beforeEach } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 지도 실패가 «관제 기능»을 죽이지 않는다.
 *
 * 예전에는 selectProject() 안에 `if (!this.mapReady) return;` 가 있어서, 카카오 지도가
 * 실패하면 신고 목록·출동 보드·실시간 구독·딥링크 착지가 **전부** 실행되지 않았다.
 * 화면에는 「지도를 불러오지 못했습니다」만 뜨고, 상황실은 «신고가 없는 것»으로 본다.
 * 카카오 쿼터 소진은 이미 리스크로 꼽힌 항목인데(09 리스크 표) 파급 범위를 아무도
 * 추적하지 않았다 — 앱 셸(N2)을 실기에 올려서야 드러났다.
 *
 * 🔑 지도는 «표현»이고, 목록·보드·실시간은 «기능»이다. 이 파일이 그 경계를 고정한다.
 *
 * Vue 를 마운트하지 않고 methods 를 직접 호출한다 — 카카오 SDK·Echo 없이
 * 「무엇을 부르는가」만 보면 되기 때문이다.
 */
describe('관제 — 지도 실패와 기능 분리', () => {
    let ctx;

    /** 지도 로딩 결과만 다른 두 상황을 같은 문맥으로 만든다. */
    function context({ mapReady }) {
        return {
            selectedProjectId: null,
            projects: [{ id: 8, name: '상시 운영' }],
            projectName: '',
            requests: [],
            requestCount: 0,
            requestStatusMap: {},
            roster: [],
            roleCounts: {},
            onlineCount: 0,
            isMobile: false,
            mapReady,
            map: mapReady ? {} : null,
            pool: null,
            requestPins: null,

            // 호출 여부만 기록한다
            _teardownRealtime: vi.fn(),
            closeAssign: vi.fn(),
            cancelRecallConfirm: vi.fn(),
            _ensureMap: vi.fn(async function () { this.mapReady = mapReady; }),
            _applyFilterToPool: vi.fn(),
            fetchRoster: vi.fn(async () => {}),
            fetchRequests: vi.fn(async () => {}),
            loadBoard: vi.fn(async () => {}),
            _subscribeRealtime: vi.fn(),
            _consumeDeepLink: vi.fn(),
            recenter: vi.fn(),
        };
    }

    beforeEach(() => {
        ctx = context({ mapReady: false });
    });

    it('🔑 지도가 실패해도 실시간을 구독한다', async () => {
        await ControlApp.methods.selectProject.call(ctx, 8);

        expect(ctx._subscribeRealtime).toHaveBeenCalled();
    });

    it('🔑 지도가 실패해도 신고 목록·출동 보드를 불러온다', async () => {
        await ControlApp.methods.selectProject.call(ctx, 8);

        expect(ctx.fetchRequests).toHaveBeenCalled();
        expect(ctx.loadBoard).toHaveBeenCalled();
        expect(ctx.fetchRoster).toHaveBeenCalled();
    });

    it('지도가 실패해도 딥링크는 착지한다', async () => {
        // 푸시 알림을 탭해 들어온 경우다. 지도가 죽었다고 그 신고를 못 여는 건 말이 안 된다.
        await ControlApp.methods.selectProject.call(ctx, 8);

        expect(ctx._consumeDeepLink).toHaveBeenCalled();
    });

    it('지도가 실패하면 마커 레이어는 만들지 않는다', async () => {
        // 만들려 하면 kakao 전역이 없어 터진다. «없음»이 올바른 상태다.
        await ControlApp.methods.selectProject.call(ctx, 8);

        expect(ctx.pool).toBeNull();
        expect(ctx.requestPins).toBeNull();
    });

    it('행사 선택 자체는 정상 반영된다', async () => {
        await ControlApp.methods.selectProject.call(ctx, 8);

        expect(ctx.selectedProjectId).toBe(8);
        expect(ctx.projectName).toBe('상시 운영');
    });
});

describe('관제 — 지도 없이도 세는 것들', () => {
    it('신고 건수는 핀이 없으면 목록 길이로 센다', () => {
        const ctx = { requestPins: null, requests: [{ request_id: 1 }, { request_id: 2 }] };

        expect(ControlApp.methods._requestCount.call(ctx)).toBe(2);
    });

    it('신규 신고는 지도가 없어도 목록에 올라간다', () => {
        // 예전에는 핀부터 찍어서 지도가 없으면 여기서 터지고 목록에도 안 올라갔다.
        // _onRequestCreated 가 내부에서 _requestCount 를 부르므로 같이 붙인다
        const ctx = {
            requestPins: null, requests: [], requestCount: 0,
            _requestCount: ControlApp.methods._requestCount,
        };
        ControlApp.methods._onRequestCreated.call(ctx, { request_id: 7 });

        expect(ctx.requests).toHaveLength(1);
        expect(ctx.requestCount).toBe(1);
    });

    it('인력 현황은 마커 풀이 없으면 roster 로 센다', () => {
        // 예전에는 그냥 빠져나가 전원 0 명으로 표시됐다.
        const now = new Date().toISOString();
        const old = new Date(Date.now() - 10 * 60 * 1000).toISOString();
        const ctx = {
            pool: null,
            roster: [
                { role: 'paramedic', last_seen_at: now },
                { role: 'paramedic', last_seen_at: old },
                { role: 'controller', last_seen_at: now },
            ],
            roleCounts: {},
            onlineCount: 0,
        };

        ControlApp.methods._refreshCounts.call(ctx);

        expect(ctx.roleCounts.paramedic).toEqual({ online: 1, total: 2 });
        expect(ctx.roleCounts.controller).toEqual({ online: 1, total: 1 });
        expect(ctx.onlineCount).toBe(2);
    });

    it('위치 브로드캐스트는 지도가 없으면 조용히 지나간다', () => {
        // 실시간 «수신»은 계속돼야 한다 — 여기서 터지면 채널이 죽는다.
        const ctx = { pool: null };

        expect(() => ControlApp.methods._onLocation.call(ctx, {
            user_id: 1, latitude: 37.5, longitude: 127.0, recorded_at: new Date().toISOString(),
        })).not.toThrow();
    });
});
