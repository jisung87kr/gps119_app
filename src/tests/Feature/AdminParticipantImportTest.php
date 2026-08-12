<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\ParticipantImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * 명단 CSV 일괄 등록 (현장 피드백: "참가자 100명을 한 명씩은 못 넣는다").
 *
 * 파서·전화번호 정규화는 순수 로직이라 자동 판정이 남아야 한다.
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

    public function test_imports_a_normal_roster(): void
    {
        $project = $this->project();

        $response = $this->upload($project, <<<'CSV'
        이름,전화번호,역할
        홍길동,010-1111-2222,구급대
        김운영,010-3333-4444,운영진
        이참가,01055556666,참가자
        CSV);

        $response->assertRedirect();

        $report = session('importReport');
        $this->assertSame(3, $report['total']);
        $this->assertSame(3, $report['success']);
        $this->assertSame(3, $report['created_users']);
        $this->assertSame(0, $report['failed']);
        $this->assertSame([], $report['errors']);

        $this->assertDatabaseCount('event_participants', 3);

        // 전화번호는 숫자만으로 저장된다(User::setPhoneAttribute 와 같은 규칙).
        $hong = User::where('phone', '01011112222')->firstOrFail();
        $this->assertSame('홍길동', $hong->name);
        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $hong->id,
            'role' => EventRole::PARAMEDIC->value,
            'status' => ParticipantStatus::ACTIVE->value,
        ]);
    }

    /**
     * 🔑 하이픈이 있는 번호가 «같은 사람»으로 매칭돼야 한다.
     * 정규화 없이 원문으로 조회하면 못 찾고 회원을 또 만들려다 DB unique 에서 터진다.
     */
    public function test_hyphenated_phone_matches_existing_member(): void
    {
        $project = $this->project();
        $existing = User::factory()->create(['name' => '기존회원', 'phone' => '01012345678']);

        $this->upload($project, "이름,전화번호,역할\n다른이름,010-1234-5678,구급대\n")
            ->assertRedirect();

        $report = session('importReport');
        $this->assertSame(1, $report['success']);
        $this->assertSame(0, $report['created_users'], '기존 회원을 새로 만들면 안 된다');

        $this->assertSame(1, User::where('phone', '01012345678')->count());
        // 기존 회원의 이름은 CSV 로 덮지 않는다.
        $this->assertSame('기존회원', $existing->fresh()->name);

        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $existing->id,
            'role' => EventRole::PARAMEDIC->value,
        ]);
    }

    public function test_accepts_korean_role_labels_and_enum_values(): void
    {
        $project = $this->project();

        $this->upload($project, <<<'CSV'
        이름,전화번호,역할
        가,01000000001,자원봉사자(코스)
        나,01000000002,volunteer_medic
        다,01000000003,상황실
        라,01000000004,
        CSV)->assertRedirect();

        $this->assertSame(4, session('importReport')['success']);

        $expected = [
            '01000000001' => EventRole::VOLUNTEER_COURSE,
            '01000000002' => EventRole::VOLUNTEER_MEDIC,
            '01000000003' => EventRole::CONTROLLER,
            // 역할 열이 비면 참가자
            '01000000004' => EventRole::PARTICIPANT,
        ];

        foreach ($expected as $phone => $role) {
            $user = User::where('phone', $phone)->firstOrFail();
            $this->assertSame(
                $role,
                EventParticipant::where('project_id', $project->id)->where('user_id', $user->id)->firstOrFail()->role,
                "{$phone} 의 역할이 다르다"
            );
        }
    }

    public function test_works_without_a_header_row(): void
    {
        $project = $this->project();

        $this->upload($project, "홍길동,01011112222,구급대\n김운영,01033334444,운영진\n")
            ->assertRedirect();

        $report = session('importReport');
        $this->assertSame(2, $report['total'], '헤더가 없으면 첫 줄도 데이터다');
        $this->assertSame(2, $report['success']);
    }

    public function test_header_row_is_skipped_and_not_counted(): void
    {
        $project = $this->project();

        $this->upload($project, "이름,전화번호,역할\n홍길동,01011112222,구급대\n")
            ->assertRedirect();

        $this->assertSame(1, session('importReport')['total']);
        $this->assertNull(User::where('name', '이름')->first());
    }

    public function test_utf8_bom_is_stripped(): void
    {
        $project = $this->project();
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);

        $this->upload($project, $bom."이름,전화번호,역할\n홍길동,01011112222,구급대\n")
            ->assertRedirect();

        $this->assertSame(1, session('importReport')['success']);
        $this->assertSame('홍길동', User::where('phone', '01011112222')->firstOrFail()->name);
    }

    /**
     * 부분 실패: 한 행이 틀렸다고 전체를 롤백하지 않는다.
     * 실패는 «행 번호 + 사유»로 리포트에 모인다.
     */
    public function test_bad_rows_are_reported_and_the_rest_succeed(): void
    {
        $project = $this->project();

        $this->upload($project, <<<'CSV'
        이름,전화번호,역할
        정상1,01011112222,구급대
        역할오타,01022223333,구급대원
        번호없음,,참가자
        ,01044445555,참가자
        번호이상,12,참가자
        정상2,01066667777,운영진
        CSV)->assertRedirect();

        $report = session('importReport');
        $this->assertSame(6, $report['total']);
        $this->assertSame(2, $report['success']);
        $this->assertSame(4, $report['failed']);

        // 성공한 2명은 실제로 들어갔다 (전체 롤백 아님)
        $this->assertDatabaseCount('event_participants', 2);
        $this->assertNotNull(User::where('phone', '01011112222')->first());
        $this->assertNotNull(User::where('phone', '01066667777')->first());

        // 실패한 행의 회원은 만들어지지 않았다 (행 안에서는 원자적)
        $this->assertNull(User::where('phone', '01022223333')->first());
        $this->assertNull(User::where('phone', '01044445555')->first());

        // 물리 행 번호(헤더 포함, 1부터) — 엑셀에서 그 줄을 바로 찾을 수 있어야 한다
        $byLine = collect($report['errors'])->keyBy('line');
        $this->assertStringContainsString('구급대원', $byLine[3]['reason']);
        $this->assertStringContainsString('전화번호', $byLine[4]['reason']);
        $this->assertStringContainsString('이름', $byLine[5]['reason']);
        $this->assertStringContainsString('전화번호', $byLine[6]['reason']);
    }

    /** 같은 파일을 두 번 올려도 결과가 같다 (설계 원칙: 멱등). */
    public function test_uploading_the_same_file_twice_is_idempotent(): void
    {
        $project = $this->project();
        $csv = "이름,전화번호,역할\n홍길동,010-1111-2222,구급대\n김운영,01033334444,운영진\n";

        $this->upload($project, $csv)->assertRedirect();
        $this->assertSame(2, session('importReport')['created_users']);

        $this->upload($project, $csv)->assertRedirect();
        $second = session('importReport');

        $this->assertSame(2, $second['success']);
        $this->assertSame(0, $second['created_users'], '두 번째에는 회원을 새로 만들지 않는다');
        $this->assertSame(0, $second['failed']);

        $this->assertDatabaseCount('event_participants', 2);
        $this->assertSame(2, User::whereIn('phone', ['01011112222', '01033334444'])->count());
    }

    /** 역할이 바뀐 명단을 다시 올리면 최신값으로 갱신된다. */
    public function test_reupload_updates_role_to_latest_value(): void
    {
        $project = $this->project();

        $this->upload($project, "홍길동,01011112222,참가자\n")->assertRedirect();
        $this->upload($project, "홍길동,01011112222,구급대\n")->assertRedirect();

        $user = User::where('phone', '01011112222')->firstOrFail();
        $this->assertDatabaseCount('event_participants', 1);
        $this->assertDatabaseHas('event_participants', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => EventRole::PARAMEDIC->value,
        ]);
    }

    /** 상한 초과는 조용히 잘라내지 않고 전량 거부한다. */
    public function test_rejects_a_file_over_the_row_limit(): void
    {
        $project = $this->project();

        $lines = ['이름,전화번호,역할'];
        for ($i = 1; $i <= ParticipantImportService::MAX_ROWS + 1; $i++) {
            $lines[] = sprintf('참가자%d,010%08d,참가자', $i, $i);
        }

        $this->upload($project, implode("\n", $lines))
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('event_participants', 0);
        $this->assertNull(session('importReport'));
    }

    public function test_rejects_an_empty_file(): void
    {
        $project = $this->project();

        $this->upload($project, "이름,전화번호,역할\n\n")
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('event_participants', 0);
    }

    public function test_template_download_has_utf8_bom_and_korean_header(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.projects.participants.template', $this->project()->id));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $body, '엑셀이 한글을 깨뜨리지 않으려면 BOM 이 필요하다');
        $this->assertStringContainsString('이름,전화번호,역할', $body);
    }

    /** 리포트가 실제로 «화면에» 나온다 (블레이드 렌더 포함). */
    public function test_report_is_rendered_on_the_participants_page(): void
    {
        $project = $this->project();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.projects.participants.import', $project->id), [
                'file' => UploadedFile::fake()->createWithContent(
                    'list.csv',
                    "이름,전화번호,역할\n홍길동,01011112222,구급대\n역할오타,01022223333,구급대원\n"
                ),
            ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.projects.participants', $project->id))
            ->assertOk()
            ->assertSee('명단 일괄 등록')
            ->assertSee('양식 다운로드')
            ->assertSee('3행')                        // 실패한 물리 행 번호
            ->assertSee('알 수 없는 역할입니다: 구급대원');
    }

    public function test_non_admin_cannot_import(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->upload($project, "홍길동,01011112222,구급대\n", $user)
            ->assertForbidden();

        $this->assertDatabaseCount('event_participants', 0);
    }
}
