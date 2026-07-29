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

    /** Request 생성 시 RequestCreated 이벤트가 dispatch 된다 (booted 훅) */
    public function test_event_is_dispatched_on_request_creation(): void
    {
        Event::fake([RequestCreated::class]);

        $owner = User::factory()->create();
        RescueRequest::factory()->for($owner)->create(['project_id' => null]);

        Event::assertDispatched(RequestCreated::class);
    }
}
