import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { PersonMarkerPool } from '../../resources/js/control/markerPool.js';
import { initRoleMeta } from '../../resources/js/control/roleMeta.js';

/**
 * 관제 마커의 online/stale/offline 재판정.
 *
 * 🔴 이 파일이 고정하는 계약: **마커 상태는 시간이 지나면 저절로 내려앉는다.**
 *    예전에는 상태 판정이 upsert() 안에만 있었고 실시간 경로는 move() 만 불렀다.
 *    WS 가 붙어 있으면 roster 재조회가 영영 없으므로, 30분 전에 사라진 사람의 핀이
 *    선명한 «온라인» 색 그대로 남았다. 헤더 숫자만 줄어들어서 상황실은
 *    「7명 중 3명 온라인」인데 지도에는 7개가 켜져 있는 화면을 봤다.
 *
 *    화면을 봐야만 걸리는 종류라 사람 눈에 기대면 다음 사람이 되돌려도 아무도 모른다.
 */

function installKakao() {
    global.kakao = {
        maps: {
            LatLng: class { constructor(lat, lng) { this.lat = lat; this.lng = lng; } },
            Size: class {}, Point: class {}, MarkerImage: class {},
            Marker: class {
                constructor(opts) { this.opts = opts; this.opacity = 1; this.image = opts.image; }
                setPosition(p) { this.position = p; }
                setImage(i) { this.image = i; }
                setOpacity(o) { this.opacity = o; }
                setMap() {}
                getMap() { return null; }
            },
            CustomOverlay: class { setMap() {} },
            InfoWindow: class { setContent() {} open() {} },
            MarkerClusterer: class { addMarker() {} addMarkers() {} removeMarker() {} removeMarkers() {} },
            event: { addListener() {} },
        },
    };
}

const ROLE_META = { paramedic: { label: '구급대', color: '#DC2626' } };

/** age 초 전의 ISO 시각 */
const agoIso = (s) => new Date(Date.now() - s * 1000).toISOString();

function makePool(rows) {
    const pool = new PersonMarkerPool({});
    pool.setVisibleRoles(new Set(['paramedic']));
    rows.forEach((r) => pool.upsert({
        user_id: r.user_id, name: 'x', role: 'paramedic', status: 'active',
        last_lat: 37.5, last_lng: 127.0, last_accuracy: 10, last_seen_at: r.lastSeenAt,
    }));

    return pool;
}

describe('관제 마커 — presence 재판정', () => {
    beforeEach(() => { installKakao(); initRoleMeta(ROLE_META); });
    afterEach(() => { delete global.kakao; vi.useRealTimers(); });

    it('🔑 아무 이벤트가 없어도 시간이 지나면 online → offline 이 된다', () => {
        const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);
        expect(pool.markers.get(1).state).toBe('online');
        expect(pool.onlineTotal()).toBe(1);

        // 4분 뒤 — last_seen_at 은 그대로고 «지금»만 흘렀다.
        vi.setSystemTime(new Date(Date.now() + 240_000));
        expect(pool.refreshPresence()).toBe(true);

        expect(pool.markers.get(1).state).toBe('offline');
        expect(pool.markers.get(1).marker.opacity).toBe(0.3);
        expect(pool.onlineTotal()).toBe(0);
    });

    it('바뀐 게 없으면 false 를 돌려준다 (불필요한 리렌더 방지)', () => {
        const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);

        expect(pool.refreshPresence()).toBe(false);
    });

    it('🔑 위치가 다시 오면 마커가 되살아난다', () => {
        const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(300) }]);
        expect(pool.markers.get(1).state).toBe('offline');

        pool.markers.get(1).row.last_seen_at = agoIso(1);
        pool.refreshPresence();

        // 한 번 어두워진 마커가 영영 어두운 채로 남으면, 돌아온 대원이 화면에서 죽어 있다.
        expect(pool.markers.get(1).state).toBe('online');
        expect(pool.markers.get(1).marker.opacity).toBe(1);
    });

    describe('presence 이탈', () => {
        it('🔑 채널에서 나가면 임계 시간을 기다리지 않고 즉시 오프라인이다', () => {
            const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);

            expect(pool.markLeft(1)).toBe(true);

            expect(pool.markers.get(1).state).toBe('offline');
            expect(pool.onlineTotal()).toBe(0);
        });

        it('🔑 마커를 «지우지는» 않는다 — 마지막 위치는 구조에서 값이 있다', () => {
            const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);
            pool.markLeft(1);

            // 연결이 끊긴 사람이야말로 찾아야 하는 사람일 수 있다.
            expect(pool.markers.has(1)).toBe(true);
            expect(pool.markers.get(1).row.last_lat).toBe(37.5);
        });

        it('위치가 다시 오면 이탈 판정이 풀린다', () => {
            const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);
            pool.markLeft(1);

            pool.move(1, 37.6, 127.1, 12);
            pool.markers.get(1).row.last_seen_at = agoIso(1);
            pool.refreshPresence();

            expect(pool.markers.get(1).state).toBe('online');
        });

        it('없는 사람에 대해서는 조용히 false', () => {
            const pool = makePool([]);
            expect(pool.markLeft(999)).toBe(false);
        });
    });

    it('오프라인 숨기기는 이탈한 사람도 대상으로 본다', () => {
        const pool = makePool([{ user_id: 1, lastSeenAt: agoIso(1) }]);
        pool.markLeft(1);
        pool.setHideOffline(true);

        expect(pool._shouldShow(pool.markers.get(1))).toBe(false);
    });
});
