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
 * 한 사람이 행사마다 다른 역할을 갖는 경우 (2026-08-13 현장 QA 에서 발견).
 *
 * 🔴 설계 오류였다. `activeEventRole()` 이 「이 사람의 역할」을 하나로 뭉개서(우선순위:
 *    상황실 > 구급대 > 그 외) 전역 판정에 썼다. 그런데 **역할은 사람이 아니라 «행사»의
 *    속성**이다.
 *
 *    그래서 A 행사 참가자이면서 B 행사 구급대인 사람은 —
 *      · 홈이 출동 대시보드로 고정되고
 *      · 「내 행사」에 B 만 떠서 A 로 갈 길이 앱 어디에도 없고
 *      · **A 행사에서 사고를 당해도 「구급대는 신고 불가」에 걸려 신고를 못 했다.**
 *    마지막이 진짜 문제다. 응급 도메인에서 신고를 막는 것이 가장 나쁜 실패다.
 */
class MixedEventRoleTest extends TestCase
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

    private function event(string $name): Project
    {
        return Project::factory()->create([
            'name' => $name,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ]);
    }

    private function join(User $user, Project $p, EventRole $role, ?string $enteredAt = null): void
    {
        EventParticipant::factory()->create([
            'project_id' => $p->id, 'user_id' => $user->id,
            'role' => $role, 'status' => ParticipantStatus::ACTIVE,
            'last_entered_at' => $enteredAt ?? now(),
        ]);
    }

    /** A 행사 참가자 + B 행사 구급대 — 이 파일의 주인공. */
    private function mixedUser(): array
    {
        $user = User::factory()->create(['phone' => '01033334444']);
        $user->assignRole('user');
        $a = $this->event('A 행사');
        $b = $this->event('B 행사');
        $this->join($user, $a, EventRole::PARTICIPANT, now()->subHours(2)->toDateTimeString());
        $this->join($user, $b, EventRole::PARAMEDIC, now()->subMinutes(10)->toDateTimeString());

        return [$user, $a, $b];
    }

    // ── 🔴 신고 (가장 심각했던 것) ────────────────────────────

    public function test_they_can_still_file_in_the_event_where_they_are_a_participant(): void
    {
        [$user, $a] = $this->mixedUser();

        $request = $this->requests->createRequest([
            'latitude' => 37.5665, 'longitude' => 126.9780,
        ], $user);

        // 그 사람은 A 에서는 «그냥 참가자»다. 막으면 안 된다.
        $this->assertSame($a->id, $request->project_id);
    }

    public function test_the_request_screen_opens_and_targets_the_participant_event(): void
    {
        [$user, $a] = $this->mixedUser();

        $this->actingAs($user)->get('/requests/create')
            ->assertOk()
            ->assertSee('A 행사')
            ->assertSee('으로 접수됩니다');
    }

    public function test_they_still_cannot_file_into_the_event_they_staff(): void
    {
        [$user, , $b] = $this->mixedUser();

        // 「구급대는 담당 행사에 신고하지 않고 지령만 받는다」는 그대로 유효하다.
        $this->actingAs($user)->get("/requests/create/{$b->slug}")->assertForbidden();

        $this->expectException(\RuntimeException::class);
        $this->requests->createRequest([
            'latitude' => 37.5665, 'longitude' => 126.9780, 'project_id' => $b->id,
        ], $user);
    }

    /** 「변경」 목록에도 담당 행사는 안 나온다 — 골라도 막히는 선택지는 주지 않는다. */
    public function test_the_event_they_staff_is_not_offered_as_a_target(): void
    {
        [$user, , $b] = $this->mixedUser();

        $this->actingAs($user)->get('/requests/create')
            ->assertDontSee(route('request.create.project', $b->slug), false);
    }

    // ── 구급대만 하는 사람은 그대로 차단 ─────────────────────

    /**
     * 🔑 ①(행사별)만 있으면 구급대가 «일반 신고» 화면으로 우회해 「상시 운영」에 접수해
     *    버린다 — 제품 결정이 무의미해지고, 그 신고를 정작 자기 행사 상황실이 못 본다.
     */
    public function test_a_paramedic_with_no_other_event_still_cannot_file_anywhere(): void
    {
        $user = User::factory()->create(['phone' => '01055556666']);
        $user->assignRole('user');
        $this->join($user, $this->event('담당 행사'), EventRole::PARAMEDIC);

        $this->actingAs($user)->get('/requests/create')->assertForbidden();

        $this->expectException(\RuntimeException::class);
        $this->requests->createRequest(['latitude' => 37.5665, 'longitude' => 126.9780], $user);
    }

    // ── 홈에서 두 행사가 모두 보여야 한다 ────────────────────

    public function test_the_home_lists_both_events_so_neither_is_unreachable(): void
    {
        [$user, $a, $b] = $this->mixedUser();

        // 예전에는 지령 수령 역할만 걸러서 B 만 떴고, A 로 갈 길이 앱 어디에도 없었다.
        $this->actingAs($user)->get('/dispatches')
            ->assertOk()
            ->assertSee('A 행사')
            ->assertSee('B 행사');
    }

    public function test_the_dispatch_home_is_still_their_landing(): void
    {
        [$user] = $this->mixedUser();

        // 지령을 놓치면 안 되므로 홈은 출동 현황이 맞다 — 다만 A 로 가는 길이 있어야 한다(위).
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('dispatches.index'));
    }

    // ── 판정이 한 곳인가 ─────────────────────────────────────

    public function test_screens_and_service_read_the_same_rule(): void
    {
        [$user, $a, $b] = $this->mixedUser();

        $this->assertTrue($user->canFileRequestFor($a), 'A 에서는 참가자라 신고 가능해야 한다');
        $this->assertFalse($user->canFileRequestFor($b), 'B 에서는 구급대라 불가해야 한다');
        $this->assertSame([$a->id], $user->reportableEvents()->pluck('id')->all());
    }

    /**
     * 🔴 목록에 모든 행사를 넣으면서 버튼 라벨을 「지령·출동」으로 «고정»해 뒀었다.
     *    참가자가 눌러도 활동 화면으로 튕기므로 «동작은» 하지만, 라벨이 거짓말을 하는 건
     *    깨진 것보다 나쁘다 — 사용자는 자기가 지령을 받는 사람인 줄 알게 된다.
     */
    public function test_each_event_row_offers_the_action_that_matches_that_role(): void
    {
        [$user, $a, $b] = $this->mixedUser();

        $html = $this->actingAs($user)->get('/dispatches')->getContent();

        // 구급대인 행사 → 지령 화면 / 참가자인 행사 → 활동 화면
        $this->assertStringContainsString(route('events.dispatch', $b->id), $html);
        $this->assertStringContainsString(route('events.active', $a->id), $html);
        $this->assertStringNotContainsString(route('events.dispatch', $a->id), $html);
    }

    /**
     * 「상시 운영」은 내부 폴백 자리다. 참가자로 거기 속해 있는 것은 사용자에게 의미가
     * 없으므로 「내 행사」에 띄우지 않는다 — 다만 거기 배정된 상시 구급 인력에게는
     * 지령 화면으로 가는 유일한 길이라 그때는 남긴다.
     */
    public function test_the_always_on_event_is_hidden_from_participants_but_kept_for_medics(): void
    {
        [$user] = $this->mixedUser();
        $this->join($user, Project::defaultEvent(), EventRole::PARTICIPANT);

        $this->actingAs($user)->get('/dispatches')->assertDontSee('상시 운영');

        $medic = User::factory()->create(['phone' => '01077778888']);
        $medic->assignRole('user');
        $this->join($medic, Project::defaultEvent(), EventRole::PARAMEDIC);

        $this->actingAs($medic)->get('/dispatches')->assertSee('상시 운영');
    }
}
