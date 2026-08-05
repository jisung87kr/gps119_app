<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관제 딥링크 `?project=` (mobile-app N1).
 *
 * 푸시 알림을 탭하면 «그 신고가 난 행사»가 열려야 한다. 예전에는 `/admin/control`
 * 만 이 파라미터를 읽었는데, 정작 «행사 상황실»은 시스템 롤이 그냥 user 라
 * `/control` 로 온다 — 딥링크가 하필 가장 필요한 사람에게만 동작하지 않았다.
 *
 * 🔑 **행사를 2개 이상 맡은 계정으로 판정한다.** 1개짜리 계정은 자동선택 때문에
 *    파라미터를 아예 무시해도 통과한다(우연히 초록불).
 */
class ControlDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function activeProject(string $name): Project
    {
        return Project::factory()->create([
            'name' => $name,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
    }

    /** 행사 2개를 맡은 상황실. 시스템 롤은 일부러 user 로 둔다(현실이 그렇다). */
    private function controllerOfTwo(): array
    {
        $a = $this->activeProject('행사 A');
        $b = $this->activeProject('행사 B');

        $user = User::factory()->create();
        $user->assignRole('user');

        foreach ([$a, $b] as $project) {
            EventParticipant::factory()->controller()->create([
                'project_id' => $project->id, 'user_id' => $user->id,
            ]);
        }

        return [$user, $a, $b];
    }

    public function test_the_named_event_is_selected(): void
    {
        [$user, $a, $b] = $this->controllerOfTwo();

        $this->actingAs($user)->get("/control?project={$b->id}")
            ->assertOk()
            ->assertSee('data-selected="'.$b->id.'"', false);
    }

    public function test_the_other_event_is_not_selected(): void
    {
        // 짝 테스트. 위 하나만 두면 «항상 B 를 고르는» 구현도 통과한다.
        [$user, $a, $b] = $this->controllerOfTwo();

        $this->actingAs($user)->get("/control?project={$a->id}")
            ->assertOk()
            ->assertSee('data-selected="'.$a->id.'"', false)
            ->assertDontSee('data-selected="'.$b->id.'"', false);
    }

    public function test_an_event_the_user_does_not_control_is_ignored(): void
    {
        // 권한 밖 행사 id 를 넣어도 그 행사가 열리면 안 된다.
        [$user] = $this->controllerOfTwo();
        $stranger = $this->activeProject('남의 행사');

        $this->actingAs($user)->get("/control?project={$stranger->id}")
            ->assertOk()
            ->assertDontSee('data-selected="'.$stranger->id.'"', false);
    }

    public function test_without_the_parameter_nothing_is_preselected(): void
    {
        [$user] = $this->controllerOfTwo();

        $this->actingAs($user)->get('/control')
            ->assertOk()
            ->assertSee('data-selected=""', false);
    }

    public function test_an_admin_gets_the_same_deep_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->activeProject('행사 A');
        $b = $this->activeProject('행사 B');

        $this->actingAs($admin)->get("/control?project={$b->id}")
            ->assertOk()
            ->assertSee('data-selected="'.$b->id.'"', false);
    }
}
