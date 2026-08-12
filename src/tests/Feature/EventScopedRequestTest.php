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

    private function join(User $user, Project $project, EventRole $role = EventRole::PARTICIPANT): void
    {
        EventParticipant::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'role' => $role, 'status' => ParticipantStatus::ACTIVE,
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
        $this->assertSame($event->id, $other->soleActiveEvent()?->id);
    }

    public function test_two_events_fall_back_instead_of_guessing(): void
    {
        $user = User::factory()->create();
        $this->join($user, $this->event('행사 A'));
        $this->join($user, $this->event('행사 B'));

        // 잘못된 행사에 붙이면 엉뚱한 상황실이 출동한다. 그럴 바엔 「상시 운영」이 낫다.
        $this->assertSame(Project::defaultEvent()->id, $this->file($user)->project_id);
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
}
