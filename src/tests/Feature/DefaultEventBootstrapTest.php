<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0005 의 기본 행사("상시 운영")가 «신고가 한 건도 없는» 설치에서도 존재하는지.
 *
 * 실서버에서 실제로 터진 것: 빈 DB 로 배포 → 신고 0건 → 백필 마이그레이션의 조건이
 * 거짓 → 기본 행사가 아예 안 생겨 관리자 행사 목록에 보이지 않았다.
 */
class DefaultEventBootstrapTest extends TestCase
{
    use RefreshDatabase;

    /** 신규 설치: 시드만 돌려도 기본 행사가 있어야 한다 (신고 0건) */
    public function test_seeding_creates_default_event_without_any_request(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $default = Project::where('is_default', true)->first();

        $this->assertNotNull($default, '시드 후 기본 행사가 없다');
        $this->assertSame('상시 운영', $default->name);
        // 기본 행사는 «항상 활성»이어야 한다 (일반 신고의 귀속처).
        // is_active 는 getIsActiveAttribute 접근자가 원값을 그대로 돌려줘 int 1 이 나온다 — 진위만 본다.
        $this->assertTrue($default->isActive());
        $this->assertSame('active', $default->status);
        $this->assertNotNull($default->created_by);
    }

    /** 시드를 두 번 돌려도 기본 행사는 하나 (멱등) */
    public function test_seeding_twice_keeps_single_default_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(1, Project::where('is_default', true)->count());
    }

    /** 이미 떠 있는 설치: 유저는 있고 기본 행사만 없을 때, 마이그레이션이 만들어 준다 */
    public function test_migration_creates_default_event_for_existing_install(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // 기본 행사가 없던 «배포 전» 상태로 되돌린다 (deleting 훅이 막으므로 쿼리로 직접).
        Project::where('is_default', true)->forceDelete();
        $this->assertSame(0, Project::withTrashed()->where('is_default', true)->count());
        $this->assertTrue(User::query()->exists());

        // 마이그레이션은 이미 실행된 것으로 기록돼 있어 artisan migrate 가 건너뛴다.
        // 파일을 직접 불러 up() 을 돌린다 — 검증 대상은 그 안의 로직이다.
        (require database_path('migrations/2026_08_10_120000_ensure_default_event_exists.php'))->up();

        $this->assertSame(1, Project::where('is_default', true)->count());
    }
}
