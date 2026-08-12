<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
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
 * requests.global / event.{id}.control 인가. 시스템 롤로는 «관리자»만 통과한다
 * (rescuer 롤은 2026-08-12 에 없앴다 — 대응 인력은 행사 역할로 표현된다).
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

    /**
     * 「상시 운영」 행사의 구급대(예전 rescuer)는 이제 전역 채널을 구독하지 않는다.
     * 그 사람의 신고 통지는 «그 행사» 스코프로 온다 — 전역은 관리자만이다.
     */
    public function test_a_paramedic_does_not_pass_requests_global(): void
    {
        $medic = User::factory()->create();
        $medic->assignRole('user');
        EventParticipant::factory()->paramedic()->create([
            'project_id' => \App\Models\Project::defaultEvent()->id,
            'user_id' => $medic->id,
        ]);

        $this->assertFalse($this->authorizeChannel($medic, 'requests.global'));
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

    /** 강화 규칙(BE-1.2): 해당 행사 active CONTROLLER 참가자는 control 통과 */
    public function test_controller_participant_passes_event_control(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create([
            'project_id' => $project->id,
            'user_id' => $controller->id,
        ]);

        $this->assertTrue($this->authorizeChannel($controller, "event.{$project->id}.control"));
    }

    /** 구급대(paramedic) 참가자는 control 채널 불통과 (ADR-0004) */
    public function test_paramedic_participant_denied_event_control(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $paramedic = User::factory()->create();
        EventParticipant::factory()->paramedic()->create([
            'project_id' => $project->id,
            'user_id' => $paramedic->id,
        ]);

        $this->assertFalse($this->authorizeChannel($paramedic, "event.{$project->id}.control"));
    }

    /** pending(승인대기) CONTROLLER 는 active 아니므로 불통과 */
    public function test_pending_controller_denied_event_control(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->pending()->create([
            'project_id' => $project->id,
            'user_id' => $controller->id,
        ]);

        $this->assertFalse($this->authorizeChannel($controller, "event.{$project->id}.control"));
    }

    /** 비참가 일반 사용자는 control 불통과 */
    public function test_non_participant_denied_event_control(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->assertFalse($this->authorizeChannel($stranger, "event.{$project->id}.control"));
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
