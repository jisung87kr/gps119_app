<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Enums\RequestStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 프로필은 「내 계정을 관리하는 곳」이다 (2026-08-13 현장 피드백).
 *
 * 활동(행사 역할·요청 건수·최근 내역)을 여기 같이 두면 홈과 두 벌이 되고, 한쪽이
 * 반드시 낡는다. 특히 «행사 역할»은 틀린 정보를 보여주고 있었다 — 여러 행사에 서로 다른
 * 역할로 참가하는 사람에게 activeEventRole() 이 그중 하나만 뽑아 「당신은 구급대」라고
 * 단정했다. 역할은 사람이 아니라 행사의 속성이다(MixedEventRoleTest 와 같은 뿌리).
 */
class ProfilePageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
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

    private function join(User $user, Project $p, EventRole $role): void
    {
        EventParticipant::factory()->create([
            'project_id' => $p->id, 'user_id' => $user->id,
            'role' => $role, 'status' => ParticipantStatus::ACTIVE,
            'last_entered_at' => now(),
        ]);
    }

    private function fileRequest(User $user, Project $p): Request
    {
        return Request::factory()->create([
            'user_id' => $user->id, 'project_id' => $p->id,
            'address' => '강원특별자치도 춘천시 세실로 261',
            'status' => RequestStatus::PENDING,
            'requested_at' => now(),
        ]);
    }

    public function test_the_profile_shows_the_account_and_nothing_about_activity(): void
    {
        $user = User::factory()->create(['phone' => '01012345679']);
        $user->assignRole('user');
        $event = $this->event('A 행사');
        $this->join($user, $event, EventRole::PARAMEDIC);
        $this->fileRequest($user, $event);

        $res = $this->actingAs($user)->get('/profile')->assertOk();

        // 남는 것 — 계정
        $res->assertSee('가입일')->assertSee('내 정보 수정')->assertSee('개인정보처리방침');

        // 빠진 것 — 활동
        $res->assertDontSee('행사 역할')
            ->assertDontSee('총 구조 요청')
            ->assertDontSee('출동 완료')
            ->assertDontSee('최근 구조 요청 내역');
    }

    /**
     * 🔑 프로필에서 내역을 빼면 «지령 홈을 쓰는 사람»의 신고가 갈 곳을 잃는다.
     *    /dashboard 는 그 사람들을 /dispatches 로 돌려보내므로 거기 없으면 앱 어디에도 없다.
     *    A 행사 참가자 + B 행사 구급대가 정확히 그 경우다.
     */
    public function test_a_dispatch_home_user_can_still_reach_the_request_they_filed(): void
    {
        $user = User::factory()->create(['phone' => '01033334444']);
        $user->assignRole('user');
        $a = $this->event('A 행사');
        $b = $this->event('B 행사');
        $this->join($user, $a, EventRole::PARTICIPANT);
        $this->join($user, $b, EventRole::PARAMEDIC);
        $request = $this->fileRequest($user, $a);

        $this->assertTrue($user->usesDispatchHome(), '이 사람의 홈은 /dispatches 다');

        $this->actingAs($user)->get('/dispatches')
            ->assertOk()
            ->assertSee('내 구조 요청')
            ->assertSee(route('request.show', $request), false);
    }

    /** 완료된 건까지 싣지 않는다 — 이 화면의 주인공은 지령이다. */
    public function test_the_dispatch_home_only_carries_requests_that_are_still_open(): void
    {
        $user = User::factory()->create(['phone' => '01055556666']);
        $user->assignRole('user');
        $b = $this->event('B 행사');
        $this->join($user, $b, EventRole::PARAMEDIC);

        $done = $this->fileRequest($user, $b);
        $done->update(['status' => RequestStatus::COMPLETED]);

        $this->actingAs($user)->get('/dispatches')
            ->assertOk()
            ->assertDontSee('내 구조 요청')
            ->assertDontSee(route('request.show', $done), false);
    }
}
