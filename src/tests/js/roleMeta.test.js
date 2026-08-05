import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    ROLE_ORDER, ROLE_META, ROLE_ICONS, initRoleMeta, roleMeta,
} from '../../resources/js/control/roleMeta.js';

/**
 * mobile-app 에픽 0-8 — 관제 역할 메타의 «주입» 계약.
 *
 * PHP 쪽 판정은 tests/Feature/EventRoleMapMetaTest.php 가 맡는다(색 값 정본·주입 여부).
 * 여기서 고정하는 것은 JS 쪽 절반이다:
 *   ① 주입값이 그대로 나오는가 (JS 가 값을 «해석»하지 않는가)
 *   ② import 참조를 재대입하지 않는가 — Vue data() 가 잡은 배열/객체가 죽으면
 *      역할 필터가 통째로 빈 채로 뜬다. 화면에서만 드러나는 종류의 버그다
 *   ③ 소스에 역할별 hex 사본이 «다시 생기지 않았는가» (드리프트 재발 방지)
 */

const SERVER_META = {
    participant: { label: '참가자', color: '#6B7280' },
    staff: { label: '운영진', color: '#2563EB' },
    police: { label: '경찰', color: '#1E3A8A' },
    volunteer_course: { label: '자원봉사자(코스)', color: '#16A34A' },
    volunteer_medic: { label: '자원봉사자(구급)', color: '#F59E0B' },
    paramedic: { label: '구급대', color: '#DC2626' },
    controller: { label: '상황실', color: '#7C3AED' },
};

describe('initRoleMeta — 서버 주입', () => {
    beforeEach(() => {
        initRoleMeta(SERVER_META);
    });

    it('주입한 색·라벨을 그대로 돌려준다', () => {
        Object.entries(SERVER_META).forEach(([role, meta]) => {
            expect(roleMeta(role).color).toBe(meta.color);
            expect(roleMeta(role).label).toBe(meta.label);
        });
    });

    it('순서는 서버가 준 키 순서를 따른다', () => {
        expect(ROLE_ORDER).toEqual(Object.keys(SERVER_META));
    });

    it('아이콘은 JS 가 붙인다 (PHP 는 SVG path 를 모른다)', () => {
        expect(roleMeta('paramedic').icon).toBe('plusBold');
        expect(roleMeta('volunteer_medic').icon).toBe('plusCircle');
    });

    it('서버가 색을 바꾸면 JS 는 따라간다 — 사본이 없다는 증거', () => {
        initRoleMeta({ paramedic: { label: '구급대', color: '#123456' } });
        expect(roleMeta('paramedic').color).toBe('#123456');
    });

    it('재주입해도 배열·객체 참조는 유지된다 (Vue data() 가 잡은 참조)', () => {
        const orderRef = ROLE_ORDER;
        const metaRef = ROLE_META;

        initRoleMeta({ staff: { label: '운영진', color: '#2563EB' } });

        expect(ROLE_ORDER).toBe(orderRef);
        expect(ROLE_META).toBe(metaRef);
        expect(ROLE_ORDER).toEqual(['staff']);
        expect(ROLE_META.participant).toBeUndefined(); // 이전 주입이 남지 않는다
    });

    it('서버가 모르는 역할이 와도 아이콘은 기본값으로 떨어진다', () => {
        initRoleMeta({ drone_pilot: { label: '드론', color: '#000000' } });
        expect(roleMeta('drone_pilot').icon).toBe('user');
        expect(roleMeta('drone_pilot').color).toBe('#000000');
    });
});

describe('initRoleMeta — 주입 실패', () => {
    let errSpy;

    beforeEach(() => {
        errSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        errSpy.mockRestore();
        initRoleMeta(SERVER_META);
    });

    it.each([null, undefined, 'not-json'])('%s 이면 false 를 돌려주고 소리를 낸다', (bad) => {
        expect(initRoleMeta(bad)).toBe(false);
        expect(errSpy).toHaveBeenCalled();
    });

    it('주입 전 조회는 회색 «알 수 없음» 으로 떨어진다 (역할별 색을 지어내지 않는다)', () => {
        initRoleMeta({});
        expect(roleMeta('paramedic')).toEqual({
            label: '알 수 없음', color: '#9CA3AF', icon: 'user',
        });
    });
});

describe('드리프트 재발 방지', () => {
    it('roleMeta.js 소스에 역할별 hex 사본이 없다', () => {
        const src = readFileSync(
            fileURLToPath(new URL('../../resources/js/control/roleMeta.js', import.meta.url)),
            'utf8'
        );

        // 역할 메타 정의부(ROLE_ICONS ~ roleMeta())만 본다.
        // 신고 우선순위(PRIORITY_META)·신고유형 색은 이 에픽의 대상이 아니다.
        const section = src.slice(0, src.indexOf('export const PRIORITY_META'));
        const specColors = Object.values(SERVER_META).map((m) => m.color.toUpperCase());
        const hexes = (section.match(/#[0-9a-fA-F]{6}/g) || []).map((h) => h.toUpperCase());

        const reintroduced = hexes.filter((h) => specColors.includes(h));

        expect(reintroduced, `역할 색 hex 가 JS 에 다시 하드코딩됐다: ${reintroduced.join(', ')}`)
            .toEqual([]);
    });

    it('ROLE_ICONS 는 7종 전부를 덮는다', () => {
        expect(Object.keys(ROLE_ICONS).sort()).toEqual(Object.keys(SERVER_META).sort());
    });
});
