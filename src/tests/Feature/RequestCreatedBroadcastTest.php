<?php

namespace Tests\Feature;

use App\Events\RequestCreated;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * BE-0.1 — RequestCreated 채널 분기(OI-1) + 페이로드(SPEC-05b) 검증.
 */
class RequestCreatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Request 생성 시 booted 훅이 NotifyRescuers(role 조회)를 동기 실행하므로 역할 시드 필요
        $this->seed(RolePermissionSeeder::class);
    }

    /** project_id 있는 신고 → event.{id}.control 채널로 broadcast */
    public function test_project_request_broadcasts_on_event_control_channel(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $request = RescueRequest::factory()->for($owner)->create([
            'project_id' => $project->id,
        ]);

        $event = new RequestCreated($request);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        // PrivateChannel 이름은 'private-' 프리픽스가 붙는다
        $this->assertSame("private-event.{$project->id}.control", $channels[0]->name);
    }

    /** ADR-0005: 행사 미지정 신고는 "상시 운영" 기본 행사로 귀속 → 그 행사 control 채널로 broadcast */
    public function test_request_without_project_is_attached_to_default_event(): void
    {
        $owner = User::factory()->create();
        $request = RescueRequest::factory()->for($owner)->create([
            'project_id' => null,
        ]);

        // 생성 훅이 기본 행사로 귀속시켜 project_id 가 항상 채워진다
        $default = Project::where('is_default', true)->firstOrFail();
        $this->assertSame($default->id, $request->fresh()->project_id);

        $channels = (new RequestCreated($request))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-event.{$default->id}.control", $channels[0]->name);
    }

    /** 레거시 채널(requests / rescuers)은 더 이상 사용하지 않는다 */
    public function test_legacy_channels_are_removed(): void
    {
        $owner = User::factory()->create();
        $request = RescueRequest::factory()->for($owner)->create(['project_id' => null]);

        $names = array_map(fn ($c) => $c->name, (new RequestCreated($request))->broadcastOn());

        $this->assertNotContains('requests', $names);
        $this->assertNotContains('private-rescuers', $names);
    }

    /** broadcastWith() 페이로드 키가 SPEC-05b 와 일치, 연락처 포함 */
    public function test_broadcast_payload_matches_spec(): void
    {
        $owner = User::factory()->create(['name' => '홍길동', 'phone' => '01098765432']);
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $request = RescueRequest::factory()->for($owner)->create([
            'project_id' => $project->id,
            'address' => '서울시 중구 세종대로',
        ]);

        $payload = (new RequestCreated($request))->broadcastWith();

        $this->assertSame([
            'request_id', 'project_id', 'type', 'priority',
            'latitude', 'longitude', 'address', 'requester', 'created_at',
        ], array_keys($payload));

        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($project->id, $payload['project_id']);
        // 신뢰 채널이므로 연락처 포함 (ADR-0004 위배 아님)
        $this->assertSame($owner->id, $payload['requester']['id']);
        $this->assertSame('홍길동', $payload['requester']['name']);
        $this->assertSame('01098765432', $payload['requester']['phone']);
    }

    /** broadcastAs 는 request.created */
    public function test_broadcast_as_name(): void
    {
        $owner = User::factory()->create();
        $request = RescueRequest::factory()->for($owner)->create(['project_id' => null]);

        $this->assertSame('request.created', (new RequestCreated($request))->broadcastAs());
    }

    /**
     * 접수 흐름(RequestService)이 RequestCreated 를 발행한다.
     *
     * 예전엔 Request 모델의 created 훅이 발행했다. 그러면 «행이 저장됐다»와
     * «구조요청이 접수됐다»가 구분되지 않아, 팩토리·시드가 행을 하나 만들 때마다
     * 관제 브로드캐스트와 통지가 같이 나갔다. 푸시가 붙으면 시드 한 번에 실제 발송이 된다.
     */
    public function test_the_service_dispatches_the_event(): void
    {
        Event::fake([RequestCreated::class]);
        $user = User::factory()->create();

        app(\App\Services\RequestService::class)->createRequest([
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'address' => '서울특별시 중구 세종대로 110',
        ], $user);

        Event::assertDispatched(RequestCreated::class);
    }

    /**
     * 실제 사용자 경로(HTTP)에서도 발행된다.
     *
     * 서비스 단위 테스트만 두면 컨트롤러가 서비스를 우회하도록 바뀌어도 초록불이 뜬다.
     * 신고 접수는 이 앱의 존재 이유라 «끝에서 끝까지» 한 번은 봐야 한다.
     */
    public function test_the_api_endpoint_dispatches_the_event(): void
    {
        Event::fake([RequestCreated::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/requests', [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'address' => '서울특별시 중구 세종대로 110',
        ])->assertSuccessful();

        Event::assertDispatched(RequestCreated::class);
    }

    /**
     * 반대 방향 — 팩토리로 «행만» 만드는 것은 도메인 사건이 아니다.
     *
     * 이 두 테스트는 짝이다. 위 하나만 두면 발행이 모델 훅으로 돌아가도 초록불이 뜬다.
     */
    public function test_creating_a_row_directly_does_not_dispatch_the_event(): void
    {
        Event::fake([RequestCreated::class]);

        RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => null]);

        Event::assertNotDispatched(RequestCreated::class);
    }
}
