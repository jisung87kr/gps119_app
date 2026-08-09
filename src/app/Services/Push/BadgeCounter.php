<?php

namespace App\Services\Push;

use App\Enums\EventRole;
use App\Models\EventParticipant;
use App\Models\Request;
use App\Models\User;

/**
 * 앱 아이콘 뱃지에 찍을 숫자 — «이 사람이 봐야 할 미처리 신고» 개수.
 *
 * 🔑 **`NotifyRescuers::recipientsFor()` 와 같은 규칙이어야 한다.** 신고가 떴을 때 푸시를
 *    받는 사람과 뱃지에 세어지는 신고가 어긋나면, 「알림은 왔는데 숫자는 그대로」이거나
 *    반대로 「숫자는 있는데 열어 보면 아무것도 없다」가 된다. 둘 다 신뢰를 깎는다.
 *
 * ⚠️ 세는 대상은 `pending` «뿐»이다. `in_progress` 는 이미 누군가 붙은 건이라 「봐야 할
 *    것」이 아니다 — 넣으면 출동 중인 내내 숫자가 남아서 뱃지가 「할 일」이 아니라
 *    「오늘 있었던 일」이 된다.
 *
 * 📌 이 값은 «보낸 시점» 기준이다. 다른 사람이 먼저 처리하면 다음 푸시가 나갈 때까지
 *    어긋난 채로 남는다. 그래서 앱은 열릴 때 뱃지를 지운다(`push-native.js`).
 *    실시간 정합을 원하면 Reverb 로 별도 채널이 필요한데, 뱃지 하나에 그 비용은 과하다.
 */
class BadgeCounter
{
    /**
     * 신고를 «접수하는» 쪽 역할. 참가자·자원봉사 코스요원은 여기 없다 —
     * 지령을 받거나 관제하는 사람만 미처리 신고를 「봐야 할 것」으로 갖는다.
     */
    private const RESPONDER_ROLES = [
        EventRole::CONTROLLER,
        EventRole::PARAMEDIC,
        EventRole::VOLUNTEER_MEDIC,
    ];

    /**
     * 이 사용자가 봐야 할 미처리 신고 수. 볼 것이 없으면 0.
     *
     * 0 은 «뱃지를 지운다»는 뜻이라 유효한 결과다 — null 로 바꾸지 말 것.
     */
    public function for(User $user): int
    {
        // 시스템 admin·rescuer 는 모든 행사의 모든 신고를 받는다(현재 정책, M-9 로 세분화 예정).
        if ($user->hasRole('admin') || $user->hasRole('rescuer')) {
            return Request::query()->pending()->count();
        }

        $projectIds = EventParticipant::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereIn('role', array_map(static fn (EventRole $r) => $r->value, self::RESPONDER_ROLES))
            ->pluck('project_id');

        if ($projectIds->isEmpty()) {
            return 0;
        }

        return Request::query()->pending()->whereIn('project_id', $projectIds)->count();
    }
}
