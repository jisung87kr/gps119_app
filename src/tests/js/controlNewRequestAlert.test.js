import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 관제 — 신고 접수 토스트 + 알림음 (2026-09-07 현장 요청).
 *
 * 상황실은 관제를 켜 두고 다른 창을 본다. 목록에 조용히 한 줄 늘어나는 것으로는 놓친다.
 * Vue 인스턴스 없이 methods 를 this 를 흉내 내 부른다(controlRecall.test.js 와 같은 방식).
 */
function ctx(overrides = {}) {
    const c = {
        requests: [],
        requestPins: null,
        requestCount: 0,
        requestToast: null,
        expandedRequestId: null,
        _alertSound: { play: vi.fn(() => 6), unlock: vi.fn() },
        ...overrides,
    };
    for (const name of ['_onRequestCreated', '_requestCount', '_announceRequest', 'dismissRequestToast', 'focusRequestFromToast']) {
        c[name] = ControlApp.methods[name];
    }

    return c;
}

const payload = {
    request_id: 37, project_id: 4, type: 'accident', priority: 'high',
    latitude: 37.5, longitude: 127.0, address: '양양군 현북면 어성전리',
    requester: { id: 204, name: '홍길동', phone: '01012345679' },
};

describe('관제 — 신고 접수 알림', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('🚨 접수되면 토스트를 띄우고 알림음을 울린다 — 목록 갱신은 그대로', () => {
        const c = ctx();
        c._onRequestCreated(payload);

        expect(c.requests[0]).toBe(payload);
        expect(c.requestCount).toBe(1);
        expect(c.requestToast).toEqual({ id: 37, title: '🚨 신고 접수 #37', body: '사고 · 홍길동 · 양양군 현북면 어성전리' });
        expect(c._alertSound.play).toHaveBeenCalledTimes(1);
    });

    it('8초 뒤 스스로 접힌다', () => {
        const c = ctx();
        c._onRequestCreated(payload);

        vi.advanceTimersByTime(7999);
        expect(c.requestToast).not.toBeNull();
        vi.advanceTimersByTime(1);
        expect(c.requestToast).toBeNull();
    });

    it('연달아 오면 마지막 것으로 바뀌고 타이머도 새로 잡힌다', () => {
        const c = ctx();
        c._onRequestCreated(payload);
        vi.advanceTimersByTime(5000);
        c._onRequestCreated({ ...payload, request_id: 38, requester: { name: '김철수' } });

        expect(c.requestToast.id).toBe(38);
        vi.advanceTimersByTime(5000);
        expect(c.requestToast.id).toBe(38);
        vi.advanceTimersByTime(3000);
        expect(c.requestToast).toBeNull();
    });

    it('토스트를 누르면 그 신고가 목록에서 펼쳐진다', () => {
        const c = ctx();
        c._onRequestCreated(payload);
        c.focusRequestFromToast();

        expect(c.expandedRequestId).toBe(37);
        expect(c.requestToast).toBeNull();
    });

    it('알림음이 준비되지 않았어도(마운트 전) 토스트는 뜬다', () => {
        const c = ctx({ _alertSound: null });

        expect(() => c._onRequestCreated(payload)).not.toThrow();
        expect(c.requestToast.id).toBe(37);
    });

    it('본문은 있는 값만 이어 붙인다 — 이름·주소가 없어도 유형은 남는다', () => {
        const c = ctx();
        c._onRequestCreated({ request_id: 5, type: 'breakdown' });

        expect(c.requestToast.body).toBe('고장');
    });

    it('request_id 가 없는 페이로드는 무시한다', () => {
        const c = ctx();
        c._announceRequest({});

        expect(c.requestToast).toBeNull();
        expect(c._alertSound.play).not.toHaveBeenCalled();
    });
});
