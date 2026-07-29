<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Events\ParticipantLocationUpdated;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\LocationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use ReflectionObject;
use Tests\TestCase;

/**
 * BE-2.2 — ParticipantLocationUpdated 채널/페이로드 + presence 채널 인가 (SPEC-05a/05b).
 */
class ParticipantLocationEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeEvent(int $projectId = 1): ParticipantLocationUpdated
    {
        return new ParticipantLocationUpdated(
            projectId: $projectId,
            userId: 42,
            role: EventRole::PARAMEDIC,
            latitude: 37.5665,
            longitude: 126.9780,
            accuracy: 10,
            recordedAt: now()->toISOString(),
        );
    }

    /** broadcastOn — locations(presence) + control(private) 두 채널 */
    public function test_broadcasts_on_locations_and_control(): void
    {
        $channels = $this->makeEvent(7)->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PresenceChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        // presence 채널은 'presence-' 프리픽스
        $this->assertSame('presence-event.7.locations', $channels[0]->name);
        $this->assertSame('private-event.7.control', $channels[1]->name);
    }

    /** broadcastWith — 최소 페이로드, 연락처 없음 */
    public function test_payload_has_no_contact(): void
    {
        $payload = $this->makeEvent()->broadcastWith();

        $this->assertSame(
            ['user_id', 'role', 'latitude', 'longitude', 'accuracy', 'recorded_at'],
            array_keys($payload)
        );
        $this->assertSame(42, $payload['user_id']);
        $this->assertSame(EventRole::PARAMEDIC->value, $payload['role']);
        // 연락처(name/phone) 미포함
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('name', $payload);
    }

    public function test_broadcast_as_name(): void
    {
        $this->assertSame('participant.location', $this->makeEvent()->broadcastAs());
    }

    /** LocationService::record 가 ParticipantLocationUpdated 를 발행 */
    public function test_location_service_dispatches_event(): void
    {
        Event::fake([ParticipantLocationUpdated::class]);

        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $user = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $user->id, 'sharing_location' => true,
        ]);

        app(LocationService::class)->record($project, $user, [
            'latitude' => 37.5, 'longitude' => 127.0, 'accuracy' => 8,
            'recorded_at' => now()->toISOString(),
        ]);

        Event::assertDispatched(ParticipantLocationUpdated::class, function ($e) use ($project, $user) {
            return $e->projectId === $project->id
                && $e->userId === $user->id
                && $e->role === EventRole::PARAMEDIC;
        });
    }

    // === presence 채널 인가 (직접 콜백 호출) ===

    private function authorizeLocations(User $user, int $projectId): mixed
    {
        $broadcaster = app(BroadcastFactory::class)->connection();
        $ref = new ReflectionObject($broadcaster);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);

        foreach ($prop->getValue($broadcaster) as $pattern => $callback) {
            $regex = '/^'.preg_replace('/\{[^}]+\}/', '([^.]+)', str_replace('.', '\.', $pattern)).'$/';
            if (preg_match($regex, "event.{$projectId}.locations", $m)) {
                array_shift($m);

                return $callback($user, (int) $m[0]);
            }
        }

        return false;
    }

    /** active 참가자는 presence 통과, payload {user_id, role} */
    public function test_locations_presence_allows_active_participant(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $user = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);

        $result = $this->authorizeLocations($user, $project->id);

        $this->assertIsArray($result);
        $this->assertSame($user->id, $result['user_id']);
        $this->assertSame(EventRole::PARAMEDIC->value, $result['role']);
    }

    /** 비참가자는 presence 거부 */
    public function test_locations_presence_denies_non_participant(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $stranger = User::factory()->create();

        $this->assertFalse($this->authorizeLocations($stranger, $project->id));
    }

    /** pending 참가자는 active 아니므로 거부 */
    public function test_locations_presence_denies_pending(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $user = User::factory()->create();
        EventParticipant::factory()->paramedic()->pending()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
        ]);

        $this->assertFalse($this->authorizeLocations($user, $project->id));
    }
}
