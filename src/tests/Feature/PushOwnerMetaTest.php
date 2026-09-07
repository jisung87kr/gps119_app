<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 푸시 등록의 «주인» 판정용 meta (2026-09-07 현장).
 *
 * 같은 기기·브라우저를 다른 계정이 쓰면 토큰·구독이 이전 사람 것으로 남는다. push.js /
 * push-native.js 는 `<meta name="gps119-user">` 와 저장된 주인을 비교해 다르면 다시 등록한다.
 * 이 meta 가 빠지면 JS 는 구버전 판정으로 조용히 돌아가고 문제가 그대로 남는다 — 그래서 고정한다.
 */
class PushOwnerMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_app_layout_carries_the_logged_in_user_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('<meta name="gps119-user" content="'.$user->id.'">', false);
    }

    public function test_control_page_carries_the_logged_in_user_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('control'))
            ->assertOk()
            ->assertSee('<meta name="gps119-user" content="'.$admin->id.'">', false);
    }

    public function test_guest_pages_carry_no_user_meta(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('gps119-user');
    }
}
