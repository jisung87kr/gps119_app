<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 행사 참가자·역할(EventRole) 관리.
 * 시스템 역할(spatie)과 별개인 행사 역할을 admin 이 배정/관리한다.
 */
class AdminEventParticipantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function project(): Project
    {
        return Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_participants_page(): void
    {
        $project = $this->project();

        $this->actingAs($this->admin())
            ->get(route('admin.projects.participants', $project->id))
            ->assertOk()
            ->assertSee('참가자 관리')
            ->assertSee($project->join_code);
    }

    public function test_admin_can_add_participant_with_event_role(): void
    {
        $project = $this->project();
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.projects.participants.store', $project->id), [
                'user_id' => $target->id,
                'role' => EventRole::PARAMEDIC->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => EventRole::PARAMEDIC->value,
            'status' => ParticipantStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_can_change_participant_role_and_status(): void
    {
        $project = $this->project();
        $target = User::factory()->create();
        EventParticipant::create([
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => EventRole::PARTICIPANT,
            'status' => ParticipantStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.projects.participants.update', [$project->id, $target->id]), [
                'role' => EventRole::CONTROLLER->value,
                'status' => ParticipantStatus::ACTIVE->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => EventRole::CONTROLLER->value,
        ]);
    }

    public function test_admin_can_remove_participant(): void
    {
        $project = $this->project();
        $target = User::factory()->create();
        EventParticipant::create([
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => EventRole::PARTICIPANT,
            'status' => ParticipantStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.projects.participants.destroy', [$project->id, $target->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('event_participants', [
            'project_id' => $project->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_non_admin_cannot_access_participant_management(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('admin.projects.participants', $project->id))
            ->assertForbidden();
    }
}
