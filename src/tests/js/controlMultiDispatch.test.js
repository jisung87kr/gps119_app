import { describe, it, expect, vi, afterEach } from 'vitest';
import ControlApp from '../../resources/js/control/ControlApp.js';

// environment 는 'node' 라 window 가 없다(vitest.config.js). 전역 의존은 window.axios 뿐.
function withAxios(stub) {
    globalThis.window = { axios: stub };

    return stub;
}
afterEach(() => { delete globalThis.window; });

/**
 * 관제 — 다중 배차 (ADR-0007 D4).
 *
 * 🔑 이 파일이 고정하는 계약: **보조는 주담당 칸을 덮지 않는다.**
 *    requestStatusMap 이 request_id → status 1:1 이던 시절 그대로 두면, 보조가 완료를
 *    누른 순간 신고행이 [완료]로 보이고 [배정] 버튼이 되살아난다 — 아직 현장에 있는
 *    주담당이 화면에서 사라지는 것이다. 사람 눈으로는 「상태가 바뀌었네」로만 보인다.
 */
describe('관제 — 다중 배차', () => {
    /** 메서드를 맵 하나만 가진 최소 컨텍스트에 붙여 부른다. */
    function ctxWith(map) {
        const ctx = {
            requestStatusMap: map,
            _statusEntry: ControlApp.methods._statusEntry,
            requestStatus: ControlApp.methods.requestStatus,
            supportCount: ControlApp.methods.supportCount,
            needsAssign: ControlApp.methods.needsAssign,
            canAddSupport: ControlApp.methods.canAddSupport,
            assignLabel: ControlApp.methods.assignLabel,
        };

        return ctx;
    }

    describe('상태맵 읽기', () => {
        it('🔑 주담당 상태와 보조 인원수를 따로 읽는다', () => {
            const c = ctxWith({ 7: { primary: 'en_route', supports: 2 } });

            expect(c.requestStatus(7)).toBe('en_route');
            expect(c.supportCount(7)).toBe(2);
        });

        it('엔트리가 없으면 미배정 · 보조 0', () => {
            const c = ctxWith({});

            expect(c.requestStatus(9)).toBeNull();
            expect(c.supportCount(9)).toBe(0);
        });

        it('🔑 needsAssign 의 의미는 그대로 «주담당이 없다»다', () => {
            // 보조가 아무리 많아도 책임자가 없으면 배정 대상이다.
            expect(ctxWith({ 7: { primary: null, supports: 3 } }).needsAssign(7)).toBe(true);
            expect(ctxWith({ 7: { primary: 'assigned', supports: 0 } }).needsAssign(7)).toBe(false);
            expect(ctxWith({ 7: { primary: 'cancelled', supports: 1 } }).needsAssign(7)).toBe(true);
            expect(ctxWith({ 7: { primary: 'rejected', supports: 1 } }).needsAssign(7)).toBe(true);
        });

        it('보조 추가는 주담당이 현장에 붙어 있는 동안만', () => {
            for (const s of ['assigned', 'accepted', 'en_route', 'arrived']) {
                expect(ctxWith({ 7: { primary: s, supports: 0 } }).canAddSupport(7), s).toBe(true);
            }
            // 서버도 「주담당 없는 신고에는 보조 배정 거부」다 — 버튼을 먼저 숨겨 422 를 피한다.
            for (const s of [null, 'rejected', 'cancelled', 'completed']) {
                expect(ctxWith({ 7: { primary: s, supports: 0 } }).canAddSupport(7), String(s)).toBe(false);
            }
        });
    });

    describe('보드 로드 → 상태맵', () => {
        async function loadBoard(active) {
            const ctx = {
                hasProject: true,
                selectedProjectId: 1,
                board: { counts: {}, active: [], history: [], loading: false },
                requestStatusMap: {},
            };
            withAxios({ get: vi.fn(async () => ({ data: { data: { counts: {}, active, history: [] } } })) });
            await ControlApp.methods.loadBoard.call(ctx);

            return ctx.requestStatusMap;
        }

        it('🔑 주담당은 상태로, 보조는 머릿수로 접힌다', async () => {
            const map = await loadBoard([
                { dispatch_id: 1, request_id: 5, status: 'en_route', is_primary: true },
                { dispatch_id: 2, request_id: 5, status: 'assigned', is_primary: false },
                { dispatch_id: 3, request_id: 5, status: 'accepted', is_primary: false },
                { dispatch_id: 4, request_id: 6, status: 'assigned', is_primary: true },
            ]);

            expect(map[5]).toEqual({ primary: 'en_route', supports: 2 });
            expect(map[6]).toEqual({ primary: 'assigned', supports: 0 });
        });

        it('보조만 남은 신고는 주담당 없음으로 읽힌다', async () => {
            // 주담당을 회수하고 보조가 아직 이동 중인 상태 — 다시 배정해야 한다.
            const map = await loadBoard([
                { dispatch_id: 2, request_id: 5, status: 'en_route', is_primary: false },
            ]);

            expect(map[5]).toEqual({ primary: null, supports: 1 });
            expect(ctxWith(map).needsAssign(5)).toBe(true);
        });
    });

    describe('실시간 지령 전이 수신', () => {
        function receive(payload, map) {
            const ctx = {
                requestStatusMap: map,
                _statusEntry: ControlApp.methods._statusEntry,
                loadBoard: vi.fn(async () => {}),
            };
            ControlApp.methods._onDispatchUpdated.call(ctx, payload);

            return ctx.requestStatusMap;
        }

        it('🔑 보조의 전이는 주담당 상태를 덮지 않는다', () => {
            const map = receive(
                { request_id: 5, status: 'completed', is_primary: false },
                { 5: { primary: 'en_route', supports: 1 } }
            );

            expect(map[5].primary).toBe('en_route');
        });

        it('주담당의 전이는 반영한다', () => {
            const map = receive(
                { request_id: 5, status: 'arrived', is_primary: true },
                { 5: { primary: 'en_route', supports: 1 } }
            );

            expect(map[5]).toEqual({ primary: 'arrived', supports: 1 });
        });

        it('is_primary 없는 옛 페이로드는 주담당으로 본다(기존 동작 유지)', () => {
            const map = receive({ request_id: 5, status: 'rejected' }, {});

            expect(map[5].primary).toBe('rejected');
        });

        it('빈 페이로드에 넘어지지 않는다', () => {
            expect(() => receive(null, {})).not.toThrow();
            expect(() => receive({}, {})).not.toThrow();
        });
    });

    describe('회수 낙관적 반영', () => {
        async function recall(dispatch, map) {
            const ctx = {
                recall: { dispatch, reason: '', submitting: false, error: '' },
                requestStatusMap: map,
                _statusEntry: ControlApp.methods._statusEntry,
                cancelRecallConfirm: vi.fn(),
                loadBoard: vi.fn(async () => {}),
            };
            withAxios({ patch: vi.fn(async () => ({ data: {} })) });
            await ControlApp.methods.submitRecall.call(ctx);

            return ctx.requestStatusMap;
        }

        it('🔑 보조를 회수해도 주담당 상태는 남는다', async () => {
            const map = await recall(
                { dispatch_id: 2, request_id: 5, status: 'assigned', is_primary: false },
                { 5: { primary: 'en_route', supports: 2 } }
            );

            // 엔트리를 통째로 지우면 그 왕복 동안 「아무도 안 갔다」로 보인다.
            expect(map[5]).toEqual({ primary: 'en_route', supports: 1 });
        });

        it('주담당을 회수하면 즉시 미배정으로 보인다', async () => {
            const map = await recall(
                { dispatch_id: 1, request_id: 5, status: 'assigned', is_primary: true },
                { 5: { primary: 'assigned', supports: 1 } }
            );

            expect(map[5]).toEqual({ primary: null, supports: 1 });
            expect(ctxWith(map).needsAssign(5)).toBe(true);
        });
    });

    describe('배정 제출', () => {
        async function submit(mode, map) {
            const post = vi.fn(async () => ({ data: {} }));
            const ctx = {
                assign: {
                    open: true, mode, request: { request_id: 5 },
                    selectedId: 9, note: '', submitting: false, error: '', confirming: true,
                },
                requestStatusMap: map,
                _statusEntry: ControlApp.methods._statusEntry,
                closeAssign: vi.fn(),
                loadBoard: vi.fn(async () => {}),
            };
            withAxios({ post });
            await ControlApp.methods.submitAssign.call(ctx);

            return { url: post.mock.calls[0][0], map: ctx.requestStatusMap };
        }

        it('🔑 보조는 «다른 URL» 로 나간다 — 플래그가 아니다', async () => {
            const { url } = await submit('support', { 5: { primary: 'accepted', supports: 0 } });

            expect(url).toBe('/api/requests/5/dispatch/support');
        });

        it('주담당은 기존 경로 그대로', async () => {
            const { url } = await submit('primary', {});

            expect(url).toBe('/api/requests/5/dispatch');
        });

        it('보조 발령은 인원수만 올리고 주담당 상태는 그대로 둔다', async () => {
            const { map } = await submit('support', { 5: { primary: 'accepted', supports: 1 } });

            expect(map[5]).toEqual({ primary: 'accepted', supports: 2 });
        });

        it('주담당 발령은 상태를 배정으로 올린다', async () => {
            const { map } = await submit('primary', { 5: { primary: null, supports: 1 } });

            expect(map[5]).toEqual({ primary: 'assigned', supports: 1 });
        });
    });

    describe('보드 행 구분 배지', () => {
        const kindLabel = (d) => ControlApp.methods.kindLabel.call({}, d);

        it('보조만 «보조»로 표시하고 나머지는 주담당이다', () => {
            expect(kindLabel({ is_primary: false })).toBe('보조');
            expect(kindLabel({ is_primary: true })).toBe('주담당');
            // 플래그가 없는 옛 페이로드도 주담당으로 — 화면에 빈 배지를 만들지 않는다.
            expect(kindLabel({})).toBe('주담당');
        });
    });
});
