<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-3.3 — 지령 API + 가드 (SPEC-06b).
 */
class DispatchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function scenario(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $paramedic->id,
            'last_lat' => 37.5, 'last_lng' => 127.0, 'last_seen_at' => now(),
        ]);

        $request = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => $project->id]);

        return compact('project', 'controller', 'paramedic', 'request');
    }

    public function test_controller_can_dispatch(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);

        $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->assertStatus(201)
            ->assertJsonPath('data.status', DispatchStatus::ASSIGNED->value);

        $this->assertDatabaseHas('dispatches', [
            'request_id' => $r->id, 'paramedic_id' => $p->id, 'status' => 'assigned',
        ]);
    }

    public function test_controller_sees_only_active_event_requests(): void
    {
        ['project' => $project, 'controller' => $c] = $this->scenario();

        $pending = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => $project->id, 'status' => 'pending']);
        $inProgress = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => $project->id, 'status' => 'in_progress']);
        $completed = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => $project->id, 'status' => 'completed']);
        $general = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => null, 'status' => 'pending']);

        Sanctum::actingAs($c);
        $ids = collect($this->getJson("/api/events/{$project->id}/requests")->assertOk()->json('data'))->pluck('request_id');

        $this->assertTrue($ids->contains($pending->id));       // 대기 포함
        $this->assertTrue($ids->contains($inProgress->id));    // 진행중 포함
        $this->assertFalse($ids->contains($completed->id));    // 완료 제외
        $this->assertFalse($ids->contains($general->id));      // 다른 행사(project null) 제외
    }

    public function test_non_controller_cannot_list_event_requests(): void
    {
        ['project' => $project, 'paramedic' => $p] = $this->scenario();
        Sanctum::actingAs($p);

        $this->getJson("/api/events/{$project->id}/requests")->assertStatus(403);
    }

    public function test_participant_cannot_dispatch(): void
    {
        ['project' => $project, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        $participant = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $participant->id]);
        Sanctum::actingAs($participant);

        // event.role:controller 가드가 차단
        $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->assertStatus(403);
    }

    public function test_paramedic_can_update_own_status(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);
        $dispatchId = $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->json('data.id');

        Sanctum::actingAs($p);
        $this->patchJson("/api/dispatches/{$dispatchId}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_invalid_transition_returns_422(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);
        $dispatchId = $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->json('data.id');

        Sanctum::actingAs($p);
        // assigned → arrived 불가
        $this->patchJson("/api/dispatches/{$dispatchId}/status", ['status' => 'arrived'])
            ->assertStatus(422);
    }

    public function test_reject_without_reason_422(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);
        $dispatchId = $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->json('data.id');

        Sanctum::actingAs($p);
        $this->patchJson("/api/dispatches/{$dispatchId}/status", ['status' => 'rejected'])
            ->assertStatus(422);
    }

    public function test_third_party_cannot_update_status(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r, 'project' => $project] = $this->scenario();
        Sanctum::actingAs($c);
        $dispatchId = $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id])
            ->json('data.id');

        // 무관한 일반 사용자
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->patchJson("/api/dispatches/{$dispatchId}/status", ['status' => 'accepted'])
            ->assertStatus(403);
    }

    public function test_mine_returns_only_own(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);
        $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id]);

        Sanctum::actingAs($p);
        $res = $this->getJson('/api/dispatches/mine')->assertOk();
        $this->assertCount(1, $res->json('data'));

        // 다른 사용자는 비어있음
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/dispatches/mine')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_board_controller_only(): void
    {
        ['controller' => $c, 'paramedic' => $p, 'request' => $r, 'project' => $project] = $this->scenario();
        Sanctum::actingAs($c);
        $this->postJson("/api/requests/{$r->id}/dispatch", ['paramedic_id' => $p->id]);

        $res = $this->getJson("/api/events/{$project->id}/dispatches")->assertOk();
        $this->assertSame(1, $res->json('data.counts.assigned'));
        $this->assertCount(1, $res->json('data.active'));

        // 구급대원은 보드 접근 불가
        Sanctum::actingAs($p);
        $this->getJson("/api/events/{$project->id}/dispatches")->assertStatus(403);
    }

    public function test_available_paramedics_sorted(): void
    {
        ['controller' => $c, 'request' => $r] = $this->scenario();
        Sanctum::actingAs($c);

        $res = $this->getJson("/api/requests/{$r->id}/available-paramedics")->assertOk();
        $data = $res->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertArrayHasKey('distance_m', $data[0]);
        $this->assertArrayHasKey('active_dispatch_count', $data[0]);
        $this->assertArrayHasKey('online', $data[0]);
    }
}
