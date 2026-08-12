<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Models\Dispatch;
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
 * 다중 배차 — 주담당 1명 + 보조 N명 (ADR-0007 D4).
 *
 * 현장 피드백: 「1개의 출동을 중복으로 배차」. 심정지·다발부상처럼 한 명으로 안 되는
 * 상황에서 두 번째 대원의 출동이 아예 기록되지 않았다.
 *
 * 🔑 이 파일이 고정하는 계약의 핵심은 «완료 판정»이다. 주담당만 신고를 닫을 수 있고,
 *    보조의 완료는 신고를 건드리지 않는다. 값이 틀려도 화면은 멀쩡해 보이는 규칙이라
 *    (신고 목록에서 한 건이 조용히 사라지거나, 반대로 영원히 안 닫힌다) 사람 눈으로는
 *    걸리지 않는다.
 */
class MultiDispatchTest extends TestCase
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
            \App\Events\DispatchRecalled::class,
            \App\Events\RequestStatusUpdated::class,
        ]);
    }

    /** 행사 + controller + 구급대원 2명 + 신고 한 세트 */
    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        $paramedic = $this->medic($project);
        $support = $this->medic($project);

        $request = RescueRequest::factory()->for(User::factory()->create())->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'controller', 'paramedic', 'support', 'request');
    }

    private function medic(Project $project): User
    {
        $user = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);

        return $user;
    }

    // ── 주담당 1건 제한 ────────────────────────────────────────

    public function test_first_assignment_becomes_the_primary(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();

        $dispatch = $this->service->assign($r, $p, $c);

        $this->assertTrue($dispatch->is_primary, '활성 주담당이 없으면 그 배정이 곧 주담당이다.');
    }

    public function test_a_second_primary_is_still_refused(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);

        // 다중 배차가 열렸다고 「주담당 2명」이 되면 책임자가 사라진다.
        $this->expectException(\RuntimeException::class);
        $this->service->assign($r, $s, $c);
    }

    // ── 보조 배정 ──────────────────────────────────────────────

    public function test_support_can_be_added_next_to_the_primary(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c, 'project' => $project] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);

        $first = $this->service->assignSupport($r, $s, $c);
        $third = $this->service->assignSupport($r, $this->medic($project), $c, '들것 지원');

        $this->assertFalse($first->is_primary);
        $this->assertFalse($third->is_primary);
        $this->assertSame('들것 지원', $third->note);

        // N 명이라는 것 — 정원 상한은 두지 않았다.
        $this->assertSame(3, Dispatch::where('request_id', $r->id)->active()->count());
        $this->assertSame(1, Dispatch::where('request_id', $r->id)->primary()->active()->count());
        $this->assertSame($primary->id, $r->fresh()->activeDispatch->id);
    }

    public function test_support_needs_a_primary_first(): void
    {
        ['request' => $r, 'support' => $s, 'controller' => $c] = $this->scenario();

        // 주담당 없이 보조만 있는 신고는 「누가 책임지는가」가 비어 있다 — 그건 지령이
        // 아니라 알림이고, 완료를 판정할 주체도 없다.
        $this->expectException(\RuntimeException::class);
        $this->service->assignSupport($r, $s, $c);
    }

    public function test_the_same_paramedic_cannot_be_assigned_twice(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);

        // 화면상 2명인데 실제로는 한 사람이면, 관제사가 있지도 않은 여유 인력을 센다.
        $this->expectException(\RuntimeException::class);
        $this->service->assignSupport($r, $p, $c);
    }

    public function test_the_same_paramedic_can_be_assigned_again_after_rejecting(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $d = $this->service->assign($r, $p, $c);
        $this->service->transition($d, DispatchStatus::REJECTED, $p, null, '잠깐 손이 묶였다');

        // 중복 방지를 DB 유니크로 걸지 않은 이유가 정확히 이것이다 — MySQL 8 에는
        // 부분 유니크가 없어 종료된 행까지 같이 막힌다.
        $again = $this->service->assign($r->fresh(), $p, $c);
        $this->assertSame(DispatchStatus::ASSIGNED, $again->status);
        $this->assertTrue($again->is_primary);
    }

    // ── 신고 상태 동기화(집계) ─────────────────────────────────

    public function test_support_acceptance_moves_the_request_to_in_progress(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);
        $sup = $this->service->assignSupport($r, $s, $c);

        // 주담당이 아직 수락 전이어도, 현장에 «누군가 대응 중»이면 신고는 진행 중이다.
        $this->service->transition($sup, DispatchStatus::ACCEPTED, $s);

        $r->refresh();
        $this->assertSame(RequestStatus::IN_PROGRESS, $r->status);
        $this->assertNotNull($r->responded_at);
    }

    /** 🔑 D4 의 핵심 규칙 — 변이 검증 대상. */
    public function test_support_completion_does_not_close_the_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $sup = $this->service->assignSupport($r, $s, $c);

        // 주담당은 아직 현장에 있다.
        $this->service->transition($primary, DispatchStatus::ACCEPTED, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::EN_ROUTE, $p);

        // 보조가 먼저 자기 몫을 끝냈다.
        $this->service->transition($sup, DispatchStatus::ACCEPTED, $s);
        $this->service->transition($sup->fresh(), DispatchStatus::EN_ROUTE, $s);
        $this->service->transition($sup->fresh(), DispatchStatus::ARRIVED, $s);
        $this->service->transition($sup->fresh(), DispatchStatus::COMPLETED, $s);

        $r->refresh();
        $this->assertSame(
            RequestStatus::IN_PROGRESS,
            $r->status,
            '보조가 완료를 눌렀다고 신고가 닫히면, 아직 현장에 있는 주담당이 화면에서 사라진다.'
        );
        $this->assertNull($r->completed_at);
    }

    public function test_primary_completion_closes_the_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $this->service->assignSupport($r, $s, $c);

        $this->service->transition($primary, DispatchStatus::ACCEPTED, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::EN_ROUTE, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::ARRIVED, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::COMPLETED, $p);

        $r->refresh();
        // 「전원 완료」를 채택하지 않은 이유 — 보조가 버튼을 안 누르면 신고가 영원히
        // 안 닫히고, 현장에서는 그게 기본값이다.
        $this->assertSame(RequestStatus::COMPLETED, $r->status);
        $this->assertNotNull($r->completed_at);
    }

    public function test_a_late_support_transition_cannot_revive_a_closed_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $sup = $this->service->assignSupport($r, $s, $c);

        $this->service->transition($sup, DispatchStatus::ACCEPTED, $s);
        $this->service->transition($primary, DispatchStatus::ACCEPTED, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::EN_ROUTE, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::ARRIVED, $p);
        $this->service->transition($primary->fresh(), DispatchStatus::COMPLETED, $p);

        // 종결된 뒤 보조가 이동을 이어가도(D3 가드) 신고는 되살아나지 않는다.
        $this->service->transition($sup->fresh(), DispatchStatus::EN_ROUTE, $s);

        $this->assertSame(RequestStatus::COMPLETED, $r->fresh()->status);
    }

    public function test_recalling_the_primary_frees_the_request_for_reassignment(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c, 'project' => $project] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $this->service->assignSupport($r, $s, $c);

        $this->service->transition($primary, DispatchStatus::CANCELLED, $c, '엉뚱한 대원');

        // 보조는 그대로 남고, 주담당 자리만 비어야 한다.
        $this->assertSame(0, Dispatch::where('request_id', $r->id)->primary()->active()->count());
        $this->assertSame(1, Dispatch::where('request_id', $r->id)->support()->active()->count());

        $replacement = $this->service->assign($r->fresh(), $this->medic($project), $c);
        $this->assertTrue($replacement->is_primary);
    }

    public function test_recalling_a_support_leaves_the_primary_alone(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $sup = $this->service->assignSupport($r, $s, $c);
        $this->service->transition($primary, DispatchStatus::ACCEPTED, $p);

        $this->service->transition($sup, DispatchStatus::CANCELLED, $c, '오배정');

        // 보조를 뺐다고 신고가 «미배정»으로 돌아가면 주담당이 화면에서 사라진다.
        $this->assertSame(RequestStatus::IN_PROGRESS, $r->fresh()->status);
        $this->assertSame($primary->id, $r->fresh()->activeDispatch->id);
    }

    public function test_terminal_request_refuses_both_doors(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $r->forceFill(['status' => RequestStatus::CANCELLED])->save();

        try {
            $this->service->assign($r->fresh(), $p, $c);
            $this->fail('종결된 신고에 주담당이 배정됐다.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('종결된 신고', $e->getMessage());
        }

        try {
            $this->service->assignSupport($r->fresh(), $s, $c);
            $this->fail('종결된 신고에 보조가 배정됐다.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('종결된 신고', $e->getMessage());
        }
    }

    // ── 관계 (activeDispatch 를 쓰는 화면들이 여기 걸려 있다) ──

    public function test_active_dispatch_relation_returns_the_primary_not_the_newest(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $support = $this->service->assignSupport($r, $s, $c);

        // 보조가 «더 최신»이다. latestOfMany 였다면 여기서 보조가 올라온다 —
        // 신고자 화면의 담당자 전화·리포트 담당자 칸이 전부 이 관계를 읽는다.
        $this->assertTrue($support->id > $primary->id);
        $this->assertSame($primary->id, $r->fresh()->activeDispatch->id);

        // 이거로딩(with)에서도 같은 행이 나와야 한다 — 목록·CSV 경로가 이쪽이다.
        $eager = RescueRequest::with('activeDispatch')->find($r->id);
        $this->assertSame($primary->id, $eager->activeDispatch->id);

        $this->assertSame(2, $r->fresh()->activeDispatches()->count());
    }

    public function test_requester_cannot_self_cancel_while_only_a_support_is_active(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $primary = $this->service->assign($r, $p, $c);
        $this->service->assignSupport($r, $s, $c);
        $this->service->transition($primary, DispatchStatus::CANCELLED, $c);

        // 주담당이 빠졌어도 보조는 현장으로 가고 있다. 신고자가 말없이 지우면
        // 그 사람은 아무도 없는 현장으로 계속 간다. (assertCanCancel 은 활성 지령 «전체»를 본다)
        $this->expectException(\RuntimeException::class);
        app(\App\Services\RequestService::class)->cancelRequest($r->fresh(), $r->user);
    }

    // ── API ───────────────────────────────────────────────────

    public function test_support_endpoint_creates_a_support_dispatch(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);

        $res = $this->actingAs($c)
            ->postJson("/api/requests/{$r->id}/dispatch/support", ['paramedic_id' => $s->id])
            ->assertCreated();

        $this->assertFalse($res->json('data.is_primary'));
        $this->assertSame(2, Dispatch::where('request_id', $r->id)->active()->count());
    }

    public function test_support_endpoint_rejects_a_duplicate_paramedic(): void
    {
        ['request' => $r, 'paramedic' => $p, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);

        $this->actingAs($c)
            ->postJson("/api/requests/{$r->id}/dispatch/support", ['paramedic_id' => $p->id])
            ->assertStatus(422);
    }

    public function test_support_endpoint_is_guarded_like_the_primary_one(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c, 'project' => $project] = $this->scenario();
        $this->service->assign($r, $p, $c);

        $stranger = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $stranger->id]);

        $this->actingAs($stranger)
            ->postJson("/api/requests/{$r->id}/dispatch/support", ['paramedic_id' => $s->id])
            ->assertForbidden();
    }

    public function test_board_marks_which_dispatch_is_primary(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c, 'project' => $project] = $this->scenario();
        $this->service->assign($r, $p, $c);
        $this->service->assignSupport($r, $s, $c);

        $res = $this->actingAs($c)->getJson("/api/events/{$project->id}/dispatches")->assertOk();

        // 관제 화면이 「주담당 상태 + 보조 인원수」를 만들 수 있는 유일한 근거다.
        $flags = collect($res->json('data.active'))->pluck('is_primary')->sort()->values()->all();
        $this->assertSame([false, true], $flags);
    }

    public function test_available_paramedics_flags_medics_already_on_this_request(): void
    {
        ['request' => $r, 'paramedic' => $p, 'support' => $s, 'controller' => $c] = $this->scenario();
        $this->service->assign($r, $p, $c);

        $res = $this->actingAs($c)->getJson("/api/requests/{$r->id}/available-paramedics")->assertOk();

        $rows = collect($res->json('data'))->keyBy('user_id');
        $this->assertTrue($rows[$p->id]['on_this_request'], '서버가 422 로 막을 대원은 목록에서 먼저 구분돼야 한다.');
        $this->assertFalse($rows[$s->id]['on_this_request']);
    }
}
