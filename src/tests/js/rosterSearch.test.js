import { describe, it, expect } from 'vitest';
import { filterRoster } from '../../resources/js/control/rosterSearch.js';
import ControlApp from '../../resources/js/control/ControlApp.js';

/**
 * 관제 — 역할 배정 명단 검색.
 *
 * 100명이 넘는 행사에서 관제사가 사람을 찾는 유일한 길이다. 검색이 «조용히» 틀리면
 * (대소문자, 공백, 이름 없는 행) 「그 사람이 명단에 없다」로 읽힌다 — 실제로는 있는데.
 */
const LABEL = {
    paramedic: '구급대',
    volunteer_medic: '자원봉사자(구급)',
    volunteer_course: '자원봉사자(코스)',
    staff: '운영진',
    participant: '참가자',
};
const label = (role) => LABEL[role] ?? '';

const rows = [
    { user_id: 1, name: '김경숙', role: 'staff' },
    { user_id: 2, name: '안기준', role: 'paramedic' },
    { user_id: 3, name: '이 수민', role: 'volunteer_medic' },
    { user_id: 4, name: 'Kim Minsu', role: 'participant' },
    { user_id: 5, name: null, role: 'volunteer_course' },
];
const ids = (list) => list.map((r) => r.user_id);

describe('관제 — 역할 배정 명단 검색', () => {
    it('검색어가 비어 있으면(공백만이어도) 전원 — 같은 배열', () => {
        expect(filterRoster(rows, '', label)).toBe(rows);
        expect(filterRoster(rows, '   ', label)).toBe(rows);
        expect(filterRoster(rows, undefined, label)).toBe(rows);
    });

    it('이름 부분 일치', () => {
        expect(ids(filterRoster(rows, '경숙', label))).toEqual([1]);
        expect(ids(filterRoster(rows, '기', label))).toEqual([2]);
    });

    it('대소문자·공백을 무시한다 — 「김 경숙」과 「김경숙」은 같은 사람', () => {
        expect(ids(filterRoster(rows, 'kim min', label))).toEqual([4]);
        expect(ids(filterRoster(rows, '이수민', label))).toEqual([3]);
        expect(ids(filterRoster(rows, ' 안기준 ', label))).toEqual([2]);
    });

    it('역할 라벨로도 찾는다 — 「구급」이면 구급대와 자원봉사자(구급)', () => {
        expect(ids(filterRoster(rows, '구급', label))).toEqual([2, 3]);
        expect(ids(filterRoster(rows, '운영진', label))).toEqual([1]);
    });

    it('이름이 없는 행은 이름으로는 안 걸리지만 역할로는 걸린다 — 오류 없이', () => {
        expect(ids(filterRoster(rows, '코스', label))).toEqual([5]);
        expect(ids(filterRoster(rows, 'null', label))).toEqual([]);
    });

    it('맞는 사람이 없으면 빈 배열', () => {
        expect(filterRoster(rows, '없는사람', label)).toEqual([]);
    });

    it('라벨 함수가 없어도 이름 검색은 된다', () => {
        expect(ids(filterRoster(rows, '경숙'))).toEqual([1]);
        expect(filterRoster(rows, '구급')).toEqual([]);
    });

    it('🔑 ControlApp.filteredRoster 가 이 함수를 «검색어·roleLabel» 로 부른다 (배선)', () => {
        const filtered = ControlApp.computed.filteredRoster.call({
            roster: rows,
            rosterQuery: '구급',
            roleLabel: label,
        });

        expect(ids(filtered)).toEqual([2, 3]);
    });
});
