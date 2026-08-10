import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { PersonMarkerPool, RequestPinLayer } from '../../resources/js/control/markerPool.js';
import { initRoleMeta } from '../../resources/js/control/roleMeta.js';

/**
 * 인포윈도는 «항상» 핀 위에 뜬다.
 *
 * 신고핀은 CustomOverlay(z=100)라, 기본 z 의 인포윈도를 그대로 덮어버렸다 —
 * 관제 화면에서 신고 인포윈도를 열면 전화번호·주소 위에 핀이 얹혔다.
 * 둘은 같은 오버레이 페인에서 z 로 겨루므로, 인포윈도에 더 큰 값을 «명시»해야 한다.
 *
 * 화면을 봐야만 걸리는 종류라 사람 눈에 기대면 다음 사람이 되돌려도 아무도 모른다.
 * 여기서 생성 옵션을 고정한다.
 */

const captured = { infoWindows: [], overlays: [], markers: [] };

function installKakao() {
    captured.infoWindows.length = 0;
    captured.overlays.length = 0;
    captured.markers.length = 0;

    global.kakao = {
        maps: {
            LatLng: class { constructor(lat, lng) { this.lat = lat; this.lng = lng; } },
            Size: class {},
            Point: class {},
            MarkerImage: class {},
            Marker: class {
                constructor(opts) { this.opts = opts; captured.markers.push(opts); }
                setOpacity() {}
                getMap() { return null; }
            },
            CustomOverlay: class {
                constructor(opts) { this.opts = opts; captured.overlays.push(opts); }
                setMap() {}
                getPosition() { return this.opts.position; }
            },
            InfoWindow: class {
                constructor(opts) { this.opts = opts; captured.infoWindows.push(opts); }
                setContent() {}
                open() {}
            },
            MarkerClusterer: class {
                addMarker() {} addMarkers() {} removeMarker() {} removeMarkers() {}
            },
            event: { addListener() {} },
        },
    };

    // RequestPinLayer 는 핀 DOM 을 직접 만든다. 속성 대입만 받으면 되는 최소 스텁.
    global.document = {
        createElement: () => ({
            style: { setProperty() {} },
            addEventListener() {},
        }),
    };
}

describe('관제 마커 — 인포윈도 z-index', () => {
    beforeEach(() => {
        installKakao();
        initRoleMeta({ paramedic: { label: '구급대원', color: '#DC2626' } });
    });

    afterEach(() => {
        delete global.kakao;
        delete global.document;
    });

    it('🔑 신고 인포윈도는 신고핀보다 위에 뜬다', () => {
        const layer = new RequestPinLayer({});
        layer.upsert({
            request_id: 1, latitude: 37.88, longitude: 127.73, priority: 'medium',
            requester: { name: '홍길동', phone: '01000000000' }, address: '강원 어딘가',
        });

        const pinZ = captured.overlays[0].zIndex;
        const infoZ = captured.infoWindows[0].zIndex;

        expect(pinZ).toBeGreaterThan(0);
        expect(infoZ).toBeGreaterThan(pinZ);
    });

    it('🔑 인원 인포윈도도 신고핀보다 위에 뜬다', () => {
        // 인원핀(z=10)만 넘으면 된다고 보면, 옆에 신고핀(z=100)이 있을 때 다시 가려진다.
        const layer = new RequestPinLayer({});
        layer.upsert({ request_id: 1, latitude: 37.88, longitude: 127.73, priority: 'medium' });
        const pinZ = captured.overlays[0].zIndex;

        const pool = new PersonMarkerPool({});
        pool.upsert({ user_id: 9, role: 'paramedic', name: '김구급', last_lat: 37.5, last_lng: 127.0 });

        const personInfo = captured.infoWindows[captured.infoWindows.length - 1];
        expect(personInfo.zIndex).toBeGreaterThan(pinZ);
    });
});

describe('관제 마커 — 경계 기여', () => {
    beforeEach(() => {
        installKakao();
        initRoleMeta({ paramedic: { label: '구급대원', color: '#DC2626' } });
    });

    afterEach(() => {
        delete global.kakao;
        delete global.document;
    });

    /** extend 호출을 세는 가짜 bounds */
    function fakeBounds() {
        const points = [];
        return { points, extend(p) { points.push(p); } };
    }

    it('신고핀은 좌표를 경계에 넣는다', () => {
        const layer = new RequestPinLayer({});
        layer.upsert({ request_id: 1, latitude: 37.88, longitude: 127.73, priority: 'medium' });
        layer.upsert({ request_id: 2, latitude: 37.50, longitude: 127.00, priority: 'low' });

        const b = fakeBounds();
        expect(layer.extendBounds(b)).toBe(true);
        expect(b.points).toHaveLength(2);
    });

    it('신고가 없으면 경계에 아무것도 넣지 않는다', () => {
        const b = fakeBounds();

        expect(new RequestPinLayer({}).extendBounds(b)).toBe(false);
        expect(b.points).toHaveLength(0);
    });

    it('🔑 화면에서 숨긴 인원은 경계에 넣지 않는다', () => {
        // 역할 필터로 끈 인원까지 담으면 «보이는 것 전체를 담는다»는 말과 어긋난다.
        const pool = new PersonMarkerPool({});
        pool.setVisibleRoles(new Set(['paramedic']));
        pool.upsert({ user_id: 1, role: 'paramedic', last_lat: 37.5, last_lng: 127.0 });
        pool.upsert({ user_id: 2, role: 'staff', last_lat: 38.0, last_lng: 128.0 });

        const b = fakeBounds();
        expect(pool.extendBounds(b)).toBe(true);
        expect(b.points).toHaveLength(1);
    });

    it('좌표 없는 인원은 건너뛴다', () => {
        const pool = new PersonMarkerPool({});
        pool.setVisibleRoles(new Set(['paramedic']));
        pool.upsert({ user_id: 1, role: 'paramedic', last_lat: null, last_lng: null });

        const b = fakeBounds();
        expect(pool.extendBounds(b)).toBe(false);
    });
});
