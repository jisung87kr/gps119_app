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

    /**
     * 🔴 관제 화면에는 탭바도 GNB 도 없어 헤더 백링크가 유일한 출구다. 비관리자 상황실에게
     *    관리자 대시보드를 주면 403 — 관제에 갇힌다(2026-09-08). 마이페이지로 내보낸다.
     */
    public function test_controller_back_link_goes_to_profile_not_admin_dashboard(): void
    {
        $project = $this->activeProject();
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        $this->actingAs($controller)->get(route('control'))
            ->assertOk()
            ->assertSee('data-back-url="'.route('profile.show').'"', false)
            ->assertSee('data-back-label="마이페이지"', false);

        // 그 링크가 실제로 열린다
        $this->actingAs($controller)->get(route('profile.show'))->assertOk();
    }

    public function test_admin_back_link_goes_to_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->activeProject();

        $this->actingAs($admin)->get(route('control'))
            ->assertOk()
            ->assertSee('data-back-url="'.route('admin.dashboard').'"', false)
            ->assertSee('data-back-label="대시보드"', false);
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
