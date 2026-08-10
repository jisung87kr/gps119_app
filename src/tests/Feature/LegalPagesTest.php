<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 법적 고지 화면.
 *
 * 🔑 여기서 지키는 건 «비로그인도 볼 수 있다»는 성질이다.
 *    - 위치정보를 다루는 서비스라 «가입 전에» 약관을 볼 수 있어야 한다
 *    - Play 스토어 심사가 개인정보처리방침 URL 을 «공개»로 요구한다
 *    누군가 라우트를 auth 그룹 안으로 옮기면 두 요건이 조용히 깨지고,
 *    그 사실은 스토어 반려 때에야 드러난다.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_비로그인도_개인정보처리방침을_볼_수_있다(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('개인정보처리방침');
    }

    public function test_비로그인도_위치기반서비스_약관을_볼_수_있다(): void
    {
        $this->get(route('legal.location-terms'))
            ->assertOk()
            ->assertSee('위치기반서비스 이용약관');
    }

    public function test_로그인_화면에_두_문서_링크가_있다(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee(route('legal.privacy'), false);
        $response->assertSee(route('legal.location-terms'), false);
    }

    public function test_마이페이지에_두_문서_링크가_있다(): void
    {
        // 역할은 주지 않는다. 마이페이지는 인증만 요구하고, 역할 시드에 의존시키면
        // 이 테스트가 「법적 고지 노출」이 아니라 시드 상태를 검사하게 된다.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.show'))->assertOk();

        $response->assertSee(route('legal.privacy'), false);
        $response->assertSee(route('legal.location-terms'), false);
    }

    /**
     * 두 문서는 서로를 가리켜야 한다. 한쪽만 본 이용자가 나머지를 찾지 못하면
     * 「고지했다」가 성립하기 어렵다.
     */
    public function test_두_문서가_서로_링크된다(): void
    {
        $this->get(route('legal.privacy'))
            ->assertSee(route('legal.location-terms'), false);

        $this->get(route('legal.location-terms'))
            ->assertSee(route('legal.privacy'), false);
    }
}
