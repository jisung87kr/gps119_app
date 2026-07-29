<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-1.2 — EnsureEventRole / EnsureEventMember 미들웨어 (SPEC-06a / OI-4).
 *
 * 테스트 전용 라우트를 등록해 미들웨어 동작만 격리 검증한다.
 */
class EnsureEventRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Route::middleware(['api', 'auth:sanctum', 'event.role:controller'])
            ->get('/_test/events/{id}/role-guard', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'event.member'])
            ->get('/_test/events/{id}/member-guard', fn () => response()->json(['ok' => true]));
    }

    private function project(): Project
    {
        return Project::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    // === event.role:controller ===

    public function test_role_guard_allows_controller(): void
    {
        $project = $this->project();
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);
        Sanctum::actingAs($controller);

        $this->getJson("/_test/events/{$project->id}/role-guard")->assertOk();
    }

    public function test_role_guard_allows_system_admin(): void
    {
        $project = $this->project();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->getJson("/_test/events/{$project->id}/role-guard")->assertOk();
    }

    public function test_role_guard_denies_participant(): void
    {
        $project = $this->project();
        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $participant->id,
        ]);
        Sanctum::actingAs($participant);

        $this->getJson("/_test/events/{$project->id}/role-guard")->assertStatus(403);
    }

    public function test_role_guard_denies_pending_controller(): void
    {
        $project = $this->project();
        $pending = User::factory()->create();
        EventParticipant::factory()->controller()->pending()->create([
            'project_id' => $project->id, 'user_id' => $pending->id,
        ]);
        Sanctum::actingAs($pending);

        $this->getJson("/_test/events/{$project->id}/role-guard")->assertStatus(403);
    }

    public function test_role_guard_unknown_project_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/_test/events/999999/role-guard')->assertStatus(404);
    }

    // === event.member ===

    public function test_member_guard_allows_any_active_role(): void
    {
        $project = $this->project();
        // 일반 participant 도 active 면 통과
        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $participant->id,
        ]);
        Sanctum::actingAs($participant);

        $this->getJson("/_test/events/{$project->id}/member-guard")->assertOk();
    }

    public function test_member_guard_denies_non_participant(): void
    {
        $project = $this->project();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/_test/events/{$project->id}/member-guard")->assertStatus(403);
    }

    public function test_member_guard_denies_left_participant(): void
    {
        $project = $this->project();
        $left = User::factory()->create();
        EventParticipant::factory()->left()->create([
            'project_id' => $project->id, 'user_id' => $left->id,
        ]);
        Sanctum::actingAs($left);

        $this->getJson("/_test/events/{$project->id}/member-guard")->assertStatus(403);
    }
}
