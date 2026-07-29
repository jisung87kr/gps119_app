<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave 2b — 신고 생성(FE-3.1 모달) / 신고자 상태추적(FE-3.4) 페이지 렌더.
 * 모달/실시간 상호작용은 브라우저 QA.
 */
class RequestPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** 전화번호 있는 사용자 → 신고 생성 화면 200 + 주소확인 모달 마크업 */
    public function test_create_page_renders_confirm_modal(): void
    {
        $user = User::factory()->create(); // 팩토리가 phone 설정

        $this->actingAs($user)->get(route('request.create'))
            ->assertOk()
            ->assertSee('이 위치가 맞습니까?')   // FE-3.1 모달
            ->assertSee('openConfirm(', false); // 유형 버튼 와이어링
    }

    /** 신고 상세(행사 신고) → 200 + 상태추적 카드 + 담당자 정보 plumbing */
    public function test_show_page_renders_status_tracker_with_paramedic(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $requester = User::factory()->create();
        $request = RescueRequest::factory()->for($requester)->create(['project_id' => $project->id]);

        $medic = User::factory()->create(['name' => '김구급', 'phone' => '01055556666']);
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $medic->id]);
        Dispatch::factory()->create([
            'request_id' => $request->id, 'project_id' => $project->id,
            'assigned_by' => User::factory()->create()->id, 'paramedic_id' => $medic->id,
            'status' => DispatchStatus::ACCEPTED,
        ]);

        $res = $this->actingAs($requester)->get(route('request.show', $request->id));

        $res->assertOk()
            ->assertSee('내 신고 상태')          // FE-3.4 트래커
            ->assertSee('01055556666')           // activeDispatch.paramedic 전화 plumbing(@json 은 한글을 이스케이프하므로 ASCII로 검증)
            ->assertSee('projectId: '.$project->id, false); // 행사 신고 구독 plumbing
    }

    /** 일반 신고(project_id 없음)도 상세 200(트래커는 클라 v-if 로 숨김) */
    public function test_show_page_attaches_default_event_when_no_project(): void
    {
        $requester = User::factory()->create();
        $request = RescueRequest::factory()->for($requester)->create(['project_id' => null]);

        // ADR-0005: 행사 미지정 신고도 "상시 운영" 기본 행사로 귀속 → 추적 활성(그 행사 기준)
        $default = Project::where('is_default', true)->firstOrFail();

        $this->actingAs($requester)->get(route('request.show', $request->id))
            ->assertOk()
            ->assertSee("projectId: {$default->id}", false);
    }
}
