// 이동 궤적 레이어 (M-25).
//
// 관제 지도는 «지금 어디 있나»만 그렸다 — 마커를 옮길 뿐 지나온 자리가 남지 않았다.
// 이 레이어가 `kakao.maps.Polyline` 으로 경로를 얹는다.
//
// 🔑 **마커 풀과 같은 원칙: 지우고 다시 만들지 않는다.** 폴리라인은 userId 로 재사용하고
//    좌표만 갈아끼운다(setPath). 새로 만들면 갱신마다 지도가 깜빡인다.
//
// 🔑 **점을 솎는 것은 서버가 한다.** 여기서 또 솎으면 판정이 두 벌이 되고, 화면에서
//    한 솎기는 테스트가 지키지 못한다(TrackSimplifierTest 참조).

import { roleMeta } from './roleMeta';

// 마커(z=10)보다 아래. 선이 핀을 덮으면 「지금 어디」를 못 읽는다.
const Z_TRACK = 5;

const WEIGHT = 4;
const OPACITY = 0.65;

export class TrackLayer {
    constructor(map) {
        this.map = map;
        this.lines = new Map();  // userId -> kakao.maps.Polyline
        this.visible = false;
    }

    /**
     * 서버가 준 궤적으로 갈아끼운다.
     *
     * @param {Array<{user_id:number, points:Array<[number,number]>}>} tracks
     * @param {(userId:number)=>string} roleOf 그 사람의 역할(색을 맞추기 위해)
     */
    render(tracks, roleOf) {
        const seen = new Set();

        for (const track of tracks) {
            const path = track.points.map(([lat, lng]) => new kakao.maps.LatLng(lat, lng));
            if (path.length < 2) continue;

            seen.add(track.user_id);
            const color = roleMeta(roleOf(track.user_id)).color;
            const existing = this.lines.get(track.user_id);

            if (existing) {
                existing.setPath(path);
                existing.setOptions({ strokeColor: color });
                continue;
            }

            const line = new kakao.maps.Polyline({
                path,
                strokeWeight: WEIGHT,
                strokeColor: color,
                strokeOpacity: OPACITY,
                strokeStyle: 'solid',
                zIndex: Z_TRACK,
            });
            if (this.visible) line.setMap(this.map);
            this.lines.set(track.user_id, line);
        }

        // 🔑 이번 응답에 없는 사람의 선은 내린다. 안 그러면 시간 범위를 좁혔을 때
        //    사라져야 할 궤적이 그대로 남아 «지금 보고 있는 범위»와 어긋난다.
        for (const [userId, line] of this.lines) {
            if (!seen.has(userId)) {
                line.setMap(null);
                this.lines.delete(userId);
            }
        }
    }

    setVisible(on) {
        this.visible = on;
        for (const line of this.lines.values()) {
            line.setMap(on ? this.map : null);
        }
    }

    /** 지도 범위를 궤적까지 포함하도록 넓힌다 (markerPool.extendBounds 와 같은 규약) */
    extendBounds(bounds) {
        for (const line of this.lines.values()) {
            for (const point of line.getPath()) {
                bounds.extend(point);
            }
        }
    }

    count() {
        return this.lines.size;
    }

    clear() {
        for (const line of this.lines.values()) line.setMap(null);
        this.lines.clear();
    }
}
