<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Models\EventParticipant;
use App\Models\LocationPing;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 관제 지도 이동 궤적 API (M-25).
 */
class TrackApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function project(): Project
    {
        return Project::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    private function join(Project $project, User $user, EventRole $role): void
    {
        EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    /** 북쪽으로 22m 씩 이동하는 ping 을 넣는다 */
    private function pings(Project $project, User $user, int $n, int $agoMinutes = 10): void
    {
        for ($i = 0; $i < $n; $i++) {
            LocationPing::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'latitude' => 37.5665 + $i * 0.0002,
                'longitude' => 126.9780,
                'accuracy' => 10,
                'recorded_at' => now()->subMinutes($agoMinutes)->addSeconds($i * 5),
            ]);
        }
    }

    public function test_관제는_궤적을_본다(): void
    {
        $project = $this->project();
        $controller = User::factory()->create();
        $runner = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        $this->join($project, $runner, EventRole::PARTICIPANT);
        $this->pings($project, $runner, 10);

        Sanctum::actingAs($controller);

        $res = $this->getJson("/api/events/{$project->id}/tracks")->assertOk();

        $tracks = $res->json('data.tracks');
        $this->assertCount(1, $tracks);
        $this->assertSame($runner->id, $tracks[0]['user_id']);
        $this->assertCount(10, $tracks[0]['points']);
        // [lat, lng] 배열이다 — 키 있는 객체면 페이로드가 두 배가 된다.
        $this->assertCount(2, $tracks[0]['points'][0]);
    }

    public function test_🔴_참가자는_남의_궤적을_볼_수_없다(): void
    {
        $project = $this->project();
        $runner = User::factory()->create();
        $this->join($project, $runner, EventRole::PARTICIPANT);

        Sanctum::actingAs($runner);

        $this->getJson("/api/events/{$project->id}/tracks")->assertForbidden();
    }

    public function test_🔴_시간_범위_밖은_안_나온다(): void
    {
        $project = $this->project();
        $controller = User::factory()->create();
        $runner = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        $this->join($project, $runner, EventRole::PARTICIPANT);
        $this->pings($project, $runner, 10, agoMinutes: 300);

        Sanctum::actingAs($controller);

        $this->getJson("/api/events/{$project->id}/tracks?minutes=60")
            ->assertOk()->assertJsonCount(0, 'data.tracks');

        $this->getJson("/api/events/{$project->id}/tracks?minutes=600")
            ->assertOk()->assertJsonCount(1, 'data.tracks');
    }

    public function test_🔴_점이_하나뿐이면_선이_아니라서_빼낸다(): void
    {
        // 마커가 이미 그 자리에 있다. 점 하나를 «궤적»이라고 보내면 화면이
        // 「경로가 있다」고 말하게 된다.
        $project = $this->project();
        $controller = User::factory()->create();
        $runner = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        $this->join($project, $runner, EventRole::PARTICIPANT);
        $this->pings($project, $runner, 1);

        Sanctum::actingAs($controller);

        $this->getJson("/api/events/{$project->id}/tracks")
            ->assertOk()->assertJsonCount(0, 'data.tracks');
    }

    public function test_솎기_전_원본_개수를_같이_준다(): void
    {
        // 「500점만 보고 있다」를 화면이 말할 수 있어야 한다.
        $project = $this->project();
        $controller = User::factory()->create();
        $runner = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        $this->join($project, $runner, EventRole::PARTICIPANT);
        $this->pings($project, $runner, 30);

        Sanctum::actingAs($controller);

        $track = $this->getJson("/api/events/{$project->id}/tracks")->json('data.tracks.0');

        $this->assertSame(30, $track['count']);
        $this->assertNotNull($track['from']);
        $this->assertNotNull($track['to']);
    }

    public function test_🔴_시간_범위_상한을_넘기면_거절한다(): void
    {
        $project = $this->project();
        $controller = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        Sanctum::actingAs($controller);

        $this->getJson("/api/events/{$project->id}/tracks?minutes=99999")
            ->assertStatus(422);
    }

    public function test_사람을_지정해_볼_수_있다(): void
    {
        $project = $this->project();
        $controller = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->join($project, $controller, EventRole::CONTROLLER);
        $this->join($project, $a, EventRole::PARTICIPANT);
        $this->join($project, $b, EventRole::PARTICIPANT);
        $this->pings($project, $a, 5);
        $this->pings($project, $b, 5);

        Sanctum::actingAs($controller);

        $res = $this->getJson("/api/events/{$project->id}/tracks?user_ids[]={$a->id}")->assertOk();

        $this->assertCount(1, $res->json('data.tracks'));
        $this->assertSame($a->id, $res->json('data.tracks.0.user_id'));
    }
}
