<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\EventParticipantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 위치 공유 «의도»는 사용자 것이다 — 화면이 되살리지 않는다.
 *
 * 🔴 활동 화면이 매번 `enable()` 을 불러서, **사용자가 끈 공유가 화면을 옮기는 것만으로
 *    되살아났다**(2026-08-31 발견). `locationShare` 의 `resume()` 주석이 정확히 그 위험을
 *    경고하고 있었는데, 정작 화면은 `enable()` 을 쓰고 있었다.
 *
 * 위치 공유가 사용자 의사와 무관하게 켜지는 것은 사소한 버그가 아니다.
 */
class SharingIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function project(): Project
    {
        return Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'join_code' => 'ABC123',
        ]);
    }

    private function user(): User
    {
        return User::factory()->create(['phone' => '01012345678']);
    }

    public function test_처음_참가하면_공유가_켜진_채로_시작한다(): void
    {
        // 구조 지원이 목적이므로 참가자가 아무것도 누르지 않아도 상황실이 봐야 한다.
        $project = $this->project();
        $user = $this->user();

        $p = app(EventParticipantService::class)->joinByCode($project->join_code, $user);

        $this->assertTrue($p->sharing_location);
    }

    public function test_🔴_껐다가_재입장해도_되살아나지_않는다(): void
    {
        // firstOrCreate 의 «생성» 값이라 재입장에는 적용되지 않아야 한다.
        // 매번 켜면 사용자의 «끔»이 아무 의미가 없어진다.
        $project = $this->project();
        $user = $this->user();
        $service = app(EventParticipantService::class);

        $service->joinByCode($project->join_code, $user);
        $service->setSharing($project, $user, false);

        $again = $service->joinByCode($project->join_code, $user);

        $this->assertFalse($again->sharing_location);
    }

    public function test_🔑_활동_화면이_현재_의도를_그대로_내려준다(): void
    {
        // 화면은 서버 값을 «이어받기»만 한다. 이 값이 없어서 매번 enable() 을 불렀다.
        $project = $this->project();
        $user = $this->user();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'sharing_location' => false,
        ]);

        $this->actingAs($user)
            ->get(route('events.active', $project->id))
            ->assertOk()
            ->assertSee('data-sharing="0"', false);
    }

    public function test_공유_중인_사람에게는_1_이_내려간다(): void
    {
        $project = $this->project();
        $user = $this->user();
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'sharing_location' => true,
        ]);

        $this->actingAs($user)
            ->get(route('events.active', $project->id))
            ->assertOk()
            ->assertSee('data-sharing="1"', false);
    }
}
