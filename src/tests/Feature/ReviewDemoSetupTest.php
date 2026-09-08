<?php

namespace Tests\Feature;

use App\Console\Commands\ReviewDemoSetup;
use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** 스토어 심사용 데모 계정·행사 (`review:demo`) — 멱등이고, 재실행이 심사자의 비밀번호를 바꾸지 않는다. */
class ReviewDemoSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_계정_3종과_활성_행사를_만든다(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('review:demo', ['--password' => 'ReviewPass1', '--days' => 10])->assertSuccessful();

        $project = Project::where('slug', ReviewDemoSetup::PROJECT_SLUG)->firstOrFail();
        $this->assertTrue($project->isActive());
        $this->assertSame(today()->addDays(10)->toDateString(), $project->end_date->toDateString());

        foreach (ReviewDemoSetup::ACCOUNTS as $spec) {
            $user = User::where('phone', $spec['phone'])->firstOrFail();
            $this->assertTrue(Hash::check('ReviewPass1', $user->password));
            $this->assertFalse($user->must_change_password);
            $this->assertCount(2, $user->consents);

            $participant = EventParticipant::where('project_id', $project->id)->where('user_id', $user->id)->firstOrFail();
            $this->assertSame($spec['role'], $participant->role);
            $this->assertSame(ParticipantStatus::ACTIVE, $participant->status);
            // 실제 입장 경로를 탔다 — 진짜 참가자처럼 위치 공유가 켜진 채로 시작한다
            $this->assertTrue($participant->sharing_location);
        }
    }

    public function test_재실행은_종료일만_늘리고_계정도_비밀번호도_그대로다(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->artisan('review:demo', ['--password' => 'ReviewPass1', '--days' => 10])->assertSuccessful();

        $this->artisan('review:demo', ['--password' => 'Other', '--days' => 20])->assertSuccessful();

        $project = Project::where('slug', ReviewDemoSetup::PROJECT_SLUG)->firstOrFail();
        $this->assertSame(today()->addDays(20)->toDateString(), $project->end_date->toDateString());
        $this->assertSame(1, Project::where('slug', ReviewDemoSetup::PROJECT_SLUG)->count());

        $phones = array_column(ReviewDemoSetup::ACCOUNTS, 'phone');
        $this->assertSame(3, User::whereIn('phone', $phones)->count());
        $this->assertSame(3, EventParticipant::where('project_id', $project->id)->count());
        $this->assertTrue(Hash::check('ReviewPass1', User::where('phone', $phones[0])->first()->password));

        // 역할도 안 바뀐다 — 상황실은 여전히 상황실
        $controller = User::where('phone', $phones[2])->first();
        $this->assertSame(EventRole::CONTROLLER, $controller->eventRoleIn($project));
    }

    public function test_reset_password_는_명시했을_때만(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->artisan('review:demo', ['--password' => 'ReviewPass1'])->assertSuccessful();

        $this->artisan('review:demo', ['--password' => 'Changed99', '--reset-password' => true])->assertSuccessful();

        $user = User::where('phone', ReviewDemoSetup::ACCOUNTS[0]['phone'])->first();
        $this->assertTrue(Hash::check('Changed99', $user->password));
    }

    public function test_관리자가_없으면_실패한다(): void
    {
        $this->artisan('review:demo', ['--password' => 'x'])->assertFailed();
        $this->assertDatabaseMissing('projects', ['slug' => ReviewDemoSetup::PROJECT_SLUG]);
    }
}
