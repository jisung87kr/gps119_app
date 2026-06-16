// 카카오/다음 지도 관련 공유 헬퍼.
// RequestMapApp.js(구조요청 생성)와 RequestShowApp.js(요청 위치 확인)가 함께 사용한다.
// 모든 함수는 kakao.maps.* 네임스페이스를 사용하며, SDK 로드 완료 후에만 호출되어야 한다.

// 기본 지오로케이션 옵션 (양쪽 화면 동일)
export const GEO_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 5000,
    maximumAge: 0
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
export function getCurrentPositionOnce(options = GEO_OPTIONS) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('UNSUPPORTED'));
            return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
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

/**
 * 변환된 WCONGNAMUL 좌표로 '큰지도보기' 앵커 HTML 생성.
 * @param {{x:string, y:string}} wcongnamul
 * @returns {string}
 */
export function buildBigMapButton(wcongnamul) {
    return `
                            <a href="https://m.map.kakao.com/actions/detailMapView?locName=요청자&urlY=${wcongnamul.y}&urlX=${wcongnamul.x}"
                               target="_blank"
                               style="
                                   display: inline-flex;
                                   align-items: center;
                                   padding: 8px 12px;
                                   background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                                   color: white;
                                   text-decoration: none;
                                   border-radius: 8px;
                                   font-size: 13px;
                                   font-weight: 500;
                                   transition: all 0.2s ease;
                                   box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
                                   border: none;
                               "
                               onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.4)'"
                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(59, 130, 246, 0.3)'"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px;">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                큰지도보기
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
                    <div style="
                        position: relative;
                        padding-bottom: 34px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    ">
                        <div style="
                            position: relative;
                            background: #ffffff;
                            border-radius: 14px;
                            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.18);
                            padding: 15px;
                            min-width: 240px;
                        ">
                            <button data-overlay-close type="button" aria-label="닫기" style="
                                position: absolute;
                                top: 10px;
                                right: 10px;
                                width: 24px;
                                height: 24px;
                                padding: 0;
                                border: none;
                                border-radius: 50%;
                                background: #f1f5f9;
                                color: #64748b;
                                font-size: 13px;
                                line-height: 1;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">✕</button>
                            <div style="
                                display: flex;
                                align-items: center;
                                margin-bottom: 12px;
                                padding-bottom: 8px;
                                padding-right: 28px;
                                border-bottom: 2px solid #fee2e2;
                            ">
                                <div style="
                                    width: 24px;
                                    height: 24px;
                                    background: #dc2626;
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    margin-right: 8px;
                                ">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                </div>
                                <span style="
                                    font-weight: 600;
                                    font-size: 16px;
                                    color: #dc2626;
                                    letter-spacing: -0.02em;
                                ">요청자 위치</span>
                            </div>
                            <div style="
                                display: flex;
                                gap: 8px;
                                flex-wrap: wrap;
                            ">
                                ${bigMapButton}
                                <a href="https://m.map.kakao.com/scheme/route?sp=&sn=&ep=${requestLat},${requestLong}&en=요청자&by=car"
                                   target="_blank"
                                   style="
                                       display: inline-flex;
                                       align-items: center;
                                       padding: 8px 12px;
                                       background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                                       color: white;
                                       text-decoration: none;
                                       border-radius: 8px;
                                       font-size: 13px;
                                       font-weight: 500;
                                       transition: all 0.2s ease;
                                       box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
                                       border: none;
                                   "
                                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.4)'"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(16, 185, 129, 0.3)'"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px;">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                    길찾기
                                </a>
                            </div>
                            <div style="
                                margin-top: 12px;
                                padding-top: 8px;
                                border-top: 1px solid #e2e8f0;
                                font-size: 11px;
                                color: #64748b;
                                text-align: center;
                            ">
                                클릭하여 카카오맵으로 이동
                            </div>
                        </div>
                        <div style="
                            position: absolute;
                            left: 50%;
                            bottom: 27px;
                            width: 14px;
                            height: 14px;
                            background: #ffffff;
                            transform: translateX(-50%) rotate(45deg);
                            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.10);
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
                    <div style="
                        position: relative;
                        padding-bottom: 36px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    ">
                        <button data-overlay-open type="button" style="
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            padding: 7px 13px;
                            border: none;
                            border-radius: 999px;
                            background: #dc2626;
                            color: #ffffff;
                            font-size: 12px;
                            font-weight: 600;
                            white-space: nowrap;
                            cursor: pointer;
                            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
                        ">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            요청 정보
                        </button>
                    </div>
                `;
}
