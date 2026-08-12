// 지령 상태/전이 메타 — 구급대원 앱(FE-3.2)용 (dispatch-screens-spec DS-3.3).
//
// ⚠ 백엔드 App\Enums\DispatchStatus 미러. 전이표는 서버가 단일 출처(SPEC-02d) —
//    UI 는 잘못된 전이를 "원천 차단"하는 보조 가드일 뿐, 최종 검증은 서버 PATCH.

// ⚠ 배지 색상은 App\Enums\DispatchStatus::badgeTone()/badgeIcon() 미러.
//    디자인 시스템 "Ink + Brand": 흰 배경 + ink-200 테두리가 기본이고 색은
//    아이콘·텍스트에만 쓴다. Vue 화면은 Blade 컴포넌트(x-ui.badge)를 쓸 수 없어
//    같은 결과를 내는 클래스 문자열을 여기서 제공한다.
export const DISPATCH_STATUS_META = {
    assigned:  { label: '배정', badge: 'border border-ink-200 text-warning-600' },
    accepted:  { label: '수락', badge: 'border border-ink-200 text-brand-600' },
    en_route:  { label: '출동', badge: 'border border-ink-200 text-brand-600' },
    arrived:   { label: '도착', badge: 'border border-ink-200 text-brand-600' },
    completed: { label: '완료', badge: 'border border-ink-200 text-success-600' },
    rejected:  { label: '거절', badge: 'border border-ink-200 text-ink-400' },
    cancelled: { label: '회수', badge: 'border border-ink-200 text-warning-600' },
};

// 전이표(SPEC-02d): 현재 상태 → 가능한 다음 상태
// 🔑 'cancelled'(회수)는 여기에 «목표»로 넣지 않는다. 회수는 상황실 권한이고
//    대원 화면은 이 표로 자기 버튼을 만든다 — 넣으면 대원에게 회수 버튼이 생기고,
//    눌러도 서버가 403 을 준다. 대원이 못 가는 상황은 'rejected' 다.
export const TRANSITIONS = {
    assigned: ['accepted', 'rejected'],
    accepted: ['en_route', 'rejected'],
    en_route: ['arrived'],
    arrived: ['completed'],
    completed: [],
    rejected: [],
    cancelled: [],
};

// 액션 버튼 라벨(주 액션) — reject 는 별도 처리
export const ACTION_LABEL = {
    accepted: '수락',
    en_route: '출동',
    arrived: '도착',
    completed: '완료',
    rejected: '거절',
};

// ⚠ App\Enums\RequestType 미러. color 는 "Ink + Brand" 팔레트 hex —
//    레드는 긴급 계열(사고/긴급전화)에만 쓰고 고장·기타는 ink 뉴트럴로 둔다.
export const REQUEST_TYPE_META = {
    accident:  { label: '사고', color: '#F0453F' },     // danger-500
    breakdown: { label: '고장', color: '#57524A' },     // ink-600
    other:     { label: '기타', color: '#A7A29A' },     // ink-400
    emergency: { label: '긴급전화', color: '#B91F1A' }, // danger-700
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
