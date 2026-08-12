<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Enums\RequestStatus;
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
 * 신고 열람 권한 (2026-08-12, 브라우저 QA 에서 발견).
 *
 * 🔴 `GET /requests/{request}` 에 `auth` 말고는 아무 검사가 없었다. 로그인만 하면
 *    id 를 1씩 바꿔가며 **남의 신고 좌표·주소·담당 대원 연락처를 그대로 읽을 수 있었다.**
 *    실제로 일반 계정으로 남의 신고를 열어 확인했다.
 *
 * 원인은 «규칙이 두 벌»이었다는 것이다 — API(RequestService::getRequestById)에는
 * 검사가 있었고 웹 라우트에만 없었다. 이제 둘 다 Request::isVisibleTo 를 읽는다.
 */
class RequestVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Event::fake([
            \App\Events\RequestCreated::class,
            \App\Events\DispatchAssigned::class,
            \App\Events\DispatchStatusUpdated::class,
            \App\Events\RequestStatusUpdated::class,
        ]);
    }

    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $owner = User::factory()->create();
        $request = RescueRequest::factory()->for($owner)->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return compact('project', 'owner', 'request');
    }

    private function inEvent(Project $project, EventRole $role): User
    {
        $user = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        return $user;
    }

    // ── 웹 라우트 (구멍이 있던 곳) ────────────────────────────

    public function test_a_stranger_cannot_open_someone_elses_request(): void
    {
        ['request' => $r] = $this->scenario();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->actingAs($stranger)->get("/requests/{$r->id}")->assertForbidden();
    }

    public function test_a_participant_of_the_same_event_still_cannot_open_it(): void
    {
        ['project' => $project, 'request' => $r] = $this->scenario();

        // 같은 행사에 있다는 것만으로는 남의 신고를 볼 자격이 되지 않는다.
        $this->actingAs($this->inEvent($project, EventRole::PARTICIPANT))
            ->get("/requests/{$r->id}")->assertForbidden();
    }

    public function test_the_owner_can_open_their_own_request(): void
    {
        ['owner' => $owner, 'request' => $r] = $this->scenario();

        $this->actingAs($owner)->get("/requests/{$r->id}")->assertOk();
    }

    public function test_admin_and_rescuer_can_open_it(): void
    {
        ['request' => $r] = $this->scenario();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $rescuer = User::factory()->create();
        $rescuer->assignRole('rescuer');

        $this->actingAs($admin)->get("/requests/{$r->id}")->assertOk();
        $this->actingAs($rescuer)->get("/requests/{$r->id}")->assertOk();
    }

    public function test_the_events_control_room_can_open_it(): void
    {
        ['project' => $project, 'request' => $r] = $this->scenario();

        // 관제 판단에 필요하다.
        $this->actingAs($this->inEvent($project, EventRole::CONTROLLER))
            ->get("/requests/{$r->id}")->assertOk();
    }

    public function test_a_controller_of_a_different_event_cannot(): void
    {
        ['request' => $r] = $this->scenario();
        $otherProject = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAs($this->inEvent($otherProject, EventRole::CONTROLLER))
            ->get("/requests/{$r->id}")->assertForbidden();
    }

    public function test_the_assigned_paramedic_can_open_it(): void
    {
        ['project' => $project, 'request' => $r] = $this->scenario();

        $paramedic = $this->inEvent($project, EventRole::PARAMEDIC);
        $controller = $this->inEvent($project, EventRole::CONTROLLER);

        // 배정 «전»에는 남의 신고다.
        $this->actingAs($paramedic)->get("/requests/{$r->id}")->assertForbidden();

        app(DispatchService::class)->assign($r, $paramedic, $controller);

        // 배정된 뒤에는 자기 출동 건이다.
        $this->actingAs($paramedic)->get("/requests/{$r->id}")->assertOk();
    }

    // ── API 와 같은 규칙인가 ─────────────────────────────────

    public function test_the_api_uses_the_same_rule(): void
    {
        ['project' => $project, 'owner' => $owner, 'request' => $r] = $this->scenario();
        $stranger = User::factory()->create();
        $controller = $this->inEvent($project, EventRole::CONTROLLER);

        $this->actingAs($owner)->getJson("/api/requests/{$r->id}")->assertOk();
        $this->actingAs($controller)->getJson("/api/requests/{$r->id}")->assertOk();
        $this->actingAs($stranger)->getJson("/api/requests/{$r->id}")->assertForbidden();
    }

    public function test_the_service_and_the_model_agree(): void
    {
        ['owner' => $owner, 'request' => $r] = $this->scenario();
        $stranger = User::factory()->create();

        $this->assertTrue($r->isVisibleTo($owner));
        $this->assertFalse($r->isVisibleTo($stranger));

        // 규칙이 두 벌이면 한쪽이 조용히 빈다 — 그게 이 버그였다.
        $this->assertSame($r->id, app(RequestService::class)->getRequestById($r->id, $owner)->id);
        $this->expectException(\Exception::class);
        app(RequestService::class)->getRequestById($r->id, $stranger);
    }
}
