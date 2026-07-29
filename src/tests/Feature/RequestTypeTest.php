<?php

namespace Tests\Feature;

use App\Enums\RequestPriority;
use App\Enums\RequestType;
use App\Models\User;
use App\Services\RequestService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BE-3.1 — 신고 유형 + defaultPriority 매핑.
 */
class RequestTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** type 별 defaultPriority 매핑(확정) */
    public function test_default_priority_mapping(): void
    {
        $this->assertSame(RequestPriority::CRITICAL, RequestType::EMERGENCY->defaultPriority());
        $this->assertSame(RequestPriority::HIGH, RequestType::ACCIDENT->defaultPriority());
        $this->assertSame(RequestPriority::MEDIUM, RequestType::BREAKDOWN->defaultPriority());
        $this->assertSame(RequestPriority::LOW, RequestType::OTHER->defaultPriority());
    }

    /** 신고 생성 시 priority 미지정 → type 기본값 자동 */
    public function test_create_applies_default_priority_from_type(): void
    {
        $user = User::factory()->create();
        $request = app(RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0, 'type' => 'accident',
        ], $user);

        $this->assertSame(RequestType::ACCIDENT, $request->type);
        $this->assertSame(RequestPriority::HIGH, $request->priority);
    }

    /** priority 명시 시 그 값 우선(수동 상향) */
    public function test_explicit_priority_takes_precedence(): void
    {
        $user = User::factory()->create();
        $request = app(RequestService::class)->createRequest([
            'latitude' => 37.5, 'longitude' => 127.0,
            'type' => 'other', 'priority' => RequestPriority::CRITICAL,
        ], $user);

        $this->assertSame(RequestType::OTHER, $request->type);
        $this->assertSame(RequestPriority::CRITICAL, $request->priority);
    }

    /** API store: type enum 검증 + 자동 priority */
    public function test_store_endpoint_accepts_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $res = $this->postJson('/api/requests', [
            'latitude' => 37.5, 'longitude' => 127.0, 'type' => 'emergency',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.type', 'emergency')
            ->assertJsonPath('data.priority', 'critical');
    }

    /** API store: 잘못된 type → 422 */
    public function test_store_rejects_invalid_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/requests', [
            'latitude' => 37.5, 'longitude' => 127.0, 'type' => 'nonsense',
        ])->assertStatus(422);
    }
}
