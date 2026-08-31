import { describe, it, expect, vi, beforeEach } from 'vitest';
import { initTrackingMeta, trackingMeta, attentionCount } from '../../resources/js/control/roleMeta.js';

/**
 * 관제의 위치 추적 상태 표시 (M-5 / ADR-0008).
 *
 * 🔑 여기서 고정하려는 것은 색이 아니라 **「무엇을 정상으로 칠하지 않는가」**다.
 *    `unknown` 을 초록으로 떨어뜨리거나 경보에서 빼면 화면은 «평온해 보이고»,
 *    그게 정확히 M-5 를 다시 잃는 길이다 — 사람 눈으로는 안 걸린다.
 */

const INJECTED = {
    tracking: { label: '추적 중', color: '#16A34A', attention: false },
    stale: { label: '신호 끊김', color: '#EA580C', attention: false },
    foreground_only: { label: '앱 열려 있을 때만', color: '#D97706', attention: false },
    blocked: { label: '위치 권한 없음', color: '#DC2626', attention: true },
    off: { label: '공유 꺼짐', color: '#9CA3AF', attention: false },
    unknown: { label: '알 수 없음', color: '#6B7280', attention: false },
};

beforeEach(() => initTrackingMeta(INJECTED));

describe('initTrackingMeta — 서버 주입', () => {
    it('주입이 없으면 false 이고 조용히 넘어가지 않는다', () => {
        const err = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(initTrackingMeta(null)).toBe(false);
        expect(err).toHaveBeenCalled();

        err.mockRestore();
    });

    it('주입된 라벨·색을 그대로 쓴다 — JS 에 사본이 없다', () => {
        expect(trackingMeta('blocked')).toEqual(INJECTED.blocked);
    });
});

describe('trackingMeta — 모르는 값', () => {
    it('🔴 모르는 상태를 «정상»으로 떨어뜨리지 않는다', () => {
        // 서버가 상태를 추가했는데 화면이 옛 번들이면 여기로 온다.
        // 초록(정상)으로 칠하면 「문제 없음」으로 읽힌다.
        const m = trackingMeta('some_new_state');

        expect(m.label).toBe('알 수 없음');
        expect(m.color).not.toBe(INJECTED.tracking.color);
    });

    it('주입 전에도 깨지지 않는다', () => {
        expect(trackingMeta(undefined).label).toBe('알 수 없음');
    });
});

describe('attentionCount — 경보 인원수', () => {
    it('🔑 서버가 attention 이라고 한 것만 센다', () => {
        // 화면이 상태 이름을 나열해 세면, 상태가 늘어날 때 여기만 안 고쳐져 조용히 빠진다.
        const rows = [
            { tracking_state: 'blocked' },
            { tracking_state: 'tracking' },
            { tracking_state: 'blocked' },
            { tracking_state: 'stale' },
        ];

        expect(attentionCount(rows)).toBe(2);
    });

    it('🔴 stale 은 경보가 아니다 — 잠깐의 끊김이 blocked 를 묻는다', () => {
        expect(attentionCount([{ tracking_state: 'stale' }, { tracking_state: 'foreground_only' }])).toBe(0);
    });

    it('unknown 도 경보는 아니다 — 「모름」이지 「사고」가 아니다', () => {
        // 다만 «표시»는 된다(위 테스트). 경보로 올리면 웹 사용자 다수인 동안
        // 경보가 상시 켜져 있어 아무도 안 본다.
        expect(attentionCount([{ tracking_state: 'unknown' }])).toBe(0);
    });

    it('빈 배열·비배열에도 안전하다', () => {
        expect(attentionCount([])).toBe(0);
        expect(attentionCount(null)).toBe(0);
        expect(attentionCount([{}, null])).toBe(0);
    });
});
