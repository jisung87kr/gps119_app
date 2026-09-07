/**
 * 역할 배정 패널의 명단 검색 (순수 함수).
 *
 * 100명이 넘는 행사에서 셀렉트 100개를 스크롤해 사람을 찾을 수는 없다.
 * 이름은 부분 일치(대소문자·공백 무시), 역할은 «라벨»로도 찾는다 —
 * 「구급」을 치면 구급대와 자원봉사자(구급)가 남는다.
 *
 * 전화번호는 검색하지 않는다: 관제 명단 API 는 번호를 싣지 않고(ADR-0004 — 번호는
 * 신고 채널에만), 검색을 위해 싣기 시작하면 100명의 번호가 관제 화면에 한꺼번에 깔린다.
 */

/** 비교용 정규화: 소문자 + 공백 제거. 「김 경숙」과 「김경숙」이 같아야 한다. */
function fold(value) {
    return String(value ?? '').toLowerCase().replace(/\s+/g, '');
}

/**
 * @param {Array<{name?: string|null, role?: string}>} rows
 * @param {string} query
 * @param {(role: string) => string} roleLabel  role 값 → 한글 라벨 (ControlApp.roleLabel)
 * @returns {Array} 검색어가 비어 있으면 rows 그대로(같은 참조).
 */
export function filterRoster(rows, query, roleLabel = () => '') {
    const q = fold(query);
    if (q === '') return rows;

    return rows.filter((r) => {
        if (fold(r.name).includes(q)) return true;

        const label = fold(roleLabel(r.role));

        return label !== '' && label.includes(q);
    });
}

/**
 * 역할 배정 패널의 명단 정렬 (순수 함수) — 1순위 역할(enum 선언순), 2순위 이름 가나다.
 *
 * 2026-09-07 현장 요청(F-08). 서버가 준 순서(입장 순)로는 100명 중 구급대 8명을 찾을 수 없다.
 *
 * 🔑 역할 순서는 서버가 주입한 ROLE_ORDER 를 그대로 쓴다 — 여기 역할 이름을 적지 않는다.
 *    모르는 역할(서버가 새로 추가했는데 아직 이 번들이 모르는 것)은 맨 뒤, 이름 없는 행도 맨 뒤.
 *    원본 배열은 건드리지 않는다(Vue computed 가 잡은 참조를 바꾸면 안 된다).
 *
 * @param {Array<{name?: string|null, role?: string}>} rows
 * @param {string[]} roleOrder  EventRole 선언 순서 (roleMeta.ROLE_ORDER)
 * @returns {Array} 새 배열
 */
export function sortRoster(rows, roleOrder = []) {
    const rank = new Map(roleOrder.map((role, i) => [role, i]));
    const rankOf = (r) => (rank.has(r.role) ? rank.get(r.role) : roleOrder.length);
    const nameOf = (r) => (typeof r.name === 'string' ? r.name.trim() : '');

    return rows
        .map((row, index) => ({ row, index }))
        .sort((a, b) => {
            const byRole = rankOf(a.row) - rankOf(b.row);
            if (byRole !== 0) return byRole;

            const an = nameOf(a.row);
            const bn = nameOf(b.row);
            if (an === '' && bn !== '') return 1;
            if (bn === '' && an !== '') return -1;

            const byName = an.localeCompare(bn, 'ko');
            if (byName !== 0) return byName;

            return a.index - b.index; // 안정 정렬 — 같은 이름은 원래 순서
        })
        .map(({ row }) => row);
}
