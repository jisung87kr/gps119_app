<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 행사 복제(admin.projects.clone).
 *
 * replicate() 가 유니크 컬럼(slug·join_code)과 is_default 까지 복사해 오던 것을 막는다.
 * join_code 는 DB 유니크라 «시끄럽게» 터졌지만(500), is_default 는 제약이 없어
 * 조용히 두 번째 「상시 운영」을 만든다 — 그쪽이 더 위험하다.
 */
class ProjectCloneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_clone_issues_a_new_join_code(): void
    {
        $original = Project::factory()->create();
        $this->assertNotNull($original->join_code);

        $this->actingAs($this->admin())
            ->post(route('admin.projects.clone', $original->id))
            ->assertRedirect();

        $clone = Project::where('name', $original->name.' (복제본)')->firstOrFail();

        $this->assertNotNull($clone->join_code, '복제본도 입장 코드를 가져야 한다');
        $this->assertNotSame(
            $original->join_code,
            $clone->join_code,
            '입장 코드를 복사해 오면 projects_join_code_unique 위반으로 복제 자체가 실패한다'
        );
    }

    public function test_clone_issues_a_new_slug(): void
    {
        $original = Project::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.projects.clone', $original->id));

        $clone = Project::where('name', $original->name.' (복제본)')->firstOrFail();

        $this->assertNotSame($original->slug, $clone->slug, '복제본은 자기 slug 를 가져야 한다');
    }

    public function test_cloning_the_default_event_does_not_create_a_second_default(): void
    {
        $default = Project::defaultEvent();
        $this->assertTrue($default->is_default);

        $this->actingAs($this->admin())
            ->post(route('admin.projects.clone', $default->id))
            ->assertRedirect();

        $this->assertSame(
            1,
            Project::where('is_default', true)->count(),
            '기본 행사는 하나뿐이어야 한다 — 둘이 되면 defaultEvent() 가 어느 쪽을 집는지 알 수 없다'
        );

        $clone = Project::where('name', $default->name.' (복제본)')->firstOrFail();
        $this->assertFalse((bool) $clone->is_default, '복제본은 기본 행사가 아니다');
        $this->assertSame($default->id, Project::defaultEvent()->id, '기본 행사는 원본 그대로여야 한다');
    }

    public function test_the_default_event_clone_is_deletable(): void
    {
        // is_default 를 복사해 오면 deleting 훅(ADR-0005)이 막아서 «지우지도 못하는» 복제본이 된다.
        $default = Project::defaultEvent();

        $this->actingAs($this->admin())
            ->post(route('admin.projects.clone', $default->id));

        $clone = Project::where('name', $default->name.' (복제본)')->firstOrFail();

        $this->assertNotFalse($clone->delete(), '복제본은 삭제할 수 있어야 한다');
        $this->assertSoftDeleted($clone);
    }

    public function test_replicate_blanks_the_unique_columns_at_the_model_level(): void
    {
        // 컨트롤러가 아니라 모델이 책임진다 — 다른 복제 경로가 생겨도 같이 지켜지도록.
        $original = Project::factory()->create(['is_default' => false]);

        $clone = $original->replicate();

        $this->assertNull($clone->slug);
        $this->assertNull($clone->join_code);
        $this->assertFalse((bool) $clone->is_default);
    }
}
