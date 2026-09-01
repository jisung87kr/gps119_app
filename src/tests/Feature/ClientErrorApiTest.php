<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 웹뷰 JS 에러 수집 엔드포인트 (M-16).
 *
 * 🔑 로그«인» 전 화면에서도 받아야 한다. 거기서 깨지면 사용자는 들어오지도 못하는데,
 *    정작 그 화면이 가장 안 보이는 곳이다.
 */
class ClientErrorApiTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'kind' => 'error',
            'message' => 'x is not defined',
            'source' => 'https://gps119.co.kr/js/components/locationShare.js',
            'line' => 42,
            'column' => 7,
            'url' => 'https://gps119.co.kr/events/7/active',
            'platform' => 'android',
        ], $override);
    }

    public function test_로그인하지_않아도_받는다(): void
    {
        Log::shouldReceive('channel')->with('client')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->postJson('/api/client-errors', $this->payload())
            ->assertNoContent();
    }

    public function test_전용_채널로_남는다(): void
    {
        // laravel.log 에 섞이면 클라이언트 잡음이 서버 장애를 덮는다.
        Log::shouldReceive('channel')->with('client')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'x is not defined')
                && $context['platform'] === 'android'
                && $context['line'] === 42;
        });

        $this->postJson('/api/client-errors', $this->payload())->assertNoContent();
    }

    public function test_🔴_메시지가_없으면_거절한다(): void
    {
        $this->postJson('/api/client-errors', $this->payload(['message' => '']))
            ->assertStatus(422);
    }

    public function test_🔴_모르는_kind_는_거절한다(): void
    {
        $this->postJson('/api/client-errors', $this->payload(['kind' => 'whatever']))
            ->assertStatus(422);
    }

    public function test_🔴_상한을_넘는_문자열은_거절한다(): void
    {
        // 클라이언트가 자르지만, 서버가 «믿고» 받으면 로그가 통째로 터진다.
        $this->postJson('/api/client-errors', $this->payload(['message' => str_repeat('a', 501)]))
            ->assertStatus(422);

        $this->postJson('/api/client-errors', $this->payload(['stack' => str_repeat('a', 2001)]))
            ->assertStatus(422);
    }

    public function test_선택값이_없어도_받는다(): void
    {
        Log::shouldReceive('channel')->with('client')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->postJson('/api/client-errors', [
            'kind' => 'unhandledrejection',
            'message' => '네트워크 실패',
        ])->assertNoContent();
    }
}
