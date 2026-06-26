<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Events\RequestCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'latitude',
        'longitude',
        'address',
        'description',
        'type',
        'status',
        'priority',
        'contact_phone',
        'assigned_rescuer_id',
        'requested_at',
        'responded_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => RequestStatus::class,
        'priority' => RequestPriority::class,
        'type' => RequestType::class,
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Request $request) {
            $request->load('user');
            RequestCreated::dispatch($request);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedRescuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_rescuer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /**
     * 현재 활성(진행) 지령 1건 (SPEC-03d). 활성 지령 1건 불변식과 연동.
     */
    public function activeDispatch(): HasOne
    {
        return $this->hasOne(Dispatch::class)
            ->whereIn('status', [
                \App\Enums\DispatchStatus::ASSIGNED->value,
                \App\Enums\DispatchStatus::ACCEPTED->value,
                \App\Enums\DispatchStatus::EN_ROUTE->value,
                \App\Enums\DispatchStatus::ARRIVED->value,
            ])
            ->latestOfMany();
    }

    public function scopePending($query)
    {
        return $query->where('status', RequestStatus::PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', RequestStatus::IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', RequestStatus::COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', RequestStatus::CANCELLED);
    }

    public function scopeByPriority($query, RequestPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [RequestStatus::PENDING, RequestStatus::IN_PROGRESS]);
    }
}
