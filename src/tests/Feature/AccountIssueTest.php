<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountIssueService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 운영진 회원계정 일괄 발급 (ADR-0009).
 *
 * 🔴 이 파일이 지키는 계약:
 *   ① 발급은 «계정 없는» 명단 행에만 계정을 만든다 — 이미 회원이면 역할만 붙인다.
 *   ② 발급 계정은 첫 로그인에서 비밀번호 변경을 «강제»한다(must_change_password).
 *   ③ 재실행이 안전하다 — 발급된 행은 claim 되어 두 번째 발급에서 빠진다.
 *   ④ 초기 비밀번호는 전원 «password» 이고 DB 에는 해시만 남는다.
 */
class AccountIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function project(): Project
    {
        return Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function roster(Project $p, string $phone, EventRole $role, string $name = '홍길동'): EventRoster
    {
        return EventRoster::create([
            'project_id' => $p->id,
            'phone' => $phone,
            'name' => $name,
            'role' => $role,
        ]);
    }

    private function svc(): AccountIssueService
    {
        return app(AccountIssueService::class);
    }

    public function test_계정_없는_명단에_계정을_발급하고_역할을_붙인다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::PARAMEDIC, '김구급');

        $report = $this->svc()->issueForRoster($project, $this->admin());

        $this->assertSame(1, $report['issued']);
        $this->assertSame(0, $report['linked']);

        $user = User::where('phone', '01011112222')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->issued_at);
        $this->assertSame(EventRole::PARAMEDIC, $user->eventRoleIn($project));

        $this->assertNotNull(EventRoster::where('phone', '01011112222')->first()->claimed_at);
    }

    public function test_초기_비밀번호는_password_이고_해시로_저장된다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::STAFF);

        $this->svc()->issueForRoster($project, $this->admin());

        $user = User::where('phone', '01011112222')->firstOrFail();
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_발급된_계정은_password_로_로그인된다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::STAFF);
        $this->svc()->issueForRoster($project, $this->admin());

        $this->post('/login', ['phone' => '010-1111-2222', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_이미_회원인_행은_계정을_안_만들고_역할만_붙인다(): void
    {
        $project = $this->project();
        $existing = User::factory()->create(['phone' => '01011112222']);
        $this->roster($project, '01011112222', EventRole::POLICE);
        $admin = $this->admin();
        $before = User::count();

        $report = $this->svc()->issueForRoster($project, $admin);

        $this->assertSame(0, $report['issued']);
        $this->assertSame(1, $report['linked']);
        $this->assertSame($before, User::count());
        $this->assertSame(EventRole::POLICE, $existing->fresh()->eventRoleIn($project));
        $this->assertFalse($existing->fresh()->must_change_password);
    }

    public function test_🔴_두_번_발급해도_안전하다_두번째는_0건(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::PARAMEDIC);

        $this->svc()->issueForRoster($project, $this->admin());
        $second = $this->svc()->issueForRoster($project, $this->admin());

        $this->assertSame(0, $second['pending']);
        $this->assertSame(0, $second['issued']);
        $this->assertSame(1, User::where('phone', '01011112222')->count());
    }

    public function test_재발급은_활성화_전_계정을_password_로_되돌린다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::STAFF);
        $this->svc()->issueForRoster($project, $this->admin());

        $user = User::where('phone', '01011112222')->firstOrFail();
        $this->svc()->reissuePassword($user->fresh());

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_🔴_본인이_쓰는_계정은_재발급되지_않는다(): void
    {
        $active = User::factory()->create(['must_change_password' => false, 'issued_at' => null]);

        $this->expectException(\RuntimeException::class);
        $this->svc()->reissuePassword($active);
    }

    public function test_컨트롤러는_발급_후_요약을_flash_로_돌려준다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::PARAMEDIC, '김구급');

        $res = $this->actingAs($this->admin())
            ->post(route('admin.projects.participants.issue', $project->id));

        $res->assertRedirect();
        $res->assertSessionHas('success');
        $this->assertStringContainsString('password', session('success'));
        $this->assertDatabaseHas('users', ['phone' => '01011112222', 'must_change_password' => true]);
    }

    public function test_발급할_대상이_없으면_요약만_돌려준다(): void
    {
        $project = $this->project();

        $this->actingAs($this->admin())
            ->post(route('admin.projects.participants.issue', $project->id))
            ->assertRedirect();
    }

    public function test_비관리자는_발급할_수_없다(): void
    {
        $project = $this->project();
        $this->roster($project, '01011112222', EventRole::STAFF);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.projects.participants.issue', $project->id))
            ->assertForbidden();
    }
}
