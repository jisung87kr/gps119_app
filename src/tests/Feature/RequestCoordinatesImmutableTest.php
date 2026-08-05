<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Events\RequestCreated;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\RequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * 신고 좌표는 사후에 바꿀 수 없다 (realtime 에픽 「기존 코드 영향」 체크리스트).
 *
 * 신고 좌표는 「신고 시점에 그 사람이 어디 있었나」라는 사실 기록이고, 구조 기록·
 * 행사 리포트·법적 분쟁의 근거가 된다. 사후 수정이 가능하면 그 기록 전체의 신뢰가 깨진다.
 *
 * 🔑 이 테스트가 «서비스»를 직접 부르는 이유 — 현재 API 는 validate() 화이트리스트로
 *    좌표를 애초에 받지 않아 지금은 안전하다. 그 안전은 「그 목록에 좌표를 추가하지
 *    않는다」는 규율에 의존하고, 두 번째 호출자가 생기면 조용히 사라진다.
 *    그래서 불변식을 소유한 층에서 판정한다.
 */
class RequestCoordinatesImmutableTest extends TestCase
{
    use RefreshDatabase;

    private RequestService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(RequestService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Event::fake([RequestCreated::class]);
    }

    private function request(): RescueRequest
    {
        return RescueRequest::factory()->for(User::factory()->create())->create([
            'latitude' => 37.56650000,
            'longitude' => 126.97800000,
            'status' => RequestStatus::PENDING,
        ]);
    }

    public function test_changing_latitude_is_rejected(): void
    {
        $request = $this->request();

        $this->expectException(RuntimeException::class);
        $this->service->updateRequest($request, ['latitude' => 35.1], $this->admin);
    }

    public function test_changing_longitude_is_rejected(): void
    {
        $request = $this->request();

        $this->expectException(RuntimeException::class);
        $this->service->updateRequest($request, ['longitude' => 129.0], $this->admin);
    }

    public function test_a_rejected_attempt_changes_nothing(): void
    {
        // 던지기 «전에» 다른 필드가 저장되면, 실패한 요청이 절반만 반영된다.
        $request = $this->request();

        try {
            $this->service->updateRequest($request, [
                'latitude' => 35.1,
                'description' => '여기를 바꾸려 했다',
            ], $this->admin);
        } catch (RuntimeException) {
            // 기대한 실패
        }

        $fresh = $request->fresh();
        $this->assertSame('37.56650000', (string) $fresh->latitude);
        $this->assertNotSame('여기를 바꾸려 했다', $fresh->description);
    }

    public function test_resending_the_same_coordinates_is_allowed(): void
    {
        // 클라이언트가 객체 전체를 되돌려보내는 흔한 패턴. 아무것도 바꾸지 않는
        // 요청까지 막으면 정상 사용이 불편해진다.
        $request = $this->request();

        $updated = $this->service->updateRequest($request, [
            'latitude' => 37.5665,          // 같은 값, 다른 표기(소수 자리)
            'longitude' => 126.978,
            'status' => RequestStatus::IN_PROGRESS,
        ], $this->admin);

        $this->assertSame(RequestStatus::IN_PROGRESS, $updated->status);
    }

    public function test_updating_other_fields_still_works(): void
    {
        // 회귀 방지 — 좌표 가드가 정상 수정까지 막으면 안 된다.
        $request = $this->request();

        $updated = $this->service->updateRequest($request, [
            'status' => RequestStatus::COMPLETED,
            'description' => '처리 완료',
        ], $this->admin);

        $this->assertSame(RequestStatus::COMPLETED, $updated->status);
        $this->assertSame('처리 완료', $updated->description);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_the_api_does_not_even_accept_coordinates(): void
    {
        // 경계(컨트롤러)의 화이트리스트도 같이 고정한다. 서비스 가드와 이중이지만,
        // 둘은 다른 것을 지킨다 — 이건 «받지 않는다», 서비스는 «바꾸지 않는다».
        $request = $this->request();

        $this->actingAs($this->admin)
            ->putJson("/api/requests/{$request->id}", [
                'latitude' => 35.1,
                'status' => 'in_progress',
            ])->assertSuccessful();

        $this->assertSame('37.56650000', (string) $request->fresh()->latitude);
    }
}
