<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FE-2.1 — 관제 페이지(/control) 접근 가드.
 * 지도/마커/실시간 동작은 브라우저 수동 QA.
 */
class ControlPageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function activeProject(): Project
    {
        return Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_control(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->activeProject();

        $this->actingAs($admin)->get(route('control'))
            ->assertOk()
            ->assertSee('control-app', false);
    }

    public function test_admin_access_even_with_no_active_projects(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 활성 행사 0개라도 admin 은 진입(빈 상태 화면)
        $this->actingAs($admin)->get(route('control'))->assertOk();
    }

    public function test_active_controller_can_access_control(): void
    {
        $project = $this->activeProject();
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        $this->actingAs($controller)->get(route('control'))->assertOk();
    }

    public function test_regular_participant_forbidden(): void
    {
        $project = $this->activeProject();
        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $participant->id,
        ]);

        $this->actingAs($participant)->get(route('control'))->assertStatus(403);
    }

    public function test_pending_controller_forbidden(): void
    {
        $project = $this->activeProject();
        $pending = User::factory()->create();
        EventParticipant::factory()->controller()->pending()->create([
            'project_id' => $project->id, 'user_id' => $pending->id,
        ]);

        $this->actingAs($pending)->get(route('control'))->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('control'))->assertRedirect(route('login'));
    }
}
