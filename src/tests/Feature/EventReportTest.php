<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\LocationPing;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-4.1 — 행사 기록 다운로드(스트리밍 CSV).
 */
class EventReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function projectWithController(): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $controller = User::factory()->create();
        EventParticipant::factory()->controller()->create(['project_id' => $project->id, 'user_id' => $controller->id]);

        return [$project, $controller];
    }

    private function csv($response): string
    {
        return $response->streamedContent();
    }

    // ── requests.csv ──
    public function test_requests_csv_headers_and_row(): void
    {
        [$project, $controller] = $this->projectWithController();
        $requester = User::factory()->create(['name' => '홍길동', 'phone' => '01012345678']);
        RescueRequest::factory()->for($requester)->create([
            'project_id' => $project->id,
            'type' => RequestType::ACCIDENT,
            'status' => RequestStatus::COMPLETED,
            'address' => '서울시 중구',
            'requested_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($controller);
        $res = $this->get("/api/events/{$project->id}/report/requests.csv");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $this->csv($res);

        // BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        // 헤더 라벨(한글) + 데이터(라벨/처리시간/연락처)
        $this->assertStringContainsString('처리시간(분)', $body);
        $this->assertStringContainsString('사고', $body);          // RequestType label
        $this->assertStringContainsString('구조 완료', $body);     // RequestStatus label
        $this->assertStringContainsString('01012345678', $body);
        $this->assertStringContainsString('30', $body);            // 처리시간 30분
    }

    public function test_requests_csv_empty_has_header_only(): void
    {
        [$project, $controller] = $this->projectWithController();
        Sanctum::actingAs($controller);

        $body = $this->csv($this->get("/api/events/{$project->id}/report/requests.csv"));
        $lines = array_values(array_filter(explode("\n", trim($body))));
        $this->assertCount(1, $lines); // 헤더 1행만
        $this->assertStringContainsString('ID', $body);
    }

    // ── dispatches.csv ──
    public function test_dispatches_csv_timeline(): void
    {
        [$project, $controller] = $this->projectWithController();
        $medic = User::factory()->create(['name' => '김구급']);
        $request = RescueRequest::factory()->create(['project_id' => $project->id]);
        Dispatch::factory()->create([
            'request_id' => $request->id, 'project_id' => $project->id,
            'assigned_by' => $controller->id, 'paramedic_id' => $medic->id,
            'status' => DispatchStatus::REJECTED, 'reject_reason' => '거리 과다',
            'rejected_at' => now(),
        ]);

        Sanctum::actingAs($controller);
        $res = $this->get("/api/events/{$project->id}/report/dispatches.csv");
        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $this->csv($res);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('거절사유', $body);   // 헤더
        $this->assertStringContainsString('거리 과다', $body);  // 데이터
        $this->assertStringContainsString('거절', $body);       // DispatchStatus label
    }

    // ── tracks.csv ──
    public function test_tracks_csv_streams_pings(): void
    {
        [$project, $controller] = $this->projectWithController();
        $medic = User::factory()->create(['name' => '이구급']);
        EventParticipant::factory()->paramedic()->create(['project_id' => $project->id, 'user_id' => $medic->id]);
        LocationPing::factory()->create([
            'project_id' => $project->id, 'user_id' => $medic->id,
            'latitude' => 37.5, 'longitude' => 127.0, 'accuracy' => 12,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($controller);
        $res = $this->get("/api/events/{$project->id}/report/tracks.csv");
        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $this->csv($res);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('기록시각', $body);   // 헤더
        $this->assertStringContainsString('37.5', $body);       // 데이터
        $this->assertStringContainsString('구급대', $body);     // EventRole label
    }

    // ── 가드 ──
    public function test_admin_can_download(): void
    {
        [$project] = $this->projectWithController();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->get("/api/events/{$project->id}/report/requests.csv")->assertOk();
    }

    public function test_participant_forbidden(): void
    {
        [$project] = $this->projectWithController();
        $participant = User::factory()->create();
        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $participant->id]);
        Sanctum::actingAs($participant);

        $this->get("/api/events/{$project->id}/report/requests.csv")->assertStatus(403);
        $this->get("/api/events/{$project->id}/report/dispatches.csv")->assertStatus(403);
        $this->get("/api/events/{$project->id}/report/tracks.csv")->assertStatus(403);
    }
}
