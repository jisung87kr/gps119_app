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
use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use App\Services\PushService;
use Tests\TestCase;

/**
 * NotifyRescuers 수신자 산정.
 *
 * 예전에는 TODO 스텁이 남기는 «로그»로 수신자를 확인했다. N1 에서 실제 발송이
 * 붙었으므로 이제 «푸시가 실제로 간 사람»으로 판정한다 — 로그는 구현 세부라
 * 지우면 테스트가 같이 죽지만, 발송 대상은 이 리스너의 «계약»이다.
 */
class NotifyRescuersRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array<int,int> 실제로 푸시가 간 user id
     *
     * 수신자 «판별»만 보려는 것이므로, 등장하는 모든 사용자에게 통로를 하나씩
     * 붙여 놓고 시작한다. 그래야 「대상에서 빠졌다」와 「구독이 없었다」가 섞이지 않는다.
     */
    private function notifiedIdsFor(RescueRequest $request): array
    {
        User::all()->each(fn (User $u) => DeviceToken::factory()->create(['user_id' => $u->id]));

        $sender = new class implements PushSender
        {
            public array $userIds = [];

            public function supports(PushPlatform $platform): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function send(DeviceToken $device, PushMessage $message): PushDelivery
            {
                $this->userIds[] = $device->user_id;

                return PushDelivery::DELIVERED;
            }
        };

        $listener = new NotifyRescuers(new PushService([$sender]));
        $listener->handle(new RequestCreated($request->load('user')));

        return $sender->userIds;
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
