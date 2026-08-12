<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\EventRole;
use App\Enums\RequestStatus;
use App\Events\DispatchRecalled;
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
 * 지령 회수 (ADR-0007).
 *
 * 회수는 상황실이 잘못 보낸 발령을 되돌리는 경로다. 이전에는 전이표에 역행 경로가
 * 아예 없어서, 관제사는 무전으로 「그냥 무시하세요」라고 말하고 지령은 화면에
 * 영원히 활성으로 남겨 두는 수밖에 없었다.
 */
class DispatchRecallTest extends TestCase
{
    use RefreshDatabase;

    private DispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(DispatchService::class);
        Event::fake([
            \App\Events\RequestCreated::class,
            \App\Events\DispatchAssigned::class,
            \App\Events\DispatchStatusUpdated::class,
            \App\Events\RequestStatusUpdated::class,
            DispatchRecalled::class,
        ]);
    }

    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $paramedic->id]);

        $request = RescueRequest::factory()->for(User::factory()->create())->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'controller', 'paramedic', 'request');
    }

    // ── 전이표 (순수 로직) ─────────────────────────────────────

    public function test_recall_is_allowed_before_arrival(): void
    {
        $this->assertTrue(DispatchStatus::ASSIGNED->canTransitionTo(DispatchStatus::CANCELLED));
        $this->assertTrue(DispatchStatus::ACCEPTED->canTransitionTo(DispatchStatus::CANCELLED));
        $this->assertTrue(DispatchStatus::EN_ROUTE->canTransitionTo(DispatchStatus::CANCELLED));
    }

    public function test_recall_is_refused_after_arrival(): void
    {
        // 대원이 이미 현장에 도착했으면 「도착해서 확인하고 종결했다」가 기록상 정확하다.
        // 회수로 지우면 그 이동이 없던 일이 된다.
        $this->assertFalse(DispatchStatus::ARRIVED->canTransitionTo(DispatchStatus::CANCELLED));
    }

    public function test_recalled_dispatch_is_terminal(): void
    {
        $this->assertTrue(DispatchStatus::CANCELLED->isTerminal());
        $this->assertFalse(DispatchStatus::CANCELLED->isActive());
        $this->assertSame([], DispatchStatus::CANCELLED->allowedTransitions());
    }

    public function test_recall_is_not_the_same_value_as_rejection(): void
    {
        // 거절률 통계 오염 방지 — 거절은 대원 책임, 회수는 관제 판단이다.
        $this->assertNotSame(DispatchStatus::REJECTED, DispatchStatus::CANCELLED);
        $this->assertSame('거절', DispatchStatus::REJECTED->label());
        $this->assertSame('회수', DispatchStatus::CANCELLED->label());
    }

    // ── 서비스 동작 ───────────────────────────────────────────

    public function test_controller_can_recall_and_request_returns_to_pending(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);
        $this->service->transition($dispatch, DispatchStatus::ACCEPTED, $p);
        $this->assertSame(RequestStatus::IN_PROGRESS, $r->fresh()->status);

        $recalled = $this->service->transition($dispatch->fresh(), DispatchStatus::CANCELLED, $c, '오인신고');

        $this->assertSame(DispatchStatus::CANCELLED, $recalled->status);
        $this->assertNotNull($recalled->cancelled_at);
        $this->assertSame('오인신고', $recalled->note);
        // 회수했으면 아무도 안 가는 상태다. 신고가 in_progress 로 남으면 좀비가 된다.
        $this->assertSame(RequestStatus::PENDING, $r->fresh()->status);
    }

    public function test_recall_notifies_the_paramedic_personally(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);
        $this->service->transition($dispatch->fresh(), DispatchStatus::CANCELLED, $c);

        // control 갱신만으로는 이미 달리고 있는 대원에게 닿지 않는다.
        Event::assertDispatched(DispatchRecalled::class, fn ($e) => $e->dispatch->id === $dispatch->id);
    }

    public function test_paramedic_cannot_recall_own_dispatch(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);

        // 대원이 못 가는 상황은 REJECTED(사유 필수)로 표현된다. 회수로 빠져나가면
        // 사유 없이 지령을 지울 수 있고 거절률에도 안 잡힌다.
        $this->expectException(DispatchAuthorizationException::class);
        $this->service->transition($dispatch, DispatchStatus::CANCELLED, $p);
    }

    public function test_admin_can_recall(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $dispatch = $this->service->assign($r, $p, $c);
        $recalled = $this->service->transition($dispatch, DispatchStatus::CANCELLED, $admin);

        $this->assertSame(DispatchStatus::CANCELLED, $recalled->status);
    }

    public function test_arrived_dispatch_cannot_be_recalled_through_the_service(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);
        $this->service->transition($dispatch->fresh(), DispatchStatus::ACCEPTED, $p);
        $this->service->transition($dispatch->fresh(), DispatchStatus::EN_ROUTE, $p);
        $this->service->transition($dispatch->fresh(), DispatchStatus::ARRIVED, $p);

        $this->expectException(DispatchTransitionException::class);
        $this->service->transition($dispatch->fresh(), DispatchStatus::CANCELLED, $c);
    }

    public function test_recalled_request_can_be_assigned_again(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c, 'project' => $project] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);
        $this->service->transition($dispatch, DispatchStatus::CANCELLED, $c);

        $other = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $other->id, 'role' => EventRole::PARAMEDIC,
        ]);

        // 회수의 목적 자체가 「다른 사람에게 다시 보내기」다. 여기서 막히면 기능이 무의미하다.
        $again = $this->service->assign($r->fresh(), $other, $c);
        $this->assertSame(DispatchStatus::ASSIGNED, $again->status);
    }

    public function test_recall_api_endpoint_uses_the_existing_status_route(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->service->assign($r, $p, $c);

        $this->actingAs($c)
            ->patchJson("/api/dispatches/{$dispatch->id}/status", [
                'status' => 'cancelled',
                'note' => '중복 신고',
            ])
            ->assertOk();

        $this->assertSame(DispatchStatus::CANCELLED, $dispatch->fresh()->status);
    }

    public function test_recall_api_is_forbidden_for_the_paramedic(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->service->assign($r, $p, $c);

        $this->actingAs($p)
            ->patchJson("/api/dispatches/{$dispatch->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();
    }
}
