import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { TrackLayer } from '../../resources/js/control/trackLayer.js';
import { initRoleMeta } from '../../resources/js/control/roleMeta.js';

/**
 * 이동 궤적 레이어 (M-25).
 *
 * 🔴 이 파일이 고정하는 계약 둘:
 *    ① 갱신은 «재사용»이다. 매번 새 폴리라인을 만들면 지도가 깜빡이고, 옛 선이
 *       지도에 남아 겹친다.
 *    ② 이번 응답에 없는 사람의 선은 «내린다». 시간 범위를 좁혔는데 사라져야 할
 *       궤적이 남으면, 관제사가 보는 선과 「지금 보고 있는 범위」가 어긋난다.
 */

function installKakao() {
    global.kakao = {
        maps: {
            LatLng: class { constructor(lat, lng) { this.lat = lat; this.lng = lng; } },
            Polyline: class {
                constructor(opts) { this.opts = opts; this.path = opts.path; this.map = null; }
                setPath(p) { this.path = p; }
                setOptions(o) { Object.assign(this.opts, o); }
                setMap(m) { this.map = m; }
                getPath() { return this.path; }
            },
        },
    };
}

const MAP = { id: 'map' };

function track(userId, n = 3) {
    return {
        user_id: userId,
        points: Array.from({ length: n }, (_, i) => [37.5665 + i * 0.001, 126.978]),
        count: n,
    };
}

beforeEach(() => {
    installKakao();
    initRoleMeta({ paramedic: { label: '구급대', color: '#DC2626' } });
});

afterEach(() => { delete global.kakao; });

describe('TrackLayer', () => {
    it('궤적마다 선을 하나씩 만든다', () => {
        const layer = new TrackLayer(MAP);

        layer.render([track(1), track(2)], () => 'paramedic');

        expect(layer.count()).toBe(2);
    });

    it('🔴 갱신은 «재사용»이다 — 새로 만들지 않는다', () => {
        const layer = new TrackLayer(MAP);
        layer.render([track(1, 3)], () => 'paramedic');
        const first = layer.lines.get(1);

        layer.render([track(1, 5)], () => 'paramedic');

        expect(layer.lines.get(1)).toBe(first);
        expect(first.getPath()).toHaveLength(5);
    });

    it('🔴 이번 응답에 없는 사람의 선은 내린다', () => {
        const layer = new TrackLayer(MAP);
        layer.setVisible(true);
        layer.render([track(1), track(2)], () => 'paramedic');
        const gone = layer.lines.get(2);

        layer.render([track(1)], () => 'paramedic');

        expect(layer.count()).toBe(1);
        expect(gone.map).toBeNull();
    });

    it('점이 둘 미만이면 선을 만들지 않는다', () => {
        const layer = new TrackLayer(MAP);

        layer.render([track(1, 1)], () => 'paramedic');

        expect(layer.count()).toBe(0);
    });

    it('색은 그 사람의 역할을 따른다', () => {
        const layer = new TrackLayer(MAP);

        layer.render([track(1)], () => 'paramedic');

        expect(layer.lines.get(1).opts.strokeColor).toBe('#DC2626');
    });

    it('🔑 마커보다 아래에 그린다 — 선이 핀을 덮으면 「지금 어디」를 못 읽는다', () => {
        const layer = new TrackLayer(MAP);

        layer.render([track(1)], () => 'paramedic');

        expect(layer.lines.get(1).opts.zIndex).toBeLessThan(10);
    });

    it('꺼져 있으면 지도에 올리지 않는다', () => {
        const layer = new TrackLayer(MAP);

        layer.render([track(1)], () => 'paramedic');

        expect(layer.lines.get(1).map).toBeNull();
    });

    it('켜면 올라가고 끄면 내려간다', () => {
        const layer = new TrackLayer(MAP);
        layer.render([track(1)], () => 'paramedic');

        layer.setVisible(true);
        expect(layer.lines.get(1).map).toBe(MAP);

        layer.setVisible(false);
        expect(layer.lines.get(1).map).toBeNull();
    });

    it('켜진 상태에서 새로 온 궤적도 바로 보인다', () => {
        const layer = new TrackLayer(MAP);
        layer.setVisible(true);

        layer.render([track(9)], () => 'paramedic');

        expect(layer.lines.get(9).map).toBe(MAP);
    });

    it('경계 계산에 궤적 점이 들어간다', () => {
        const layer = new TrackLayer(MAP);
        layer.render([track(1, 3)], () => 'paramedic');
        const extended = [];

        layer.extendBounds({ extend: (p) => extended.push(p) });

        expect(extended).toHaveLength(3);
    });

    it('clear 하면 전부 내려간다', () => {
        const layer = new TrackLayer(MAP);
        layer.setVisible(true);
        layer.render([track(1), track(2)], () => 'paramedic');
        const line = layer.lines.get(1);

        layer.clear();

        expect(layer.count()).toBe(0);
        expect(line.map).toBeNull();
    });
});
