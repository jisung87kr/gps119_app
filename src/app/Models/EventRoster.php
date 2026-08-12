<?php

namespace App\Models;

use App\Enums\EventRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 행사 사전명단 1행 — 「이 전화번호로 들어오면 이 역할」.
 *
 * 참가(EventParticipant)와 다르다. 명단은 «예정»이고 참가는 «사실»이다.
 * 명단은 입장 시 소진되며(claimed_at), 소진 후에도 지워지지 않는다 — 누가 명단에
 * 있었는지는 행사 기록이다.
 */
class EventRoster extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'phone', 'name', 'role', 'user_id', 'claimed_at'];

    protected $casts = [
        'role' => EventRole::class,
        'claimed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 아직 입장하지 않은 명단 — 행사 시작 전 점검 대상. */
    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->whereNull('claimed_at');
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * 전화번호로 명단 1행 조회.
     *
     * 🔑 호출자가 정규화된 번호를 넘긴다는 전제다. 이 테이블에는 «숫자만» 저장되므로
     *    원문(`010-1234-5678`)으로 찾으면 있는 사람을 못 찾는다 — 그러면 운영진이
     *    조용히 «참가자»로 들어오고, 아무도 그 사실을 모른다.
     */
    public static function findByPhone(int $projectId, string $normalizedPhone): ?self
    {
        return static::forProject($projectId)->where('phone', $normalizedPhone)->first();
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
