<?php

namespace Tests\Feature;

use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 푸시 수신 통로 등록/해제 (mobile-app N1).
 *
 * 토큰은 «이 기기에 푸시를 보낼 수 있는 자격증명»이라, 이 파일의 절반은
 * 기능이 아니라 «토큰이 어디로 새지 않는가»를 본다.
 */
class DeviceTokenApiTest extends TestCase
{
    use RefreshDatabase;

    /** 실제 브라우저가 만드는 형식 — 비압축 P-256 점 65바이트 / 인증 시크릿 16바이트. */
    private const P256DH = 'BLBp8HPFqpO7-5A5LYAst0PpZhMm_vBliL_ivSQ2ovTgNNcXDkIM9HHmCB2qXgXmIfiQzStsvGiP9VFA_55rJ3M';

    private const AUTH = 'WSQQVi70R0dbW8G6Ztj6kQ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function webPayload(string $token = 'https://fcm.googleapis.com/wp/abc123'): array
    {
        return [
            'platform' => 'web',
            'token' => $token,
            'keys' => ['p256dh' => self::P256DH, 'auth' => self::AUTH],
        ];
    }

    public function test_registers_a_web_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/devices', $this->webPayload())
            ->assertCreated()
            ->assertJsonPath('data.platform', 'web');

        $device = DeviceToken::firstOrFail();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame(PushPlatform::WEB, $device->platform);
        $this->assertSame(self::P256DH, $device->keys['p256dh']);
        $this->assertNull($device->revoked_at);
    }

    public function test_stores_a_hash_for_lookup(): void
    {
        $user = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/hash-me';

        $this->actingAs($user)->postJson('/api/devices', $this->webPayload($token))->assertCreated();

        $this->assertSame(
            hash('sha256', $token),
            DeviceToken::firstOrFail()->token_hash,
            '조회·중복판정은 해시로 한다 — 원문에 인덱스를 걸면 로그에 자격증명이 남는 습관이 되살아난다.'
        );
    }

    public function test_registering_the_same_token_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/devices', $this->webPayload())->assertCreated();
        $this->actingAs($user)->postJson('/api/devices', $this->webPayload())->assertCreated();

        $this->assertSame(1, DeviceToken::count(), '같은 기기가 두 행이 되면 알림이 두 번 온다');
    }

    public function test_a_revoked_token_is_revived_on_re_registration(): void
    {
        $user = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/revived';
        DeviceToken::factory()->revoked()->create([
            'user_id' => $user->id,
            'token' => $token,
            'token_hash' => DeviceToken::hashFor($token),
        ]);

        $this->actingAs($user)->postJson('/api/devices', $this->webPayload($token))->assertCreated();

        $this->assertNull(DeviceToken::firstOrFail()->revoked_at);
        $this->assertSame(1, DeviceToken::count());
    }

    public function test_a_handed_over_device_follows_its_new_owner(): void
    {
        // 같은 기기에서 다른 사람이 로그인한 경우. 새 행을 만들면 이전 사용자에게
        // 계속 푸시가 간다 — 남의 지령 알림을 받는 것은 개인정보 사고다.
        $first = User::factory()->create();
        $second = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/shared-device';

        $this->actingAs($first)->postJson('/api/devices', $this->webPayload($token))->assertCreated();
        $this->actingAs($second)->postJson('/api/devices', $this->webPayload($token))->assertCreated();

        $this->assertSame(1, DeviceToken::count());
        $this->assertSame($second->id, DeviceToken::firstOrFail()->user_id);
    }

    public function test_a_web_subscription_without_keys_is_rejected(): void
    {
        // 키 없는 웹 구독은 암호화가 불가능해 «등록은 되지만 영영 안 가는» 통로가 된다.
        $this->actingAs(User::factory()->create())
            ->postJson('/api/devices', ['platform' => 'web', 'token' => 'https://x/y'])
            ->assertStatus(422);

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_an_app_token_needs_no_keys(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/devices', [
                'platform' => 'android',
                'token' => str_repeat('a', 163),
                'app_version' => '1.0.0',
            ])->assertCreated();

        $this->assertNull(DeviceToken::firstOrFail()->keys);
    }

    public function test_unknown_platform_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/devices', ['platform' => 'blackberry', 'token' => 'x'])
            ->assertStatus(422);
    }

    public function test_guests_cannot_register(): void
    {
        $this->postJson('/api/devices', $this->webPayload())->assertStatus(401);
    }

    public function test_unregistering_revokes_the_device(): void
    {
        $user = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/bye';
        $this->actingAs($user)->postJson('/api/devices', $this->webPayload($token))->assertCreated();

        $this->actingAs($user)->deleteJson('/api/devices/current', ['token' => $token])->assertOk();

        $this->assertNotNull(DeviceToken::firstOrFail()->revoked_at);
    }

    public function test_cannot_revoke_someone_elses_device(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/not-yours';
        $this->actingAs($owner)->postJson('/api/devices', $this->webPayload($token))->assertCreated();

        // 응답은 성공으로 보이지만(토큰 존재 여부를 흘리지 않는다) 폐기되면 안 된다.
        $this->actingAs($attacker)->deleteJson('/api/devices/current', ['token' => $token])->assertOk();

        $this->assertNull(DeviceToken::firstOrFail()->revoked_at, '남의 기기가 폐기됐다');
    }

    public function test_a_malformed_web_key_is_rejected(): void
    {
        // 길이가 틀린 키는 암호화 단계에서 실패하고, 그 실패는 네트워크 장애와
        // 구분되지 않아 «영영 성공 못 할 통로를 영원히 재시도»하게 된다.
        // 실제로 tinker 로 보내보고 확인한 동작이다.
        $this->actingAs(User::factory()->create())
            ->postJson('/api/devices', [
                'platform' => 'web',
                'token' => 'https://fcm.googleapis.com/wp/x',
                'keys' => ['p256dh' => 'too-short', 'auth' => self::AUTH],
            ])->assertStatus(422);

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_the_raw_token_is_never_returned(): void
    {
        $user = User::factory()->create();
        $token = 'https://fcm.googleapis.com/wp/secret-token-value';

        $response = $this->actingAs($user)->postJson('/api/devices', $this->webPayload($token));

        $response->assertCreated();
        $this->assertStringNotContainsString($token, $response->getContent());
    }
}
