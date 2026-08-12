<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 위치 이력 보존기간 집행 (2026-08-12, OI-7 / 에픽 Q2 종결).
 *
 * 🔴 `location_pings` 는 「누가 언제 어디 있었는지」의 이력이다. 이 앱에서 가장 민감한
 *    데이터인데 지금까지 «자동 파기가 없어» 쌓이기만 했다.
 *
 * 🔑 이 파일이 지키는 계약은 「방침에 적은 숫자를 코드가 실제로 집행한다」이다.
 *    개인정보처리방침·위치기반서비스 약관의 보존기간과 config('location.retention_days')
 *    가 같은 값이어야 하고, 한쪽만 바뀌면 문서가 거짓말을 하게 된다.
 */
class LocationRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function ping(int $daysAgo): LocationPing
    {
        $owner = User::factory()->create();

        return LocationPing::create([
            'project_id' => Project::factory()->create(['created_by' => $owner->id])->id,
            'user_id' => $owner->id,
            'latitude' => 37.5665,
            'longitude' => 126.9780,
            'recorded_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_pings_older_than_the_retention_window_are_purged(): void
    {
        config(['location.retention_days' => 90]);
        $old = $this->ping(91);
        $fresh = $this->ping(89);

        $this->artisan('location:purge')->assertSuccessful();

        $this->assertDatabaseMissing('location_pings', ['id' => $old->id]);
        $this->assertDatabaseHas('location_pings', ['id' => $fresh->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        config(['location.retention_days' => 90]);
        $old = $this->ping(200);

        $this->artisan('location:purge --dry-run')->assertSuccessful();

        // 「무엇이 지워질지」를 먼저 보고 싶을 때가 있다. 그때 지우면 안 된다.
        $this->assertDatabaseHas('location_pings', ['id' => $old->id]);
    }

    public function test_running_it_twice_is_safe(): void
    {
        config(['location.retention_days' => 30]);
        $this->ping(60);

        $this->artisan('location:purge')->assertSuccessful();
        $this->artisan('location:purge')->assertSuccessful();

        $this->assertSame(0, LocationPing::count());
    }

    public function test_it_purges_across_chunks(): void
    {
        // 운영 DB 에서 한 문장으로 수백만 행을 지우면 락이 오래 걸려 청크로 나눈다.
        // 청크 경계에서 루프가 멈춰버리면 절반만 지워지고 아무도 모른다.
        config(['location.retention_days' => 30, 'location.purge_chunk' => 2]);
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        foreach (range(1, 7) as $i) {
            LocationPing::create([
                'project_id' => $project->id, 'user_id' => $owner->id,
                'latitude' => 37.5, 'longitude' => 127.0,
                'recorded_at' => now()->subDays(60),
            ]);
        }

        $this->artisan('location:purge')->assertSuccessful();

        $this->assertSame(0, LocationPing::count());
    }

    /**
     * 🔴 0 이나 음수를 허용하면 «전부 삭제»가 된다. 오타 한 번에 이력이 사라지고
     *    되돌릴 방법이 없다.
     */
    public function test_a_zero_or_negative_window_is_refused(): void
    {
        $keep = $this->ping(1000);

        $this->artisan('location:purge --days=0')->assertFailed();
        $this->artisan('location:purge --days=-1')->assertFailed();

        $this->assertDatabaseHas('location_pings', ['id' => $keep->id]);
    }

    public function test_the_retention_window_is_configurable_in_one_place(): void
    {
        // 방침 문서의 숫자와 같아야 하는 값이다. 코드 곳곳에 흩어지면 어긋난다.
        $this->assertIsInt(config('location.retention_days'));
        $this->assertGreaterThan(0, config('location.retention_days'));
    }

    /**
     * 🔴 방침과 시스템이 갈라지지 않게 하는 장치.
     *
     * 개인정보처리방침에 「실시간 위치 이력: 6개월」이라고 적혀 있다. 실제 처리와 어긋난
     * 방침은 그 자체로 위반이므로, 설정을 바꾸면 문서도 같이 바꾸도록 여기서 묶는다.
     * (기본값 180일 = 6개월. 법무 검토로 바뀌면 이 테스트가 먼저 알려준다.)
     */
    public function test_the_configured_window_matches_the_published_policy(): void
    {
        $policy = file_get_contents(resource_path('views/legal/privacy.blade.php'));

        $this->assertMatchesRegularExpression(
            '/실시간 위치 이력.*?6개월/su',
            $policy,
            '개인정보처리방침의 위치 이력 보유기간 문구를 찾지 못했다'
        );
        $this->assertSame(
            180,
            (int) config('location.retention_days'),
            '방침은 6개월(180일)인데 설정이 다르다 — 둘 중 하나를 고쳐야 한다'
        );
    }

    public function test_the_purge_is_scheduled(): void
    {
        // 명령만 있고 스케줄이 없으면 「만들었는데 아무도 안 돌리는」 상태가 된다.
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->command);

        $this->assertTrue(
            $events->contains(fn ($c) => str_contains((string) $c, 'location:purge')),
            'location:purge 가 스케줄에 등록돼 있어야 한다'
        );
    }
}
