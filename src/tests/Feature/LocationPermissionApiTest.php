<?php

namespace Tests\Feature;

use App\Enums\LocationPermission;
use App\Enums\TrackingState;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * OS 위치 권한 보고 경로 (M-5, ADR-0008).
 *
 * 🔴 **이 경로가 ping 과 «따로» 있어야 하는 이유를 고정한다.** 권한이 끊기면 ping 도
 *    끊긴다 — 즉 정작 알아야 할 순간에 ping 경로로는 아무 신호도 오지 않는다.
 *    그래서 공유가 꺼져 있어도 받아야 하고, 이 계약이 깨지면 M-5 가 통째로 무의미해진다.
 */
class LocationPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array{0: Project, 1: User, 2: EventParticipant} */
    private function scene(array $attrs = []): array
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $user = User::factory()->create();
        $participant = EventParticipant::factory()->create(array_merge([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'sharing_location' => true,
        ], $attrs));
        Sanctum::actingAs($user);

        return [$project, $user, $participant];
    }

    public function test_권한을_보고하면_기록되고_파생상태가_돌아온다(): void
    {
        [$project, , $participant] = $this->scene();

        $res = $this->patchJson("/api/events/{$project->id}/location-permission", [
            'permission' => 'denied',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.location_permission', 'denied')
            ->assertJsonPath('data.tracking_state', TrackingState::BLOCKED->value);

        $participant->refresh();
        $this->assertSame(LocationPermission::DENIED, $participant->location_permission);
        $this->assertNotNull($participant->location_permission_at, '언제 본 상태인지 모르면 낡은 값을 현재로 읽는다');
    }

    public function test_🔴_공유가_꺼져_있어도_받는다(): void
    {
        // 권한이 끊기면 ping 도 끊긴다. 공유 여부를 조건으로 걸면 정작 알아야 할
        // 상태를 영영 못 받는다 — 이 경로가 따로 존재하는 이유가 사라진다.
        [$project, , $participant] = $this->scene(['sharing_location' => false]);

        $this->patchJson("/api/events/{$project->id}/location-permission", [
            'permission' => 'always',
        ])->assertOk();

        $this->assertSame(LocationPermission::ALWAYS, $participant->refresh()->location_permission);
    }

    public function test_🔑_권한_보고가_사용자의_공유_켬을_끄지_않는다(): void
    {
        // 의도와 능력은 다른 축이다. 권한이 없다고 서버가 사용자의 «켬»을 꺼버리면,
        // 권한을 되돌렸을 때 왜 안 되는지 아무도 모른다.
        [$project, , $participant] = $this->scene();

        $this->patchJson("/api/events/{$project->id}/location-permission", [
            'permission' => 'denied',
        ])->assertOk();

        $this->assertTrue($participant->refresh()->sharing_location);
    }

    public function test_모르는_값은_422_다(): void
    {
        [$project] = $this->scene();

        $this->patchJson("/api/events/{$project->id}/location-permission", [
            'permission' => 'sorta_maybe',
        ])->assertStatus(422);
    }

    public function test_행사_참가자가_아니면_거부된다(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/events/{$project->id}/location-permission", [
            'permission' => 'always',
        ])->assertForbidden();
    }

    public function test_관제_roster_가_권한없음을_구분해_내려준다(): void
    {
        // roster 는 관제 초기 로드의 유일한 경로다. 여기 안 실리면 화면이 알 방법이 없다.
        [$project, , $participant] = $this->scene(['last_seen_at' => now()]);
        $participant->update(['location_permission' => LocationPermission::DENIED]);

        $roster = app(\App\Services\EventParticipantService::class)->rosterForControl($project);

        $this->assertSame(TrackingState::BLOCKED->value, $roster[0]['tracking_state']);
        $this->assertTrue($roster[0]['sharing_location']);
        // online 은 그대로 살아 있다 — 「최근에 뭔가 왔다」와 「지금 추적 가능한가」는 다른 질문이다.
        $this->assertTrue($roster[0]['online']);
    }
}
