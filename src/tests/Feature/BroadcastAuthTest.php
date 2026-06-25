<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionObject;
use Tests\TestCase;

/**
 * BE-0.1 / FE-0.1 — 채널 인가 콜백 검증 (SPEC-05a, Phase 0 잠정 규칙).
 *
 * /broadcasting/auth 의 인가 판정은 routes/channels.php 의 채널 콜백이 담당한다.
 * Phase 0 잠정 인가: requests.global / event.{id}.control 모두 admin·rescuer 통과, 그 외 거부.
 *
 * 콜백을 직접 실행하여 검증한다(impl-tasks: "Broadcast::channel 인가 콜백 직접 호출").
 * 이렇게 하면 브로드캐스터 드라이버(null/reverb) 구성과 무관하게 인가 규칙만 단위 검증된다.
 */
class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * 등록된 채널 콜백을 채널 이름으로 찾아 실행하고 결과(통과 여부)를 반환한다.
     * 채널 인자(예: {projectId})는 placeholder 순서대로 전달.
     */
    private function authorizeChannel(User $user, string $channel, array $params = []): bool
    {
        $broadcaster = app(BroadcastFactory::class)->connection();

        $ref = new ReflectionObject($broadcaster);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);
        $channels = $prop->getValue($broadcaster);

        foreach ($channels as $pattern => $callback) {
            $regex = '/^'.preg_replace('/\{[^}]+\}/', '([^.]+)', str_replace('.', '\.', $pattern)).'$/';
            if (preg_match($regex, $channel, $matches)) {
                array_shift($matches);
                $args = array_map(fn ($m) => is_numeric($m) ? (int) $m : $m, $matches);
                $result = $callback($user, ...$args);

                return $result === true || (is_array($result) && $result !== []);
            }
        }

        // 매칭되는 채널이 없으면 인가 실패로 간주(deny-by-default)
        return false;
    }

    public function test_admin_passes_requests_global(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($this->authorizeChannel($admin, 'requests.global'));
    }

    public function test_rescuer_passes_requests_global(): void
    {
        $rescuer = User::factory()->create();
        $rescuer->assignRole('rescuer');

        $this->assertTrue($this->authorizeChannel($rescuer, 'requests.global'));
    }

    public function test_regular_user_denied_requests_global(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->assertFalse($this->authorizeChannel($user, 'requests.global'));
    }

    public function test_admin_passes_event_control(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $project = Project::factory()->create(['created_by' => $admin->id]);

        $this->assertTrue($this->authorizeChannel($admin, "event.{$project->id}.control"));
    }

    public function test_rescuer_passes_event_control(): void
    {
        $rescuer = User::factory()->create();
        $rescuer->assignRole('rescuer');
        $project = Project::factory()->create(['created_by' => $rescuer->id]);

        $this->assertTrue($this->authorizeChannel($rescuer, "event.{$project->id}.control"));
    }

    public function test_regular_user_denied_event_control(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->assertFalse($this->authorizeChannel($user, "event.{$project->id}.control"));
    }

    /**
     * HTTP 경로 스모크: 비로그인 요청은 /broadcasting/auth 에서 거부된다(미인증).
     *
     * 테스트 기본 null 브로드캐스터는 인가를 강제하지 않으므로,
     * 운영과 동일하게 인가를 검증하는 reverb 드라이버로 전환해 거부(4xx)를 확인한다.
     */
    public function test_guest_request_is_rejected(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-requests.global',
        ]);

        // 미인증 → 인가 실패(401 또는 403). 200(인가 통과)이면 안 된다.
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
