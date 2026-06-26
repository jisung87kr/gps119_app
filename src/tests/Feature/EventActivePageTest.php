<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FE-2.2 — 참가자 활동 화면(/events/{id}/active) 접근 가드.
 * watchPosition/전송/토글 동작은 브라우저 수동 QA(geolocation override).
 */
class EventActivePageTest extends TestCase
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

    public function test_active_participant_can_view(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('events.active', $project->id))
            ->assertOk()
            ->assertSee('실시간 위치 공유')
            ->assertSee('eventActiveApp', false)
            // role-label 이 서버에서 주입되는지(참가자)
            ->assertSee('data-role="participant"', false);
    }

    public function test_non_participant_redirected_to_join(): void
    {
        $project = $this->project();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('events.active', $project->id))
            ->assertRedirect(route('events.join'));
    }

    public function test_pending_participant_redirected_to_join(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        EventParticipant::factory()->pending()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('events.active', $project->id))
            ->assertRedirect(route('events.join'));
    }

    public function test_guest_redirected_to_login(): void
    {
        $project = $this->project();
        $this->get(route('events.active', $project->id))->assertRedirect(route('login'));
    }

    public function test_unknown_project_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('events.active', 999999))->assertStatus(404);
    }
}
