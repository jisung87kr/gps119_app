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
        'cancelled_by',
        'cancel_reason',
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
        // ADR-0005: 모든 신고는 행사에 소속. 행사 미지정 신고는 "상시 운영" 기본 행사로 귀속.
        static::creating(function (Request $request) {
            if (empty($request->project_id)) {
                $request->project_id = \App\Models\Project::defaultEvent()->id;
            }
        });

        // ⚠️ 여기에 RequestCreated 발행이 있었다. RequestService::createRequest 로 옮겼다.
        //    모델 훅은 «행이 저장됐다»만 알 뿐 «구조요청이 접수됐다»와 구분하지 못해서,
        //    팩토리·시드가 행을 하나 만들 때마다 관제 브로드캐스트와 통지가 같이 나갔다.
        //    위의 creating 훅은 남는다 — 기본 행사 귀속은 경로와 무관한 «불변식»이다.
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedRescuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_rescuer_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
