<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 참가자 페이지 — 2026-09-07 현장 피드백(field-feedback-2026-09).
 *
 *   F-08 정렬: 1순위 역할(enum 선언순), 2순위 이름 가나다. `orderBy('role')` 은 영문 값
 *        알파벳순(controller, paramedic, participant …)이라 뜻과 달랐다.
 *   F-06 검색: 「참가자 추가」가 회원 «전체» 셀렉트였다 — 수백 명이면 못 찾는다. 이름 검색
 *        피커로 바꾸되, 전화번호는 뒤 4자리만 실린다(PhoneMaskingTest 와 같은 규칙).
 */
class AdminParticipantPageTest extends TestCase
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
        return Project::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    private function join(Project $project, string $name, EventRole $role): User
    {
        $user = User::factory()->create(['name' => $name]);
        EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        return $user;
    }

    public function test_participants_are_listed_by_role_declaration_order_then_name(): void
    {
        $project = $this->project();

        // 일부러 뒤섞어 넣는다 — 입장 순서가 정렬을 결정하면 안 된다.
        $this->join($project, '홍길동', EventRole::PARAMEDIC);
        $this->join($project, '김경숙', EventRole::STAFF);
        $this->join($project, '가나다', EventRole::PARTICIPANT);
        $this->join($project, '강나래', EventRole::PARAMEDIC);
        $this->join($project, '박회송', EventRole::TRANSPORT);
        $this->join($project, '최상황', EventRole::CONTROLLER);

        $this->actingAs($this->admin())
            ->get("/admin/projects/{$project->id}/participants")
            ->assertOk()
            ->assertSeeInOrder(['가나다', '김경숙', '강나래', '홍길동', '박회송', '최상황']);
    }

    public function test_the_role_order_sql_follows_enum_declaration(): void
    {
        $sql = EventRole::orderByDeclarationSql('role');

        $this->assertStringStartsWith('CASE role WHEN ', $sql);
        // 선언 순서대로 0,1,2… 가 붙고, 모르는 값은 맨 뒤(ELSE = 개수).
        $this->assertStringContainsString("WHEN 'participant' THEN 0", $sql);
        $this->assertStringContainsString("WHEN 'controller' THEN ".(count(EventRole::cases()) - 1), $sql);
        $this->assertStringEndsWith('ELSE '.count(EventRole::cases()).' END', $sql);
    }

    public function test_add_participant_picker_lists_only_members_not_yet_in_the_event(): void
    {
        $project = $this->project();
        $this->join($project, '이미참가', EventRole::PARTICIPANT);
        User::factory()->create(['name' => '이지형', 'phone' => '01098765432']);

        $html = $this->actingAs($this->admin())
            ->get("/admin/projects/{$project->id}/participants")
            ->assertOk()
            ->getContent();

        // 피커 데이터: 이름 + 뒤 4자리만. 전체 번호는 실리지 않는다(ADR-0004 / PhoneMaskingTest 와 같은 규칙).
        $this->assertStringContainsString('***5432', $html);
        $this->assertStringNotContainsString('01098765432', $html);
        $this->assertStringContainsString('이지형', $html);

        // 이미 참가 중인 사람은 피커 후보에 없다 — 목록에는 있으므로 «피커 라벨» 형태로 검사한다.
        $this->assertStringNotContainsString('이미참가 · ', html_entity_decode($html));
    }
}
