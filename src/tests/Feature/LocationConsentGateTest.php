<?php

namespace Tests\Feature;

use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Models\UserConsent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 동의 없이는 위치를 수집하지 않는다 — «수집 시점»에서 막는다.
 *
 * 🔴 가입 시 동의(ConsentTest)는 **신규 가입자에게만** 걸린다. 가입 폼이 생기기 전에
 *    만들어진 계정은 동의 기록이 없고, 여기서 막지 않으면 그들의 위치는 영영 동의
 *    없이 수집된다. 기존 사용자가 이 앱의 대다수다.
 */
class LocationConsentGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function participant(User $user): Project
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        EventParticipant::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'sharing_location' => true,
        ]);

        return $project;
    }

    public function test_🔴_동의가_없으면_위치가_기록되지_않는다(): void
    {
        $user = User::factory()->create();
        $project = $this->participant($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'accuracy' => 12,
            'recorded_at' => now()->subSeconds(2)->toISOString(),
        ])->assertStatus(409)->assertJsonPath('errors.code', 'consent_required');
    }

    public function test_고칠_방법을_함께_돌려준다(): void
    {
        // 그냥 막기만 하면 화면은 「전송 실패」만 말하고 사용자는 방법을 모른다.
        $user = User::factory()->create();
        $project = $this->participant($user);
        Sanctum::actingAs($user);

        $res = $this->postJson("/api/events/{$project->id}/location", [
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'accuracy' => 12,
            'recorded_at' => now()->subSeconds(2)->toISOString(),
        ]);

        $items = $res->json('errors.items');
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            ['privacy', 'location_terms'],
            array_column($items, 'type'),
        );
        foreach ($items as $item) {
            $this->assertNotEmpty($item['label']);
            $this->assertStringStartsWith('http', $item['url']);
        }
    }

    public function test_🔴_동의가_없으면_공유를_켤_수_없다(): void
    {
        $user = User::factory()->create();
        $project = $this->participant($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/events/{$project->id}/sharing", ['sharing_location' => true])
            ->assertStatus(409)->assertJsonPath('errors.code', 'consent_required');
    }

    public function test_🔴_동의가_없어도_«끄는»_것은_언제나_된다(): void
    {
        // 동의가 없다고 끄지도 못하게 하면 수집을 멈출 방법이 사라진다 — 정반대다.
        $user = User::factory()->create();
        $project = $this->participant($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/events/{$project->id}/sharing", ['sharing_location' => false])
            ->assertOk()->assertJsonPath('data.sharing_location', false);
    }

    public function test_동의하면_바로_통과한다(): void
    {
        $user = User::factory()->create();
        $project = $this->participant($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/consents', ['consents' => ['privacy', 'location_terms']])
            ->assertOk()->assertJsonPath('data.missing', []);

        $this->patchJson("/api/events/{$project->id}/sharing", ['sharing_location' => true])
            ->assertOk();
    }

    public function test_🔴_부분_동의는_기록되지_않는다(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/consents', ['consents' => ['privacy']])
            ->assertStatus(422)->assertJsonPath('errors.code', 'consent_incomplete');

        $this->assertSame(0, UserConsent::count());
    }

    public function test_무엇이_비어_있는지_물어볼_수_있다(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/consents')->assertOk()
            ->assertJsonCount(2, 'data.missing');

        $this->postJson('/api/consents', ['consents' => ['privacy', 'location_terms']]);

        $this->getJson('/api/consents')->assertOk()
            ->assertJsonCount(0, 'data.missing');
    }
}
