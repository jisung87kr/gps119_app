<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * 로그인 성공 후 착지 지점.
 *
 * `config/fortify.php` 의 `home` 이 Laravel 스켈레톤 기본값 `/home` 인 채로 남아 있었고,
 * 이 앱에는 그 라우트가 없어서 **로그인에 성공하면 404 가 떴다.** 인증 자체는 멀쩡했기에
 * 테스트도 통과했다 — «리다이렉트했다»만 보고 «그 끝에 무엇이 있는가»를 아무도 안 봤다.
 *
 * 그래서 여기서는 목적지 문자열을 비교하지 않고 **따라가서 열리는지**를 본다.
 * `/dashboard` 로 값만 맞추는 테스트는 나중에 그 라우트가 사라지면 또 초록불이 된다.
 */
class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_configured_home_path_resolves_to_a_route(): void
    {
        $home = config('fortify.home');

        try {
            app('router')->getRoutes()->match(Request::create($home, 'GET'));
        } catch (NotFoundHttpException) {
            $this->fail("fortify.home 이 존재하지 않는 경로를 가리킨다: {$home}");
        }

        $this->assertTrue(true);
    }

    public function test_user_lands_on_a_page_that_opens_after_login(): void
    {
        $user = User::factory()->create([
            'phone' => '01012345678',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('user');

        $this->post('/login', [
            'phone' => '01012345678',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        // 리다이렉트를 «따라가서» 열리는지 본다. 404 면 여기서 잡힌다.
        $this->get(config('fortify.home'))->assertOk();
    }

    public function test_admin_lands_on_a_page_that_opens_after_login(): void
    {
        // 관리자는 이메일로 로그인한다(FortifyServiceProvider::authenticateUsing).
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->post('/login', [
            'phone' => 'admin@admin.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($admin);
        $this->get(config('fortify.home'))->assertOk();
    }
}
