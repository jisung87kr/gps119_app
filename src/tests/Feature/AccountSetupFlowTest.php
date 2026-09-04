<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 발급 계정의 첫 로그인 셋업 흐름 (ADR-0009 D3).
 *
 * 🔴 발급 계정은 «동의 없이» 만들어진다. 비밀번호 변경 + 필수 동의를 마칠 때까지
 *    다른 화면에 닿으면 안 된다 — 닿으면 동의 없는 계정이 서비스에 들어간다.
 */
class AccountSetupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function issued(string $plainPassword = 'initPass99'): User
    {
        return User::factory()->create([
            'phone' => '01044445555',
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
            'issued_at' => now(),
        ]);
    }

    public function test_발급된_초기_비밀번호로_로그인된다(): void
    {
        $this->issued('initPass99');

        $this->post('/login', ['phone' => '010-4444-5555', 'password' => 'initPass99'])
            ->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_🔴_발급_계정은_다른_화면에서_셋업으로_쫓겨난다(): void
    {
        $user = $this->issued();

        $this->actingAs($user)->get('/requests/create')
            ->assertRedirect(route('account.setup.show'));
    }

    public function test_약관_열람은_셋업_전에도_허용된다(): void
    {
        $user = $this->issued();

        $this->actingAs($user)->get(route('legal.privacy'))->assertOk();
        $this->actingAs($user)->get(route('legal.location-terms'))->assertOk();
    }

    public function test_🔴_동의_없이는_셋업이_완료되지_않는다(): void
    {
        $user = $this->issued();

        $this->actingAs($user)->post(route('account.setup.store'), [
            'password' => 'NewStrongPass!2026',
            'password_confirmation' => 'NewStrongPass!2026',
            'consents' => [], // 동의 없음
        ])->assertSessionHasErrors('consents');

        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertCount(0, $user->fresh()->consents);
    }

    public function test_🔴_위치약관만_빠져도_완료되지_않는다(): void
    {
        $user = $this->issued();

        $this->actingAs($user)->post(route('account.setup.store'), [
            'password' => 'NewStrongPass!2026',
            'password_confirmation' => 'NewStrongPass!2026',
            'consents' => ['privacy'],
        ])->assertSessionHasErrors('consents');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_비밀번호_변경과_동의를_마치면_게이트가_풀린다(): void
    {
        $user = $this->issued('initPass99');

        $this->actingAs($user)->post(route('account.setup.store'), [
            'password' => 'NewStrongPass!2026',
            'password_confirmation' => 'NewStrongPass!2026',
            'consents' => ['privacy', 'location_terms'],
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('NewStrongPass!2026', $fresh->password));
        $this->assertCount(2, $fresh->consents);

        // 이제 일반 화면에 닿는다 (셋업으로 안 쫓겨난다).
        $this->actingAs($fresh)->get('/requests/create')->assertOk();
    }

    public function test_일반_계정은_게이트에_걸리지_않는다(): void
    {
        $normal = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($normal)->get('/requests/create')->assertOk();
    }

    public function test_🔴_발급된_번호로_가입을_시도하면_로그인을_안내한다(): void
    {
        $this->issued();

        $this->post('/register', [
            'phone' => '01044445555',
            'password' => 'Whatever!2026',
            'password_confirmation' => 'Whatever!2026',
            'consents' => ['privacy', 'location_terms'],
        ])->assertSessionHasErrors('phone');
    }
}
