// 관제 지도 역할/우선순위 시각 메타 (control-map-spec §2·§6).
//
// 🔑 역할 라벨·마커색은 **여기에 사본을 두지 않는다.** 서버가 `EventRole::mapMeta()` 를
//    `#control-app[data-role-meta]` 로 주입하고, main.js 가 마운트 전에 initRoleMeta() 로 채운다.
//    (예전에는 hex 를 양쪽에 적고 "변경 시 같이 수정" 주석으로 규율에 맡겼는데,
//     실제로 7개 중 4개가 어긋난 채 운영됐다 — 그래서 미러 자체를 없앴다. mobile-app 에픽 0-8)
//    JS 가 갖는 것은 PHP 가 알 수 없는 것 하나뿐이다: 아이콘 «형태»(SVG path 이름).

// 역할 → 아이콘 이름 (ICON_PATHS 의 키). 색맹 대비로 색과 병용하는 형태 구분(§2).
// 서버가 모르는 새 역할이 주입되면 'user' 로 떨어진다.
export const ROLE_ICONS = {
    participant: 'user',
    staff: 'badge',
    police: 'shield',
    volunteer_course: 'flag',
    volunteer_medic: 'plusCircle',
    paramedic: 'plusBold',
    controller: 'signal',
};

// 주입 전에는 비어 있다. import 하는 쪽이 참조를 계속 들고 있으므로
// «재대입» 하지 않고 in-place 로 채운다(Vue data() 가 잡은 참조가 살아 있어야 함).
export const ROLE_ORDER = [];
export const ROLE_META = {};

// 주입이 없을 때의 최후 표시값. 역할별 색이 아니라 «모르는 값»을 뜻하는 회색 하나다.
const UNKNOWN_ROLE = { label: '알 수 없음', color: '#9CA3AF', icon: 'user' };

/**
 * 서버가 주입한 역할 메타로 ROLE_ORDER/ROLE_META 를 채운다. 마운트 전에 1회.
 *
 * @param {Object} injected - { role: { label, color } } (EventRole::mapMeta())
 * @returns {boolean} 채웠으면 true
 */
export function initRoleMeta(injected) {
    if (!injected || typeof injected !== 'object') {
        console.error('[control] data-role-meta 주입 실패 — 역할 색·라벨을 표시할 수 없습니다.');
        return false;
    }

    ROLE_ORDER.length = 0;
    Object.keys(ROLE_META).forEach((k) => delete ROLE_META[k]);

    // 순서는 서버가 준 키 순서(= EventRole 선언 순서)를 그대로 따른다.
    Object.entries(injected).forEach(([role, meta]) => {
        ROLE_ORDER.push(role);
        ROLE_META[role] = {
            label: meta.label,
            color: meta.color,
            icon: ROLE_ICONS[role] || UNKNOWN_ROLE.icon,
        };
    });

    return true;
}

export function roleMeta(role) {
    return ROLE_META[role] || UNKNOWN_ROLE;
}

// 신고 우선순위 → { color, blink(ms|null) } (§6 표)
export const PRIORITY_META = {
    critical: { color: '#DC2626', blink: 800,  label: '긴급' },
    high:     { color: '#EA580C', blink: 1200, label: '사고' },
    medium:   { color: '#F59E0B', blink: 1600, label: '고장' },
    low:      { color: '#6B7280', blink: null, label: '기타' },
};

export function priorityMeta(priority) {
    return PRIORITY_META[priority] || PRIORITY_META.low;
}

// online/stale/offline 임계 (§6). 단위: 초
export const ONLINE_THRESHOLD_S = 30;
export const STALE_THRESHOLD_S = 120;

/**
 * last_seen_at(ISO|null) → 'online' | 'stale' | 'offline'
 */
export function presenceState(lastSeenAt) {
    if (!lastSeenAt) return 'offline';
    const ageS = (Date.now() - new Date(lastSeenAt).getTime()) / 1000;
    if (ageS <= ONLINE_THRESHOLD_S) return 'online';
    if (ageS <= STALE_THRESHOLD_S) return 'stale';
    return 'offline';
}

// 지령 상태 메타 (control-map / dispatch-screens-spec). 백엔드 App\Enums\DispatchStatus 미러.
export const DISPATCH_STATUS_ORDER = ['assigned', 'accepted', 'en_route', 'arrived', 'completed', 'rejected', 'cancelled'];

export const DISPATCH_STATUS_META = {
    assigned:  { label: '배정', badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200', dot: 'bg-amber-500' },
    accepted:  { label: '수락', badge: 'bg-sky-50 text-sky-700 ring-1 ring-sky-200', dot: 'bg-sky-500' },
    en_route:  { label: '출동', badge: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200', dot: 'bg-blue-500' },
    arrived:   { label: '도착', badge: 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200', dot: 'bg-indigo-500' },
    completed: { label: '완료', badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200', dot: 'bg-emerald-500' },
    rejected:  { label: '거절', badge: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200', dot: 'bg-rose-500' },
    // 회수 = 상황실이 발령을 되돌린 것(ADR-0007). 거절과 «다른 색»이어야 한다 —
    // 같은 회색으로 묶으면 이력에서 「대원이 안 갔다」와 「관제가 뺐다」가 구분되지 않는다.
    cancelled: { label: '회수', badge: 'bg-orange-50 text-orange-700 ring-1 ring-orange-200', dot: 'bg-orange-400' },
};

export function dispatchStatusMeta(s) {
    return DISPATCH_STATUS_META[s] || DISPATCH_STATUS_META.assigned;
}

// 신고 유형 메타 (dispatch-screens-spec §0). 백엔드 App\Enums\RequestType 미러.
export const REQUEST_TYPE_META = {
    accident:  { label: '사고', color: '#EA580C' },
    breakdown: { label: '고장', color: '#F59E0B' },
    other:     { label: '기타', color: '#6B7280' },
    emergency: { label: '긴급전화', color: '#DC2626' },
};

export function requestTypeMeta(t) {
    return REQUEST_TYPE_META[t] || REQUEST_TYPE_META.other;
}

// Heroicons 차용 path(흰색 글리프). 24x24 viewBox 기준 d 문자열.
// 인접 역할은 형태로도 구분(색맹 대비, §2 검증).
export const ICON_PATHS = {
    user: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a7.5 7.5 0 0115 0v.25H4.5v-.25z',
    badge: 'M4.5 6.75A2.25 2.25 0 016.75 4.5h10.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25V6.75zM9 9.75a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM7.5 15a3 3 0 016 0H7.5zM14.25 9.75h3M14.25 13.5h3',
    shield: 'M11.7 2.8a.75.75 0 01.6 0c2 .86 4.2 1.4 6.5 1.55a.75.75 0 01.7.75v5.4c0 4.7-3 8.86-7.2 10.4a.75.75 0 01-.5 0C7.9 19.55 5 15.4 5 10.5v-5.4a.75.75 0 01.7-.75c2.3-.15 4.5-.7 6.5-1.55zM15.3 9.5l-3.6 4-1.8-1.8',
    flag: 'M4.5 3.75v16.5M4.5 4.5h11.25l-1.5 3 1.5 3H4.5',
    plusCircle: 'M12 3.75a8.25 8.25 0 100 16.5 8.25 8.25 0 000-16.5zM12 8.25v7.5M8.25 12h7.5',
    plusBold: 'M12 4.5v15M4.5 12h15',
    signal: 'M5 12a7 7 0 0114 0M8 12a4 4 0 018 0M12 12h.01',
    // 신고핀 기본 글리프(경고 느낌표) — RequestType::markerIcon() 도입 전 임시
    alert: 'M12 9v3.75M12 16.5h.01M10.3 4.3l-7.4 12.8A1.5 1.5 0 004.2 19.5h15.6a1.5 1.5 0 001.3-2.4L13.7 4.3a1.5 1.5 0 00-2.6 0z',
};

// ── 위치 추적 상태 (M-5 / ADR-0008) ──────────────────────────────
//
// 🔑 역할 메타와 «같은 이유»로 서버가 준다. 라벨·색·「경보인가」 판단을 JS 가 또 갖지
//    않는다 — 0-8 이 그 실패였다. 축이 여섯이라 손으로 맞추면 더 빨리 어긋난다.

export const TRACKING_META = {};

/**
 * 🔴 주입이 없을 때의 «모름». 초록(정상)으로 떨어뜨리지 않는다 —
 *    「모른다」를 「문제 없음」으로 칠하면 M-5 를 통째로 잃는다.
 */
const UNKNOWN_TRACKING = { label: '알 수 없음', color: '#6B7280', attention: false };

/**
 * @param {Object} injected - { state: { label, color, attention } } (TrackingState::mapMeta())
 * @returns {boolean} 채웠으면 true
 */
export function initTrackingMeta(injected) {
    if (!injected || typeof injected !== 'object') {
        console.error('[control] data-tracking-meta 주입 실패 — 위치 추적 상태를 표시할 수 없습니다.');
        return false;
    }

    Object.keys(TRACKING_META).forEach((k) => delete TRACKING_META[k]);
    Object.entries(injected).forEach(([state, meta]) => {
        TRACKING_META[state] = {
            label: meta.label,
            color: meta.color,
            attention: Boolean(meta.attention),
        };
    });

    return true;
}

export function trackingMeta(state) {
    return TRACKING_META[state] || UNKNOWN_TRACKING;
}

/**
 * 지금 «조치가 필요한» 사람 수.
 *
 * 🔑 판단 기준은 서버가 준 attention 이다. 화면이 상태 이름을 나열해 세면
 *    상태가 늘어날 때 여기만 안 고쳐져서 조용히 빠진다.
 */
export function attentionCount(rows) {
    if (!Array.isArray(rows)) return 0;

    return rows.filter((r) => trackingMeta(r?.tracking_state).attention).length;
}
