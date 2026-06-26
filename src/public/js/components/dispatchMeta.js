// 지령 상태/전이 메타 — 구급대원 앱(FE-3.2)용 (dispatch-screens-spec DS-3.3).
//
// ⚠ 백엔드 App\Enums\DispatchStatus 미러. 전이표는 서버가 단일 출처(SPEC-02d) —
//    UI 는 잘못된 전이를 "원천 차단"하는 보조 가드일 뿐, 최종 검증은 서버 PATCH.

export const DISPATCH_STATUS_META = {
    assigned:  { label: '배정', badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' },
    accepted:  { label: '수락', badge: 'bg-sky-50 text-sky-700 ring-1 ring-sky-200' },
    en_route:  { label: '출동', badge: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' },
    arrived:   { label: '도착', badge: 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200' },
    completed: { label: '완료', badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' },
    rejected:  { label: '거절', badge: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' },
};

// 전이표(SPEC-02d): 현재 상태 → 가능한 다음 상태
export const TRANSITIONS = {
    assigned: ['accepted', 'rejected'],
    accepted: ['en_route', 'rejected'],
    en_route: ['arrived'],
    arrived: ['completed'],
    completed: [],
    rejected: [],
};

// 액션 버튼 라벨(주 액션) — reject 는 별도 처리
export const ACTION_LABEL = {
    accepted: '수락',
    en_route: '출동',
    arrived: '도착',
    completed: '완료',
    rejected: '거절',
};

export const REQUEST_TYPE_META = {
    accident:  { label: '사고', color: '#EA580C' },
    breakdown: { label: '고장', color: '#F59E0B' },
    other:     { label: '기타', color: '#6B7280' },
    emergency: { label: '긴급전화', color: '#DC2626' },
};

export function statusMeta(s) {
    return DISPATCH_STATUS_META[s] || DISPATCH_STATUS_META.assigned;
}

export function nextStatuses(s) {
    return TRANSITIONS[s] || [];
}

export function typeMeta(t) {
    return REQUEST_TYPE_META[t] || REQUEST_TYPE_META.other;
}
