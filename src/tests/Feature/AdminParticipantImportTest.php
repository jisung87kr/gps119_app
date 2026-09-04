<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\User;
use App\Services\EventParticipantService;
use App\Services\ParticipantImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * 행사 «운영진» 사전명단 CSV 일괄 등록.
 *
 * 운영 흐름(2026-08-12 확정):
 *   ① 프로젝트 생성 → ②-1 관리자가 운영진 명단 업로드
 *                  → ②-2 참가자·운영진 모두 «같은 입장 QR» 로 입장
 *                        명단에 있으면 그 역할, 없으면 일반 참가자
 *
 * 🔴 이 파일이 지키는 가장 중요한 계약: **임포트는 계정을 만들지 않는다.**
 *    예전 구현은 회원을 생성했는데, 그 사람은 임의 비밀번호라 로그인할 수 없고
 *    재설정은 이메일 기반이라 못 쓰며, 전화번호가 점유돼 «본인이 회원가입도 못 했다».
 *    명단은 들어가는데 사람은 못 들어오는 상태였고, 100명을 올린 뒤 현장에서 발견하면
 *    되돌릴 방법이 없다.
 */
class AdminParticipantImportTest extends TestCase
{
    use RefreshDatabase;

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

    private function project(): Project
    {
        return Project::factory()->create([
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function upload(Project $project, string $csv, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin())
            ->post(route('admin.projects.participants.import', $project->id), [
                'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
            ]);
    }

    private function join(Project $project, User $user): EventParticipant
    {
        return app(EventParticipantService::class)->joinByCode($project->join_code, $user);
    }

    // ── 명단 등록 ─────────────────────────────────────────────

    public function test_import_registers_a_roster_and_creates_no_accounts(): void
    {
        $project = $this->project();
        // 관리자 계정도 회원이므로 «업로드 이전»에 만들어 두고 센다.
        $admin = $this->admin();
        $before = User::count();

        $this->upload($project, <<<'CSV'
        이름,전화번호,역할
        홍길동,010-1111-2222,구급대
        김운영,010-3333-4444,운영진
        CSV, $admin)->assertRedirect();

        $report = session('importReport');
        $this->assertSame(2, $report['total']);
        $this->assertSame(2, $report['success']);
        $this->assertSame(0, $report['joined'], '아직 회원이 아니므로 지금 배정될 사람은 없다');
        $this->assertSame(2, $report['pending']);
        $this->assertSame(0, $report['failed']);

        // 🔴 계정을 만들지 않는다.
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('event_participants', 0);

        // 전화번호는 숫자만으로 저장된다(User::setPhoneAttribute 와 같은 규칙).
        $this->assertDatabaseHas('event_rosters', [
            'project_id' => $project->id,
            'phone' => '01011112222',
            'name' => '홍길동',
            'role' => EventRole::PARAMEDIC->value,
            'claimed_at' => null,
        ]);
    }

    public function test_an_existing_member_gets_the_role_immediately(): void
    {
        $project = $this->project();
        $member = User::factory()->create(['name' => '기존회원', 'phone' => '01012345678']);

        $this->upload($project, "이름,전화번호,역할\n다른이름,010-1234-5678,구급대\n")->assertRedirect();

        $report = session('importReport');
        $this->assertSame(1, $report['joined']);
        $this->assertSame(0, $report['pending']);

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => EventRole::PARAMEDIC->value,
            'status' => ParticipantStatus::ACTIVE->value,
        ]);

        // 명단은 그 자리에서 소진된다.
        $this->assertNotNull(EventRoster::findByPhone($project->id, '01012345678')->claimed_at);
        // 기존 회원의 이름은 CSV 로 덮지 않는다 — 본인이 정한 값이 우선.
        $this->assertSame('기존회원', $member->fresh()->name);
        $this->assertSame(1, User::where('phone', '01012345678')->count());
    }

    /**
     * 🔑 하이픈이 있는 번호가 «같은 사람»으로 매칭돼야 한다. 정규화 없이 원문으로
     *    조회하면 못 찾고, 그 운영진은 조용히 명단에만 남는다.
     */
    public function test_hyphenated_phone_matches_an_existing_member(): void
    {
        $project = $this->project();
        $member = User::factory()->create(['phone' => '01012345678']);

        $this->upload($project, "010-1234-5678 은 하이픈 있음\n김구급,010-1234-5678,구급대\n")->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'user_id' => $member->id,
            'role' => EventRole::PARAMEDIC->value,
        ]);
    }

    public function test_accepts_korean_labels_and_enum_values(): void
    {
        $project = $this->project();

        $this->upload($project, <<<'CSV'
        가,01000000001,구급대
        나,01000000002,paramedic
        다,01000000003,자원봉사자(코스)
        라,01000000004,controller
        마,01000000005,
        CSV)->assertRedirect();

        $roles = EventRoster::forProject($project->id)->pluck('role', 'phone');
        $this->assertSame(EventRole::PARAMEDIC, $roles['01000000001']);
        $this->assertSame(EventRole::PARAMEDIC, $roles['01000000002']);
        $this->assertSame(EventRole::VOLUNTEER_COURSE, $roles['01000000003']);
        $this->assertSame(EventRole::CONTROLLER, $roles['01000000004']);
        // 역할 열을 비운 명단이 흔하다. 실패로 처리하면 리포트가 노이즈가 된다.
        $this->assertSame(EventRole::PARTICIPANT, $roles['01000000005']);
    }

    /**
     * 상황실은 전원의 실시간 위치와 신고자 연락처를 보는 자리다. 엑셀 부여를 허용하기로
     * 했으므로(2026-08-12 결정), 최소한 «몇 명에게 줬는지»는 결과에서 보여야 한다 —
     * 붙여넣기 사고는 단서가 없으면 영영 발견되지 않는다.
     */
    public function test_the_report_surfaces_how_many_got_control_room_access(): void
    {
        $project = $this->project();

        $this->upload($project, "가,01000000001,상황실\n나,01000000002,controller\n다,01000000003,참가자\n")
            ->assertRedirect();

        $this->assertSame(2, session('importReport')['controllers']);
    }

    // ── 파싱 ─────────────────────────────────────────────────

    public function test_works_without_a_header_row(): void
    {
        $project = $this->project();

        $this->upload($project, "홍길동,01011112222,구급대\n")->assertRedirect();

        $this->assertSame(1, session('importReport')['total']);
        $this->assertDatabaseCount('event_rosters', 1);
    }

    public function test_a_header_row_is_skipped_and_not_counted(): void
    {
        $project = $this->project();

        $this->upload($project, "이름,전화번호,역할\n홍길동,01011112222,구급대\n")->assertRedirect();

        $this->assertSame(1, session('importReport')['total']);
    }

    public function test_a_utf8_bom_is_stripped(): void
    {
        $project = $this->project();

        // 엑셀의 「CSV UTF-8」이 붙이는 BOM. 그대로 읽으면 첫 열 이름이 깨진다.
        $this->upload($project, "\u{FEFF}이름,전화번호,역할\n홍길동,01011112222,구급대\n")->assertRedirect();

        $this->assertSame(1, session('importReport')['total']);
        $this->assertDatabaseHas('event_rosters', ['name' => '홍길동']);
    }

    /**
     * 🔴 2026-09-04 실제 사고: 엑셀 「텍스트(탭으로 분리)」로 저장한 뒤 확장자만 .csv 로 바꾼
     *    161행 명단이 «전화번호가 비어 있습니다» 로 전부 실패했다. 탭 파일을 쉼표로 읽으면
     *    한 줄이 통째로 «이름» 셀이 되고 전화번호 열은 비기 때문이다.
     *    그 파일의 모양(CP949 + 탭 + CRLF + 제목행)을 그대로 재현한다.
     */
    public function test_a_tab_delimited_cp949_file_is_parsed_like_a_csv(): void
    {
        $project = $this->project();

        $tsv = mb_convert_encoding(
            "이름\t전화번호\t역할\r\n김경숙\t010-5027-1259\t운영진\r\n박코스\t010 9541 7049\t자원봉사자(코스)\r\n",
            'CP949',
            'UTF-8',
        );

        $this->upload($project, $tsv)->assertRedirect();

        $report = session('importReport');
        $this->assertSame(2, $report['total']);
        $this->assertSame(2, $report['success'], json_encode($report['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseHas('event_rosters', ['name' => '김경숙', 'phone' => '01050271259', 'role' => EventRole::STAFF->value]);
        $this->assertDatabaseHas('event_rosters', ['name' => '박코스', 'phone' => '01095417049', 'role' => EventRole::VOLUNTEER_COURSE->value]);
    }

    public function test_a_semicolon_delimited_file_is_accepted(): void
    {
        $project = $this->project();

        $this->upload($project, "이름;전화번호;역할\n홍길동;01011112222;구급대\n")->assertRedirect();

        $this->assertSame(1, session('importReport')['success']);
        $this->assertDatabaseHas('event_rosters', ['name' => '홍길동', 'phone' => '01011112222']);
    }

    /** 엑셀 「유니코드 텍스트」 — BOM 붙은 UTF-16LE + 탭. 같은 저장 메뉴에서 나오는 쌍둥이 함정. */
    public function test_an_excel_unicode_text_export_is_accepted(): void
    {
        $project = $this->project();

        $utf16 = "\xFF\xFE".mb_convert_encoding("이름\t전화번호\t역할\r\n홍길동\t01011112222\t구급대\r\n", 'UTF-16LE', 'UTF-8');

        $this->upload($project, $utf16)->assertRedirect();

        $this->assertSame(1, session('importReport')['success']);
        $this->assertDatabaseHas('event_rosters', ['name' => '홍길동', 'phone' => '01011112222']);
    }

    public function test_delimiter_detection_picks_the_most_frequent_separator(): void
    {
        $this->assertSame(',', ParticipantImportService::detectDelimiter("이름,전화번호,역할\n"));
        $this->assertSame("\t", ParticipantImportService::detectDelimiter("\n\n이름\t전화번호\t역할\n"));
        $this->assertSame(';', ParticipantImportService::detectDelimiter("홍길동;01011112222;구급대\n"));
        // 구분자가 없으면 쉼표 — 한 열짜리 파일은 행 단위 «전화번호 없음» 으로 보고된다.
        $this->assertSame(',', ParticipantImportService::detectDelimiter("홍길동\n"));
    }

    /**
     * 🔑 한 행이 틀렸다고 전체를 롤백하지 않는다 — 100명 중 1명 오타로 99명이 안 들어가면
     *    현장에서 못 쓴다.
     */
    public function test_bad_rows_are_reported_and_the_rest_succeed(): void
    {
        $project = $this->project();

        $this->upload($project, <<<'CSV'
        이름,전화번호,역할
        홍길동,01011112222,구급대
        ,01033334444,운영진
        김번호,없음,참가자
        박역할,01055556666,대장
        정상,01077778888,경찰
        CSV)->assertRedirect();

        $report = session('importReport');
        $this->assertSame(5, $report['total']);
        $this->assertSame(2, $report['success']);
        $this->assertSame(3, $report['failed']);
        $this->assertCount(3, $report['errors']);

        // 행 번호는 «파일의 물리 행»이라 엑셀에서 그 줄을 바로 찾을 수 있어야 한다.
        $this->assertSame([3, 4, 5], array_column($report['errors'], 'line'));
        $this->assertDatabaseCount('event_rosters', 2);
    }

    // ── 멱등 ─────────────────────────────────────────────────

    public function test_uploading_the_same_file_twice_is_idempotent(): void
    {
        $project = $this->project();
        $csv = "이름,전화번호,역할\n홍길동,01011112222,구급대\n";

        $this->upload($project, $csv)->assertRedirect();
        $this->upload($project, $csv)->assertRedirect();

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertSame(0, session('importReport')['failed']);
    }

    public function test_reupload_updates_the_role_to_the_latest_value(): void
    {
        $project = $this->project();
        $member = User::factory()->create(['phone' => '01011112222']);

        $this->upload($project, "홍길동,01011112222,참가자\n")->assertRedirect();
        $this->upload($project, "홍길동,01011112222,구급대\n")->assertRedirect();

        $this->assertSame(EventRole::PARAMEDIC, EventRoster::findByPhone($project->id, '01011112222')->role);
        $this->assertDatabaseHas('event_participants', [
            'user_id' => $member->id,
            'role' => EventRole::PARAMEDIC->value,
        ]);
    }

    // ── 상한 · 거부 ──────────────────────────────────────────

    public function test_rejects_a_file_over_the_row_limit(): void
    {
        $project = $this->project();
        $rows = collect(range(1, ParticipantImportService::MAX_ROWS + 1))
            ->map(fn ($i) => "사람{$i},010".str_pad((string) $i, 8, '0', STR_PAD_LEFT).',참가자')
            ->implode("\n");

        // 조용히 잘라내지 않는다.
        $this->upload($project, $rows)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('event_rosters', 0);
    }

    public function test_rejects_an_empty_file(): void
    {
        $this->upload($this->project(), "\n\n")->assertSessionHasErrors('file');
    }

    public function test_non_admin_cannot_import(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->upload($this->project(), "홍길동,01011112222,구급대\n", $user)->assertForbidden();
    }

    public function test_the_template_download_has_a_bom_and_korean_headers(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.projects.participants.template', $this->project()->id));

        $response->assertOk();
        // BOM 이 없으면 엑셀이 한글 헤더를 깨뜨린다.
        $this->assertStringStartsWith("\u{FEFF}", $response->getContent());
        $this->assertStringContainsString('전화번호', $response->getContent());
    }

    // ── 입장 시 역할 매칭 (②-2) ──────────────────────────────

    public function test_joining_by_code_uses_the_roster_role(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n")->assertRedirect();

        $user = User::factory()->create(['phone' => '01099998888']);
        $participant = $this->join($project, $user);

        $this->assertSame(EventRole::PARAMEDIC, $participant->role);
    }

    public function test_someone_not_on_the_roster_joins_as_a_participant(): void
    {
        $project = $this->project();
        $user = User::factory()->create(['phone' => '01000001111']);

        $this->assertSame(EventRole::PARTICIPANT, $this->join($project, $user)->role);
    }

    public function test_joining_claims_the_roster_row(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n")->assertRedirect();
        $this->assertSame(1, EventRoster::forProject($project->id)->unclaimed()->count());

        $user = User::factory()->create(['phone' => '01099998888']);
        $this->join($project, $user);

        $roster = EventRoster::findByPhone($project->id, '01099998888');
        $this->assertSame($user->id, $roster->user_id);
        $this->assertNotNull($roster->claimed_at);
        $this->assertSame(0, EventRoster::forProject($project->id)->unclaimed()->count());
    }

    /**
     * 🔑 전화번호 오타는 «막을 수 없다» — 본인이 입력한 번호와 명단 번호가 같아야 하므로.
     *    막는 대신 «남는다»: 그 사람은 참가자로 들어오고 명단 줄은 미입장으로 남아,
     *    관리자가 행사 시작 전에 발견할 수 있다.
     */
    public function test_a_typo_makes_the_operator_a_participant_and_leaves_the_roster_pending(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n")->assertRedirect();

        // 명단은 ...8888, 본인은 ...8889 로 가입했다.
        $user = User::factory()->create(['phone' => '01099998889']);

        $this->assertSame(EventRole::PARTICIPANT, $this->join($project, $user)->role);
        $this->assertSame(1, EventRoster::forProject($project->id)->unclaimed()->count());
    }

    public function test_rejoining_does_not_overwrite_a_manually_changed_role(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n")->assertRedirect();

        $user = User::factory()->create(['phone' => '01099998888']);
        $this->join($project, $user);

        // 관리자가 화면에서 상황실로 올렸다.
        app(EventParticipantService::class)->assignRole($project, $user, EventRole::CONTROLLER);

        // 재입장이 그것을 되돌리면 안 된다.
        $this->assertSame(EventRole::CONTROLLER, $this->join($project, $user)->role);
    }

    // ── 관리자 화면 ──────────────────────────────────────────

    public function test_the_admin_page_lists_who_has_not_joined_yet(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n박운영,01088887777,운영진\n")->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.projects.participants', $project->id))
            ->assertOk()
            ->assertSee('입장 대기 명단')
            // 관리자 화면의 연락처는 «가려서» 나간다(PhoneMaskingTest).
            // 목록은 화면공유·스크린샷에 통째로 노출되는 자리라 토글도 주지 않는다.
            ->assertSee('010-****-8888')
            ->assertSee('010-****-7777')
            ->assertDontSee('01099998888');
    }

    public function test_a_claimed_roster_row_cannot_be_deleted(): void
    {
        $project = $this->project();
        $this->upload($project, "김구급,01099998888,구급대\n")->assertRedirect();
        $user = User::factory()->create(['phone' => '01099998888']);
        $this->join($project, $user);

        $roster = EventRoster::findByPhone($project->id, '01099998888');

        // 이미 입장한 줄은 «행사 기록»이다. 지워도 참가는 남아 화면만 헷갈려진다.
        $this->actingAs($this->admin())
            ->delete(route('admin.projects.roster.destroy', [$project->id, $roster->id]))
            ->assertSessionHasErrors('roster');

        $this->assertDatabaseCount('event_rosters', 1);
    }

    public function test_a_pending_roster_row_can_be_deleted(): void
    {
        $project = $this->project();
        $this->upload($project, "오타,01000000001,구급대\n")->assertRedirect();
        $roster = EventRoster::findByPhone($project->id, '01000000001');

        $this->actingAs($this->admin())
            ->delete(route('admin.projects.roster.destroy', [$project->id, $roster->id]))
            ->assertRedirect();

        $this->assertDatabaseCount('event_rosters', 0);
    }

    // ── 입장 QR 일원화 ───────────────────────────────────────

    public function test_the_project_qr_points_at_the_join_flow_not_the_request_form(): void
    {
        $project = $this->project();

        // 신고 작성 직행이 아니라 «입장» 이어야 한다 — 직행 QR 로 들어온 사람은
        // 행사에 참가하지 않으므로 역할도 위치 공유도 관제 표시도 없다.
        $this->assertStringContainsString('/events/join/', $project->getJoinUrl());
        $this->assertStringContainsString($project->join_code, $project->getJoinUrl());
        $this->assertStringContainsString('/requests/create/', $project->getUrl());
    }

    public function test_the_qr_route_backfills_a_missing_join_code(): void
    {
        $project = $this->project();
        $project->forceFill(['join_code' => null])->save();

        // 예전 QR 은 join_code 를 아예 쓰지 않았다 — 이 백필이 도는 것 자체가
        // QR 이 입장 링크를 담게 됐다는 증거다.
        $this->actingAs($this->admin())
            ->get(route('admin.projects.qrcode', $project->id))
            ->assertOk();

        $this->assertNotNull($project->fresh()->join_code);
    }
}
