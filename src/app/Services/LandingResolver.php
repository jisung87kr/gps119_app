<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 「이 사람은 로그인하면 어디로 가야 하는가」의 단일 출처 (현장 피드백 #6).
 *
 * 🔑 예전에는 규칙이 두 벌이었다 — `/` 는 admin 외 전부를 신고 작성으로, `LoginResponse`
 *    는 admin 외 전부를 대시보드로 보냈다. 같은 사람이 «어떻게 들어왔는가»에 따라 다른
 *    화면을 봤고, 그래서 「내 화면이 왜 이렇지」에 아무도 답할 수 없었다. 둘 다 여기를 부른다.
 *
 * 우선순위: 시스템 admin > 상황실 > 구급(지령 수령) > 그 외.
 * 「그 외」의 기본값이 대시보드가 아니라 «구조요청 화면»인 이유는 현장 요구가 그렇기
 * 때문이다 — 운영진·경찰·자원봉사는 지도를 보는 사람이 아니라 신고를 올리는 사람이다.
 *
 * ⚠️ 이것은 «갈 곳이 따로 없을 때»의 기본값이다. `redirect()->intended()` 가 항상
 *    우선한다 — QR 딥링크와 푸시 딥링크가 거기 걸려 있고, 그게 밀리면 알림을 눌러도
 *    엉뚱한 화면이 열린다.
 */
class LandingResolver
{
    public function for(?User $user): string
    {
        if (! $user) {
            return route('login');
        }

        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        $participations = $this->activeParticipations($user);

        // 상황실 — 관제 SPA. 행사가 여럿이어도 SPA 에 행사 선택기가 있어 직행해도 된다.
        $control = $participations->firstWhere('role', EventRole::CONTROLLER);
        if ($control) {
            return route('control', ['project' => $control->project_id]);
        }

        // 구급 인력 — 지령 화면. 시스템 롤 rescuer 도 여기로 본다(현장 결정 2026-08-12).
        $dispatchable = $participations->filter(fn ($p) => $p->role->canReceiveDispatch());
        if ($dispatchable->count() === 1) {
            return route('events.dispatch', $dispatchable->first()->project_id);
        }
        if ($dispatchable->count() > 1 || $user->hasRole('rescuer')) {
            // 🔑 행사가 둘 이상이면 «직행하지 않는다». 잘못된 현장을 여는 비용이
            //    탭 한 번보다 훨씬 크다. 행사가 0개인 rescuer 도 여기서 빈 상태를 본다.
            return route('dispatches.index');
        }

        // 그 외(참가자·운영진·경찰·자원봉사, 그리고 행사 없는 일반 사용자) — 신고 화면.
        return route('request.create');
    }

    /**
     * 활성 행사의 active 참가만. 끝난 행사의 역할은 역할이 아니다.
     *
     * @return Collection<int, EventParticipant>
     */
    private function activeParticipations(User $user): Collection
    {
        return EventParticipant::query()
            ->where('user_id', $user->id)
            ->where('status', ParticipantStatus::ACTIVE)
            ->whereHas('project', fn ($q) => $q->active())
            ->orderByDesc('project_id')
            ->get();
    }
}
