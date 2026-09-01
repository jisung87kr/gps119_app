<?php

namespace Tests\Feature;

use App\Enums\ConsentType;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 약관 동의 (위치정보법 대응).
 *
 * 🔴 위치정보법은 개인위치정보 수집에 **위치기반서비스 약관의 «별도» 동의**를 요구한다.
 *    링크를 걸어두는 것은 동의가 아니다.
 */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $override = []): array
    {
        return array_merge([
            'phone' => '01099998888',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
            'consents' => ['privacy', 'location_terms'],
        ], $override);
    }

    public function test_동의하면_가입되고_기록이_남는다(): void
    {
        $this->post('/register', $this->form());

        $user = User::where('phone', '01099998888')->firstOrFail();

        $this->assertCount(2, $user->consents);
        $this->assertEqualsCanonicalizing(
            ['privacy', 'location_terms'],
            $user->consents->pluck('type')->map(fn ($t) => $t->value)->all(),
        );
    }

    public function test_🔴_동의_없이는_가입되지_않는다(): void
    {
        $this->post('/register', $this->form(['consents' => []]))
            ->assertSessionHasErrors('consents');

        $this->assertDatabaseMissing('users', ['phone' => '01099998888']);
    }

    public function test_🔴_위치약관만_빠져도_가입되지_않는다(): void
    {
        // 개인정보처리방침 하나로 «묶어서» 받는 것을 막는다.
        $this->post('/register', $this->form(['consents' => ['privacy']]))
            ->assertSessionHasErrors('consents');

        $this->assertDatabaseMissing('users', ['phone' => '01099998888']);
    }

    public function test_🔴_동의가_실패하면_계정도_남지_않는다(): void
    {
        // 계정만 생기고 동의가 없는 상태는 «위치를 수집할 근거가 없는» 계정이다.
        $this->post('/register', $this->form(['consents' => ['privacy']]));

        $this->assertSame(0, User::where('phone', '01099998888')->count());
        $this->assertSame(0, UserConsent::count());
    }

    public function test_어느_판에_동의했는지_남는다(): void
    {
        $this->post('/register', $this->form());

        $consent = UserConsent::where('type', 'location_terms')->firstOrFail();

        $this->assertSame(config('legal.versions.location_terms'), $consent->version);
        $this->assertNotNull($consent->agreed_at);
    }

    public function test_🔑_두_번_기록해도_행이_늘지_않는다(): void
    {
        // 폼 이중 제출·재시도 경로에서 기록이 부풀면 안 된다.
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $service->record($user, ConsentType::required());
        $service->record($user, ConsentType::required());

        $this->assertSame(2, UserConsent::where('user_id', $user->id)->count());
    }

    public function test_판이_올라가면_다시_받아야_한다(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);
        $service->record($user, ConsentType::required());

        $this->assertSame([], $service->missingRequired($user));

        config(['legal.versions.location_terms' => '2027-01-01']);

        $this->assertSame(
            [ConsentType::LOCATION_TERMS],
            $service->missingRequired($user),
        );
    }

    public function test_시각을_주입받는다(): void
    {
        $user = User::factory()->create();
        $at = Carbon::parse('2026-09-01 12:00:00');

        app(ConsentService::class)->record($user, [ConsentType::PRIVACY], '203.0.113.9', $at);

        $consent = UserConsent::firstOrFail();
        $this->assertTrue($at->equalTo($consent->agreed_at));
        $this->assertSame('203.0.113.9', $consent->ip);
    }

    public function test_동의가_없는_사용자는_동의_화면을_본다(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/consent')->assertOk()->assertSee('약관 동의');
    }

    public function test_동의가_끝난_사용자는_동의_화면에_머물지_않는다(): void
    {
        $user = User::factory()->create();
        app(ConsentService::class)->record($user, ConsentType::required());

        $this->actingAs($user)->get('/consent')->assertRedirect();
    }

    public function test_동의_화면에서_필수를_다_체크해야_통과한다(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/consent', ['consents' => ['privacy']])
            ->assertSessionHasErrors('consents');

        $this->assertSame(0, UserConsent::count());

        $this->actingAs($user)->post('/consent', ['consents' => ['privacy', 'location_terms']])
            ->assertRedirect();

        $this->assertSame(2, UserConsent::count());
    }

    public function test_동의하지_않고_나가면_로그아웃된다(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/consent/decline')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
