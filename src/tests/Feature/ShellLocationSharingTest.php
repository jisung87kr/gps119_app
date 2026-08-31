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
 * 위치 공유를 «셸(레이아웃) 레벨»로 올린 것 (현장 피드백 #7).
 *
 * 예전에는 활동/지령 화면 안에서만 송신했다. 그 화면을 떠나면 watchPosition 이 죽고,
 * 관제 지도의 그 사람 좌표는 거기서 얼어붙는다 — 일반 참가자는 아예 「행사 입장 시각의
 * 좌표 1건」으로 남아 있었다(그 화면이 3초 뒤 강제로 신고화면으로 보냈기 때문에).
 *
 * 🔑 셸은 «본인이 켠» 공유만 이어받는다. 셸이 플래그를 켜면 사용자가 끈 공유가
 *    화면을 옮기는 것만으로 되살아난다 — 위치 동의를 코드가 대신 하는 셈이다.
 */
class ShellLocationSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function participant(array $attrs = []): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $user = User::factory()->create();

        EventParticipant::factory()->create(array_merge([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => EventRole::PARTICIPANT,
            'status' => ParticipantStatus::ACTIVE,
            'sharing_location' => true,
        ], $attrs));

        return [$user, $project];
    }

    public function test_a_sharing_participant_is_found(): void
    {
        [$user, $project] = $this->participant();

        $this->assertSame($project->id, $user->sharingParticipation()?->project_id);
    }

    public function test_sharing_off_means_no_shell_sender(): void
    {
        [$user] = $this->participant(['sharing_location' => false]);

        $this->assertNull($user->sharingParticipation());
    }

    public function test_a_participant_who_left_is_not_tracked(): void
    {
        [$user] = $this->participant(['status' => ParticipantStatus::LEFT]);

        $this->assertNull($user->sharingParticipation());
    }

    public function test_a_finished_event_stops_tracking(): void
    {
        [$user, $project] = $this->participant();
        // 행사가 끝났는데 계속 위치를 보내면 그건 서비스가 아니라 추적이다.
        $project->forceFill([
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(3),
        ])->save();

        $this->assertNull($user->fresh()->sharingParticipation());
    }

    // ── 레이아웃 주입 ────────────────────────────────────────

    public function test_the_shell_keeps_sending_on_an_ordinary_page(): void
    {
        [$user] = $this->participant();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('locationShare.js')
            ->assertSee('window.__shellLocationShare', false);
    }

    public function test_the_shell_stays_out_of_the_screen_that_owns_the_sharer(): void
    {
        [$user, $project] = $this->participant();

        // 활동 화면은 자기 sharer 를 직접 띄우고 상태를 그린다. 셸이 하나 더 띄우면
        // 같은 좌표를 두 번 보낸다. (마운트 순서가 아니라 라우트로 판정한다 —
        // 순서 경합은 언젠가 반드시 진다.)
        $this->actingAs($user)->get("/events/{$project->id}/active")
            ->assertOk()
            ->assertDontSee('window.__shellLocationShare', false);
    }

    public function test_a_user_sharing_nothing_gets_no_sender(): void
    {
        [$user] = $this->participant(['sharing_location' => false]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('window.__shellLocationShare', false);
    }

    /**
     * 🔴 회귀: 활동 화면이 첫 좌표 전송 뒤 신고화면으로 «강제 이동»시켰다.
     *    그게 일반 참가자 좌표가 1건으로 동결되던 직접 원인이다.
     */
    public function test_the_active_screen_no_longer_kicks_the_participant_out(): void
    {
        [$user, $project] = $this->participant();

        $this->actingAs($user)->get("/events/{$project->id}/active")
            ->assertOk()
            ->assertDontSee('초 후 구조요청 화면으로 이동');
    }
}
