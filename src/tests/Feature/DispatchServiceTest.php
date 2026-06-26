<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Exceptions\DispatchAuthorizationException;
use App\Exceptions\DispatchTransitionException;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\DispatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * BE-3.2 — DispatchService 전이/동기화/불변식.
 */
class DispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private DispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(DispatchService::class);
        // 커스텀 브로드캐스트 이벤트만 fake — Eloquent 모델 이벤트(Project slug/join_code 훅)는 보존
        Event::fake([
            \App\Events\RequestCreated::class,
            \App\Events\DispatchAssigned::class,
            \App\Events\DispatchStatusUpdated::class,
            \App\Events\RequestStatusUpdated::class,
        ]);
    }

    /** 행사 + controller + paramedic + 신고 한 세트 */
    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $paramedic->id]);

        $requester = User::factory()->create();
        $request = RescueRequest::factory()->for($requester)->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'controller', 'paramedic', 'requester', 'request');
    }

    public function test_assign_creates_dispatch(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);

        $this->assertSame(DispatchStatus::ASSIGNED, $dispatch->status);
        $this->assertSame($p->id, $dispatch->paramedic_id);
        $this->assertSame($c->id, $dispatch->assigned_by);
        Event::assertDispatched(\App\Events\DispatchAssigned::class);
    }

    public function test_assign_denied_for_non_controller(): void
    {
        ['request' => $r, 'paramedic' => $p, 'project' => $project] = $this->scenario();
        $stranger = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $stranger->id]); // participant

        $this->expectException(DispatchAuthorizationException::class);
        $this->service->assign($r, $p, $stranger);
    }

    public function test_assign_rejects_ineligible_paramedic(): void
    {
        ['request' => $r, 'controller' => $c, 'project' => $project] = $this->scenario();
        $notMedic = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $notMedic->id]); // participant

        $this->expectException(\RuntimeException::class);
        $this->service->assign($r, $notMedic, $c);
    }

    /** 활성 지령 1건 불변식: 두 번째 배정 거부 */
    public function test_active_dispatch_singleton_invariant(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c, 'project' => $project] = $this->scenario();
        $this->service->assign($r, $p, $c);

        $p2 = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $p2->id]);

        $this->expectException(\RuntimeException::class);
        $this->service->assign($r, $p2, $c);
    }

    /** 유효 전이: assigned→accepted→en_route→arrived→completed */
    public function test_valid_full_transition_chain(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);

        $d = $this->service->transition($d, DispatchStatus::ACCEPTED, $p);
        $this->assertSame(DispatchStatus::ACCEPTED, $d->status);
        $this->assertNotNull($d->accepted_at);

        $d = $this->service->transition($d, DispatchStatus::EN_ROUTE, $p);
        $d = $this->service->transition($d, DispatchStatus::ARRIVED, $p);
        $d = $this->service->transition($d, DispatchStatus::COMPLETED, $p);
        $this->assertSame(DispatchStatus::COMPLETED, $d->status);
        $this->assertNotNull($d->completed_at);
    }

    /** 무효 전이: assigned→arrived 422(도메인 예외) */
    public function test_invalid_transition_throws(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);

        $this->expectException(DispatchTransitionException::class);
        $this->service->transition($d, DispatchStatus::ARRIVED, $p);
    }

    /** 멱등: 동일 상태 재전송 no-op */
    public function test_idempotent_same_status_noop(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $d = $this->service->transition($d, DispatchStatus::ACCEPTED, $p);

        $again = $this->service->transition($d, DispatchStatus::ACCEPTED, $p);
        $this->assertSame(DispatchStatus::ACCEPTED, $again->status);
        // 재전이가 예외 없이 동일 상태 반환
        $this->assertSame($d->id, $again->id);
    }

    /** reject 시 사유 필수 */
    public function test_reject_requires_reason(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);

        $this->expectException(\RuntimeException::class);
        $this->service->transition($d, DispatchStatus::REJECTED, $p);
    }

    /** en_route 에서는 reject 불가(전이표) */
    public function test_cannot_reject_after_en_route(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $d = $this->service->transition($d, DispatchStatus::ACCEPTED, $p);
        $d = $this->service->transition($d, DispatchStatus::EN_ROUTE, $p);

        $this->expectException(DispatchTransitionException::class);
        $this->service->transition($d, DispatchStatus::REJECTED, $p, null, '사유');
    }

    /** 동기화: accepted → requests in_progress + responded_at */
    public function test_sync_accepted_sets_in_progress(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $this->service->transition($d, DispatchStatus::ACCEPTED, $p);

        $r->refresh();
        $this->assertSame(RequestStatus::IN_PROGRESS, $r->status);
        $this->assertNotNull($r->responded_at);
    }

    /** 동기화: completed → requests completed + completed_at */
    public function test_sync_completed_sets_completed(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $d = $this->service->transition($d, DispatchStatus::ACCEPTED, $p);
        $d = $this->service->transition($d, DispatchStatus::EN_ROUTE, $p);
        $d = $this->service->transition($d, DispatchStatus::ARRIVED, $p);
        $this->service->transition($d, DispatchStatus::COMPLETED, $p);

        $r->refresh();
        $this->assertSame(RequestStatus::COMPLETED, $r->status);
        $this->assertNotNull($r->completed_at);
    }

    /** 동기화: rejected → requests 무변경(pending 유지, 재지령 대기) */
    public function test_sync_rejected_keeps_request_pending(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $this->service->transition($d, DispatchStatus::REJECTED, $p, null, '현장 거리 과다');

        $r->refresh();
        $this->assertSame(RequestStatus::PENDING, $r->status);
    }

    /** 거절 후 재지령 가능(활성 지령 없음) */
    public function test_reassign_after_reject(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c, 'project' => $project] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $this->service->transition($d, DispatchStatus::REJECTED, $p, null, '거절');

        $p2 = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $p2->id]);

        $d2 = $this->service->reassign($r, $p2, $c);
        $this->assertSame(DispatchStatus::ASSIGNED, $d2->status);
        $this->assertSame($p2->id, $d2->paramedic_id);
    }

    /** 권한: 타 paramedic 은 전이 불가 */
    public function test_other_paramedic_cannot_transition(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c, 'project' => $project] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);

        $other = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $other->id]);

        $this->expectException(DispatchAuthorizationException::class);
        $this->service->transition($d, DispatchStatus::ACCEPTED, $other);
    }

    /** 비행사 신고(project_id null)는 배정 불가 */
    public function test_cannot_dispatch_non_event_request(): void
    {
        $controller = User::factory()->create();
        $controller->assignRole('admin');
        $paramedic = User::factory()->create();
        $request = RescueRequest::factory()->create(['project_id' => null]);

        $this->expectException(\RuntimeException::class);
        $this->service->assign($request, $paramedic, $controller);
    }
}
