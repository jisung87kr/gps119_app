import { describe, it, expect, vi } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 관제 — 지령 회수 (ADR-0007).
 *
 * 🔑 이 파일이 고정하는 계약은 하나다: **회수한 신고는 다시 배정할 수 있어야 한다.**
 *    「담당자가 없어서 지금 누군가 보내야 하는 신고」를 판정하는 술어에서 cancelled 가
 *    빠지면, 회수는 성공하는데 그 신고에 [배정] 버튼이 안 뜬다 — 화면은 멀쩡해 보이고
 *    회수 기능만 조용히 무의미해진다. 사람 눈으로는 안 걸리는 종류다.
 */
describe('관제 — 지령 회수', () => {
    const needsAssign = (map, id) =>
        ControlApp.methods.needsAssign.call({
            requestStatusMap: map,
            requestStatus(rid) { return this.requestStatusMap[rid] ?? null; },
        }, id);

    describe('재배정 대상 판정', () => {
        it('🔑 회수(cancelled)된 신고는 다시 배정 대상이다', () => {
            expect(needsAssign({ 7: 'cancelled' }, 7)).toBe(true);
        });

        it('거절(rejected)된 신고도 마찬가지다 — 같은 자리', () => {
            expect(needsAssign({ 7: 'rejected' }, 7)).toBe(true);
        });

        it('한 번도 배정 안 된 신고는 당연히 대상이다', () => {
            expect(needsAssign({}, 7)).toBe(true);
        });

        it('진행 중인 지령이 붙은 신고는 대상이 아니다', () => {
            for (const s of ['assigned', 'accepted', 'en_route', 'arrived']) {
                expect(needsAssign({ 7: s }, 7), s).toBe(false);
            }
        });

        it('완료된 신고도 대상이 아니다', () => {
            expect(needsAssign({ 7: 'completed' }, 7)).toBe(false);
        });
    });

    describe('회수 버튼 노출', () => {
        const canRecall = (d) => ControlApp.methods.canRecall.call({}, d);

        it('🔑 도착(arrived) 지령은 회수할 수 없다 — 버튼을 만들지 않는다', () => {
            // 서버 전이표가 422 로 막는다(도착 기록을 없던 일로 만들지 않는다).
            // 눌리는 버튼으로 두면 상황실은 "왜 안 되지"만 배운다.
            expect(canRecall({ dispatch_id: 1, status: 'arrived' })).toBe(false);
        });

        it('배정·수락·출동 중에는 회수할 수 있다', () => {
            for (const s of ['assigned', 'accepted', 'en_route']) {
                expect(canRecall({ dispatch_id: 1, status: s }), s).toBe(true);
            }
        });

        it('지령 없는 행에는 버튼이 없다', () => {
            expect(canRecall(null)).toBe(false);
            expect(canRecall({ status: 'assigned' })).toBe(false);
        });
    });

    describe('신고 취소 수신', () => {
        /** control 채널로 들어오는 .request.status.updated 핸들러 */
        function receive(payload, seed = {}) {
            const ctx = {
                requests: seed.requests ?? [{ request_id: 5 }, { request_id: 6 }],
                requestStatusMap: seed.requestStatusMap ?? { 5: 'assigned' },
                requestCount: 2,
                expandedRequests: {},
                requestPins: null,
                assign: seed.assign ?? { open: false, request: null },
                closeAssign: vi.fn(),
                _removeRequestPin: vi.fn(),
                _requestCount: vi.fn(function () { return this.requests.length; }),
                loadBoard: vi.fn(async () => {}),
            };
            ctx._removeRequest = ControlApp.methods._removeRequest.bind(ctx);
            ControlApp.methods._onRequestStatusUpdated.call(ctx, payload);

            return ctx;
        }

        it('🔑 취소된 신고는 관제 목록에서 사라진다', () => {
            const ctx = receive({ request_id: 5, status: 'cancelled' });

            expect(ctx.requests.map((r) => r.request_id)).toEqual([6]);
            expect(ctx.requestStatusMap[5]).toBeUndefined();
        });

        it('취소 외의 상태 변경은 목록을 건드리지 않는다', () => {
            // 같은 전이에서 나가는 .dispatch.updated 가 이미 보드를 다시 읽는다.
            const ctx = receive({ request_id: 5, status: 'in_progress' });

            expect(ctx.requests).toHaveLength(2);
        });

        it('🔑 취소된 신고를 열어 둔 배정 패널은 닫는다', () => {
            const ctx = receive({ request_id: 5, status: 'cancelled' }, {
                requests: [{ request_id: 5 }],
                assign: { open: true, request: { request_id: 5 } },
            });
            // 안 닫으면 이미 취소된 신고에 발령하고 422 로 알게 된다.
            expect(ctx.closeAssign).toHaveBeenCalled();
        });

        it('빈 페이로드에 넘어지지 않는다', () => {
            expect(() => receive(null)).not.toThrow();
            expect(() => receive({})).not.toThrow();
        });
    });
});
