// 관제 지도 우클릭 컨텍스트 메뉴 — 순수 로직.
//
// 좌표 포맷·공유 URL·메뉴 위치 계산만 담는다(부작용 없음). 클립보드·Web Share·
// 카카오 이벤트 바인딩 같은 부작용은 ControlApp 쪽에 둔다 — 여기는 Vitest 로 지킨다.

/** 컨텍스트 메뉴의 예상 크기(px). 화면 밖으로 안 나가게 clamp 할 때 쓴다. */
export const MENU_SIZE = { width: 176, height: 196 };

/**
 * 좌표를 사람이 읽고 붙여넣을 수 있는 문자열로. 기본 6자리(≈0.1m).
 *
 * @returns {string} 예: "37.566500, 126.978000". 값이 유효하지 않으면 빈 문자열.
 */
export function formatCoords(lat, lng, digits = 6) {
    if (lat == null || lng == null) return ''; // null·undefined (Number(null)===0 이라 따로 막는다)
    const a = Number(lat);
    const b = Number(lng);
    if (!Number.isFinite(a) || !Number.isFinite(b)) return '';

    return `${a.toFixed(digits)}, ${b.toFixed(digits)}`;
}

/**
 * 카카오맵 좌표 링크. 앱·웹 어디서 열어도 그 지점을 가리킨다.
 * label 은 URL 에 들어가므로 인코딩한다(한글·공백·쉼표 방어).
 */
export function kakaoMapUrl(lat, lng, label = '위치') {
    const a = Number(lat).toFixed(6);
    const b = Number(lng).toFixed(6);

    return `https://map.kakao.com/link/map/${encodeURIComponent(label)},${a},${b}`;
}

/**
 * 공유·복사에 쓰는 여러 줄 텍스트: 라벨 + 좌표 + 링크.
 * 무전·메신저에 그대로 붙여넣어 현장에 위치를 전달하는 용도다.
 */
export function shareText(lat, lng, label = '구조 지점') {
    return `${label}\n${formatCoords(lat, lng)}\n${kakaoMapUrl(lat, lng, label)}`;
}

/**
 * 메뉴가 지도 컨테이너 밖으로 삐져나가지 않도록 위치를 가둔다 (순수 함수).
 *
 * 우클릭 지점(pos)에서 오른쪽·아래로 펼쳐지는 메뉴가 경계를 넘으면 안쪽으로 당긴다.
 * 컨테이너가 메뉴보다 작은 극단(초기 렌더 0×0 등)에서는 margin 으로 떨어뜨린다.
 *
 * @returns {{x:number,y:number}}
 */
export function clampMenuPosition(pos, menu = MENU_SIZE, container = { width: 0, height: 0 }, margin = 8) {
    const maxX = Math.max(margin, container.width - menu.width - margin);
    const maxY = Math.max(margin, container.height - menu.height - margin);

    return {
        x: Math.min(Math.max(margin, pos.x), maxX),
        y: Math.min(Math.max(margin, pos.y), maxY),
    };
}

/**
 * 카카오 coord2Address 결과에서 보여줄 주소 문자열을 고른다 (순수 함수).
 *
 * 도로명 주소 우선, 없으면 지번 주소. 둘 다 없으면 null(주소 없이 좌표만 복사한다).
 *
 * @param {Array|null} result kakao.maps.services.Geocoder().coord2Address 콜백의 첫 인자
 * @returns {string|null}
 */
export function pickAddress(result) {
    const r = Array.isArray(result) ? result[0] : null;
    if (!r) return null;

    const road = r.road_address?.address_name;
    const jibun = r.address?.address_name;

    return road || jibun || null;
}

/**
 * 「위치정보 복사」에 쓰는 문자열: 주소가 있으면 주소 + 좌표(두 줄), 없으면 좌표만.
 *
 * 무전·메신저에 붙여넣어 현장에 전달하는 값이라, 주소만으로는 GPS 안내를 못 하고
 * 좌표만으로는 사람이 어딘지 못 읽는다 — 둘 다 준다.
 */
export function locationText(address, lat, lng) {
    const coords = formatCoords(lat, lng);

    return address ? `${address}\n${coords}` : coords;
}
