<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * mobile-app 에픽 0-8 — 관제 마커색의 «단일 출처»를 자동 판정으로 고정한다.
 *
 * 배경: roleMeta.js 와 EventRole::markerColor() 에 hex 를 두 벌 적고 "변경 시 같이 수정"
 * 주석으로 규율에 맡겼더니, 7개 중 4개가 어긋난 채로 운영됐다(06 §2-1 실측표).
 * 사람 눈으로 한 번 맞춰도 다음 사람이 또 어긋뜨리므로, 여기서 세 가지를 «판정»으로 남긴다.
 *
 *   ① markerColor() 가 control-map-spec §2 표와 정확히 같은가 (정본 고정)
 *   ② mapMeta() 가 7종 전부를 선언 순서대로 담는가 (관제 필터 순서가 여기서 나온다)
 *   ③ 그 값이 실제로 /control 페이지까지 «주입»되는가 (enum→blade→dataset 경로 전체)
 *
 * ③ 이 핵심이다. ①②만 있으면 뷰에서 주입을 빠뜨려도 초록불이 뜨고,
 * JS 는 다시 자기 사본을 갖게 된다.
 */
class EventRoleMapMetaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * control-map-spec.md §2 표 — 이 배열이 정본의 사본이 아니라 «판정 기준»이다.
     * 스펙을 바꾸려면 이 테스트를 의도적으로 고쳐야 한다.
     */
    private const SPEC_COLORS = [
        'participant' => '#6B7280',       // gray-500
        'staff' => '#2563EB',             // blue-600
        'police' => '#1E3A8A',            // blue-900
        'volunteer_course' => '#16A34A',  // green-600
        'volunteer_medic' => '#F59E0B',   // amber-500
        'paramedic' => '#DC2626',         // red-600
        'controller' => '#7C3AED',        // violet-600
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marker_colors_match_the_control_map_spec(): void
    {
        foreach (EventRole::cases() as $role) {
            $this->assertSame(
                self::SPEC_COLORS[$role->value],
                $role->markerColor(),
                "역할 {$role->value} 의 마커색이 control-map-spec §2 표와 다르다."
            );
        }
    }

    public function test_marker_colors_are_uppercase_hex(): void
    {
        // 대소문자가 섞이면 «다른 값»과 «같은 색 다른 표기»를 눈으로 구분하기 어려워진다.
        // 실제로 participant 는 #6b7280 / #6B7280 로 갈려 있었고, 이건 드리프트가 아니었다.
        foreach (EventRole::cases() as $role) {
            $this->assertMatchesRegularExpression(
                '/^#[0-9A-F]{6}$/',
                $role->markerColor(),
                "역할 {$role->value} 의 마커색은 대문자 6자리 hex 여야 한다."
            );
        }
    }

    public function test_map_meta_covers_every_role_in_declaration_order(): void
    {
        $meta = EventRole::mapMeta();

        $this->assertSame(
            array_map(fn (EventRole $r) => $r->value, EventRole::cases()),
            array_keys($meta),
            'mapMeta() 키 순서가 enum 선언 순서와 달라졌다 — 관제 역할 필터 순서가 여기서 나온다.'
        );

        foreach (EventRole::cases() as $role) {
            $this->assertSame($role->label(), $meta[$role->value]['label']);
            $this->assertSame($role->markerColor(), $meta[$role->value]['color']);
        }
    }

    public function test_control_page_injects_role_meta_into_the_mount_root(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);

        $html = $this->actingAs($admin)->get(route('control'))
            ->assertOk()
            ->assertSee('data-role-meta', false)
            ->getContent();

        // 7종의 색이 전부 페이지에 실려야 JS 가 사본 없이 렌더할 수 있다.
        foreach (self::SPEC_COLORS as $role => $hex) {
            $this->assertStringContainsString(
                $hex,
                $html,
                "역할 {$role} 의 색이 /control 페이지에 주입되지 않았다."
            );
        }
    }
}
