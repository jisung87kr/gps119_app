<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Models\DeviceToken;
use App\Models\Dispatch;
use App\Models\EventParticipant;
use App\Models\EventRoster;
use App\Models\LocationPing;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 회원 탈퇴 = 익명화 (ADR-0010).
 *
 * 🔴 2026-09-08 실측: 지령 이력이 있는 구급대원·상황실은 users 물리 삭제가
 *    dispatches.paramedic_id / assigned_by RESTRICT 에 걸려 500 이었다. 스토어 심사자가
 *    그 계정으로 「계정 삭제」를 누르면 그 자리에서 반려다. 이 파일이 그 회귀를 막는다.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** 신고자·구급대원·상황실이 얽힌 지령 하나. */
    private function dispatchedScene(): array
    {
        $project = Project::factory()->create();
        $requester = User::factory()->create();
        $medic = User::factory()->create();
        $controller = User::factory()->create();

        $request = RescueRequest::factory()->create([
            'user_id' => $requester->id,
            'project_id' => $project->id,
            'contact_phone' => $requester->phone,
            'description' => '넘어져서 발목을 다쳤어요',
        ]);
        $dispatch = Dispatch::factory()->create([
            'request_id' => $request->id,
            'project_id' => $project->id,
            'paramedic_id' => $medic->id,
            'assigned_by' => $controller->id,
        ]);

        return compact('project', 'requester', 'medic', 'controller', 'request', 'dispatch');
    }

    public function test_🔴_지령_이력이_있는_구급대원도_탈퇴할_수_있다(): void
    {
        ['medic' => $medic, 'dispatch' => $dispatch] = $this->dispatchedScene();

        $this->actingAs($medic)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $medic->refresh();
        $this->assertNotNull($medic->account_deleted_at);
        // 지령 기록은 남고, 「누가 출동했는가」는 탈퇴 회원으로 읽힌다
        $this->assertDatabaseHas('dispatches', ['id' => $dispatch->id, 'paramedic_id' => $medic->id]);
        $this->assertSame(AccountDeletionService::DELETED_NAME, $dispatch->fresh()->paramedic->name);
    }

    public function test_🔴_지령을_내린_상황실도_탈퇴할_수_있다(): void
    {
        ['controller' => $controller, 'dispatch' => $dispatch] = $this->dispatchedScene();

        $this->actingAs($controller)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertNotNull($controller->fresh()->account_deleted_at);
        $this->assertDatabaseHas('dispatches', ['id' => $dispatch->id, 'assigned_by' => $controller->id]);
    }

    public function test_신고자가_탈퇴하면_사람은_지워지고_신고_기록은_익명으로_남는다(): void
    {
        ['project' => $project, 'requester' => $requester, 'request' => $request, 'dispatch' => $dispatch] = $this->dispatchedScene();
        $phone = $requester->phone;

        EventParticipant::factory()->create(['project_id' => $project->id, 'user_id' => $requester->id, 'role' => EventRole::PARTICIPANT]);
        LocationPing::factory()->count(3)->create(['project_id' => $project->id, 'user_id' => $requester->id]);
        DeviceToken::factory()->create(['user_id' => $requester->id]);
        EventRoster::forceCreate([
            'project_id' => $project->id, 'phone' => $phone, 'role' => EventRole::PARAMEDIC->value,
            'user_id' => $requester->id, 'claimed_at' => now(),
        ]);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $requester->id, 'payload' => '', 'last_activity' => time()]);
        $requester->assignRole('user');

        $this->actingAs($requester)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $requester->refresh();
        // 사람 — 지워졌다
        $this->assertSame(AccountDeletionService::DELETED_NAME, $requester->name);
        $this->assertNull($requester->phone);
        $this->assertNull($requester->email);
        $this->assertFalse(Hash::check('password', $requester->password));
        $this->assertNull($requester->remember_token);
        $this->assertSame(0, EventParticipant::where('user_id', $requester->id)->count());
        $this->assertSame(0, LocationPing::where('user_id', $requester->id)->count());
        $this->assertSame(0, DeviceToken::where('user_id', $requester->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $requester->id)->count());
        $this->assertSame([], $requester->getRoleNames()->all());
        $this->assertDatabaseHas('event_rosters', ['phone' => $phone, 'user_id' => null]);

        // 기록 — 남되 식별자는 없다
        $this->assertDatabaseHas('requests', ['id' => $request->id, 'user_id' => $requester->id, 'contact_phone' => null, 'description' => null]);
        $this->assertDatabaseHas('dispatches', ['id' => $dispatch->id]);
        $this->assertNotNull($request->fresh()->latitude);
    }

    public function test_탈퇴한_전화번호로_로그인은_안_되고_재가입은_된다(): void
    {
        $user = User::factory()->create();
        $phone = $user->phone;

        $this->actingAs($user)->delete('/profile', ['password' => 'password']);

        $this->post('/login', ['phone' => $phone, 'password' => 'password']);
        $this->assertGuest();

        $this->post('/register', [
            'phone' => $phone,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'consents' => ['privacy', 'location_terms'],
        ])->assertSessionHasNoErrors();

        $fresh = User::where('phone', $phone)->firstOrFail();
        $this->assertNotSame($user->id, $fresh->id);
    }

    public function test_비밀번호가_틀리면_아무것도_지우지_않는다(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile/delete')
            ->delete('/profile', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertNull($user->fresh()->account_deleted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_두_번_돌려도_결과가_같다(): void
    {
        $user = User::factory()->create();
        $service = app(AccountDeletionService::class);

        $first = Carbon::parse('2026-09-08 10:00:00');
        $service->delete($user, $first);
        $service->delete($user, $first->copy()->addHour());

        $this->assertTrue($first->equalTo($user->fresh()->account_deleted_at));
    }
}
