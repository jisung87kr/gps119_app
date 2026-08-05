<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Events\RequestCreated;
use App\Listeners\NotifyRescuers;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * NotifyRescuers 수신자 산정.
 *
 * 실제 발송(sendNotificationToRescuer)은 아직 TODO 스텁이라 로그만 남긴다.
 * 그래서 "누구에게 알릴 것인가"는 로그로만 검증 가능하다 — 발송 채널이 붙는 시점에
 * 수신자 집합이 조용히 틀어지지 않도록 여기서 고정해 둔다.
 */
class NotifyRescuersRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array<int,int> 알림 대상으로 로깅된 user id */
    private function notifiedIdsFor(RescueRequest $request): array
    {
        $ids = [];

        Log::shouldReceive('info')
            ->andReturnUsing(function ($message, $context = []) use (&$ids) {
                if ($message === 'Notifying rescuer about new request') {
                    $ids[] = $context['rescuer_id'];
                }
            });
        Log::shouldReceive('error')->andReturnNull();

        (new NotifyRescuers)->handle(new RequestCreated($request));

        return $ids;
    }

    /** 행사 신고 → 그 행사의 활동중 상황실(EventRole::CONTROLLER)도 알림 대상이다 */
    public function test_event_controller_is_notified_for_project_request(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        // 시스템 롤은 그냥 'user' — 행사 역할로만 상황실인 사람
        $controller = User::factory()->create();
        $controller->assignRole('user');
        EventParticipant::create([
            'project_id' => $project->id,
            'user_id' => $controller->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $request = RescueRequest::factory()->for($owner)->create(['project_id' => $project->id]);

        $this->assertContains($controller->id, $this->notifiedIdsFor($request));
    }

    /** 퇴장(left)한 상황실은 알리지 않는다 */
    public function test_inactive_event_participant_is_not_notified(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $left = User::factory()->create();
        $left->assignRole('user');
        EventParticipant::create([
            'project_id' => $project->id,
            'user_id' => $left->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::LEFT,
        ]);

        $request = RescueRequest::factory()->for($owner)->create(['project_id' => $project->id]);

        $this->assertNotContains($left->id, $this->notifiedIdsFor($request));
    }

    /** 다른 행사의 상황실에게는 알리지 않는다 */
    public function test_controller_of_other_event_is_not_notified(): void
    {
        $owner = User::factory()->create();
        $mine = Project::factory()->create(['created_by' => $owner->id]);
        $other = Project::factory()->create(['created_by' => $owner->id]);

        $otherController = User::factory()->create();
        $otherController->assignRole('user');
        EventParticipant::create([
            'project_id' => $other->id,
            'user_id' => $otherController->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $request = RescueRequest::factory()->for($owner)->create(['project_id' => $mine->id]);

        $this->assertNotContains($otherController->id, $this->notifiedIdsFor($request));
    }

    /** 시스템 롤과 행사 역할을 겸해도 한 번만 받는다 */
    public function test_recipient_is_not_duplicated(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $both = User::factory()->create();
        $both->assignRole('rescuer');
        $both->assignRole('admin');
        EventParticipant::create([
            'project_id' => $project->id,
            'user_id' => $both->id,
            'role' => EventRole::PARAMEDIC,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $request = RescueRequest::factory()->for($owner)->create(['project_id' => $project->id]);

        $ids = $this->notifiedIdsFor($request);
        $this->assertSame(1, count(array_keys($ids, $both->id, true)));
    }
}
