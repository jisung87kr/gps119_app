<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Enums\RequestStatus;
use App\Events\RequestCreated;
use App\Models\DeviceToken;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use App\Services\PushService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 도메인 이벤트 → 푸시 배선 (mobile-app N1).
 *
 * 「신고가 접수되면 상황실 폰이 울린다」를 끝에서 끝까지 고정한다.
 * 전송 규격은 가짜 sender 로 대체하되 **페이로드는 진짜**를 검사한다 —
 * 이 파일의 목적 절반은 연락처가 푸시에 새지 않는지(ADR-0004) 보는 것이다.
 */
class PushNotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    /** 발송된 메시지를 그대로 모으는 가짜 전송기. */
    private object $sender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Http::fake();

        $this->sender = new class implements PushSender
        {
            /** @var array<int, array{device: DeviceToken, message: PushMessage}> */
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
                $this->sent[] = ['device' => $device, 'message' => $message];

                return PushDelivery::DELIVERED;
            }
        };

        $this->app->instance(PushService::class, new PushService([$this->sender]));
    }

    /** @return array<int, PushMessage> */
    private function messages(): array
    {
        return array_column($this->sent(), 'message');
    }

    private function sent(): array
    {
        return $this->sender->sent;
    }

    private function userIdsPushed(): array
    {
        return array_values(array_unique(array_map(
            fn ($row) => $row['device']->user_id,
            $this->sent(),
        )));
    }

    private function subscribe(User $user): DeviceToken
    {
        return DeviceToken::factory()->create(['user_id' => $user->id]);
    }

    // ── 신규 신고 ────────────────────────────────────────────────────────────

    public function test_a_new_request_pushes_to_the_event_controller(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        // 행사 상황실 — 시스템 롤은 그냥 user 다. 여기가 예전에 통째로 빠지던 자리다.
        $controller = User::factory()->create();
        $controller->assignRole('user');
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);
        $this->subscribe($controller);

        $requester = User::factory()->create();
        app(\App\Services\RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0, 'project_id' => $project->id,
        ], $requester);

        $this->assertContains($controller->id, $this->userIdsPushed());
    }

    public function test_a_new_request_does_not_push_to_an_unrelated_event(): void
    {
        $mine = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $other = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $otherController = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $other->id, 'user_id' => $otherController->id,
        ]);
        $this->subscribe($otherController);

        app(\App\Services\RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0, 'project_id' => $mine->id,
        ], User::factory()->create());

        $this->assertNotContains($otherController->id, $this->userIdsPushed(), '남의 행사 신고가 갔다');
    }

    public function test_the_deep_link_names_the_event(): void
    {
        // 상황실이 행사를 2개 이상 맡으면, 행사를 명시하지 않은 링크는 엉뚱한 현장을 연다.
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);
        $this->subscribe($controller);

        $request = app(\App\Services\RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0, 'project_id' => $project->id,
        ], User::factory()->create());

        $this->assertSame(
            "/control?project={$project->id}&request={$request->id}",
            $this->messages()[0]->url,
        );
    }

    // ── 지령 배정 ────────────────────────────────────────────────────────────

    public function test_an_assignment_pushes_only_to_the_assigned_paramedic(): void
    {
        [$project, $controller, $paramedic, $request] = $this->dispatchScenario();

        $bystander = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $bystander->id,
        ]);
        $this->subscribe($bystander);
        $this->subscribe($paramedic);

        Event::fake([RequestCreated::class]); // 신고 생성분 소음 제거
        app(\App\Services\DispatchService::class)->assign($request, $paramedic, $controller);

        $this->assertSame([$paramedic->id], $this->userIdsPushed());
    }

    // ── 신고자 알림 ──────────────────────────────────────────────────────────

    public function test_accepting_a_dispatch_pushes_to_the_requester(): void
    {
        [$project, $controller, $paramedic, $request] = $this->dispatchScenario();
        $this->subscribe($request->user);

        $dispatch = app(\App\Services\DispatchService::class)->assign($request, $paramedic, $controller);
        app(\App\Services\DispatchService::class)->transition($dispatch, DispatchStatus::ACCEPTED, $paramedic);

        $this->assertContains($request->user_id, $this->userIdsPushed());
    }

    // ── ADR-0004: 연락처는 푸시에 실리지 않는다 ────────────────────────────

    public function test_no_push_payload_ever_contains_a_phone_number(): void
    {
        // 푸시는 잠금화면에 뜨고 전송 사업자 서버를 거친다. 인가된 채널(control /
        // 개인 dispatch / requester)과 달리 연락처를 실을 수 없다.
        [$project, $controller, $paramedic, $request] = $this->dispatchScenario(
            requesterPhone: '01098765432',
            paramedicPhone: '01011112222',
        );
        $this->subscribe($controller);
        $this->subscribe($paramedic);
        $this->subscribe($request->user);

        $dispatch = app(\App\Services\DispatchService::class)->assign($request, $paramedic, $controller);
        app(\App\Services\DispatchService::class)->transition($dispatch, DispatchStatus::ACCEPTED, $paramedic);

        $this->assertNotEmpty($this->messages(), '아무것도 안 보내면 이 검사는 무의미하다');

        foreach ($this->messages() as $message) {
            $blob = json_encode([
                $message->title, $message->body, $message->url, $message->data, $message->tag,
            ], JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString('01098765432', $blob, '신고자 연락처가 푸시에 실렸다');
            $this->assertStringNotContainsString('01011112222', $blob, '대원 연락처가 푸시에 실렸다');
        }
    }

    public function test_a_request_is_created_even_when_nobody_can_be_pushed(): void
    {
        // 구독자가 하나도 없어도 접수는 성공해야 한다.
        // 「알림이 안 갔다」보다 「신고가 안 됐다」가 훨씬 나쁘다.
        $request = app(\App\Services\RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0,
        ], User::factory()->create());

        $this->assertDatabaseHas('requests', ['id' => $request->id]);
        $this->assertSame([], $this->sent());
    }

    /** @return array{0: Project, 1: User, 2: User, 3: RescueRequest} */
    private function dispatchScenario(
        ?string $requesterPhone = null,
        ?string $paramedicPhone = null,
    ): array {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id, 'user_id' => $controller->id,
        ]);

        $paramedic = User::factory()->create(
            $paramedicPhone ? ['phone' => $paramedicPhone] : []
        );
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $paramedic->id,
        ]);

        $requester = User::factory()->create(
            $requesterPhone ? ['phone' => $requesterPhone] : []
        );
        $request = RescueRequest::factory()->for($requester)->create([
            'project_id' => $project->id, 'status' => RequestStatus::PENDING,
        ]);

        return [$project, $controller, $paramedic, $request->load('user')];
    }
}
