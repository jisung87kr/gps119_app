<?php

namespace App\Models;

use App\Enums\EventRole;
use App\Enums\LocationPermission;
use App\Enums\ParticipantStatus;
use App\Enums\TrackingState;
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
        'sharing_location', 'location_permission', 'location_permission_at',
        'last_lat', 'last_lng', 'last_accuracy', 'joined_at', 'last_entered_at', 'last_seen_at',
    ];

    protected $casts = [
        'role' => EventRole::class,
        'status' => ParticipantStatus::class,
        'sharing_location' => 'boolean',
        'location_permission' => LocationPermission::class,
        'location_permission_at' => 'datetime',
        'last_lat' => 'decimal:8',
        'last_lng' => 'decimal:8',
        'joined_at' => 'datetime',
        'last_entered_at' => 'datetime',
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

    /**
     * 관제가 볼 «위치 추적 상태» — 의도·능력·증거 세 축을 여기서 «한 번만» 조합한다 (M-5).
     *
     * 🔑 **화면에서 다시 조합하지 않는다.** N0 의 0-8 이 그 실패였다 — 같은 판단이
     *    PHP 와 JS 두 벌로 있다가 어긋났고, 「PHP 가 단일 출처」라는 주석과 달리
     *    실질 출처는 JS 였다. 축이 셋이면 그 위험이 그만큼 커진다.
     *
     * 판정 순서에 의미가 있다:
     *
     *   1. 공유를 껐으면 나머지는 볼 필요가 없다 (OFF — 정상 상태다)
     *   2. 보고가 «없으면» 판정하지 않는다 (UNKNOWN)
     *      🔴 null 을 「권한 없음」으로 읽으면 웹으로 잘 쓰는 사람이 전부 붉게 뜬다
     *   3. 권한이 막혔으면 BLOCKED — 참가자는 보이는 줄 알지만 한 번도 안 보였다
     *   4. 「앱 사용 중만」은 FOREGROUND_ONLY. 여기서 신선도를 «안» 본다 —
     *      화면을 닫아 끊긴 것은 고장이 아니라 그 권한의 정상 동작이고,
     *      STALE 로 올리면 진짜 이상과 구분이 안 된다
     *   5. 항상 허용인데 안 들어오면 STALE (네트워크·배터리·앱 종료)
     */
    public function trackingState(int $onlineThresholdSeconds = 60): TrackingState
    {
        if (! $this->sharing_location) {
            return TrackingState::OFF;
        }

        if ($this->location_permission === null) {
            return TrackingState::UNKNOWN;
        }

        if ($this->location_permission->blocksTracking()) {
            return TrackingState::BLOCKED;
        }

        if (! $this->location_permission->allowsBackground()) {
            return TrackingState::FOREGROUND_ONLY;
        }

        return $this->isOnline($onlineThresholdSeconds)
            ? TrackingState::TRACKING
            : TrackingState::STALE;
    }
}
