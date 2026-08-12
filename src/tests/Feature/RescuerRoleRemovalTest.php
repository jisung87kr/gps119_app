<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 시스템 롤 `rescuer` 제거와 이관 (2026-08-12).
 *
 * 회원 권한은 «일반회원 / 관리자회원» 둘뿐이다. 대응 인력의 체계가 둘이라 관리자가
 * 「이 사람 권한이 뭐지」를 두 군데서 봐야 했다. 신고는 ADR-0005 로 이미 「모든 신고는
 * 행사에 속한다」로 일원화됐으므로 사람도 행사 역할 하나로 모은다.
 *
 * 🔴 이 파일이 지키는 가장 중요한 계약은 **길가 신고 알림이 끊기지 않는다**는 것이다.
 *    예전에는 시스템 롤 rescuer 가 전역으로 통지를 받았다. 그 롤을 지우면서 이관을
 *    빠뜨리면 「상시 운영」 행사의 신고를 관리자 말고는 아무도 못 받는다 —
 *    화면은 멀쩡해 보이고, 사람이 안 온다는 걸 현장에서야 안다.
 */
class RescuerRoleRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** 이관 마이그레이션을 직접 실행한다(테스트 DB 에는 rescuer 롤이 없으므로 먼저 만든다). */
    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_08_12_120000_move_rescuers_to_default_event.php');
        $migration->up();
    }

    private function legacyRescuer(): User
    {
        Role::findOrCreate('rescuer', 'web');
        $user = User::factory()->create();
        $user->assignRole('rescuer');

        return $user;
    }

    // ── 이관 ─────────────────────────────────────────────────

    public function test_a_rescuer_becomes_a_paramedic_of_the_always_on_event(): void
    {
        $user = $this->legacyRescuer();

        $this->runMigration();

        $participant = EventParticipant::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(Project::defaultEvent()->id, $participant->project_id);
        $this->assertSame(EventRole::PARAMEDIC, $participant->role);
        $this->assertSame(ParticipantStatus::ACTIVE, $participant->status);
    }

    public function test_the_role_itself_is_gone(): void
    {
        $user = $this->legacyRescuer();

        $this->runMigration();

        $this->assertFalse($user->fresh()->hasRole('rescuer'));
        $this->assertDatabaseMissing('roles', ['name' => 'rescuer']);
    }

    public function test_an_existing_participation_is_not_overwritten(): void
    {
        $user = $this->legacyRescuer();
        // 관리자가 이미 상황실로 올려뒀을 수 있다.
        EventParticipant::factory()->create([
            'project_id' => Project::defaultEvent()->id,
            'user_id' => $user->id,
            'role' => EventRole::CONTROLLER,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        $this->runMigration();

        $this->assertSame(EventRole::CONTROLLER, EventParticipant::where('user_id', $user->id)->first()->role);
    }

    public function test_running_it_twice_is_safe(): void
    {
        $user = $this->legacyRescuer();

        $this->runMigration();
        $this->runMigration(); // 롤이 이미 없으므로 조용히 지나가야 한다

        $this->assertSame(1, EventParticipant::where('user_id', $user->id)->count());
    }

    public function test_it_is_a_no_op_when_the_role_never_existed(): void
    {
        $before = EventParticipant::count();

        $this->runMigration();

        $this->assertSame($before, EventParticipant::count());
    }

    /**
     * 🔴 순서가 중요하다. 참가 행을 «먼저» 만들고 롤을 떼야 한다. 반대로 하면 그 사이에
     *    들어온 신고의 알림 대상이 0명이 된다.
     */
    public function test_the_participation_exists_before_the_role_is_dropped(): void
    {
        $user = $this->legacyRescuer();

        $this->runMigration();

        // 롤이 사라진 시점에 참가 행이 «이미» 있어야 한다.
        $this->assertDatabaseMissing('roles', ['name' => 'rescuer']);
        $this->assertTrue(EventParticipant::where('user_id', $user->id)->exists());
    }

    // ── 이관 뒤에도 알림이 끊기지 않는가 ─────────────────────

    public function test_the_migrated_paramedic_still_gets_notified_about_roadside_requests(): void
    {
        $user = $this->legacyRescuer();
        $this->runMigration();

        // 행사를 지정하지 않은 «길가» 신고 — creating 훅이 「상시 운영」으로 귀속시킨다.
        $request = RescueRequest::factory()->for(User::factory()->create())->create(['project_id' => null]);
        $this->assertSame(Project::defaultEvent()->id, $request->project_id);

        $listener = app(\App\Listeners\NotifyRescuers::class);
        $method = (new \ReflectionObject($listener))->getMethod('recipientsFor');
        $method->setAccessible(true);
        $ids = $method->invoke($listener, $request)->pluck('id');

        $this->assertContains($user->id, $ids->all(), '이관된 구급대가 길가 신고 통지를 못 받으면 이 정리는 실패다');
    }

    // ── 남은 시스템 롤은 둘뿐 ────────────────────────────────

    public function test_the_seeder_only_creates_two_system_roles(): void
    {
        $this->assertSame(['admin', 'user'], DB::table('roles')->orderBy('name')->pluck('name')->all());
    }

    /** permission 목록은 남긴다 — 관리자별 메뉴 권한 오픈에 쓸 예정. */
    public function test_permissions_are_kept_for_future_menu_control(): void
    {
        $this->assertGreaterThan(0, DB::table('permissions')->count());
        $this->assertDatabaseHas('permissions', ['name' => 'view-all-requests']);
    }
}
