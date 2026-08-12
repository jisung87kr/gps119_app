<?php

namespace App\Services;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Events\DispatchAssigned;
use App\Events\DispatchRecalled;
use App\Events\DispatchStatusUpdated;
use App\Events\RequestStatusUpdated;
use App\Exceptions\DispatchAuthorizationException;
use App\Exceptions\DispatchTransitionException;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 지령 배정·전이 도메인 서비스 (SPEC-04a).
 *
 * 신고 status 동기화의 단일 출처(드리프트 방지). 전이 부수효과는 1 트랜잭션.
 * 이벤트는 서비스가 명시 발행(모델 훅 아님).
 */
class DispatchService
{
    /** 온라인 판정 임계(초). */
    private const ONLINE_THRESHOLD_S = 60;

    /**
     * 지령 배정. controller(active)/admin 만. 동일 신고 활성 지령 있으면 거부(재지령은 reassign).
     */
    public function assign(Request $request, User $paramedic, User $controller, ?string $note = null): Dispatch
    {
        $this->assertCanDispatch($request, $controller);
        $this->assertReceiverEligible($request, $paramedic);

        return DB::transaction(function () use ($request, $paramedic, $controller, $note) {
            // OI-2: 신고 행 잠금으로 동시 배정 직렬화 → 활성 지령 1건 보장
            $locked = Request::where('id', $request->id)->lockForUpdate()->firstOrFail();

            // 종결된 신고에는 배정하지 않는다. 이게 없으면 취소·완료된 신고에 지령이 붙고,
            // 그 지령이 전이될 때 신고 상태 동기화 가드에 막혀 «활성 지령은 있는데
            // 신고는 종결» 이라는 조용한 모순이 생긴다.
            if ($locked->status->isTerminal()) {
                throw new RuntimeException('종결된 신고에는 지령을 배정할 수 없습니다.');
            }

            if ($this->hasActiveDispatch($locked->id)) {
                throw new RuntimeException('이미 진행 중인 지령이 있습니다. 재지령을 사용하세요.');
            }

            $dispatch = Dispatch::create([
                'request_id' => $locked->id,
                'project_id' => $locked->project_id,
                'assigned_by' => $controller->id,
                'paramedic_id' => $paramedic->id,
                'status' => DispatchStatus::ASSIGNED,
                'note' => $note,
                'assigned_at' => now(),
            ]);

            $dispatch->setRelation('request', $locked->load('user'));
            DispatchAssigned::dispatch($dispatch);

            return $dispatch;
        });
    }

    /**
     * 상태 전이. paramedic 본인 또는 controller/admin. 전이표 검증 → 타임스탬프·신고동기화·브로드캐스트.
     */
    public function transition(
        Dispatch $dispatch,
        DispatchStatus $target,
        User $actor,
        ?string $note = null,
        ?string $rejectReason = null
    ): Dispatch {
        $this->assertCanTransition($dispatch, $actor, $target);

        // OI-6: 동일 상태 재전송은 멱등 no-op
        if ($dispatch->status === $target) {
            return $dispatch;
        }

        if ($target === DispatchStatus::REJECTED && empty($rejectReason)) {
            throw new RuntimeException('거절 사유는 필수입니다.');
        }

        return DB::transaction(function () use ($dispatch, $target, $note, $rejectReason) {
            // 잠금 후 재조회(전이 경합 방지)
            $fresh = Dispatch::where('id', $dispatch->id)->lockForUpdate()->firstOrFail();

            // 잠금 후 멱등 재확인
            if ($fresh->status === $target) {
                return $fresh;
            }

            if (! $fresh->status->canTransitionTo($target)) {
                throw new DispatchTransitionException(
                    "허용되지 않은 상태 전이입니다: {$fresh->status->value} → {$target->value}"
                );
            }

            // 타임스탬프 스탬프
            $fresh->{$this->timestampColumn($target)} = now();
            $fresh->status = $target;
            if ($note !== null) {
                $fresh->note = $note;
            }
            if ($target === DispatchStatus::REJECTED) {
                $fresh->reject_reason = $rejectReason;
            }
            $fresh->save();

            // requests.status 동기화(단일 출처)
            $this->syncRequestStatus($fresh, $target);

            // 브로드캐스트: control 상태 갱신
            DispatchStatusUpdated::dispatch($fresh);

            // 회수는 대원 «본인» 채널로 따로 알린다. control 갱신만으로는 이미 출동을
            // 시작한 대원의 화면과 손 안의 알림이 그대로 남는다 — 그 사람은 계속 달린다.
            if ($target === DispatchStatus::CANCELLED) {
                $fresh->loadMissing('request', 'paramedic');
                DispatchRecalled::dispatch($fresh);
            }

            // accepted/completed 시 신고자에게도 알림(담당자 정보 포함)
            if (in_array($target, [DispatchStatus::ACCEPTED, DispatchStatus::COMPLETED], true)) {
                $fresh->loadMissing('request', 'paramedic');
                RequestStatusUpdated::dispatch($fresh->request, $fresh);
            }

            return $fresh;
        });
    }

    /**
     * 재지령. 기존 활성 지령이 없을 때(직전 거절 등) 새 대원에게 새 row 생성.
     */
    public function reassign(Request $request, User $newParamedic, User $controller, ?string $note = null): Dispatch
    {
        $this->assertCanDispatch($request, $controller);
        $this->assertReceiverEligible($request, $newParamedic);

        return DB::transaction(function () use ($request, $newParamedic, $controller, $note) {
            $locked = Request::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($this->hasActiveDispatch($locked->id)) {
                throw new RuntimeException('진행 중인 지령이 있어 재지령할 수 없습니다. 먼저 회수/완료하세요.');
            }

            $dispatch = Dispatch::create([
                'request_id' => $locked->id,
                'project_id' => $locked->project_id,
                'assigned_by' => $controller->id,
                'paramedic_id' => $newParamedic->id,
                'status' => DispatchStatus::ASSIGNED,
                'note' => $note,
                'assigned_at' => now(),
            ]);

            $dispatch->setRelation('request', $locked->load('user'));
            DispatchAssigned::dispatch($dispatch);

            return $dispatch;
        });
    }

    /**
     * 신고 취소에 딸린 활성 지령 일괄 회수.
     *
     * 🔑 취소가 dispatch 를 건드리지 않으면 그 지령은 «고아»가 된다. 담당 대원의 지령
     *    화면은 신고 status 를 보지 않고 dispatch status 만 보기 때문에, 취소된 신고가
     *    계속 출동 목록에 떠 있고 대원은 현장으로 간다. RequestService::cancelRequest
     *    가 반드시 이걸 부른다.
     *
     * @return int 회수된 지령 수
     */
    public function recallAllForRequest(Request $request, User $actor, ?string $reason = null): int
    {
        $active = Dispatch::where('request_id', $request->id)->active()->get();

        $count = 0;
        foreach ($active as $dispatch) {
            // ARRIVED 는 전이표상 회수 불가(도착 기록을 지우지 않는다). 그 건은
            // 신고만 취소되고 지령은 대원이 완료로 종결한다.
            if (! $dispatch->status->canTransitionTo(DispatchStatus::CANCELLED)) {
                continue;
            }

            $this->transition($dispatch, DispatchStatus::CANCELLED, $actor, $reason);
            $count++;
        }

        return $count;
    }

    /**
     * 출동 현황 보드 집계(상태별 카운트 + 활성 목록 + 이력).
     *
     * @return array<string, mixed>
     */
    public function boardForProject(Project $project): array
    {
        $dispatches = Dispatch::forProject($project->id)
            ->with(['request:id,type,priority,address,latitude,longitude', 'paramedic:id,name'])
            ->orderByDesc('id')
            ->get();

        $counts = [];
        foreach (DispatchStatus::cases() as $st) {
            $counts[$st->value] = 0;
        }
        foreach ($dispatches as $d) {
            $counts[$d->status->value]++;
        }

        $map = fn (Dispatch $d) => [
            'dispatch_id' => $d->id,
            'request_id' => $d->request_id,
            'status' => $d->status->value,
            'paramedic_id' => $d->paramedic_id,
            'paramedic_name' => $d->paramedic?->name,
            'request' => $d->request ? [
                'id' => $d->request->id,
                'type' => $d->request->type?->value,
                'priority' => $d->request->priority?->value,
                'address' => $d->request->address,
            ] : null,
            'assigned_at' => $d->assigned_at?->toISOString(),
            'completed_at' => $d->completed_at?->toISOString(),
        ];

        return [
            'counts' => $counts,
            'active' => $dispatches->filter(fn ($d) => $d->status->isActive())->map($map)->values(),
            'history' => $dispatches->filter(fn ($d) => $d->status->isTerminal())->map($map)->values(),
        ];
    }

    /**
     * 본인 소유 지령 목록(행사 무관).
     */
    public function myDispatches(User $paramedic): Collection
    {
        return Dispatch::where('paramedic_id', $paramedic->id)
            ->with(['request:id,type,priority,address,latitude,longitude', 'project:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Dispatch $d) => [
                'dispatch_id' => $d->id,
                'status' => $d->status->value,
                'note' => $d->note,
                'project' => $d->project ? ['id' => $d->project->id, 'name' => $d->project->name] : null,
                'request' => $d->request ? [
                    'id' => $d->request->id,
                    'type' => $d->request->type?->value,
                    'priority' => $d->request->priority?->value,
                    'address' => $d->request->address,
                    'latitude' => $d->request->latitude,
                    'longitude' => $d->request->longitude,
                ] : null,
                'assigned_at' => $d->assigned_at?->toISOString(),
            ]);
    }

    /**
     * 가용 구급대원 목록 (Q3 기본값: 거리 우선 + 보유 지령수 보조, online).
     * 거리 = 신고 고정좌표 ↔ 대원 최신 캐시 위치.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableParamedics(Request $request): array
    {
        $rows = EventParticipant::forProject($request->project_id)
            ->active()
            ->dispatchCandidates()
            ->with('user:id,name')
            ->get();

        // 대원별 활성 지령수
        $loads = Dispatch::forProject($request->project_id)
            ->active()
            ->selectRaw('paramedic_id, count(*) as c')
            ->groupBy('paramedic_id')
            ->pluck('c', 'paramedic_id');

        $list = $rows->map(function (EventParticipant $p) use ($request, $loads) {
            $distance = ($p->last_lat !== null && $p->last_lng !== null)
                ? $this->haversine((float) $request->latitude, (float) $request->longitude, (float) $p->last_lat, (float) $p->last_lng)
                : null;

            return [
                'user_id' => $p->user_id,
                'name' => $p->user?->name,
                'role' => $p->role->value,
                'online' => $p->isOnline(self::ONLINE_THRESHOLD_S),
                'distance_m' => $distance !== null ? (int) round($distance) : null,
                'active_dispatch_count' => (int) ($loads[$p->user_id] ?? 0),
            ];
        })->all();

        // 정렬: online 우선 → 거리 가까운 순(거리 없음은 뒤로)
        usort($list, function ($a, $b) {
            if ($a['online'] !== $b['online']) {
                return $a['online'] ? -1 : 1;
            }
            $da = $a['distance_m'] ?? PHP_INT_MAX;
            $db = $b['distance_m'] ?? PHP_INT_MAX;

            return $da <=> $db;
        });

        return $list;
    }

    // ── 내부 헬퍼 ───────────────────────────────────────────────

    private function hasActiveDispatch(int $requestId): bool
    {
        return Dispatch::where('request_id', $requestId)->active()->exists();
    }

    private function assertCanDispatch(Request $request, User $controller): void
    {
        if (! $request->project_id) {
            throw new RuntimeException('행사 신고가 아니어서 지령을 배정할 수 없습니다.');
        }

        if ($controller->hasRole('admin')) {
            return;
        }

        $role = $controller->eventRoleIn($request->project);
        if ($role === null || ! $role->canDispatch()) {
            throw new DispatchAuthorizationException('지령 배정 권한이 없습니다.');
        }
    }

    private function assertReceiverEligible(Request $request, User $paramedic): void
    {
        $role = $paramedic->eventRoleIn($request->project);
        if ($role === null || ! $role->isDispatchCandidate()) {
            throw new RuntimeException('대상이 지령을 받을 수 있는 구급대(active)가 아닙니다.');
        }
    }

    private function assertCanTransition(Dispatch $dispatch, User $actor, DispatchStatus $target): void
    {
        $isAdmin = $actor->hasRole('admin');
        $role = $actor->eventRoleIn($dispatch->project);
        $isController = $role !== null && $role->canDispatch();

        // 🔑 회수는 관제의 결정이다. 대원 본인은 회수할 수 없다 — 대원이 못 가는 상황은
        //    REJECTED(사유 필수)로 표현된다. 대원이 회수로 빠져나갈 수 있으면 사유 없이
        //    지령을 지울 수 있고, 거절률 통계에도 안 잡힌다.
        if ($target === DispatchStatus::CANCELLED) {
            if ($isAdmin || $isController) {
                return;
            }

            throw new DispatchAuthorizationException('지령 회수는 상황실만 할 수 있습니다.');
        }

        if ($isAdmin || $dispatch->isOwnedBy($actor)) {
            return;
        }

        if ($isController) {
            return; // 그 행사 controller 는 현장 대리 전이 가능
        }

        throw new DispatchAuthorizationException('이 지령을 변경할 권한이 없습니다.');
    }

    private function timestampColumn(DispatchStatus $target): string
    {
        return match ($target) {
            DispatchStatus::ACCEPTED => 'accepted_at',
            DispatchStatus::EN_ROUTE => 'en_route_at',
            DispatchStatus::ARRIVED => 'arrived_at',
            DispatchStatus::COMPLETED => 'completed_at',
            DispatchStatus::REJECTED => 'rejected_at',
            DispatchStatus::CANCELLED => 'cancelled_at',
            DispatchStatus::ASSIGNED => 'assigned_at',
        };
    }

    /**
     * requests.status 동기화 (SPEC-02d). 신고 status 변경의 단일 출처.
     */
    private function syncRequestStatus(Dispatch $dispatch, DispatchStatus $target): void
    {
        $sync = $target->syncsRequestStatus();
        if ($sync === null) {
            return; // rejected: 신고 무변경(재지령 대기)
        }

        $request = $dispatch->request()->first();
        if (! $request) {
            return;
        }

        // 🔴 종결된 신고는 뒤늦은 지령 전이가 되살릴 수 없다. 취소된 신고에 배정돼 있던
        //    대원이 나중에 「완료」를 누르면 취소가 완료로 덮여 쓰였다 — 취소 기록이
        //    사라지는 종류의 버그라 사후에 발견조차 어렵다.
        if ($request->status->isTerminal()) {
            return;
        }

        $request->status = $sync;
        if ($sync === RequestStatus::IN_PROGRESS && ! $request->responded_at) {
            $request->responded_at = now();
        }
        if ($sync === RequestStatus::COMPLETED && ! $request->completed_at) {
            $request->completed_at = now();
        }
        $request->save();
    }

    private function haversine(float $aLat, float $aLng, float $bLat, float $bLng): float
    {
        $r = 6371000;
        $dLat = deg2rad($bLat - $aLat);
        $dLng = deg2rad($bLng - $aLng);
        $s = sin($dLat / 2) ** 2 + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($s));
    }
}
