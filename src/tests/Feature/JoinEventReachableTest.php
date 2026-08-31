<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 「행사 참가」로 가는 길이 «화면에» 있어야 한다.
 *
 * 🔴 앱 셸에는 주소창이 없고, QR 은 폰 기본 카메라가 읽어 Safari 로 열린다
 *    (Universal Links 미설정). 즉 **링크가 화면에 없으면 앱에서는 행사에 참가할
 *    방법이 «전혀» 없다.** 실제로 실기기 테스트가 여기서 막혔다(2026-08-31).
 *
 * 라우트가 살아 있는지가 아니라 **«닿을 수 있는지»**를 고정한다 — 라우트는 계속
 * 있었는데도 막혔던 것이 이 결함의 요점이다.
 */
class JoinEventReachableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_🔴_참가한_행사가_없어도_참가_링크가_보인다(): void
    {
        // 예전에는 「내 행사」 절이 통째로 사라져서 아무 안내도 없었다.
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('events.join'), false)
            ->assertSee('행사 참가하기');
    }

    public function test_이미_참가_중이어도_다른_행사로_갈_길이_있다(): void
    {
        // 한 사람이 두 행사를 오가는 운용이 실제로 있다(last_entered_at 이 그 근거).
        $user = User::factory()->create();
        \App\Models\EventParticipant::factory()->create([
            'project_id' => \App\Models\Project::factory()->create(['created_by' => $user->id])->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('events.join'), false);
    }
}
