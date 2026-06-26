// 카카오내비 딥링크 (FE-3.2, DS-3.3 §4).
//
// "출동" 시 신고 **고정 스냅샷 좌표**로 길안내(신고자 실시간 위치 아님 — 06/03 강조).
// 카카오내비 앱 미설치 시 카카오맵 길찾기 웹으로 폴백.

/**
 * 고정좌표로 카카오내비 실행(앱) → 실패 시 카카오맵 웹 길찾기.
 * @param {number} lat  신고 고정 위도
 * @param {number} lng  신고 고정 경도
 * @param {string} name 목적지 라벨
 */
export function openKakaoNavi(lat, lng, name = '신고 위치') {
    if (lat == null || lng == null) return;

    // 카카오내비 앱 딥링크 (kakaonavi 스킴)
    const scheme = `kakaonavi://navigate?ep=${lat},${lng}&name=${encodeURIComponent(name)}&coord_type=wgs84`;
    // 카카오맵 웹 길찾기 폴백(도착지 좌표)
    const webFallback = `https://map.kakao.com/link/to/${encodeURIComponent(name)},${lat},${lng}`;

    let fellBack = false;
    const fallback = () => {
        if (fellBack) return;
        fellBack = true;
        window.open(webFallback, '_blank');
    };

    // 앱 전환이 일어나면 페이지가 백그라운드로 → visibilitychange 로 폴백 취소
    const onHide = () => { fellBack = true; };
    document.addEventListener('visibilitychange', onHide, { once: true });

    // 앱 실행 시도
    window.location.href = scheme;

    // 1.2초 내 전환 없으면 웹 폴백
    setTimeout(() => {
        document.removeEventListener('visibilitychange', onHide);
        fallback();
    }, 1200);
}

export default openKakaoNavi;
