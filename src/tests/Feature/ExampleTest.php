<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * 루트 경로는 신고 생성 화면으로 리다이렉트한다(앱 기본 동작).
     */
    public function test_the_root_redirects_to_request_create(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('request.create'));
    }
}
