<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sign in with Apple (App Store 심사 4.8, 2026-10-13).
 *
 * Apple 대신 테스트가 만든 RSA 키로 토큰을 서명하고, JWKS 응답을 그 공개키로 가짜로 둔다.
 * 그래서 «서명 검증»까지 실제로 돈다 — 검증을 빼먹는 회귀는 여기서 걸린다.
 */
class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const BUNDLE_ID = 'kr.co.gps119.app';
    private const KID = 'test-kid';

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.apple.bundle_id' => self::BUNDLE_ID]);
        Cache::forget('apple.jwks');

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->privateKey = $pem;
        $details = openssl_pkey_get_details($key);

        $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'kid' => self::KID,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $b64($details['rsa']['n']),
                'e' => $b64($details['rsa']['e']),
            ]]]),
        ]);
    }

    private function nonce(): string
    {
        return $this->postJson('/auth/apple/nonce')->assertOk()->json('data.nonce');
    }

    private function token(array $overrides = [], ?string $privateKey = null): string
    {
        $claims = array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::BUNDLE_ID,
            'exp' => time() + 600,
            'iat' => time(),
            'sub' => '001234.apple-user.0001',
            'email' => 'abc@privaterelay.appleid.com',
            'email_verified' => 'true',
        ], $overrides);

        return JWT::encode($claims, $privateKey ?? $this->privateKey, 'RS256', self::KID);
    }

    public function test_valid_token_creates_user_logs_in_and_asks_for_consent(): void
    {
        $nonce = $this->nonce();

        $res = $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => $nonce]),
            'given_name' => '길동',
            'family_name' => '홍',
        ]);

        $res->assertOk()->assertJsonPath('data.redirect', route('consent.show'));

        $user = User::where('provider', 'apple')->firstOrFail();
        $this->assertSame('001234.apple-user.0001', $user->provider_id);
        $this->assertSame('홍길동', $user->name);
        $this->assertNull($user->phone);
        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_user_is_found_by_sub_not_duplicated(): void
    {
        $existing = User::factory()->create(['provider' => 'apple', 'provider_id' => '001234.apple-user.0001']);

        $nonce = $this->nonce();
        $this->postJson('/auth/apple/native', [
            // 재로그인 때 Apple 은 이메일·이름을 다시 주지 않는다.
            'identity_token' => $this->token(['nonce' => $nonce, 'email' => null]),
        ])->assertOk();

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::where('provider', 'apple')->count());
    }

    public function test_wrong_nonce_is_rejected(): void
    {
        $this->nonce();

        $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => 'someone-elses-nonce']),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_nonce_is_single_use(): void
    {
        $nonce = $this->nonce();
        $token = $this->token(['nonce' => $nonce]);

        $this->postJson('/auth/apple/native', ['identity_token' => $token])->assertOk();
        auth()->logout();

        // 같은 토큰을 다시 보내도 세션의 nonce 는 이미 소비됐다.
        $this->postJson('/auth/apple/native', ['identity_token' => $token])->assertStatus(422);
    }

    public function test_token_for_another_app_is_rejected(): void
    {
        $nonce = $this->nonce();

        $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => $nonce, 'aud' => 'com.example.other']),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_expired_token_is_rejected(): void
    {
        $nonce = $this->nonce();

        $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => $nonce, 'exp' => time() - 3600, 'iat' => time() - 7200]),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_token_signed_by_another_key_is_rejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPem);

        $nonce = $this->nonce();

        $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => $nonce], $otherPem),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_unverified_email_never_links_to_an_existing_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);

        $nonce = $this->nonce();
        $this->postJson('/auth/apple/native', [
            'identity_token' => $this->token(['nonce' => $nonce, 'email' => 'victim@example.com', 'email_verified' => 'false']),
        ])->assertOk();

        $this->assertNotSame($victim->id, auth()->id());
        $this->assertNull($victim->fresh()->provider);
    }

    public function test_login_screen_carries_hidden_apple_button(): void
    {
        // 버튼은 서버가 항상 그리되 hidden 이다 — iOS 앱(플러그인 있는 빌드)에서만 JS 가 연다.
        $this->get('/login')->assertOk()->assertSee('data-apple-signin hidden', false);
        $this->get('/register')->assertOk()->assertSee('data-apple-signin hidden', false);
    }
}
