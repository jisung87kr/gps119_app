<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 화면의 연락처 마스킹 (2026-08-12).
 *
 * 관리자 화면은 전화번호를 «목록으로» 펼쳐 놓는 자리다. 화면공유·스크린샷·어깨너머로
 * 수십 명의 연락처가 한 번에 새는 경로가 여기다. DB 컬럼 암호화는 이걸 전혀 막지
 * 못한다 — 관리자 화면은 어차피 복호화해서 보여주기 때문이다.
 *
 * ⚠️ 이건 «우발적 노출»을 줄이는 장치이지 접근통제가 아니다. reveal 을 켠 상세 화면은
 *    원문이 DOM 에 들어가므로 개발자도구로는 보인다. 그래서 목록은 «토글 없이» 가리고,
 *    상세에서만 열 수 있게 한다.
 */
class PhoneMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const RAW = '01098765432';

    private const MASKED = '010-****-5432';

    private const FULL = '010-9876-5432';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    // ── 목록 화면: 원문이 아예 안 나간다 ──────────────────────

    public function test_the_member_list_never_ships_the_full_number(): void
    {
        User::factory()->create(['name' => '홍길동', 'phone' => self::RAW]);

        $this->actingAs($this->admin())->get('/admin/members')
            ->assertOk()
            ->assertSee(self::MASKED)
            ->assertDontSee(self::FULL);
    }

    public function test_the_participant_list_and_pending_roster_are_masked(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $member = User::factory()->create(['phone' => self::RAW]);
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $member->id,
            'role' => EventRole::PARAMEDIC, 'status' => ParticipantStatus::ACTIVE,
        ]);
        EventRoster::create([
            'project_id' => $project->id, 'phone' => '01055556666', 'name' => '김운영',
            'role' => EventRole::STAFF,
        ]);

        $this->actingAs($this->admin())->get("/admin/projects/{$project->id}/participants")
            ->assertOk()
            ->assertSee(self::MASKED)
            ->assertDontSee(self::FULL)
            ->assertSee('010-****-6666')
            ->assertDontSee('01055556666');
    }

    /**
     * 「참가자 추가」 셀렉트는 회원 «전체»가 들어가는 자리다. 여기가 가장 크게 샌다.
     */
    public function test_the_add_participant_select_only_shows_the_last_four(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        User::factory()->create(['name' => '홍길동', 'phone' => self::RAW]);

        $this->actingAs($this->admin())->get("/admin/projects/{$project->id}/participants")
            ->assertSee('***5432')
            ->assertDontSee(self::RAW);
    }

    // ── 상세 화면: 가린 채로 열리고, 열어볼 수 있다 ───────────

    public function test_the_member_detail_opens_masked_with_a_reveal_control(): void
    {
        $member = User::factory()->create(['phone' => self::RAW]);

        $this->actingAs($this->admin())->get("/admin/members/{$member->id}")
            ->assertOk()
            ->assertSee(self::MASKED)
            ->assertSee('보기');
    }

    public function test_the_request_detail_keeps_the_tel_link_working(): void
    {
        $requester = User::factory()->create(['phone' => self::RAW]);
        $request = RescueRequest::factory()->for($requester)->create();

        // 🔑 구조 현장에서 필요한 건 번호를 «읽는» 게 아니라 «거는» 것이다.
        //    가려도 전화는 걸려야 한다.
        $this->actingAs($this->admin())->get("/admin/requests/{$request->id}")
            ->assertOk()
            ->assertSee(self::MASKED)
            ->assertSee('tel:'.self::RAW, false);
    }

    // ── 마스킹하면 안 되는 곳 ────────────────────────────────

    public function test_the_csv_export_still_carries_full_numbers(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $requester = User::factory()->create(['phone' => self::RAW]);
        RescueRequest::factory()->for($requester)->create(['project_id' => $project->id]);

        // 리포트는 사후 기록·정산용이라 가리면 쓸모가 없다. 파일 자체를 보호해야 하는
        // 대상이지, 내용을 가려서 해결할 문제가 아니다.
        $csv = $this->actingAs($this->admin())
            ->get("/admin/projects/{$project->id}/export-csv")
            ->streamedContent();

        $this->assertStringContainsString(self::RAW, $csv);
    }

    // ── 마스킹 규칙 자체 ─────────────────────────────────────

    public function test_short_or_missing_numbers_do_not_leak(): void
    {
        $view = fn ($v) => (string) view('components.ui.phone', ['value' => $v, 'reveal' => false, 'tel' => false])->render();

        $this->assertStringContainsString('-', $view(null));          // '-' 로 표시
        $this->assertStringNotContainsString('12345', $view('12345')); // 짧으면 전부 가린다
    }
}
