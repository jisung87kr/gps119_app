<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Enums\RequestStatus;
use App\Models\DeviceToken;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use App\Services\Push\BadgeCounter;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use App\Services\PushService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 앱 아이콘 뱃지 — «이 사람이 봐야 할 미처리 신고» 개수.
 *
 * 🔑 여기서 고정하려는 것은 숫자 자체가 아니라 **누구 기준의 숫자인가**다.
 *    같은 푸시가 여러 사람에게 나가는데 뱃지만 사람마다 달라야 한다 — 한 덩어리로
 *    보내던 예전 구조 그대로면 남의 건수가 내 아이콘에 찍힌다.
 */
class PushBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /** 보낸 메시지를 그대로 모아 두는 sender. 뱃지를 «무엇으로» 찍었는지 보려는 것이다. */
    private function recordingSender(): PushSender
    {
        return new class implements PushSender
        {
            /** @var array<int, array{user_id: int, badge: int|null}> */
            public array $sent = [];

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
                $this->sent[] = ['user_id' => $device->user_id, 'badge' => $message->badge];

                return PushDelivery::DELIVERED;
            }
        };
    }

    private function deviceFor(User $user): DeviceToken
    {
        return DeviceToken::factory()->create([
            'user_id' => $user->id,
            'platform' => PushPlatform::IOS,
        ]);
    }

    private function pendingRequest(?Project $project = null): Request
    {
        return Request::factory()->create([
            'project_id' => $project?->id,
            'status' => RequestStatus::PENDING,
        ]);
    }

    public function test_admin_은_모든_미처리_신고를_센다(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->pendingRequest();
        $this->pendingRequest();

        $this->assertSame(2, app(BadgeCounter::class)->for($admin));
    }

    public function test_🔑_처리중인_건은_세지_않는다(): void
    {
        // in_progress 는 이미 누군가 붙은 건이다. 세면 출동 내내 숫자가 남아서
        // 뱃지가 「할 일」이 아니라 「오늘 있었던 일」이 된다.
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->pendingRequest();
        Request::factory()->create(['status' => RequestStatus::IN_PROGRESS]);
        Request::factory()->create(['status' => RequestStatus::COMPLETED]);

        $this->assertSame(1, app(BadgeCounter::class)->for($admin));
    }

    public function test_🔑_행사_상황실은_자기_행사만_센다(): void
    {
        // 시스템 롤이 그냥 'user' 인 상황실이 흔하다(recipientsFor 가 존재하는 이유).
        // 여기서 전체 건수를 세면 남의 행사 신고가 내 아이콘에 찍힌다.
        $mine = Project::factory()->create();
        $others = Project::factory()->create();

        $controller = User::factory()->create();
        EventParticipant::factory()->create([
            'user_id' => $controller->id,
            'project_id' => $mine->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $this->pendingRequest($mine);
        $this->pendingRequest($others);

        $this->assertSame(1, app(BadgeCounter::class)->for($controller));
    }

    public function test_볼_것이_없는_사람은_0_이다(): void
    {
        // 0 은 «뱃지를 지운다»는 뜻이라 유효한 결과다.
        $plain = User::factory()->create();
        $this->pendingRequest();

        $this->assertSame(0, app(BadgeCounter::class)->for($plain));
    }

    public function test_🔑_같은_푸시라도_뱃지는_수신자별로_다르다(): void
    {
        $project = Project::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $controller = User::factory()->create();
        EventParticipant::factory()->create([
            'user_id' => $controller->id,
            'project_id' => $project->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $this->pendingRequest($project);   // 둘 다 본다
        $this->pendingRequest();           // admin 만 본다(행사 없는 상시 신고)

        $this->deviceFor($admin);
        $this->deviceFor($controller);

        $sender = $this->recordingSender();
        (new PushService([$sender], app(BadgeCounter::class)))
            ->sendToUsers([$admin, $controller], new PushMessage('신규 신고', '사고', '/control'));

        $byUser = collect($sender->sent)->pluck('badge', 'user_id');

        $this->assertSame(2, $byUser[$admin->id]);
        $this->assertSame(1, $byUser[$controller->id]);
    }

    public function test_뱃지를_명시한_메시지는_덮어쓰지_않는다(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->pendingRequest();
        $this->deviceFor($admin);

        $sender = $this->recordingSender();
        (new PushService([$sender], app(BadgeCounter::class)))->sendToUser(
            $admin,
            (new PushMessage('제목', '본문'))->withBadge(9),
        );

        $this->assertSame(9, $sender->sent[0]['badge']);
    }

    public function test_badge_counter_없이도_발송은_된다(): void
    {
        // 뱃지 때문에 발송이 막히는 일은 없어야 한다 — 「알림이 안 갔다」보다 나쁜 게 없다.
        $user = User::factory()->create();
        $this->deviceFor($user);

        $sender = $this->recordingSender();
        (new PushService([$sender]))->sendToUser($user, new PushMessage('제목', '본문'));

        $this->assertCount(1, $sender->sent);
        $this->assertNull($sender->sent[0]['badge']);
    }
}
