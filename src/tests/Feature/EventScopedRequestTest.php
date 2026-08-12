<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\RequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * 행사에 «참가 중인» 사람의 신고는 그 행사로 간다 (2026-08-13 현장 QA 에서 발견).
 *
 * 🔴 실제로 깨져 있었다. 행사에 입장한 참가자가 「구조요청 하기」를 누르면 slug 없는
 *    `/requests/create` 로 갔고, 그 신고가 「상시 운영」에 붙었다. 결과적으로
 *    **정작 그 행사의 관제 화면에는 뜨지 않았다** — 신고는 접수됐는데 상황실은 모르는
 *    상태다. 이 도메인에서 가장 나쁜 실패다.
 *
 * 🔑 원인은 링크였지만 «링크를 고치는 것»으로 끝내지 않았다. 같은 실수를 하는 링크가
 *    9곳 있었고, 다음에 또 생긴다. 귀속 규칙은 RequestService 한 곳에 있고, 화면 링크는
 *    「어디로 가는지 보이게」 하는 보조 수단이다.
 */
class EventScopedRequestTest extends TestCase
{
    use RefreshDatabase;

    private RequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->requests = app(RequestService::class);
        Event::fake([\App\Events\RequestCreated::class]);
    }

    private function event(string $name = '테스트 마라톤'): Project
    {
        return Project::factory()->create([
            'name' => $name,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ]);
    }

    private function join(User $user, Project $project, EventRole $role = EventRole::PARTICIPANT, ?string $enteredAt = null): void
    {
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'role' => $role, 'status' => ParticipantStatus::ACTIVE,
            'last_entered_at' => $enteredAt ?? now(),
        ]);
    }

    private function file(User $user, array $extra = []): \App\Models\Request
    {
        return $this->requests->createRequest(array_merge([
            'latitude' => 37.5665, 'longitude' => 126.9780,
        ], $extra), $user);
    }

    // ── 핵심 계약 ────────────────────────────────────────────

    public function test_a_participants_request_goes_to_their_event(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $this->join($user, $event);

        // 화면이 행사를 지정하지 않아도(= 일반 /requests/create 경로) 그 행사로 간다.
        $this->assertSame($event->id, $this->file($user)->project_id);
    }

    public function test_the_same_request_through_the_api_also_lands_on_the_event(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $event = $this->event();
        $this->join($user, $event);

        $this->actingAs($user)->postJson('/api/requests', [
            'latitude' => 37.5665, 'longitude' => 126.9780,
        ])->assertCreated();

        $this->assertSame($event->id, $user->requests()->first()->project_id);
    }

    public function test_someone_with_no_event_still_goes_to_the_always_on_event(): void
    {
        $user = User::factory()->create();

        // ADR-0005: 모든 신고는 행사에 속한다. 행사가 없으면 「상시 운영」.
        $this->assertSame(Project::defaultEvent()->id, $this->file($user)->project_id);
    }

    /**
     * 🔴 「상시 운영」은 항상 활성이라, 세는 데 끼워 넣으면 상시 운영 소속자가 실제 행사에
     *    들어가는 순간 «2개»가 되어 조용히 폴백된다 — 고치려던 버그가 그대로 남는다.
     */
    public function test_belonging_to_the_always_on_event_does_not_block_scoping(): void
    {
        $user = User::factory()->create();
        $this->join($user, Project::defaultEvent(), EventRole::PARAMEDIC);
        $event = $this->event();
        $this->join($user, $event);

        // 구급대는 신고를 못 하므로 참가자로 확인한다.
        $other = User::factory()->create();
        $this->join($other, Project::defaultEvent());
        $this->join($other, $event);

        $this->assertSame($event->id, $this->file($other)->project_id);
        $this->assertSame($event->id, $other->currentEvent()?->id);
    }

    /**
     * 동시에 두 행사에 참가한 경우 — «마지막으로 입장한» 행사로 간다.
     *
     * 🔑 응급 화면에서 드롭다운을 고르게 할 수는 없다. 마찰 없이 쓸 수 있는 근거는
     *    「마지막으로 입장 QR 을 찍은 곳」뿐이다. 대신 «조용히» 정하지 않는다 —
     *    신고 화면이 어느 행사인지 보여주고 「변경」을 준다(아래 테스트).
     */
    public function test_two_events_use_the_most_recently_entered_one(): void
    {
        $user = User::factory()->create();
        $first = $this->event('먼저 들어간 행사');
        $latest = $this->event('나중에 들어간 행사');
        $this->join($user, $first, EventRole::PARTICIPANT, now()->subHours(3)->toDateTimeString());
        $this->join($user, $latest, EventRole::PARTICIPANT, now()->subMinutes(5)->toDateTimeString());

        $this->assertSame($latest->id, $this->file($user)->project_id);
        $this->assertSame($latest->id, $user->currentEvent()?->id);
    }

    /**
     * 🔴 joined_at 으로는 안 된다. 그건 «최초» 입장이고 재입장해도 갱신되지 않아,
     *    두 행사를 오가는 사람에게는 영원히 처음 들어간 쪽이 이긴다.
     */
    public function test_re_entering_the_other_event_moves_the_target(): void
    {
        $user = User::factory()->create(['phone' => '01044445555']);
        $a = $this->event('행사 A');
        $b = $this->event('행사 B');
        $this->join($user, $a, EventRole::PARTICIPANT, now()->subHours(2)->toDateTimeString());
        $this->join($user, $b, EventRole::PARTICIPANT, now()->subHours(1)->toDateTimeString());
        $this->assertSame($b->id, $user->currentEvent()?->id);

        // A 의 입장 QR 을 다시 찍었다.
        app(\App\Services\EventParticipantService::class)->joinByCode($a->join_code, $user);

        $this->assertSame($a->id, $user->currentEvent()?->id);
        $this->assertSame($a->id, $this->file($user)->project_id);
    }

    public function test_a_finished_event_does_not_capture_the_request(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $this->join($user, $event);
        $event->forceFill(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(3)])->save();

        $this->assertSame(Project::defaultEvent()->id, $this->file($user->fresh())->project_id);
    }

    public function test_an_explicit_project_id_always_wins(): void
    {
        $user = User::factory()->create();
        $this->join($user, $this->event('내 행사'));
        $other = $this->event('QR 로 들어온 행사');

        // 현수막 QR(slug 경로)로 들어온 경우 — 화면이 지정한 행사가 우선이다.
        $this->assertSame($other->id, $this->file($user, ['project_id' => $other->id])->project_id);
    }

    public function test_a_participant_who_left_is_not_scoped(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        EventParticipant::factory()->create([
            'project_id' => $event->id, 'user_id' => $user->id,
            'role' => EventRole::PARTICIPANT, 'status' => ParticipantStatus::LEFT,
        ]);

        $this->assertSame(Project::defaultEvent()->id, $this->file($user)->project_id);
    }

    // ── 화면도 어디로 가는지 «보여야» 한다 ───────────────────

    public function test_the_activity_screen_links_to_the_events_own_request_page(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $event = $this->event();
        $this->join($user, $event);

        // 일반 경로로 보내면 화면에 행사 이름이 안 뜨고, 신고자는 자기 신고가
        // 어디로 가는지 알 수 없다.
        $this->actingAs($user)->get("/events/{$event->id}/active")
            ->assertOk()
            ->assertSee("/requests/create/{$event->slug}", false);
    }

    public function test_the_request_tab_points_at_the_event(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $event = $this->event();
        $this->join($user, $event);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee("/requests/create/{$event->slug}", false);
    }
    // ── 접수 대상 배너 ───────────────────────────────────────

    /**
     * 🔴 이 배너가 이 버그를 «다시» 막는 장치다. 2026-08-13 에 신고가 「상시 운영」으로
     *    새고 있었는데 아무도 몰랐던 이유가 화면에 목적지가 없었기 때문이다.
     *    행사가 하나뿐이어도 띄운다.
     */
    public function test_the_request_screen_always_shows_where_it_will_be_filed(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $event = $this->event('강원마라톤');
        $this->join($user, $event);

        $this->actingAs($user)->get('/requests/create')
            ->assertOk()
            ->assertSee('강원마라톤')
            ->assertSee('으로 접수됩니다');
    }

    public function test_someone_with_no_event_is_told_it_is_a_general_request(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');

        // 「상시 운영」은 내부 개념이라 그대로 보여주면 사용자에게 아무 의미가 없다.
        $this->actingAs($user)->get('/requests/create')
            ->assertOk()
            ->assertSee('일반 신고')
            ->assertDontSee('상시 운영');
    }

    public function test_the_change_control_appears_only_with_a_second_event(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $a = $this->event('행사 A');
        $this->join($user, $a, EventRole::PARTICIPANT, now()->subHour()->toDateTimeString());

        // 행사가 하나면 고를 것이 없다 — 급할 때 선택지는 적을수록 좋다.
        $this->actingAs($user)->get('/requests/create')->assertDontSee('변경');

        $b = $this->event('행사 B');
        $this->join($user, $b, EventRole::PARTICIPANT, now()->toDateTimeString());

        $this->actingAs($user)->get('/requests/create')
            ->assertSee('변경')
            ->assertSee('행사 B')                                  // 지금 대상(마지막 입장)
            ->assertSee(route('request.create.project', $a->slug), false); // 다른 행사로 바꾸는 길
    }

    /** 「변경」 목록에는 참가 중인 행사만 — 「상시 운영」은 선택지가 아니다. */
    public function test_the_change_list_only_offers_events_the_user_is_in(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $this->join($user, $this->event('행사 A'), EventRole::PARTICIPANT, now()->subHour()->toDateTimeString());
        $this->join($user, $this->event('행사 B'), EventRole::PARTICIPANT, now()->toDateTimeString());
        $this->join($user, Project::defaultEvent());
        $notMine = $this->event('참가 안 한 행사');

        $this->actingAs($user)->get('/requests/create')
            ->assertDontSee('참가 안 한 행사')
            ->assertDontSee(route('request.create.project', $notMine->slug), false);
    }

    /**
     * 🔴 이 가드가 생긴 이유: 배너의 「변경」 목록을 Alpine(x-data/x-show)으로 짰더니
     *    «항상 펼쳐진 채»로 렌더됐다. 이 파일은 Vue 마운트 루트(#app) 안이고,
     *    사용자 화면에는 Alpine 이 없다(Alpine 은 관리자 셸 전용) — Vue 는 x-show 를
     *    모르고 통과시키고, @click 은 Vue 가 그 변수를 못 찾아 죽는다.
     *
     * 🔑 assertSee 로는 못 잡는다. 접혀 있든 펼쳐져 있든 DOM 에는 있기 때문이다.
     *    그래서 «결과»가 아니라 «금지된 도구»를 본다.
     */
    public function test_the_request_screen_uses_no_alpine_directives(): void
    {
        $source = file_get_contents(resource_path('views/request/_map-screen.blade.php'));
        // 주석 안의 설명은 제외하고 실제 마크업만 본다.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        foreach (['x-data', 'x-show', 'x-cloak', 'x-text'] as $directive) {
            $this->assertStringNotContainsString(
                $directive,
                $markup,
                "{$directive} 는 Vue 루트 안에서 동작하지 않는다 — 사용자 화면에는 Alpine 이 없다"
            );
        }
    }

    /** 「변경」 목록은 접힌 채로 시작해야 한다 — 그게 <details> 를 쓴 이유다. */
    public function test_the_change_list_starts_collapsed(): void
    {
        $user = User::factory()->create(['phone' => '01011112222']);
        $user->assignRole('user');
        $this->join($user, $this->event('행사 A'), EventRole::PARTICIPANT, now()->subHour()->toDateTimeString());
        $this->join($user, $this->event('행사 B'), EventRole::PARTICIPANT, now()->toDateTimeString());

        $html = $this->actingAs($user)->get('/requests/create')->getContent();

        $this->assertStringContainsString('<details', $html);
        // open 속성이 붙어 있으면 처음부터 펼쳐진다.
        $this->assertStringNotContainsString('<details open', $html);
    }
}
