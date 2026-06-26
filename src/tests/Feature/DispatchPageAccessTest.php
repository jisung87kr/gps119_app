<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FE-3.2 — 구급대원 지령 화면(/events/{id}/dispatch) 접근 가드.
 * 지령 수신/전이/내비 동작은 브라우저 end-to-end QA.
 */
class DispatchPageAccessTest extends TestCase
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

    public function test_paramedic_can_view(): void
    {
        $project = $this->project();
        $medic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $medic->id]);

        $this->actingAs($medic)->get(route('events.dispatch', $project->id))
            ->assertOk()
            ->assertSee('dispatchApp', false)
            ->assertSee('지령 수신 대기');
    }

    public function test_volunteer_medic_can_view(): void
    {
        $project = $this->project();
        $vol = User::factory()->create();
        EventParticipant::factory()->role(\App\Enums\EventRole::VOLUNTEER_MEDIC)
            ->create(['project_id' => $project->id, 'user_id' => $vol->id]);

        $this->actingAs($vol)->get(route('events.dispatch', $project->id))->assertOk();
    }

    public function test_non_receiver_participant_redirected_to_active(): void
    {
        $project = $this->project();
        $participant = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $participant->id]);

        $this->actingAs($participant)->get(route('events.dispatch', $project->id))
            ->assertRedirect(route('events.active', $project->id));
    }

    public function test_non_participant_redirected_to_join(): void
    {
        $project = $this->project();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('events.dispatch', $project->id))
            ->assertRedirect(route('events.join'));
    }

    public function test_guest_redirected_to_login(): void
    {
        $project = $this->project();
        $this->get(route('events.dispatch', $project->id))->assertRedirect(route('login'));
    }
}
