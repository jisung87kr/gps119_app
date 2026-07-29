<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-1.2 — 행사 입장 API (SPEC-06b).
 */
class EventJoinApiTest extends TestCase
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

    /** GET /api/events/{joinCode} — 민감정보 없는 미리보기 */
    public function test_show_returns_safe_preview(): void
    {
        $project = $this->activeProject();
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson("/api/events/{$project->join_code}");

        $res->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.name', $project->name)
            ->assertJsonPath('data.is_active', true);

        // 민감정보(슬러그/생성자/설정 등) 미노출
        $res->assertJsonMissingPath('data.created_by');
        $res->assertJsonMissingPath('data.join_code');
    }

    public function test_show_unknown_code_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/events/ZZZZZZ')->assertStatus(404);
    }

    /** POST join — participant=active 생성 */
    public function test_join_creates_active_participant(): void
    {
        $project = $this->activeProject();
        $user = User::factory()->create(['phone' => '01011112222']);
        Sanctum::actingAs($user);

        $res = $this->postJson("/api/events/{$project->join_code}/join");

        $res->assertOk()
            ->assertJsonPath('data.participant.role', EventRole::PARTICIPANT->value)
            ->assertJsonPath('data.participant.status', ParticipantStatus::ACTIVE->value)
            ->assertJsonPath('data.project.id', $project->id);

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => EventRole::PARTICIPANT->value,
            'status' => ParticipantStatus::ACTIVE->value,
        ]);
    }

    /** join 멱등 — 재호출해도 중복 row 없음 */
    public function test_join_is_idempotent(): void
    {
        $project = $this->activeProject();
        $user = User::factory()->create(['phone' => '01033334444']);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->join_code}/join")->assertOk();
        $this->postJson("/api/events/{$project->join_code}/join")->assertOk();

        $this->assertSame(1, EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)->count());
    }

    /** 전화번호 없으면 거부(422) — require-phone 정책 계승 */
    public function test_join_without_phone_is_rejected(): void
    {
        $project = $this->activeProject();
        // setPhoneAttribute 가 빈 문자열을 ''로 저장 → DB unique 충돌 회피 위해 명시적 null 처리
        $user = User::factory()->create();
        $user->forceFill(['phone' => null])->save();
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->join_code}/join")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('event_participants', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }

    /** 비활성 행사 join → 422 */
    public function test_join_inactive_event_422(): void
    {
        $project = Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDay()->toDateString(), // 종료됨 → 비활성
        ]);
        $user = User::factory()->create(['phone' => '01055556666']);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->join_code}/join")->assertStatus(422);
    }

    /** GET /me — active 참가자만, 미참가 404 */
    public function test_me_returns_participation(): void
    {
        $project = $this->activeProject();
        $user = User::factory()->create(['phone' => '01077778888']);
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/events/{$project->id}/me")
            ->assertOk()
            ->assertJsonPath('data.role', EventRole::PARAMEDIC->value)
            ->assertJsonPath('data.status', ParticipantStatus::ACTIVE->value);
    }

    public function test_me_non_participant_denied_by_member_guard(): void
    {
        $project = $this->activeProject();
        Sanctum::actingAs(User::factory()->create(['phone' => '01099990000']));

        // event.member 가드가 비참가자를 403 으로 차단
        $this->getJson("/api/events/{$project->id}/me")->assertStatus(403);
    }

    /** PATCH participants/{userId} — controller 통과 */
    public function test_assign_role_by_controller(): void
    {
        $project = $this->activeProject();

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        $target = User::factory()->create();
        Sanctum::actingAs($controller);

        $this->patchJson("/api/events/{$project->id}/participants/{$target->id}", [
            'role' => EventRole::PARAMEDIC->value,
        ])->assertOk()
            ->assertJsonPath('data.participant.role', EventRole::PARAMEDIC->value);

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $target->id,
            'role' => EventRole::PARAMEDIC->value,
        ]);
    }

    /** PATCH participants/{userId} — 일반 참가자는 403 */
    public function test_assign_role_by_participant_forbidden(): void
    {
        $project = $this->activeProject();

        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $participant->id,
        ]);

        $target = User::factory()->create();
        Sanctum::actingAs($participant);

        $this->patchJson("/api/events/{$project->id}/participants/{$target->id}", [
            'role' => EventRole::PARAMEDIC->value,
        ])->assertStatus(403);
    }

    /** PATCH participants/{userId} — 시스템 admin 통과 */
    public function test_assign_role_by_admin(): void
    {
        $project = $this->activeProject();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/events/{$project->id}/participants/{$target->id}", [
            'role' => EventRole::CONTROLLER->value,
            'status' => ParticipantStatus::ACTIVE->value,
        ])->assertOk();
    }
}
