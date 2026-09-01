// 카카오/다음 지도 관련 공유 헬퍼.
// RequestMapApp.js(구조요청 생성)와 RequestShowApp.js(요청 위치 확인)가 함께 사용한다.
// 모든 함수는 kakao.maps.* 네임스페이스를 사용하며, SDK 로드 완료 후에만 호출되어야 한다.

// 기본 지오로케이션 옵션 (양쪽 화면 동일)
//
// 🔴 **실기기에서만 나는 실패가 있었다.** 예전 값은 `timeout: 5000, maximumAge: 0` 이었는데,
//    안드로이드 실기기에서 「사용자 위치 정보를 가져오는 요청이 시간 초과되었습니다」가
//    떴다(2026-09-01, Galaxy A36 / Android 16). 콜드 GPS 픽스는 10~30초가 걸리고
//    실내면 더 걸린다. 데스크톱 브라우저는 Wi-Fi 측위라 즉시 떠서 **개발 중에는 절대
//    안 걸린다** — 그래서 이 값이 오래 살아남았다.
//
// 🔑 `maximumAge` 를 0 으로 두지 않는다. 구조요청에서 30초 전 좌표는 «없는 것»보다
//    압도적으로 낫다. 0 은 캐시를 거부해서 매번 콜드 픽스를 강요한다.
export const GEO_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 30000
};

// 고정밀이 실패했을 때의 2차 시도. 기지국·Wi-Fi 측위라 거의 즉시 오고 정확도만 낮다.
//
// 🔑 **정확도가 낮은 좌표가 「좌표 없음」보다 낫다.** 구조요청 화면은 주소를 보여주고
//    사용자가 «지도에서 보정»할 수 있다 — 대략이라도 있으면 고칠 수 있지만,
//    없으면 사용자는 주소를 직접 검색해야 한다.
export const GEO_FALLBACK_OPTIONS = {
    enableHighAccuracy: false,
    timeout: 10000,
    maximumAge: 120000
};

/**
 * 위·경도를 숫자로 정규화. 둘 중 하나라도 숫자가 아니면 null.
 * @returns {{lat:number, long:number}|null}
 */
export function normalizeCoords(lat, long) {
    const numLat = parseFloat(lat);
    const numLong = parseFloat(long);
    if (isNaN(numLat) || isNaN(numLong)) {
        return null;
    }
    return { lat: numLat, long: numLong };
}

/**
 * 좌표 → 주소 역지오코딩. 도로명주소 우선, 없으면 지번주소.
 * 카카오 coord2Address는 (x=경도, y=위도) 순서이므로 인자도 (long, lat).
 * @returns {Promise<string|null>} 실패 시 null
 */
export function reverseGeocode(long, lat) {
    return new Promise((resolve) => {
        const coords = normalizeCoords(lat, long);
        if (!coords) {
            console.warn('좌표 값이 올바르지 않아 주소 변환을 건너뜁니다.', long, lat);
            resolve(null);
            return;
        }
        const geocoder = new kakao.maps.services.Geocoder();
        geocoder.coord2Address(coords.long, coords.lat, (result, status) => {
            if (status === kakao.maps.services.Status.OK) {
                const address = result[0].road_address && result[0].road_address.address_name
                    ? result[0].road_address.address_name
                    : result[0].address.address_name;
                resolve(address);
            } else {
                resolve(null);
            }
        });
    });
}

/**
 * navigator.geolocation.getCurrentPosition을 Promise로 래핑.
 * 미지원 브라우저 alert / loading 플래그는 호출자가 처리한다(화면별 동작 차이 보존).
 * @returns {Promise<GeolocationPosition>}
 */
export function getCurrentPositionOnce(options = GEO_OPTIONS, env = globalThis) {
    return new Promise((resolve, reject) => {
        const geo = env.navigator?.geolocation;
        if (!geo) {
            reject(new Error('UNSUPPORTED'));
            return;
        }

        geo.getCurrentPosition(resolve, (error) => {
            // 🔑 **시간 초과만 다시 시도한다.** 거부(PERMISSION_DENIED)를 다시 물으면
            //    프롬프트가 두 번 뜨거나 조용히 또 거부되고, 사용자는 이유를 모른 채
            //    기다리기만 한다. 「고칠 수 있는 실패」와 「고칠 수 없는 실패」는 다르다.
            if (error?.code !== error?.TIMEOUT) {
                reject(error);
                return;
            }

            // 이미 저정밀로 부른 것이 또 시간 초과면 더 해볼 게 없다.
            if (options.enableHighAccuracy === false) {
                reject(error);
                return;
            }

            geo.getCurrentPosition(resolve, reject, GEO_FALLBACK_OPTIONS);
        }, options);
    });
}

/**
 * 지오로케이션 에러 코드별 한국어 alert.
 */
export function showGeolocationError(error) {
    let message = '';
    switch (error.code) {
        case error.PERMISSION_DENIED:
            message = "사용자가 위치 정보 요청을 거부했습니다.";
            break;
        case error.POSITION_UNAVAILABLE:
            message = "위치 정보를 사용할 수 없습니다.";
            break;
        case error.TIMEOUT:
            message = "사용자 위치 정보를 가져오는 요청이 시간 초과되었습니다.";
            break;
        case error.UNKNOWN_ERROR:
            message = "알 수 없는 오류가 발생했습니다.";
            break;
    }
    alert(message);
}

/**
 * WGS84 좌표 → WCONGNAMUL 좌표 변환 ('큰지도보기' 딥링크용). services 라이브러리 필요.
 * @returns {Promise<{x:string, y:string}>} 실패 시 reject
 */
export function wgs84ToWCONGNAMUL(lat, long) {
    return new Promise((resolve, reject) => {
        const coords = normalizeCoords(lat, long);
        if (!coords) {
            reject(new Error("좌표 값이 올바르지 않습니다."));
            return;
        }
        const geocoder = new kakao.maps.services.Geocoder();
        geocoder.transCoord(coords.long, coords.lat, (result, status) => {
            if (status === kakao.maps.services.Status.OK) {
                resolve({ x: result[0].x, y: result[0].y });
            } else {
                reject(new Error("좌표 변환 실패"));
            }
        }, {
            input_coord: kakao.maps.services.Coords.WGS84,
            output_coord: kakao.maps.services.Coords.WCONGNAMUL
        });
    });
}

/* ─────────────────────────────────────────────────────────────────────────
   지도 오버레이 UI (2026-08-10 리뉴얼)

   CustomOverlay 안쪽은 Tailwind 가 닿지 않는다(런타임에 문자열로 심는 DOM이라
   빌드 시점 스캔 대상이 아니다). 그래서 인라인 스타일로 쓰되, «색은 디자인 토큰의
   실제 값»을 쓴다. 예전 카드는 파랑/초록 그라디언트(#3b82f6, #10b981)와 slate 회색을
   썼는데 팔레트에 없는 색이라 화면 나머지와 따로 놀았다.

     ink   50 #fafaf9 · 100 #f2f1ee · 200 #e5e3df · 400 #a7a29a · 500 #79746c · 950 #0e0c0a
     brand 600 #0e6e7c · 700 #0a5560
     danger 50 #feecec · 600 #e32f28

   모바일 전용 화면이라 hover 효과는 넣지 않는다(터치엔 hover 가 없고, 예전 코드의
   onmouseover 트랜스폼은 데스크톱에서만 도는 죽은 코드였다). 대신 :active 로 눌림을 준다.
   ───────────────────────────────────────────────────────────────────────── */

/** 오버레이 버튼 공통 스타일. tone: 'brand' | 'neutral' */
function overlayButtonStyle(tone) {
    const palette = tone === 'brand'
        ? 'background:#0e6e7c;color:#ffffff;'
        : 'background:#f2f1ee;color:#17140f;';

    return `
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:5px;
        flex:1 1 0;
        min-height:40px;
        padding:9px 12px;
        border:none;
        border-radius:14px;
        font-size:13px;
        font-weight:700;
        letter-spacing:-0.01em;
        text-decoration:none;
        white-space:nowrap;
        cursor:pointer;
        ${palette}
    `.replace(/\s+/g, ' ').trim();
}

/**
 * 변환된 WCONGNAMUL 좌표로 '큰지도보기' 앵커 HTML 생성.
 * @param {{x:string, y:string}} wcongnamul
 * @returns {string}
 */
export function buildBigMapButton(wcongnamul) {
    return `
        <a href="https://m.map.kakao.com/actions/detailMapView?locName=요청자&urlY=${wcongnamul.y}&urlX=${wcongnamul.x}"
           target="_blank" rel="noopener"
           style="${overlayButtonStyle('neutral')}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h6v6" /><path d="M10 14 21 3" />
                <path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />
            </svg>
            큰지도
        </a>`;
}

/**
 * 요청 마커 인포윈도우 카드 HTML 생성.
 * CustomOverlay로 표시되므로 카드 자체 배경/그림자/말풍선 꼬리를 포함한다.
 * 하단 여백(padding-bottom)으로 카드를 마커 위로 띄워, 핀이 카드를 파고드는 겹침을 방지한다.
 * @param {{requestLat:string|number, requestLong:string|number, bigMapButton:string}} params
 * @returns {string}
 */
export function buildRequestInfoWindowContent({ requestLat, requestLong, bigMapButton }) {
    return `
        <div style="position:relative;padding-bottom:34px;font-family:inherit;">
            <div style="
                position:relative;
                min-width:238px;
                padding:14px;
                background:#ffffff;
                border-radius:20px;
                box-shadow:0 12px 32px -12px rgba(14,12,10,0.35), 0 0 0 1px rgba(14,12,10,0.05);
            ">
                <button data-overlay-close type="button" aria-label="닫기" style="
                    position:absolute;top:10px;right:10px;
                    width:28px;height:28px;padding:0;
                    display:flex;align-items:center;justify-content:center;
                    border:none;border-radius:50%;
                    background:#f2f1ee;color:#79746c;
                    font-size:14px;line-height:1;cursor:pointer;
                ">✕</button>

                <div style="display:flex;align-items:center;gap:8px;padding-right:32px;">
                    <span style="
                        display:flex;align-items:center;justify-content:center;
                        width:28px;height:28px;flex:none;
                        background:#feecec;border-radius:50%;
                    ">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="#e32f28" aria-hidden="true">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z" />
                        </svg>
                    </span>
                    <span style="font-size:15px;font-weight:800;letter-spacing:-0.02em;color:#0e0c0a;">
                        구조 요청 위치
                    </span>
                </div>

                <div style="display:flex;gap:8px;margin-top:12px;">
                    ${bigMapButton}
                    <a href="https://m.map.kakao.com/scheme/route?sp=&sn=&ep=${requestLat},${requestLong}&en=요청자&by=car"
                       target="_blank" rel="noopener"
                       style="${overlayButtonStyle('brand')}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 11l19-9-9 19-2-8-8-2Z" />
                        </svg>
                        길찾기
                    </a>
                </div>
            </div>

            <div style="
                position:absolute;left:50%;bottom:27px;
                width:14px;height:14px;background:#ffffff;
                transform:translateX(-50%) rotate(45deg);
                box-shadow:3px 3px 6px rgba(14,12,10,0.08);
            "></div>
        </div>
    `;
}

/**
 * 카드가 닫혔을 때 마커 위에 표시되는 '열기' 핀 버튼 HTML.
 * 하단 여백으로 마커 위에 띄운다. data-overlay-open 으로 클릭을 연결한다.
 * @returns {string}
 */
export function buildReopenButtonContent() {
    return `
        <div style="position:relative;padding-bottom:36px;font-family:inherit;">
            <button data-overlay-open type="button" style="
                display:inline-flex;align-items:center;gap:5px;
                min-height:34px;padding:7px 13px;
                border:none;border-radius:999px;
                background:#e32f28;color:#ffffff;
                font-size:12px;font-weight:700;letter-spacing:-0.01em;
                white-space:nowrap;cursor:pointer;
                box-shadow:0 6px 16px -4px rgba(227,47,40,0.45);
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff" aria-hidden="true">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z" />
                </svg>
                요청 정보
            </button>
        </div>
    `;
}
