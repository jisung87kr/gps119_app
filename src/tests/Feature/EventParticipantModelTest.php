<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BE-1.1 — 스키마/모델/Enum 검증.
 */
class EventParticipantModelTest extends TestCase
{
    use RefreshDatabase;

    /** Project 생성 시 join_code 자동 발급 + 유니크 */
    public function test_project_auto_generates_unique_join_code(): void
    {
        $owner = User::factory()->create();
        $a = Project::factory()->create(['created_by' => $owner->id]);
        $b = Project::factory()->create(['created_by' => $owner->id]);

        $this->assertNotNull($a->join_code);
        $this->assertSame(6, strlen($a->join_code));
        // 혼동문자 미포함
        $this->assertDoesNotMatchRegularExpression('/[01OI]/', $a->join_code);
        $this->assertNotSame($a->join_code, $b->join_code);
    }

    /** EventRole::canReceiveDispatch — PARAMEDIC/VOLUNTEER_MEDIC 만 true */
    public function test_event_role_can_receive_dispatch_matrix(): void
    {
        $this->assertTrue(EventRole::PARAMEDIC->canReceiveDispatch());
        $this->assertTrue(EventRole::VOLUNTEER_MEDIC->canReceiveDispatch());
        $this->assertFalse(EventRole::CONTROLLER->canReceiveDispatch());
        $this->assertFalse(EventRole::PARTICIPANT->canReceiveDispatch());
        $this->assertFalse(EventRole::STAFF->canReceiveDispatch());
    }

    /** EventRole::canDispatch / canViewControl — CONTROLLER 만 true */
    public function test_event_role_can_dispatch_and_view_control_matrix(): void
    {
        $this->assertTrue(EventRole::CONTROLLER->canDispatch());
        $this->assertTrue(EventRole::CONTROLLER->canViewControl());

        foreach ([EventRole::PARAMEDIC, EventRole::PARTICIPANT, EventRole::VOLUNTEER_MEDIC] as $role) {
            $this->assertFalse($role->canDispatch());
            $this->assertFalse($role->canViewControl());
        }
    }

    /** ParticipantStatus::isActive — ACTIVE 만 true */
    public function test_participant_status_is_active(): void
    {
        $this->assertTrue(ParticipantStatus::ACTIVE->isActive());
        $this->assertFalse(ParticipantStatus::PENDING->isActive());
        $this->assertFalse(ParticipantStatus::LEFT->isActive());
    }

    /** User::eventRoleIn — active 만 역할 반환, pending/미참가는 null */
    public function test_event_role_in_returns_role_only_for_active(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $activeController = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $activeController->id,
        ]);

        $pending = User::factory()->create();
        EventParticipant::factory()->controller()->pending()->create([
            'project_id' => $project->id, 'user_id' => $pending->id,
        ]);

        $stranger = User::factory()->create();

        $this->assertSame(EventRole::CONTROLLER, $activeController->eventRoleIn($project));
        $this->assertNull($pending->eventRoleIn($project));
        $this->assertNull($stranger->eventRoleIn($project));
    }

    /** role/status enum 캐스팅 라운드트립 */
    public function test_enum_casting_roundtrip(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $user = User::factory()->create();

        $participant = EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => EventRole::PARAMEDIC,
            'status' => ParticipantStatus::PENDING,
        ]);

        $fresh = $participant->fresh();
        $this->assertInstanceOf(EventRole::class, $fresh->role);
        $this->assertSame(EventRole::PARAMEDIC, $fresh->role);
        $this->assertInstanceOf(ParticipantStatus::class, $fresh->status);
        $this->assertSame(ParticipantStatus::PENDING, $fresh->status);
    }

    /** unique(project_id,user_id) — 동일 사용자/행사 중복 행 불가 */
    public function test_unique_participant_per_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $user = User::factory()->create();

        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);
    }

    /** isOnline — last_seen_at 임계 기준 */
    public function test_is_online_by_last_seen(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $online = EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => User::factory(),
            'last_seen_at' => now()->subSeconds(10),
        ]);
        $offline = EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => User::factory(),
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $this->assertTrue($online->isOnline());
        $this->assertFalse($offline->isOnline());
    }
}
