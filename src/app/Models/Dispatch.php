<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 지령(출동) (SPEC-03c).
 *
 * 전이 브로드캐스트는 모델 booted() 훅이 아니라 DispatchService 가 명시 발행한다(훅 남발 방지).
 */
class Dispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'project_id', 'assigned_by', 'paramedic_id',
        'status', 'note', 'reject_reason',
        'assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'completed_at', 'rejected_at',
    ];

    protected $casts = [
        'status' => DispatchStatus::class,
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function paramedic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paramedic_id');
    }

    /** 활성(진행) 지령만. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DispatchStatus::ASSIGNED->value,
            DispatchStatus::ACCEPTED->value,
            DispatchStatus::EN_ROUTE->value,
            DispatchStatus::ARRIVED->value,
        ]);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->paramedic_id === $user->id;
    }
}
