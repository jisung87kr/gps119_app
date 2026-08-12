// 관제 마커 풀 (control-map-spec §3·§5).
//
// - PersonMarkerPool: 인원 이동마커(둥근 물방울 핀). userId→marker 풀 재사용,
//   갱신은 setPosition 만(전체 리렌더 금지). 역할 필터 토글 = setMap only. 클러스터러 사용.
// - RequestPinLayer: 신고 고정핀(각진 경고핀 + 점멸). CustomOverlay, z-100, 클러스터 제외, 이동 안 함.

import { roleMeta, priorityMeta, presenceState, ICON_PATHS } from './roleMeta';

const Z_PERSON = 10;
const Z_REQUEST = 100;
// 🔑 인포윈도는 «항상» 핀 위에 뜬다. 인포윈도와 CustomOverlay 는 같은 오버레이 페인에서
//    z 로 겨루는데, 신고핀(z=100)이 기본 인포윈도를 덮어 전화번호·주소를 가렸다.
//    핀보다 큰 값을 명시적으로 준다 — 기본값에 기대면 SDK 판올림에 다시 깨진다.
const Z_INFO = 1000;

// 정확도 경고 임계값(m). 산·코스에서 이 이상 벌어지면 «그 점을 믿고 가면 안 된다».
const ACCURACY_WARN_M = 50;
const ACCURACY_BAD_M = 150;

// 정확도 표시 — 값이 없으면 «모름»을 명시한다. 빈칸으로 두면 «정확하다»로 읽힌다.
function accuracyHtml(acc) {
    if (acc == null) {
        return '<div style="color:#9ca3af;margin-top:2px;">정확도 정보 없음</div>';
    }
    const color = acc >= ACCURACY_BAD_M ? '#dc2626' : acc >= ACCURACY_WARN_M ? '#d97706' : '#16a34a';
    const note = acc >= ACCURACY_BAD_M ? ' · 신뢰 낮음' : '';
    return `<div style="color:${color};margin-top:2px;">오차 반경 ±${acc}m${note}</div>`;
}

// ── 인원 핀 SVG (둥근 물방울) ───────────────────────────────────────────────
function personPinSvg(role, state) {
    const meta = roleMeta(role);
    const iconPath = ICON_PATHS[meta.icon] || ICON_PATHS.user;
    // offline 은 회색조, stale 은 점선 테두리
    const fill = state === 'offline' ? '#9CA3AF' : meta.color;
    const stroke = state === 'stale' ? '#ffffff' : '#ffffff';
    const dash = state === 'stale' ? 'stroke-dasharray="3 2"' : '';
    const svg =
        `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40">` +
        `<path d="M16 0C7.16 0 0 7.16 0 16c0 11 16 24 16 24s16-13 16-24C32 7.16 24.84 0 16 0z" ` +
        `fill="${fill}" stroke="${stroke}" stroke-width="2" ${dash}/>` +
        `<circle cx="16" cy="15" r="9.5" fill="#ffffff" opacity="0.95"/>` +
        `<g transform="translate(7.6,6.6) scale(0.7)" fill="none" stroke="${fill}" ` +
        `stroke-width="2" stroke-linecap="round" stroke-linejoin="round">` +
        `<path d="${iconPath}"/></g></svg>`;
    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
}

// 클러스터 파라미터는 지도 «픽셀» 기준이라 화면 폭에 따라 의미가 달라진다.
// gridSize 80 은 1000px 지도에서 폭의 8% 지만 375px 지도에서는 21% — 모바일에서
// 전 인원이 한 덩어리로 뭉쳐 지도가 조망 기능을 잃는다. 그래서 프로파일을 나눈다.
export const CLUSTER_PROFILE = {
    desktop: { minLevel: 5, gridSize: 80, minClusterSize: 10 },
    mobile: { minLevel: 5, gridSize: 50, minClusterSize: 5 },
};

export class PersonMarkerPool {
    constructor(map, clusterProfile = CLUSTER_PROFILE.desktop) {
        this.map = map;
        this.markers = new Map();   // userId -> { marker, role, state, row }
        this.visibleRoles = new Set(); // 표시 중인 역할
        this.hideOffline = false;

        this.clusterer = new kakao.maps.MarkerClusterer({
            map,
            averageCenter: true,
            // minLevel: level>=N 클러스터링, 미만은 개별(§5)
            minLevel: clusterProfile.minLevel,
            gridSize: clusterProfile.gridSize,
            minClusterSize: clusterProfile.minClusterSize,
            disableClickZoom: false,
            styles: [{
                width: '40px', height: '40px',
                background: 'rgba(37,99,235,0.9)', color: '#fff',
                borderRadius: '20px', textAlign: 'center', lineHeight: '40px',
                fontSize: '13px', fontWeight: '700',
                border: '2px solid #fff', boxShadow: '0 1px 4px rgba(0,0,0,0.3)',
            }],
        });
    }

    setVisibleRoles(rolesSet) {
        this.visibleRoles = rolesSet;
        this._reapplyVisibility();
    }

    setHideOffline(hide) {
        this.hideOffline = hide;
        this._reapplyVisibility();
    }

    // roster row 1건 upsert
    upsert(row) {
        const userId = row.user_id;
        const state = presenceState(row.last_seen_at);
        const hasCoord = row.last_lat != null && row.last_lng != null;
        let entry = this.markers.get(userId);

        if (!entry) {
            if (!hasCoord) return; // 좌표 없으면 마커 생성 보류
            const marker = new kakao.maps.Marker({
                position: new kakao.maps.LatLng(Number(row.last_lat), Number(row.last_lng)),
                image: this._image(row.role, state),
                zIndex: Z_PERSON,
                title: row.name || '',
            });
            marker.setOpacity(this._opacity(state));
            this._bindInfo(marker, row, state);
            entry = { marker, role: row.role, state, row };
            this.markers.set(userId, entry);
            if (this._shouldShow(entry)) this.clusterer.addMarker(marker);
        } else {
            entry.row = row;
            // 좌표만 이동(리렌더 금지)
            if (hasCoord) {
                entry.marker.setPosition(new kakao.maps.LatLng(Number(row.last_lat), Number(row.last_lng)));
            }
            // 상태/역할 변하면 이미지·투명도만 갱신
            if (entry.state !== state || entry.role !== row.role) {
                entry.marker.setImage(this._image(row.role, state));
                entry.marker.setOpacity(this._opacity(state));
                entry.state = state;
                entry.role = row.role;
            }
            this._applyOne(entry);
        }
    }

    // 좌표만 이동(participant.location 수신용 경량 경로)
    // accuracy 는 좌표와 같이 바뀌므로 row 에 반영한다 — 안 하면 인포윈도가
    // 최초 roster 값에 영원히 고정돼, 이동해도 옛 정확도를 보여준다.
    move(userId, lat, lng, accuracy) {
        const entry = this.markers.get(userId);
        if (!entry) return false;
        entry.marker.setPosition(new kakao.maps.LatLng(Number(lat), Number(lng)));
        if (accuracy !== undefined) entry.row.last_accuracy = accuracy;
        entry.left = false; // 위치가 오면 「나갔다」는 판정을 되돌린다
        return true;
    }

    /**
     * presence 채널에서 나간 사람을 «즉시» 오프라인으로 본다.
     *
     * 🔑 마커를 «지우지» 않는다. 마지막으로 알려진 위치는 구조에서 여전히 값이 있다 —
     *    연결이 끊긴 사람이야말로 찾아야 하는 사람일 수 있다. 지우는 것은 상황실이
     *    「오프라인 숨기기」로 결정한다.
     */
    markLeft(userId) {
        const entry = this.markers.get(userId);
        if (!entry) return false;
        entry.left = true;
        this._applyState(entry);
        return true;
    }

    /**
     * 모든 마커의 online/stale/offline 을 «다시» 판정한다.
     *
     * 🔴 이게 없어서 지도와 숫자가 어긋났다. 상태 판정은 upsert() 안에만 있었고,
     *    실시간 경로는 move() 만 부른다. WS 가 잘 붙어 있으면 roster 재조회가 영영
     *    일어나지 않으므로, 30분 전에 사라진 사람의 핀이 «선명한 온라인 색» 그대로
     *    남았다. 헤더의 온라인 수만 줄어들어, 상황실은 「7명 중 3명 온라인」인데
     *    지도에는 7개가 똑같이 켜져 있는 화면을 봤다.
     *
     * @return {boolean} 하나라도 바뀌었으면 true
     */
    refreshPresence() {
        let changed = false;
        for (const [, entry] of this.markers) {
            if (this._applyState(entry)) changed = true;
        }
        return changed;
    }

    /** 한 마커의 상태를 재판정해 이미지·투명도·표시 여부를 맞춘다. */
    _applyState(entry) {
        const state = this._stateOf(entry);
        if (entry.state === state) return false;

        entry.state = state;
        entry.marker.setImage(this._image(entry.role, state));
        entry.marker.setOpacity(this._opacity(state));
        this._applyOne(entry);
        return true;
    }

    /** presence 이탈은 last_seen_at 보다 «강하다» — 임계 시간을 기다릴 필요가 없다. */
    _stateOf(entry) {
        return entry.left ? 'offline' : presenceState(entry.row.last_seen_at);
    }

    // online 카운트(역할별) — 인력현황/필터 카운트용
    counts() {
        const result = {};
        for (const [, e] of this.markers) {
            const st = this._stateOf(e);
            const r = e.row.role;
            result[r] = result[r] || { online: 0, total: 0 };
            result[r].total++;
            if (st === 'online') result[r].online++;
        }
        return result;
    }

    onlineTotal() {
        let n = 0;
        for (const [, e] of this.markers) {
            if (this._stateOf(e) === 'online') n++;
        }
        return n;
    }

    _image(role, state) {
        return new kakao.maps.MarkerImage(
            personPinSvg(role, state),
            new kakao.maps.Size(32, 40),
            { offset: new kakao.maps.Point(16, 40) }
        );
    }

    _opacity(state) {
        return state === 'offline' ? 0.3 : state === 'stale' ? 0.6 : 1;
    }

    _shouldShow(entry) {
        if (!this.visibleRoles.has(entry.role)) return false;
        if (this.hideOffline && this._stateOf(entry) === 'offline') return false;
        return true;
    }

    _applyOne(entry) {
        const show = this._shouldShow(entry);
        const onMap = entry.marker.getMap() != null;
        if (show && !onMap) this.clusterer.addMarker(entry.marker);
        else if (!show && onMap) this.clusterer.removeMarker(entry.marker);
    }

    _reapplyVisibility() {
        const toAdd = [];
        const toRemove = [];
        for (const [, e] of this.markers) {
            const show = this._shouldShow(e);
            const onMap = e.marker.getMap() != null;
            if (show && !onMap) toAdd.push(e.marker);
            else if (!show && onMap) toRemove.push(e.marker);
        }
        if (toRemove.length) this.clusterer.removeMarkers(toRemove);
        if (toAdd.length) this.clusterer.addMarkers(toAdd);
    }

    // 인원 인포윈도 — §7: 연락처 슬롯 없음(이름·역할·online·정확도)
    //
    // 인포윈도는 생성 시점의 row 를 캡처하므로 매번 새로 그린다. accuracy 는 위치가
    // 갱신될 때마다 바뀌기 때문에, 열리는 순간의 값을 읽어야 한다.
    _bindInfo(marker, row, state) {
        const iw = new kakao.maps.InfoWindow({ content: '', removable: true, zIndex: Z_INFO });
        kakao.maps.event.addListener(marker, 'click', () => {
            const entry = this.markers.get(row.user_id);
            const cur = entry ? entry.row : row;
            const curState = entry ? entry.state : state;
            iw.setContent(this._infoHtml(cur, curState));
            iw.open(this.map, marker);
        });
    }

    _infoHtml(row, state) {
        const meta = roleMeta(row.role);
        const stLabel = state === 'online' ? '온라인' : state === 'stale' ? '위치지연' : '오프라인';
        return `<div style="padding:8px 10px;min-width:140px;font-size:12px;">` +
            `<div style="font-weight:700;margin-bottom:2px;">${escapeHtml(row.name || '이름없음')}</div>` +
            `<div style="color:#555;">` +
            `<span style="display:inline-block;width:8px;height:8px;border-radius:4px;background:${meta.color};margin-right:4px;"></span>` +
            `${meta.label} · ${stLabel}</div>` +
            accuracyHtml(row.last_accuracy) +
            `</div>`;
    }

    /**
     * 인원 좌표로 bounds 를 넓힌다. 하나라도 넣었으면 true.
     *
     * 「맞추는」 일은 여기서 하지 않는다 — 지도를 어디에 맞출지는 인원핀만으로 결정되지
     * 않고 신고핀까지 합쳐야 하는데, 레이어가 각자 setBounds 를 부르면 나중에 부른 쪽이
     * 앞의 것을 지운다. 경계만 내놓고 합치는 건 ControlApp.recenter 가 한다.
     *
     * 화면에서 숨긴(역할 필터 off·오프라인 숨김) 인원은 제외한다 — 안 보이는 점 때문에
     * 지도가 넓어지면 «보이는 것 전체»를 담는다는 말과 어긋난다.
     */
    extendBounds(bounds) {
        let any = false;
        for (const [, e] of this.markers) {
            const r = e.row;
            if (r.last_lat == null || r.last_lng == null) continue;
            if (!this._shouldShow(e)) continue;
            bounds.extend(new kakao.maps.LatLng(Number(r.last_lat), Number(r.last_lng)));
            any = true;
        }
        return any;
    }
}

// ── 신고 고정핀 (각진 경고핀 + 점멸, CustomOverlay z-100) ────────────────────
export class RequestPinLayer {
    constructor(map) {
        this.map = map;
        this.pins = new Map(); // requestId -> overlay
    }

    upsert(req) {
        const id = req.request_id ?? req.id;
        if (this.pins.has(id)) return; // 신고 좌표 고정 — 이동/재생성 안 함
        if (req.latitude == null || req.longitude == null) return;

        const meta = priorityMeta(req.priority);
        const el = document.createElement('div');
        el.className = 'ctrl-req-pin';
        el.style.setProperty('--pin-color', meta.color);
        if (meta.blink) {
            el.style.animation = `ctrlReqBlink ${meta.blink}ms ease-in-out infinite`;
        }
        el.innerHTML =
            `<svg width="36" height="44" viewBox="0 0 24 28" xmlns="http://www.w3.org/2000/svg">` +
            `<path d="M12 0 L23 7 V17 L12 28 L1 17 V7 Z" fill="${meta.color}" stroke="#fff" stroke-width="1.5"/>` +
            `<path d="M12 7v6M12 17h.01" stroke="#fff" stroke-width="2.2" ` +
            `stroke-linecap="round" fill="none"/></svg>`;
        el.title = `신고 #${id}`;

        const overlay = new kakao.maps.CustomOverlay({
            position: new kakao.maps.LatLng(Number(req.latitude), Number(req.longitude)),
            content: el,
            yAnchor: 1,
            zIndex: Z_REQUEST,
        });
        overlay.setMap(this.map);
        this.pins.set(id, overlay);

        // §7: 신고핀 인포윈도에서만 연락처 노출 허용(이름·전화 + tel:)
        const r = req.requester || {};
        const phone = r.phone || '';
        const infoHtml =
            `<div style="padding:10px 12px;min-width:180px;font-size:12px;">` +
            `<div style="font-weight:700;margin-bottom:4px;">신고 #${id} · ${escapeHtml(priorityMeta(req.priority).label)}</div>` +
            `<div style="color:#555;margin-bottom:2px;">${escapeHtml(r.name || '이름없음')}</div>` +
            (phone
                ? `<a href="tel:${escapeHtml(phone)}" style="color:#2563eb;font-weight:600;">${escapeHtml(phone)} · 전화</a>`
                : '') +
            (req.address ? `<div style="color:#888;margin-top:4px;">${escapeHtml(req.address)}</div>` : '') +
            `</div>`;
        const pos = new kakao.maps.LatLng(Number(req.latitude), Number(req.longitude));
        const iw = new kakao.maps.InfoWindow({
            content: infoHtml, removable: true, position: pos, zIndex: Z_INFO,
        });
        el.addEventListener('click', () => iw.open(this.map));
    }

    /** 신고 좌표로 bounds 를 넓힌다. 하나라도 넣었으면 true. */
    extendBounds(bounds) {
        let any = false;
        for (const [, overlay] of this.pins) {
            bounds.extend(overlay.getPosition());
            any = true;
        }
        return any;
    }

    count() {
        return this.pins.size;
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}
