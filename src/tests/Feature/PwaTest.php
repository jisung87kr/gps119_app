<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — PWA 마감(manifest/SW/아이콘/온보딩 링크) 검증.
 * 정적 public 파일은 웹서버가 직접 서빙(Laravel 라우팅 아님) → 파일 존재/유효성 + 레이아웃 배선 검증.
 * 설치가능성/SW 등록 실동작은 브라우저 QA.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pwa_static_files_exist(): void
    {
        foreach (['manifest.webmanifest', 'sw.js', 'offline.html', 'icon-192.png', 'icon-512.png'] as $f) {
            $this->assertFileExists(public_path($f), "{$f} 누락");
        }
    }

    public function test_manifest_is_valid_and_complete(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('#2563EB', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        // 192/512 아이콘 + maskable 포함
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $purposes = implode(' ', array_column($manifest['icons'], 'purpose'));
        $this->assertStringContainsString('maskable', $purposes);
    }

    public function test_service_worker_excludes_api_from_cache(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        // 실시간 데이터 stale 방지: /api·/broadcasting 캐시 제외
        $this->assertStringContainsString('/api/', $sw);
        $this->assertStringContainsString('/broadcasting/', $sw);
        $this->assertStringContainsString('offline.html', $sw);
    }

    public function test_participant_layout_includes_manifest_and_theme(): void
    {
        $user = User::factory()->create(); // 팩토리가 phone 설정

        $res = $this->actingAs($user)->get(route('events.join'));

        $res->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('theme-color', false)
            ->assertSee('apple-touch-icon', false);
    }
}
