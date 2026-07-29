<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Events\DispatchAssigned;
use App\Events\DispatchStatusUpdated;
use App\Events\RequestStatusUpdated;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use ReflectionObject;
use Tests\TestCase;

/**
 * BE-3.3 — 3개 이벤트 채널·페이로드(연락처 규칙) + dispatch/requester 채널 인가 + EnsureEventRole dispatch 해석.
 */
class DispatchEventChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function dispatchModel(string $status = 'assigned'): Dispatch
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $requester = User::factory()->create(['name' => '신고자', 'phone' => '01011112222']);
        $request = RescueRequest::factory()->for($requester)->create([
            'project_id' => $project->id, 'address' => '서울시 중구',
        ]);
        $paramedic = User::factory()->create(['name' => '구급대원', 'phone' => '01033334444']);

        return Dispatch::factory()->create([
            'request_id' => $request->id,
            'project_id' => $project->id,
            'assigned_by' => User::factory()->create()->id,
            'paramedic_id' => $paramedic->id,
            'status' => DispatchStatus::from($status),
        ]);
    }

    // ── 이벤트 채널/페이로드 ─────────────────────────────────────

    public function test_dispatch_assigned_channel_and_contact(): void
    {
        $d = $this->dispatchModel();
        $event = new DispatchAssigned($d);

        $ch = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $ch[0]);
        $this->assertSame("private-event.{$d->project_id}.dispatch.{$d->paramedic_id}", $ch[0]->name);
        $this->assertSame('dispatch.assigned', $event->broadcastAs());

        // 개인 dispatch 채널 → 신고자 연락처 포함 허용(ADR-0004)
        $p = $event->broadcastWith();
        $this->assertSame('구급대원', $d->paramedic->name);
        $this->assertSame('01011112222', $p['request']['requester_phone']);
        $this->assertSame($d->id, $p['dispatch_id']);
    }

    public function test_dispatch_status_updated_no_contact(): void
    {
        $d = $this->dispatchModel('accepted');
        $event = new DispatchStatusUpdated($d);

        $ch = $event->broadcastOn();
        $this->assertSame("private-event.{$d->project_id}.control", $ch[0]->name);
        $this->assertSame('dispatch.updated', $event->broadcastAs());

        $p = $event->broadcastWith();
        $this->assertSame(['dispatch_id', 'request_id', 'status', 'paramedic_id', 'occurred_at'], array_keys($p));
        // 연락처 키 부재
        $this->assertArrayNotHasKey('requester_phone', $p);
        $this->assertArrayNotHasKey('phone', $p);
    }

    public function test_request_status_updated_channel_and_contact(): void
    {
        $d = $this->dispatchModel('accepted');
        $request = $d->request;
        $event = new RequestStatusUpdated($request, $d);

        $ch = $event->broadcastOn();
        $this->assertSame("private-event.{$request->project_id}.requester.{$request->user_id}", $ch[0]->name);
        $this->assertSame('request.status.updated', $event->broadcastAs());

        // 신고자 본인 채널 → 담당자 이름·연락처 포함
        $p = $event->broadcastWith();
        $this->assertSame('구급대원', $p['dispatch']['paramedic_name']);
        $this->assertSame('01033334444', $p['dispatch']['paramedic_phone']);
    }

    // ── 채널 인가(직접 콜백 호출) ────────────────────────────────

    private function authorize(User $user, string $channel): mixed
    {
        $broadcaster = app(BroadcastFactory::class)->connection();
        $ref = new ReflectionObject($broadcaster);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);

        foreach ($prop->getValue($broadcaster) as $pattern => $callback) {
            $regex = '/^'.preg_replace('/\{[^}]+\}/', '([^.]+)', str_replace('.', '\.', $pattern)).'$/';
            if (preg_match($regex, $channel, $m)) {
                array_shift($m);
                $args = array_map(fn ($x) => is_numeric($x) ? (int) $x : $x, $m);

                return $callback($user, ...$args);
            }
        }

        return false;
    }

    public function test_dispatch_channel_owner_only(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $paramedic->id]);

        // 본인 + 수령가능 역할 → 통과
        $this->assertTrue((bool) $this->authorize($paramedic, "event.{$project->id}.dispatch.{$paramedic->id}"));

        // 타인 채널 구독 시도 → 거부
        $other = User::factory()->create();
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $other->id]);
        $this->assertFalse((bool) $this->authorize($other, "event.{$project->id}.dispatch.{$paramedic->id}"));
    }

    public function test_dispatch_channel_denies_non_receiver(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $participant = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $participant->id]); // participant

        $this->assertFalse((bool) $this->authorize($participant, "event.{$project->id}.dispatch.{$participant->id}"));
    }

    public function test_requester_channel_requires_own_request(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $requester = User::factory()->create();
        RescueRequest::factory()->for($requester)->create(['project_id' => $project->id]);

        // 본인 + 신고 이력 → 통과
        $this->assertTrue((bool) $this->authorize($requester, "event.{$project->id}.requester.{$requester->id}"));

        // 신고 이력 없는 사용자 → 거부
        $noHistory = User::factory()->create();
        $this->assertFalse((bool) $this->authorize($noHistory, "event.{$project->id}.requester.{$noHistory->id}"));
    }

    // ── EnsureEventRole 의 dispatch 라우트 project 해석 ──────────

    public function test_ensure_event_role_resolves_project_from_dispatch_route(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'event.role:controller'])
            ->get('/_test/dispatches/{dispatch}/guard', fn () => response()->json(['ok' => true]));

        $d = $this->dispatchModel();
        $project = $d->project;

        // 그 행사 controller → 통과(dispatch→project 해석)
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);
        Sanctum::actingAs($controller);
        $this->getJson("/_test/dispatches/{$d->id}/guard")->assertOk();

        // 비-controller → 403
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->getJson("/_test/dispatches/{$d->id}/guard")->assertStatus(403);
    }
}
