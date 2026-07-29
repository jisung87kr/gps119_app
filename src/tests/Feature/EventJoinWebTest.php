<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FE-1.1 — 행사 입장 web 라우트 렌더링/인증 가드 검증.
 * (코드입력→미리보기→입장 JS 상호작용은 수동 QA.)
 */
class EventJoinWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_join_page_renders_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('events.join'))
            ->assertOk()
            ->assertSee('행사 입장')
            ->assertSee('eventJoinApp', false); // Vue 마운트 지점
    }

    public function test_join_page_redirects_guest_to_login(): void
    {
        $response = $this->get(route('events.join'));

        $response->assertRedirect(route('login'));
    }

    public function test_join_page_preserves_intended_url_for_guest(): void
    {
        // auth 미들웨어가 intended URL 을 세션에 저장 → 로그인 후 복귀 가능
        $this->get(route('events.join'));

        $this->assertSame(route('events.join'), session()->get('url.intended'));
    }

    public function test_deeplink_prefills_uppercased_code(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/events/join/ab3k9p');

        $response->assertOk()
            // 서버가 대문자로 정규화해 data-prefill 에 주입
            ->assertSee('data-prefill="AB3K9P"', false);
    }

    public function test_deeplink_redirects_guest_to_login(): void
    {
        $this->get('/events/join/AB3K9P')->assertRedirect(route('login'));
    }
}
