<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Events\RequestStatusUpdated;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\DispatchService;
use App\Services\RequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * 신고 취소 (2026-08-12 현장 피드백 #1).
 *
 * 취소는 status 한 칸을 바꾸는 일이 아니다. 활성 지령을 같이 회수하지 않으면 그 지령은
 * 고아가 되고 — 대원 화면은 신고 status 를 보지 않는다 — 취소된 현장으로 사람이 계속 간다.
 * 게다가 그 대원이 나중에 「완료」를 누르면 취소가 완료로 덮여 쓰였다.
 */
class RequestCancellationTest extends TestCase
{
    use RefreshDatabase;

    private RequestService $requests;

    private DispatchService $dispatches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->requests = app(RequestService::class);
        $this->dispatches = app(DispatchService::class);
        Event::fake([
            \App\Events\RequestCreated::class,
            \App\Events\DispatchAssigned::class,
            \App\Events\DispatchRecalled::class,
            \App\Events\DispatchStatusUpdated::class,
            RequestStatusUpdated::class,
        ]);
    }

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

    // ── 권한 ─────────────────────────────────────────────────

    public function test_requester_can_cancel_before_assignment(): void
    {
        ['request' => $r, 'requester' => $u] = $this->scenario();

        $cancelled = $this->requests->cancelRequest($r, $u, '괜찮아졌습니다');

        $this->assertSame(RequestStatus::CANCELLED, $cancelled->status);
        $this->assertSame($u->id, $cancelled->cancelled_by);
        $this->assertSame('괜찮아졌습니다', $cancelled->cancel_reason);
    }

    public function test_requester_cannot_cancel_after_a_paramedic_is_assigned(): void
    {
        ['request' => $r, 'requester' => $u, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->dispatches->assign($r, $p, $c);

        // 사람이 이미 이동 중인데 신고자가 말없이 지우면, 그 대원은 아무도 없는 현장으로 간다.
        $this->expectException(\RuntimeException::class);
        $this->requests->cancelRequest($r->fresh(), $u);
    }

    public function test_controller_can_cancel_even_after_assignment(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->dispatches->assign($r, $p, $c);

        $cancelled = $this->requests->cancelRequest($r->fresh(), $c, '오인신고');

        $this->assertSame(RequestStatus::CANCELLED, $cancelled->status);
    }

    public function test_a_stranger_cannot_cancel(): void
    {
        ['request' => $r] = $this->scenario();

        $this->expectException(\Exception::class);
        $this->requests->cancelRequest($r, User::factory()->create());
    }

    // ── 부수효과 ─────────────────────────────────────────────

    public function test_cancelling_recalls_the_active_dispatch(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->dispatches->assign($r, $p, $c);

        $this->requests->cancelRequest($r->fresh(), $c, '오인신고');

        // 고아 지령이 남으면 대원의 출동 목록에 취소된 신고가 계속 뜬다.
        $this->assertSame(DispatchStatus::CANCELLED, $dispatch->fresh()->status);
        $this->assertFalse(Dispatch::where('request_id', $r->id)->active()->exists());
    }

    public function test_cancelling_broadcasts_so_the_requester_and_control_find_out(): void
    {
        ['request' => $r, 'requester' => $u] = $this->scenario();

        $this->requests->cancelRequest($r, $u);

        // 예전엔 취소가 아무 이벤트도 쏘지 않아 관제 화면이 끝까지 몰랐다.
        Event::assertDispatched(RequestStatusUpdated::class, fn ($e) => $e->request->id === $r->id);
    }

    public function test_the_status_update_event_reaches_the_control_channel(): void
    {
        ['request' => $r, 'requester' => $u] = $this->scenario();
        $r->status = RequestStatus::CANCELLED;

        $channels = collect((new RequestStatusUpdated($r))->broadcastOn())
            ->map(fn ($c) => $c->name)->all();

        $this->assertContains("private-event.{$r->project_id}.control", $channels);
        $this->assertContains("private-event.{$r->project_id}.requester.{$u->id}", $channels);
    }

    /**
     * 🔴 회귀 테스트. 이게 이 PR 의 핵심 버그였다.
     */
    public function test_a_late_dispatch_transition_cannot_resurrect_a_cancelled_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->dispatches->assign($r, $p, $c);
        $this->dispatches->transition($dispatch->fresh(), DispatchStatus::ACCEPTED, $p);

        $this->requests->cancelRequest($r->fresh(), $c, '오인신고');
        $this->assertSame(RequestStatus::CANCELLED, $r->fresh()->status);

        // 취소로 지령도 회수됐으니 이 전이 자체가 이미 막힌다. 그래도 어떤 경로로든
        // 뒤늦은 전이가 들어왔을 때 취소가 살아남는지를 신고 상태로 확인한다.
        try {
            $this->dispatches->transition($dispatch->fresh(), DispatchStatus::COMPLETED, $p);
        } catch (\Throwable $e) {
            // 전이가 막히는 것도 정상 결과다.
        }

        $this->assertSame(RequestStatus::CANCELLED, $r->fresh()->status);
    }

    /**
     * 🔴 위 테스트는 회수 덕분에 전이가 «먼저» 막혀서 통과할 수 있다 — 그러면 가드 자체는
     *    검증되지 않는다. 여기서는 살아 있는 지령을 그대로 두고 신고만 취소 상태로 만들어
     *    (다른 경로·과거 데이터를 흉내), 동기화 가드가 단독으로 작동하는지 본다.
     */
    public function test_the_sync_guard_alone_protects_a_cancelled_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->dispatches->assign($r, $p, $c);
        $this->dispatches->transition($dispatch->fresh(), DispatchStatus::ACCEPTED, $p);

        // 지령은 활성인 채로 신고만 취소 — 회수 로직을 우회한다.
        $r->forceFill(['status' => RequestStatus::CANCELLED])->save();

        $this->dispatches->transition($dispatch->fresh(), DispatchStatus::EN_ROUTE, $p);
        $this->dispatches->transition($dispatch->fresh(), DispatchStatus::ARRIVED, $p);
        $this->dispatches->transition($dispatch->fresh(), DispatchStatus::COMPLETED, $p);

        // 가드가 없으면 여기서 COMPLETED 로 덮여 취소 기록이 사라진다.
        $this->assertSame(RequestStatus::CANCELLED, $r->fresh()->status);
    }

    public function test_a_terminal_request_cannot_be_cancelled_twice(): void
    {
        ['request' => $r, 'requester' => $u] = $this->scenario();
        $this->requests->cancelRequest($r, $u);

        $this->expectException(\Exception::class);
        $this->requests->cancelRequest($r->fresh(), $u);
    }

    public function test_a_terminal_request_rejects_new_dispatches(): void
    {
        ['request' => $r, 'requester' => $u, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->requests->cancelRequest($r, $u);

        $this->expectException(\RuntimeException::class);
        $this->dispatches->assign($r->fresh(), $p, $c);
    }

    public function test_cancellation_cannot_slip_through_the_generic_update_path(): void
    {
        ['request' => $r] = $this->scenario();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 이 문으로 들어오면 지령 회수도, 통지도, 「누가 왜 껐는가」 기록도 전부 건너뛴다.
        $this->expectException(\RuntimeException::class);
        $this->requests->updateRequest($r, ['status' => RequestStatus::CANCELLED], $admin);
    }

    // ── HTTP 경로 ────────────────────────────────────────────

    public function test_api_cancel_returns_422_when_already_assigned(): void
    {
        ['request' => $r, 'requester' => $u, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->dispatches->assign($r, $p, $c);

        // 권한 문제(403)가 아니라 상태 충돌(422)이다 — 화면이 「전화하세요」로 안내해야 한다.
        $this->actingAs($u)
            ->deleteJson("/api/requests/{$r->id}")
            ->assertStatus(422);
    }

    public function test_api_cancel_accepts_a_reason(): void
    {
        ['request' => $r, 'requester' => $u] = $this->scenario();

        $this->actingAs($u)
            ->deleteJson("/api/requests/{$r->id}", ['reason' => '잘못 눌렀습니다'])
            ->assertOk();

        $this->assertSame('잘못 눌렀습니다', $r->fresh()->cancel_reason);
    }

    public function test_admin_screen_cancellation_goes_through_the_service(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $dispatch = $this->dispatches->assign($r, $p, $c);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->patch("/admin/requests/{$r->id}", ['status' => 'cancelled', 'cancel_reason' => '중복'])
            ->assertRedirect();

        // 관리자 화면 취소만 서비스를 안 거쳐서 좀비 지령을 만들던 경로다.
        $this->assertSame(RequestStatus::CANCELLED, $r->fresh()->status);
        $this->assertSame(DispatchStatus::CANCELLED, $dispatch->fresh()->status);
        $this->assertSame('중복', $r->fresh()->cancel_reason);
    }
}
