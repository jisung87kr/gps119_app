<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Jobs\PersistLocationPing;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-2.1 — 위치 ping / roster / sharing API (SPEC-06b).
 */
class LocationPingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function project(): Project
    {
        return Project::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    /** sharing 켜진 active 참가자가 ping → 캐시 갱신 + 큐 적재 */
    public function test_ping_updates_cache_and_queues_persist(): void
    {
        Queue::fake();

        $project = $this->project();
        $user = User::factory()->create();
        $participant = EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'sharing_location' => true,
        ]);
        Sanctum::actingAs($user);

        $res = $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'accuracy' => 12,
            'recorded_at' => now()->subSeconds(2)->toISOString(),
        ]);

        $res->assertStatus(202)->assertJsonPath('success', true);

        $participant->refresh();
        $this->assertEquals(37.5665, (float) $participant->last_lat);
        $this->assertEquals(126.9780, (float) $participant->last_lng);
        $this->assertNotNull($participant->last_seen_at);

        Queue::assertPushed(PersistLocationPing::class, 1);
    }

    /** 좌표 범위 초과 → 422 */
    public function test_out_of_range_coordinates_422(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id, 'sharing_location' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 120,        // > 90
            'longitude' => 200,       // > 180
            'recorded_at' => now()->toISOString(),
        ])->assertStatus(422);
    }

    /** 미래 recorded_at → 422 */
    public function test_future_recorded_at_422(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id, 'sharing_location' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5,
            'longitude' => 127.0,
            'recorded_at' => now()->addMinutes(5)->toISOString(),
        ])->assertStatus(422);
    }

    /** sharing off → 캐시/큐/브로드캐스트 스킵(잡 미적재) */
    public function test_sharing_off_skips_persist_and_cache(): void
    {
        Queue::fake();

        $project = $this->project();
        $user = User::factory()->create();
        $participant = EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'sharing_location' => false,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5,
            'longitude' => 127.0,
            'recorded_at' => now()->toISOString(),
        ])->assertStatus(202);

        $participant->refresh();
        $this->assertNull($participant->last_lat);
        $this->assertNull($participant->last_seen_at);
        Queue::assertNotPushed(PersistLocationPing::class);
    }

    /** 비참가자 ping → event.member 가드가 403 */
    public function test_non_participant_denied(): void
    {
        $project = $this->project();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5, 'longitude' => 127.0, 'recorded_at' => now()->toISOString(),
        ])->assertStatus(403);
    }

    /** GET participants — controller 만, online 플래그 포함 */
    public function test_participants_roster_controller_only(): void
    {
        $project = $this->project();

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        // 온라인 구급대원 1명 (last_seen 최근)
        $medic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $medic->id,
            'last_lat' => 37.5, 'last_lng' => 127.0, 'last_seen_at' => now()->subSeconds(5),
        ]);

        Sanctum::actingAs($controller);
        $res = $this->getJson("/api/events/{$project->id}/participants");

        $res->assertOk();
        $data = collect($res->json('data'));
        $this->assertCount(2, $data);
        $medicRow = $data->firstWhere('user_id', $medic->id);
        $this->assertSame(EventRole::PARAMEDIC->value, $medicRow['role']);
        $this->assertTrue($medicRow['online']);
    }

    public function test_participants_roster_denied_for_participant(): void
    {
        $project = $this->project();
        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $participant->id,
        ]);
        Sanctum::actingAs($participant);

        $this->getJson("/api/events/{$project->id}/participants")->assertStatus(403);
    }

    /** PATCH sharing — boolean 반영 */
    public function test_sharing_toggle(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        $participant = EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id, 'sharing_location' => false,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/events/{$project->id}/sharing", ['sharing_location' => true])
            ->assertOk()
            ->assertJsonPath('data.sharing_location', true);

        $this->assertTrue($participant->refresh()->sharing_location);
    }
}
