// 관제 마커 풀 (control-map-spec §3·§5).
//
// - PersonMarkerPool: 인원 이동마커(둥근 물방울 핀). userId→marker 풀 재사용,
//   갱신은 setPosition 만(전체 리렌더 금지). 역할 필터 토글 = setMap only. 클러스터러 사용.
// - RequestPinLayer: 신고 고정핀(각진 경고핀 + 점멸). CustomOverlay, z-100, 클러스터 제외, 이동 안 함.

import { roleMeta, priorityMeta, presenceState, ICON_PATHS } from './roleMeta';

const Z_PERSON = 10;
const Z_REQUEST = 100;

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
    move(userId, lat, lng) {
        const entry = this.markers.get(userId);
        if (!entry) return false;
        entry.marker.setPosition(new kakao.maps.LatLng(Number(lat), Number(lng)));
        return true;
    }

    // online 카운트(역할별) — 인력현황/필터 카운트용
    counts() {
        const result = {};
        for (const [, e] of this.markers) {
            const st = presenceState(e.row.last_seen_at);
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
            if (presenceState(e.row.last_seen_at) === 'online') n++;
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
        if (this.hideOffline && presenceState(entry.row.last_seen_at) === 'offline') return false;
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

    // 인원 인포윈도 — §7: 연락처 슬롯 없음(이름·역할·online 만)
    _bindInfo(marker, row, state) {
        const meta = roleMeta(row.role);
        const stLabel = state === 'online' ? '온라인' : state === 'stale' ? '위치지연' : '오프라인';
        const html =
            `<div style="padding:8px 10px;min-width:140px;font-size:12px;">` +
            `<div style="font-weight:700;margin-bottom:2px;">${escapeHtml(row.name || '이름없음')}</div>` +
            `<div style="color:#555;">` +
            `<span style="display:inline-block;width:8px;height:8px;border-radius:4px;background:${meta.color};margin-right:4px;"></span>` +
            `${meta.label} · ${stLabel}</div></div>`;
        const iw = new kakao.maps.InfoWindow({ content: html, removable: true });
        kakao.maps.event.addListener(marker, 'click', () => iw.open(this.map, marker));
    }

    /**
     * 전 인원이 보이도록 맞춘다.
     * padding: {top,right,bottom,left} px. 모바일 바텀시트가 지도 하단을 가리므로
     * 그만큼 하단 패딩을 줘야 마커가 시트 밑에 깔리지 않는다.
     */
    fitBounds(padding = null) {
        if (this.markers.size === 0) return;
        const bounds = new kakao.maps.LatLngBounds();
        let any = false;
        for (const [, e] of this.markers) {
            const r = e.row;
            if (r.last_lat != null && r.last_lng != null) {
                bounds.extend(new kakao.maps.LatLng(Number(r.last_lat), Number(r.last_lng)));
                any = true;
            }
        }
        if (!any) return;
        if (padding) {
            this.map.setBounds(bounds, padding.top || 0, padding.right || 0, padding.bottom || 0, padding.left || 0);
        } else {
            this.map.setBounds(bounds);
        }
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
        const iw = new kakao.maps.InfoWindow({ content: infoHtml, removable: true, position: pos });
        el.addEventListener('click', () => iw.open(this.map));
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
