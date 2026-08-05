<?php

namespace App\Models;

use App\Enums\PushPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 푸시 수신 통로 1건 (mobile-app N1).
 *
 * 원문 토큰을 다루는 규칙이 이 클래스에 모여 있다 — 밖에서 `token_hash` 를
 * 직접 계산하지 않는다. 해시 방식이 갈리는 순간 같은 기기가 두 행이 된다.
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'token',
        'token_hash',
        'keys',
        'app_version',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'platform' => PushPlatform::class,
        'keys' => 'array',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * 로그·직렬화에 원문 토큰이 새지 않도록 기본 숨김.
     * 발송 코드는 `$token->token` 으로 «명시적으로» 꺼내 쓴다.
     */
    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 토큰 원문 → 조회용 해시. 단일 출처.
     */
    public static function hashFor(string $token): string
    {
        return hash('sha256', $token);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 기기 등록/갱신. 같은 토큰이 다시 오면 되살린다.
     *
     * 🔑 `token_hash` 기준으로 찾는 이유가 둘이다.
     *   1) 기기 재로그인으로 «주인이 바뀐» 토큰을 새 행으로 만들지 않는다.
     *      안 그러면 이전 사용자에게 계속 푸시가 간다 — 개인위치정보가 걸린 도메인에서
     *      남의 지령 알림을 받는 것은 사고다.
     *   2) 폐기됐던 행(revoked_at)이 재구독으로 되살아난다.
     */
    public static function register(
        User $user,
        PushPlatform $platform,
        string $token,
        ?array $keys = null,
        ?string $appVersion = null
    ): self {
        $device = static::firstOrNew(['token_hash' => static::hashFor($token)]);

        $device->fill([
            'user_id' => $user->id,
            'platform' => $platform,
            'token' => $token,
            'keys' => $keys,
            'app_version' => $appVersion,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        return $device;
    }

    /**
     * 폐기. 행은 남긴다(재구독 시 되살아나고, 이력이 남는다).
     */
    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }
}
