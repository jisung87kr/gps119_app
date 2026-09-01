import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    createReportGate,
    initErrorReporting,
    normalizeError,
    reportKey,
} from '../../resources/js/errorReport';

describe('normalizeError — 경계에서 한 번만 바꾼다', () => {
    it('error 이벤트에서 메시지·위치를 뽑는다', () => {
        const payload = normalizeError({
            message: 'x is not defined',
            filename: 'https://gps119.co.kr/js/components/locationShare.js',
            lineno: 42,
            colno: 7,
            error: { message: 'x is not defined', stack: 'ReferenceError\n  at f' },
        });

        expect(payload).toMatchObject({
            kind: 'error',
            message: 'x is not defined',
            line: 42,
            column: 7,
        });
        expect(payload.stack).toContain('ReferenceError');
    });

    it('unhandledrejection 은 reason 에서 읽는다', () => {
        const payload = normalizeError(
            { reason: new Error('네트워크 실패') },
            { kind: 'unhandledrejection' },
        );

        expect(payload.kind).toBe('unhandledrejection');
        expect(payload.message).toBe('네트워크 실패');
    });

    it('문자열로 reject 된 것도 받는다', () => {
        const payload = normalizeError({ reason: '토큰 없음' }, { kind: 'unhandledrejection' });

        expect(payload.message).toBe('토큰 없음');
    });

    it('🔑 메시지가 없으면 보내지 않는다 — 로그에 빈 줄만 남는다', () => {
        expect(normalizeError({})).toBeNull();
        expect(normalizeError({ message: '   ' })).toBeNull();
        expect(normalizeError(null)).toBeNull();
    });

    it('상한을 넘는 문자열은 잘라낸다 (서버 검증과 같은 값)', () => {
        const payload = normalizeError({ message: 'a'.repeat(900) });

        expect(payload.message).toHaveLength(500);
    });
});

describe('createReportGate — 폭주를 막는다', () => {
    it('🔴 같은 에러는 창 안에서 maxPerKey 번까지만 보낸다', () => {
        const gate = createReportGate({ maxPerKey: 3, maxTotal: 100, windowMs: 60_000 });

        expect(gate.allow('k', 0)).toBe(true);
        expect(gate.allow('k', 1)).toBe(true);
        expect(gate.allow('k', 2)).toBe(true);
        expect(gate.allow('k', 3)).toBe(false);
    });

    it('창이 지나면 다시 열린다', () => {
        const gate = createReportGate({ maxPerKey: 1, windowMs: 1000 });

        expect(gate.allow('k', 0)).toBe(true);
        expect(gate.allow('k', 999)).toBe(false);
        expect(gate.allow('k', 1000)).toBe(true);
    });

    it('🔴 다른 에러가 계속 나도 전체 상한에서 멈춘다', () => {
        // 렌더 루프가 «매번 다른» 메시지를 뱉으면 키별 상한은 소용이 없다.
        const gate = createReportGate({ maxPerKey: 5, maxTotal: 3, windowMs: 60_000 });

        expect(gate.allow('a', 0)).toBe(true);
        expect(gate.allow('b', 0)).toBe(true);
        expect(gate.allow('c', 0)).toBe(true);
        expect(gate.allow('d', 0)).toBe(false);
    });

    it('막힌 동안에도 창은 흐른다 — 영구히 잠기지 않는다', () => {
        const gate = createReportGate({ maxPerKey: 1, windowMs: 100 });

        gate.allow('k', 0);
        expect(gate.allow('k', 50)).toBe(false);
        expect(gate.allow('k', 150)).toBe(true);
    });

    it('키는 줄·열까지 보지 않는다 — 소스맵 없는 번들에서 전부 달라진다', () => {
        const a = reportKey({ kind: 'error', message: 'boom', source: 's.js', line: 1, column: 9 });
        const b = reportKey({ kind: 'error', message: 'boom', source: 's.js', line: 1, column: 77 });

        expect(a).toBe(b);
    });
});

describe('initErrorReporting — 배선', () => {
    let env;
    let listeners;

    beforeEach(() => {
        listeners = {};
        env = {
            location: { href: 'https://gps119.co.kr/events/7/active' },
            console: { warn: vi.fn() },
            addEventListener: (type, fn) => { listeners[type] = fn; },
            removeEventListener: (type) => { delete listeners[type]; },
        };
    });

    it('두 종류를 모두 듣는다', () => {
        initErrorReporting({ env, send: vi.fn() });

        expect(Object.keys(listeners).sort()).toEqual(['error', 'unhandledrejection']);
    });

    it('컨텍스트와 현재 주소를 덧붙여 보낸다', () => {
        const send = vi.fn();
        initErrorReporting({ env, send, context: () => ({ platform: 'android', appVersion: '1.0' }) });

        listeners.error({ message: '터졌다', filename: 'a.js', lineno: 3 });

        expect(send).toHaveBeenCalledTimes(1);
        expect(send.mock.calls[0][0]).toMatchObject({
            message: '터졌다',
            platform: 'android',
            appVersion: '1.0',
            url: 'https://gps119.co.kr/events/7/active',
        });
    });

    it('🔴 보고 경로가 깨져도 앱을 멈추지 않는다', () => {
        const send = vi.fn(() => { throw new Error('전송 실패'); });
        initErrorReporting({ env, send });

        expect(() => listeners.error({ message: '터졌다' })).not.toThrow();
        expect(env.console.warn).toHaveBeenCalled();
    });

    it('🔴 보고 실패를 다시 보고하지 않는다 — 무한루프', () => {
        const send = vi.fn(() => { throw new Error('전송 실패'); });
        initErrorReporting({ env, send });

        listeners.error({ message: '터졌다' });

        expect(send).toHaveBeenCalledTimes(1);
    });

    it('게이트가 막으면 보내지 않는다', () => {
        const send = vi.fn();
        let clock = 0;
        initErrorReporting({
            env,
            send,
            now: () => clock,
            gate: createReportGate({ maxPerKey: 2, windowMs: 60_000 }),
        });

        for (let i = 0; i < 10; i += 1) {
            clock += 1;
            listeners.error({ message: '같은 에러', filename: 'a.js', lineno: 1 });
        }

        expect(send).toHaveBeenCalledTimes(2);
    });

    it('🔴 기본 전송기는 CSRF 토큰을 싣는다 — 없으면 419 로 «조용히» 버려진다', () => {
        // Sanctum 이 같은 오리진 요청을 stateful 로 처리해서 api 라우트에도 토큰을
        // 요구한다. 실제로 이것 없이 배선했다가 「fetch 는 나가는데 로그가 안 남는」
        // 상태를 겪었다(2026-09-01). 실패가 화면에 안 보이므로 테스트가 유일한 방어다.
        const fetchSpy = vi.fn(() => ({ catch: () => {} }));
        env.fetch = fetchSpy;
        env.document = {
            querySelector: (sel) => (sel === 'meta[name="csrf-token"]'
                ? { getAttribute: () => 'TOKEN123' }
                : null),
        };

        initErrorReporting({ env });
        listeners.error({ message: '터졌다' });

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        const [url, options] = fetchSpy.mock.calls[0];
        expect(url).toBe('/api/client-errors');
        expect(options.headers['X-CSRF-TOKEN']).toBe('TOKEN123');
        expect(options.keepalive).toBe(true);
    });

    it('토큰 메타가 없어도 전송은 시도한다', () => {
        const fetchSpy = vi.fn(() => ({ catch: () => {} }));
        env.fetch = fetchSpy;
        env.document = { querySelector: () => null };

        initErrorReporting({ env });
        listeners.error({ message: '터졌다' });

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect(fetchSpy.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBeUndefined();
    });

    it('addEventListener 가 없는 환경에서는 아무것도 안 한다', () => {
        expect(initErrorReporting({ env: {} })).toBeNull();
    });
});
