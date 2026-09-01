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

    /**
     * 시스템 롤 rescuer 는 2026-08-12 에 없앴다. 상시 구급 인력은 「상시 운영」 행사의
     * 구급대이고, 그 행사는 항상 활성이라 착지도 그 행사의 지령 화면이 된다.
     */
    public function test_a_paramedic_of_the_always_on_event_lands_on_its_dispatch_screen(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $always = Project::defaultEvent();
        $this->inEvent($user, EventRole::PARAMEDIC, $always);

        $this->assertSame(route('events.dispatch', $always->id), $this->landing->for($user));
    }

    /**
     * 현장 요구 그대로: 운영진·경찰·자원봉사(코스)·자원봉사(구급) → 구조요청 화면.
     *
     * 🔴 참가 중인 행사가 있으면 «그 행사의» 신고 화면이다. 일반 경로로 보내면 화면에
     *    행사 이름이 안 뜨고, 무엇보다 그 신고가 「상시 운영」으로 새던 버그가 여기서 시작됐다.
     */
    public function test_support_roles_land_on_their_events_request_screen(): void
    {
        foreach ([EventRole::STAFF, EventRole::POLICE, EventRole::VOLUNTEER_COURSE, EventRole::PARTICIPANT] as $role) {
            $user = User::factory()->create();
            $project = $this->inEvent($user, $role);

            $this->assertSame(
                route('request.create.project', $project->slug),
                $this->landing->for($user),
                $role->value
            );
        }
    }

    public function test_a_user_with_no_event_lands_on_the_generic_request_screen(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->assertSame(route('request.create'), $this->landing->for($user));
    }

    /**
     * 🔑 현장 요구(#6)는 「자원봉사(구급) → 구조요청 화면」이다. 처음엔 착지 판정에
     *    canReceiveDispatch()(구급대+자원봉사구급)를 써서 지령 화면으로 보냈는데,
     *    #5 로 배정 후보에서도 빠졌으니 새 지령이 갈 일이 없다. 판정을
     *    isDispatchCandidate()(구급대만)로 바로잡았다.
     *    지령 «화면 접근» 자격(canReceiveDispatch)은 진행 중 지령을 위해 남아 있다.
     */
    public function test_volunteer_medic_lands_on_the_request_screen(): void
    {
        $user = User::factory()->create();
        $this->inEvent($user, EventRole::VOLUNTEER_MEDIC);

        $this->assertStringContainsString('/requests/create/', $this->landing->for($user));
        $this->assertTrue(EventRole::VOLUNTEER_MEDIC->canReceiveDispatch());
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

    /**
     * 🔴 브라우저 QA 에서 발견: 로그인은 새 규칙을 타는데 «회원가입»만 fortify.home 을
     *    그대로 써서 혼자 /dashboard 로 떨어졌다. 문이 셋인데 둘만 고친 상태였고,
     *    이런 건 아무도 안 보는 채로 오래간다.
     */
    public function test_registration_uses_the_same_rule_as_login(): void
    {
        $this->post('/register', [
            'phone' => '010-9999-0001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // 필수 약관 동의가 없으면 가입 자체가 막힌다 (ConsentTest 참조).
            'consents' => ['privacy', 'location_terms'],
        ])->assertRedirect(route('request.create'));

        $this->assertAuthenticated();
    }

    public function test_the_root_path_sends_guests_straight_to_login(): void
    {
        // 신고 작성으로 한 번 튕기면 auth 미들웨어가 intended 를 심고,
        // 그 intended 가 역할별 착지를 이긴다. 가장 흔한 진입 경로라 늘 밀렸다.
        $this->get('/')->assertRedirect(route('login'));
        $this->assertNull(session('url.intended'));
    }

    // ── 구급대의 신고 차단 (#4) ───────────────────────────────
    //
    // 판정 기준이 시스템 롤 rescuer 에서 «지금 행사에서 구급대인가»로 바뀌었다
    // (2026-08-12, 회원 권한을 일반/관리자 둘로 정리하면서).

    /** 행사 중인 구급대를 만든다. */
    private function onDutyParamedic(): User
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $this->inEvent($user, EventRole::PARAMEDIC);

        return $user;
    }

    public function test_an_on_duty_paramedic_cannot_open_the_request_form(): void
    {
        $user = $this->onDutyParamedic();

        $this->actingAs($user)->get('/requests/create')
            ->assertForbidden()
            ->assertSee('구급대 계정입니다');
    }

    /**
     * 🔑 행사가 끝나면 그 사람도 평범한 사용자로 돌아가 신고할 수 있다.
     *    비번기에도 신고를 못 하는 건 부작용이지 의도가 아니었다.
     */
    public function test_the_same_person_can_file_once_the_event_ends(): void
    {
        $user = $this->onDutyParamedic();
        $user->eventParticipations()->first()->project
            ->forceFill(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(3)])->save();

        $this->actingAs($user)->get('/requests/create')->assertOk();
    }

    public function test_the_block_screen_still_offers_a_way_to_get_help(): void
    {
        $user = $this->onDutyParamedic();

        // 🔑 막다른 길이면 안 된다. 구급대원 본인이 코스에서 쓰러지는 것은 이 도메인의
        //    실제 사고 유형이고, 그때 그 사람은 앱을 다시 배울 수 없다.
        $this->actingAs($user)->get('/requests/create')
            ->assertSee('tel:119', false)
            ->assertSee('상황실 전화');
    }

    public function test_a_paramedic_is_blocked_on_the_project_request_form_too(): void
    {
        $user = $this->onDutyParamedic();
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
    public function test_a_paramedic_cannot_file_through_the_api_either(): void
    {
        $user = $this->onDutyParamedic();

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
        $user->assignRole('user');
        $this->inEvent($user, EventRole::PARAMEDIC);

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
        $paramedic = User::factory()->create();
        $paramedic->assignRole('user');
        $this->inEvent($paramedic, EventRole::PARAMEDIC);
        $ordinary = User::factory()->create();
        $ordinary->assignRole('user');

        $this->actingAs($paramedic)->get('/dispatches')
            ->assertSee('프로필')
            ->assertDontSee('구조요청');

        $this->actingAs($ordinary)->get('/dashboard')
            ->assertSee('구조요청')
            ->assertSee('프로필');
    }
}
