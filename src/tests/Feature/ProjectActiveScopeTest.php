<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActiveScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_excludes_ended_project_with_stale_active_status(): void
    {
        // 진행 중에 생성되어 status 컬럼이 'active'로 굳은 뒤, 종료일만 과거로 지난 행사.
        // 자동 비활성화가 컬럼을 갱신하지 않으므로 status='active'가 stale하게 남는다(실제 버그 상황).
        $ended = Project::factory()->create([
            'is_active' => true,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(3),
        ]);
        $this->assertSame('active', $ended->status);
        $ended->update(['end_date' => now()->subDay()]); // 종료됐지만 status는 그대로 'active'

        $ids = Project::active()->pluck('id');

        $this->assertNotContains($ended->id, $ids, '종료된 행사는 active 스코프에서 제외돼야 한다');
    }

    public function test_active_scope_includes_current_and_excludes_future(): void
    {
        $current = Project::factory()->create([
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(5),
        ]);
        $future = Project::factory()->create([
            'is_active' => true,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(9),
        ]);

        $ids = Project::active()->pluck('id');

        $this->assertContains($current->id, $ids);
        $this->assertNotContains($future->id, $ids, '시작 전 행사는 active 스코프에서 제외돼야 한다');
    }
}
