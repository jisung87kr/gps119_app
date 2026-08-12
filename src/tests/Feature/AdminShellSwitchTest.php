<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 사용자 셸 ↔ 관리 셸 왕복.
 *
 * 관리자가 로그인하면 관리 셸로 가야 하고, 사용자 화면으로 넘어가더라도
 * 돌아올 길이 화면에 있어야 한다. 「리다이렉트가 어디로 갔나」만 보면
 * 왕복 중 한쪽이 끊겨도 초록불이 뜨므로 양방향을 다 본다.
 */
class AdminShellSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function regularUser(): User
    {
        $user = User::factory()->create([
            'phone' => '01012345678',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('user');

        return $user;
    }

    public function test_admin_login_lands_on_the_admin_shell(): void
    {
        $this->post('/login', [
            'phone' => 'admin@admin.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    /**
     * 2026-08-12: 착지 규칙이 LandingResolver 한 곳으로 합쳐지면서 일반 사용자의 기본
     * 착지가 `/dashboard` → `/requests/create` 로 바뀌었다. 현장 요구(#6)가 「참가자·
     * 운영진·경찰·자원봉사는 구조요청 화면」이고, `/` 는 원래도 거기로 보내고 있었다 —
     * 즉 진입 경로에 따라 다른 화면이 뜨던 것을 없앤 것이다.
     *
     * 이 테스트가 지키는 것은 목적지 문자열이 아니라 «관리 셸로 새지 않는다»이다.
     */
    public function test_regular_user_login_does_not_land_in_the_admin_shell(): void
    {
        $this->regularUser();

        $this->post('/login', [
            'phone' => '01012345678',
            'password' => 'password',
        ])->assertRedirect(route('request.create'));
    }

    public function test_intended_destination_still_wins_over_the_role_default(): void
    {
        // 로그아웃 상태로 보호된 페이지에 들어왔다가 로그인한 경우에는 그 페이지로 가야 한다.
        // 역할 기반 경로는 «갈 곳이 따로 없을 때»의 기본값이지 강제 이동이 아니다.
        $this->get(route('admin.members'))->assertRedirect(route('login'));

        $this->post('/login', [
            'phone' => 'admin@admin.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.members'));
    }

    public function test_admin_sees_a_way_back_to_the_admin_shell_from_the_profile(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->actingAs($admin)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('관리자 화면')
            ->assertSee(route('admin.dashboard'), false);
    }

    public function test_regular_user_does_not_see_the_admin_entry_point(): void
    {
        $this->actingAs($this->regularUser())->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('관리자 화면')
            ->assertDontSee(route('admin.dashboard'), false);
    }

    public function test_admin_shell_links_back_to_the_user_screen(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('사용자 페이지')
            ->assertSee(route('dashboard'), false);
    }
}
