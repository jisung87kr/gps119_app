// 관제 지도 역할/우선순위 시각 메타 (control-map-spec §2·§6).
//
// ⚠ 단일 출처 동기화: 마커색 hex 는 백엔드 App\Enums\EventRole::markerColor() 와
//   1:1 로 일치시킨다(JS 는 PHP enum 을 호출 못 하므로 미러). 변경 시 양쪽 같이 수정.
//   기존 admin/requests/index.blade.php 도 상태색을 JS 에 미러하는 동일 패턴.

// 역할 표시 순서(필터/현황 패널 공용)
export const ROLE_ORDER = [
    'participant', 'staff', 'police',
    'volunteer_course', 'volunteer_medic', 'paramedic', 'controller',
];

// 역할 → { label, color, icon } (color-map-spec §2 표 그대로)
export const ROLE_META = {
    participant:      { label: '참가자',           color: '#6B7280', icon: 'user' },
    staff:            { label: '운영진',           color: '#2563EB', icon: 'badge' },
    police:           { label: '경찰',             color: '#1E3A8A', icon: 'shield' },
    volunteer_course: { label: '자원봉사자(코스)', color: '#16A34A', icon: 'flag' },
    volunteer_medic:  { label: '자원봉사자(구급)', color: '#F59E0B', icon: 'plusCircle' },
    paramedic:        { label: '구급대',           color: '#DC2626', icon: 'plusBold' },
    controller:       { label: '상황실',           color: '#7C3AED', icon: 'signal' },
};

export function roleMeta(role) {
    return ROLE_META[role] || ROLE_META.participant;
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
