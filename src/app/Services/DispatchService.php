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
     * 주담당 지령 배정. controller(active)/admin 만.
     *
     * 🔑 한 신고에 «활성 주담당»은 1명이다(ADR-0007 D4). 두 번째 대원이 필요하면
     *    assignSupport() 로 명시해서 붙인다 — 이 문으로는 절대 두 명이 되지 않는다.
     *    활성 주담당이 없으면(최초 배정·거절·회수 뒤) 이 배정이 곧 주담당이다.
     */
    public function assign(Request $request, User $paramedic, User $controller, ?string $note = null): Dispatch
    {
        $this->assertCanDispatch($request, $controller);
        $this->assertReceiverEligible($request, $paramedic);

        return DB::transaction(function () use ($request, $paramedic, $controller, $note) {
            // OI-2: 신고 행 잠금으로 동시 배정 직렬화 → 활성 주담당 1건 보장
            $locked = Request::where('id', $request->id)->lockForUpdate()->firstOrFail();

            $this->assertAssignable($locked, $paramedic);

            if ($this->hasActivePrimaryDispatch($locked->id)) {
                throw new RuntimeException('이미 주담당 지령이 있습니다. 보조 배정을 쓰거나 먼저 회수하세요.');
            }

            return $this->createDispatchRow($locked, $paramedic, $controller, $note, true);
        });
    }

    /**
     * 보조 지령 배정 (ADR-0007 D4). 주담당은 그대로 두고 인원을 «추가»한다.
     *
     * 🔑 별도 메서드인 것 자체가 안전장치다. 기존 배정 경로에 정원만 늘렸다면 관제사가
     *    같은 버튼을 두 번 눌러 실수로 두 명을 보내고, 그 신고의 «책임자»가 누구인지
     *    데이터에 남지 않는다. 보조는 명시적으로 요청했을 때만 생긴다.
     *
     * 🔑 주담당 없이 보조만 있는 신고는 만들지 않는다. 「누가 이 환자를 책임지는가」가
     *    비어 있으면 그건 지령이 아니라 알림이고, 완료 판정을 할 주체도 사라진다.
     */
    public function assignSupport(Request $request, User $paramedic, User $controller, ?string $note = null): Dispatch
    {
        $this->assertCanDispatch($request, $controller);
        $this->assertReceiverEligible($request, $paramedic);

        return DB::transaction(function () use ($request, $paramedic, $controller, $note) {
            $locked = Request::where('id', $request->id)->lockForUpdate()->firstOrFail();

            $this->assertAssignable($locked, $paramedic);

            if (! $this->hasActivePrimaryDispatch($locked->id)) {
                throw new RuntimeException('주담당이 없는 신고입니다. 먼저 주담당을 배정하세요.');
            }

            return $this->createDispatchRow($locked, $paramedic, $controller, $note, false);
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
     * 재지령. 활성 주담당이 없을 때(직전 거절·회수 등) 새 대원을 주담당으로 세운다.
     *
     * ⚠️ 어떤 라우트도 이걸 부르지 않는다 — 관제 화면의 [재배정]은 assign() 을 그대로
     *    쓴다(활성 주담당이 없으면 그 배정이 곧 주담당). 메시지만 다른 assign() 이다.
     */
    public function reassign(Request $request, User $newParamedic, User $controller, ?string $note = null): Dispatch
    {
        $this->assertCanDispatch($request, $controller);
        $this->assertReceiverEligible($request, $newParamedic);

        return DB::transaction(function () use ($request, $newParamedic, $controller, $note) {
            $locked = Request::where('id', $request->id)->lockForUpdate()->firstOrFail();

            $this->assertAssignable($locked, $newParamedic);

            if ($this->hasActivePrimaryDispatch($locked->id)) {
                throw new RuntimeException('진행 중인 주담당 지령이 있어 재지령할 수 없습니다. 먼저 회수/완료하세요.');
            }

            return $this->createDispatchRow($locked, $newParamedic, $controller, $note, true);
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
            // 관제 화면은 이 값으로 「주담당 상태 + 보조 인원수」를 만든다. 없으면 보조가
            // 주담당 행을 덮어써서 상황실이 책임자를 잘못 읽는다(ADR-0007 D4).
            'is_primary' => (bool) $d->is_primary,
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
                'is_primary' => (bool) $d->is_primary,
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

        // 🔑 «이 신고에» 이미 붙어 있는 대원. 보조 배정 화면에서 이 사람을 고르면 서버가
        //    422 로 막는데, 목록에서 구분이 안 되면 관제사는 그걸 눌러 보고서야 안다.
        $onThisRequest = Dispatch::where('request_id', $request->id)
            ->active()
            ->pluck('paramedic_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $list = $rows->map(function (EventParticipant $p) use ($request, $loads, $onThisRequest) {
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
                'on_this_request' => in_array((int) $p->user_id, $onThisRequest, true),
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

    /** 이 신고에 «활성 주담당»이 있는가. 배정 문 3개(assign/assignSupport/reassign)의 공통 축. */
    private function hasActivePrimaryDispatch(int $requestId): bool
    {
        return Dispatch::where('request_id', $requestId)->primary()->active()->exists();
    }

    /**
     * 배정 공통 가드. 반드시 신고 행 lockForUpdate «안»에서 부른다 —
     * 밖에서 검사하면 두 관제사가 동시에 같은 대원을 보낼 수 있다.
     */
    private function assertAssignable(Request $locked, User $paramedic): void
    {
        // 종결된 신고에는 배정하지 않는다. 이게 없으면 취소·완료된 신고에 지령이 붙고,
        // 그 지령이 전이될 때 신고 상태 동기화 가드에 막혀 «활성 지령은 있는데
        // 신고는 종결» 이라는 조용한 모순이 생긴다.
        if ($locked->status->isTerminal()) {
            throw new RuntimeException('종결된 신고에는 지령을 배정할 수 없습니다.');
        }

        // 🔑 같은 대원을 같은 신고에 두 번 붙이지 않는다(ADR-0007 D4). 화면상 인원이
        //    2명으로 보이는데 실제로는 한 사람이라, 관제사가 있지도 않은 여유 인력을
        //    센다. DB 유니크로 막지 않는 이유는 마이그레이션 주석 참조 —
        //    거절·회수로 «끝난» 같은 대원 재배정은 정당하므로 활성 지령만 본다.
        $duplicate = Dispatch::where('request_id', $locked->id)
            ->where('paramedic_id', $paramedic->id)
            ->active()
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('이미 이 신고에 배정된 대원입니다.');
        }
    }

    /** 지령 row 생성 + 배정 브로드캐스트. 호출자는 이미 잠금·가드를 통과한 상태여야 한다. */
    private function createDispatchRow(
        Request $locked,
        User $paramedic,
        User $controller,
        ?string $note,
        bool $isPrimary
    ): Dispatch {
        $dispatch = Dispatch::create([
            'request_id' => $locked->id,
            'project_id' => $locked->project_id,
            'assigned_by' => $controller->id,
            'paramedic_id' => $paramedic->id,
            'is_primary' => $isPrimary,
            'status' => DispatchStatus::ASSIGNED,
            'note' => $note,
            'assigned_at' => now(),
        ]);

        $dispatch->setRelation('request', $locked->load('user'));
        DispatchAssigned::dispatch($dispatch);

        return $dispatch;
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
     * requests.status 동기화 (SPEC-02d, ADR-0007 D4). 신고 status 변경의 단일 출처.
     */
    private function syncRequestStatus(Dispatch $dispatch, DispatchStatus $target): void
    {
        $request = $dispatch->request()->first();
        if (! $request) {
            return;
        }

        // 🔴 종결된 신고는 뒤늦은 지령 전이가 되살릴 수 없다. 취소된 신고에 배정돼 있던
        //    대원이 나중에 「완료」를 누르면 취소가 완료로 덮여 쓰였다 — 취소 기록이
        //    사라지는 종류의 버그라 사후에 발견조차 어렵다. (ADR-0007 D3)
        if ($request->status->isTerminal()) {
            return;
        }

        $next = $this->deriveRequestStatus($dispatch, $target);
        if ($next === null || $next === $request->status) {
            return;
        }

        $request->status = $next;
        if ($next === RequestStatus::IN_PROGRESS && ! $request->responded_at) {
            $request->responded_at = now();
        }
        if ($next === RequestStatus::COMPLETED && ! $request->completed_at) {
            $request->completed_at = now();
        }
        $request->save();
    }

    /**
     * 이 전이 뒤 신고가 있어야 할 상태(바꿀 필요 없으면 null). ADR-0007 D4.
     *
     * 🔑 단일 전이가 아니라 그 신고의 «활성 지령 집계»로 판정한다. 다중 배차에서는
     *    「마지막에 버튼을 누른 사람」이 신고 상태를 덮어쓰면 안 된다 — 보조가 먼저
     *    완료를 누르면 아직 주담당이 현장에 있는데 신고가 닫힌다.
     *
     * 🔑 완료 판정은 «주담당»만 한다. 「전원 완료」 방식은 채택하지 않았다 — 보조 한 명이
     *    버튼을 안 누르면 신고가 영원히 안 닫히고, 현장에서는 그게 기본값이다.
     *    (주담당이 완료했는데 보조가 남아 있으면 보조는 활성으로 남는다. 신고가 종결이라
     *    위 D3 가드가 그 뒤 전이를 전부 무시하므로 상태가 되살아나지는 않는다.)
     *
     * 이 함수는 «계산»만 한다 — 저장·브로드캐스트는 호출자 몫이다.
     */
    private function deriveRequestStatus(Dispatch $dispatch, DispatchStatus $target): ?RequestStatus
    {
        if ($target === DispatchStatus::COMPLETED && $dispatch->is_primary) {
            return RequestStatus::COMPLETED;
        }

        // 전이는 이미 저장된 뒤이므로 방금 바뀐 행도 집계에 반영된다.
        $responding = Dispatch::where('request_id', $dispatch->request_id)
            ->whereIn('status', DispatchStatus::respondingValues())
            ->exists();

        if ($responding) {
            return RequestStatus::IN_PROGRESS;
        }

        // 아무도 «대응 중»이 아니다.
        //   - 회수: 신고를 재배정 대기(pending)로 되돌린다. null 로 두면 아무도 안 가는데
        //     신고만 in_progress 로 남는 좀비가 된다. (ADR-0007 D1)
        //   - 거절 / 보조 완료 / 배정: 신고 무변경.
        return $target === DispatchStatus::CANCELLED ? RequestStatus::PENDING : null;
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
