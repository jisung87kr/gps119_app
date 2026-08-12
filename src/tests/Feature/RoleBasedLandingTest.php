<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\LandingResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 역할별 화면 구성 (현장 피드백 #6) + 구조대 계정의 신고 차단 (#4).
 *
 * 🔑 이 파일이 고정하는 계약 둘:
 *    ① 착지 규칙은 «한 벌»이다. 예전에는 `/` 와 LoginResponse 가 서로 다른 규칙을 갖고
 *       있어서, 같은 사람이 도메인을 직접 치고 들어왔을 때와 로그인 폼을 거쳤을 때
 *       다른 화면을 봤다.
 *    ② `intended()` 가 항상 이긴다. QR 딥링크와 푸시 딥링크가 거기 걸려 있고, 그게
 *       밀리면 알림을 눌러도 엉뚱한 화면이 열린다. 이건 실제로 한 번 깨졌던 규칙이다.
 */
class RoleBasedLandingTest extends TestCase
{
    use RefreshDatabase;

    private LandingResolver $landing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->landing = app(LandingResolver::class);
    }

    private function inEvent(User $user, EventRole $role, ?Project $project = null): Project
    {
        $project = $project ?: Project::factory()->create(['created_by' => User::factory()->create()->id]);
        EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ParticipantStatus::ACTIVE,
        ]);

        return $project;
    }

    // ── 착지 규칙 ─────────────────────────────────────────────

    public function test_admin_lands_on_the_admin_shell(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->assertSame(route('admin.dashboard'), $this->landing->for($admin));
    }

    public function test_controller_lands_on_the_control_room(): void
    {
        $user = User::factory()->create();
        $project = $this->inEvent($user, EventRole::CONTROLLER);

        $this->assertSame(route('control', ['project' => $project->id]), $this->landing->for($user));
    }

    public function test_paramedic_lands_on_the_dispatch_screen_of_their_only_event(): void
    {
        $user = User::factory()->create();
        $project = $this->inEvent($user, EventRole::PARAMEDIC);

        $this->assertSame(route('events.dispatch', $project->id), $this->landing->for($user));
    }

    public function test_paramedic_in_two_events_lands_on_the_list_instead(): void
    {
        $user = User::factory()->create();
        $this->inEvent($user, EventRole::PARAMEDIC);
        $this->inEvent($user, EventRole::PARAMEDIC);

        // 잘못된 현장을 여는 비용이 탭 한 번보다 훨씬 크다.
        $this->assertSame(route('dispatches.index'), $this->landing->for($user));
    }

    public function test_system_rescuer_without_any_event_still_lands_on_dispatch_home(): void
    {
        $user = User::factory()->create();
        $user->assignRole('rescuer');

        // 구조대 계정은 「내가 신고한 0건」 화면을 보면 안 된다 — 그게 피드백 #4 다.
        $this->assertSame(route('dispatches.index'), $this->landing->for($user));
    }

    /**
     * 현장 요구 그대로: 운영진·경찰·자원봉사(코스)·자원봉사(구급) → 구조요청 화면.
     */
    public function test_support_roles_land_on_the_request_screen(): void
    {
        foreach ([EventRole::STAFF, EventRole::POLICE, EventRole::VOLUNTEER_COURSE, EventRole::PARTICIPANT] as $role) {
            $user = User::factory()->create();
            $this->inEvent($user, $role);

            $this->assertSame(route('request.create'), $this->landing->for($user), $role->value);
        }
    }

    public function test_volunteer_medic_lands_on_the_dispatch_screen(): void
    {
        // 배정 «후보»에서는 빠졌지만(피드백 #5) 지령 수령 «자격»은 남아 있다 —
        // 진행 중인 지령을 가진 사람이 자기 화면에 못 들어가면 그 지령이 고아가 된다.
        $user = User::factory()->create();
        $project = $this->inEvent($user, EventRole::VOLUNTEER_MEDIC);

        $this->assertSame(route('events.dispatch', $project->id), $this->landing->for($user));
    }

    public function test_a_finished_event_no_longer_decides_the_landing(): void
    {
        $user = User::factory()->create();
        $project = $this->inEvent($user, EventRole::PARAMEDIC);
        $project->forceFill([
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(3),
        ])->save();

        // 행사가 끝나면 구급대원도 평범한 사용자로 돌아간다.
        $this->assertSame(route('request.create'), $this->landing->for($user->fresh()));
    }

    // ── 두 진입점이 같은 규칙을 쓴다 ──────────────────────────

    public function test_the_root_path_uses_the_same_rule(): void
    {
        $user = User::factory()->create();
        $project = $this->inEvent($user, EventRole::CONTROLLER);

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('control', ['project' => $project->id]));
    }

    public function test_the_root_path_sends_guests_straight_to_login(): void
    {
        // 신고 작성으로 한 번 튕기면 auth 미들웨어가 intended 를 심고,
        // 그 intended 가 역할별 착지를 이긴다. 가장 흔한 진입 경로라 늘 밀렸다.
        $this->get('/')->assertRedirect(route('login'));
        $this->assertNull(session('url.intended'));
    }

    // ── 구조대 계정의 신고 차단 (#4) ──────────────────────────

    public function test_rescuer_cannot_open_the_request_form(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('rescuer');

        $this->actingAs($user)->get('/requests/create')
            ->assertForbidden()
            ->assertSee('구조대 계정입니다');
    }

    public function test_the_block_screen_still_offers_a_way_to_get_help(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('rescuer');

        // 🔑 막다른 길이면 안 된다. 구급대원 본인이 코스에서 쓰러지는 것은 이 도메인의
        //    실제 사고 유형이고, 그때 그 사람은 앱을 다시 배울 수 없다.
        $this->actingAs($user)->get('/requests/create')
            ->assertSee('tel:119', false)
            ->assertSee('상황실 전화');
    }

    public function test_rescuer_is_blocked_on_the_project_request_form_too(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('rescuer');
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAs($user)->get("/requests/create/{$project->slug}")
            ->assertForbidden();
    }

    public function test_an_ordinary_user_can_still_open_the_request_form(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');

        $this->actingAs($user)->get('/requests/create')->assertOk();
    }

    /**
     * 🔑 화면만 막으면 API 는 그대로 열려 있다. 이 앱의 신고는 JSON 한 번이면 만들어진다 —
     *    「기능 자체를 차단」이라는 결정을 지키려면 규칙이 서비스에 있어야 한다.
     */
    public function test_rescuer_cannot_file_through_the_api_either(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('rescuer');

        $this->actingAs($user)->postJson('/api/requests', [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
        ])->assertStatus(400);

        $this->assertSame(0, $user->requests()->count());
    }

    public function test_an_ordinary_user_can_still_file_through_the_api(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');

        $this->actingAs($user)->postJson('/api/requests', [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
        ])->assertCreated();
    }

    // ── 홈 ───────────────────────────────────────────────────

    public function test_the_dispatch_side_gets_the_dispatch_home(): void
    {
        $user = User::factory()->create();
        $user->assignRole('rescuer');

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('dispatches.index'));
        $this->actingAs($user)->get('/dispatches')
            ->assertOk()
            ->assertSee('새 지령')
            ->assertSee('누적 완료');
    }

    public function test_an_ordinary_user_keeps_the_request_based_home(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('전체 요청');
    }

    public function test_the_tab_bar_swaps_only_the_middle_slot(): void
    {
        $rescuer = User::factory()->create();
        $rescuer->assignRole('rescuer');
        $ordinary = User::factory()->create();
        $ordinary->assignRole('user');

        $this->actingAs($rescuer)->get('/dispatches')
            ->assertSee('프로필')
            ->assertDontSee('구조요청');

        $this->actingAs($ordinary)->get('/dashboard')
            ->assertSee('구조요청')
            ->assertSee('프로필');
    }
}
