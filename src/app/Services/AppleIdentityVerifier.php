<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Sign in with Apple 의 identityToken 검증 (App Store 심사 4.8 대응, 2026-10-13).
 *
 * 🔑 앱이 넘겨준 값은 «경계»에서 한 번만 파싱한다. 이 클래스를 통과한 결과만 믿는다 —
 *    클라이언트가 함께 보낸 email·user 필드는 위조할 수 있으므로 쓰지 않는다.
 *
 * 검사하는 것: 서명(Apple JWKS, RS256) · iss · aud(앱 번들 ID) · exp · nonce.
 * nonce 는 서버가 세션에 발급한 일회용 값이라, 가로챈 토큰을 다른 세션에서 재사용할 수 없다.
 */
class AppleIdentityVerifier
{
    public const ISSUER = 'https://appleid.apple.com';
    public const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    private const JWKS_CACHE_KEY = 'apple.jwks';

    public function __construct(private readonly string $bundleId)
    {
    }

    /**
     * @return array{sub: string, email: ?string, email_verified: bool}
     *
     * @throws UnexpectedValueException 토큰이 유효하지 않을 때
     */
    public function verify(string $identityToken, string $expectedNonce): array
    {
        $claims = $this->decode($identityToken);

        if (($claims->iss ?? null) !== self::ISSUER) {
            throw new UnexpectedValueException('Apple 토큰 발급자가 다르다');
        }

        // aud 는 문자열이다(Apple 은 단일 aud). 다른 앱용 토큰을 들고 오는 것을 막는다.
        if (($claims->aud ?? null) !== $this->bundleId) {
            throw new UnexpectedValueException('Apple 토큰 대상(aud)이 이 앱이 아니다');
        }

        if ($expectedNonce === '' || ! hash_equals($expectedNonce, (string) ($claims->nonce ?? ''))) {
            throw new UnexpectedValueException('Apple 토큰 nonce 가 일치하지 않는다');
        }

        if (empty($claims->sub)) {
            throw new UnexpectedValueException('Apple 토큰에 sub 가 없다');
        }

        // email_verified 는 문자열 "true" 로 올 때가 있다.
        $verified = filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'sub' => (string) $claims->sub,
            'email' => isset($claims->email) ? (string) $claims->email : null,
            'email_verified' => $verified,
        ];
    }

    /**
     * JWKS 를 못 받으면 RuntimeException 이 «그대로» 올라간다 — 그건 토큰 탓이 아니라
     * Apple·네트워크 탓이므로 「잘못된 토큰」(UnexpectedValueException)과 섞지 않는다.
     */
    private function decode(string $token): object
    {
        $keys = $this->keySet();

        try {
            return $this->decodeWith($token, $keys);
        } catch (UnexpectedValueException $e) {
            // kid 를 못 찾으면 Apple 이 키를 돌린 것일 수 있다 — 캐시를 비우고 한 번만 다시 받는다.
            if (! str_contains($e->getMessage(), '"kid"')) {
                throw $e;
            }
        }

        Cache::forget(self::JWKS_CACHE_KEY);

        return $this->decodeWith($token, $this->keySet());
    }

    private function decodeWith(string $token, array $keys): object
    {
        try {
            return JWT::decode($token, JWK::parseKeySet($keys, 'RS256'));
        } catch (UnexpectedValueException $e) {
            throw $e;
        } catch (Throwable $e) {
            // 만료(ExpiredException)·서명 불일치·형식 오류 전부 「잘못된 토큰」이다.
            throw new UnexpectedValueException('Apple 토큰 해석 실패: '.$e->getMessage(), 0, $e);
        }
    }

    /** @return array{keys: array} */
    private function keySet(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, now()->addDay(), function () {
            $response = Http::timeout(5)->get(self::JWKS_URL);

            if (! $response->ok() || ! is_array($response->json('keys'))) {
                // 캐시에 실패를 담지 않도록 던진다(remember 는 예외 시 저장하지 않는다).
                throw new RuntimeException('Apple 공개키(JWKS)를 받지 못했다: HTTP '.$response->status());
            }

            return $response->json();
        });
    }
}
