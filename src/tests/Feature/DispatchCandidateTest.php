<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\EventRole;
use App\Enums\RequestStatus;
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
 * 배정 후보는 구급대(PARAMEDIC)만 (2026-08-12 현장 피드백 #5).
 *
 * 🔑 「후보인가」와 「지령 화면·개인 채널에 들어올 자격이 있는가」는 다른 질문이다.
 *    canReceiveDispatch() 를 그대로 좁혔다면, 이미 지령을 받아 이동 중인 자원봉사(구급)가
 *    자기 지령 화면에서 쫓겨나고(진행 중 지령이 즉시 고아가 된다) 활동화면에서도
 *    참가자로 강등되어 3초 뒤 강제 리다이렉트까지 걸렸을 것이다.
 */
class DispatchCandidateTest extends TestCase
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
        ]);
    }

    public function test_only_paramedic_is_a_dispatch_candidate(): void
    {
        $this->assertTrue(EventRole::PARAMEDIC->isDispatchCandidate());

        foreach (EventRole::cases() as $role) {
            if ($role === EventRole::PARAMEDIC) {
                continue;
            }
            $this->assertFalse($role->isDispatchCandidate(), "{$role->value} 는 배정 후보가 아니어야 한다");
        }
    }

    public function test_volunteer_medic_keeps_dispatch_screen_eligibility(): void
    {
        // 후보에서만 빠진다. 자격까지 뺐다면 진행 중인 지령이 고아가 된다.
        $this->assertTrue(EventRole::VOLUNTEER_MEDIC->canReceiveDispatch());
        $this->assertFalse(EventRole::VOLUNTEER_MEDIC->isDispatchCandidate());
    }

    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $paramedic->id]);

        $volunteer = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $volunteer->id, 'role' => EventRole::VOLUNTEER_MEDIC,
        ]);

        $request = RescueRequest::factory()->for(User::factory()->create())->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'controller', 'paramedic', 'volunteer', 'request');
    }

    public function test_available_paramedics_excludes_volunteer_medics(): void
    {
        ['request' => $r, 'paramedic' => $p, 'volunteer' => $v] = $this->scenario();

        $ids = collect($this->service->availableParamedics($r))->pluck('user_id')->all();

        $this->assertContains($p->id, $ids);
        $this->assertNotContains($v->id, $ids);
    }

    public function test_assigning_a_volunteer_medic_is_refused(): void
    {
        ['request' => $r, 'volunteer' => $v, 'controller' => $c] = $this->scenario();

        $this->expectException(\RuntimeException::class);
        $this->service->assign($r, $v, $c);
    }

    public function test_an_existing_volunteer_dispatch_can_still_be_completed(): void
    {
        ['project' => $project, 'request' => $r, 'volunteer' => $v, 'controller' => $c] = $this->scenario();

        // 규칙 변경 «전»에 만들어진 지령을 흉내낸다 — 서비스를 우회해 직접 생성.
        $dispatch = \App\Models\Dispatch::create([
            'request_id' => $r->id,
            'project_id' => $project->id,
            'assigned_by' => $c->id,
            'paramedic_id' => $v->id,
            'status' => DispatchStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);

        // transition 은 수령 역할을 재검사하지 않는다 — 진행 중 건은 그대로 완주해야 한다.
        $this->service->transition($dispatch, DispatchStatus::ACCEPTED, $v);

        $this->assertSame(DispatchStatus::ACCEPTED, $dispatch->fresh()->status);
    }
}
