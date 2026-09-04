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
