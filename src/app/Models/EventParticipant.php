<?php

namespace App\Models;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 행사 참가자 (SPEC-03a).
 */
class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'user_id', 'role', 'status',
        'sharing_location', 'last_lat', 'last_lng', 'last_accuracy', 'joined_at', 'last_seen_at',
    ];

    protected $casts = [
        'role' => EventRole::class,
        'status' => ParticipantStatus::class,
        'sharing_location' => 'boolean',
        'last_lat' => 'decimal:8',
        'last_lng' => 'decimal:8',
        'joined_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 활동중(active) 참가만.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ParticipantStatus::ACTIVE);
    }

    /**
     * 특정 행사 스코프.
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * 지령 수령 «자격»이 있는 역할(구급대/자원봉사 구급). 채널·화면 접근용.
     */
    public function scopeReceivers(Builder $query): Builder
    {
        return $query->whereIn('role', [EventRole::PARAMEDIC->value, EventRole::VOLUNTEER_MEDIC->value]);
    }

    /**
     * 새 지령의 배정 «후보»(구급대만). EventRole::isDispatchCandidate() 와 짝.
     */
    public function scopeDispatchCandidates(Builder $query): Builder
    {
        return $query->where('role', EventRole::PARAMEDIC->value);
    }

    /**
     * 온라인 여부 — last_seen_at 가 임계초 이내인지.
     */
    public function isOnline(int $thresholdSeconds = 60): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subSeconds($thresholdSeconds));
    }
}
