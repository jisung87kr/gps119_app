<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 루트 경로(`/`) — «이 사람이 여기 온 이유»로 보낸다.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_goes_straight_to_login(): void
    {
        // 신고 작성으로 한 번 튕기면 intended 가 심겨서 로그인 후 역할별 착지가 밀린다.
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_guest_root_does_not_leave_an_intended_url_behind(): void
    {
        $this->get('/');

        $this->assertNull(
            session('url.intended'),
            '루트 진입만으로 intended 가 남으면 로그인 직후 역할별 착지가 무력화된다.'
        );
    }

    public function test_logged_in_user_goes_to_request_create(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/')->assertRedirect(route('request.create'));
    }

    public function test_admin_goes_to_the_admin_shell(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
