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
     * 현재 «책임자»인 활성 지령 1건 — 주담당 우선 (SPEC-03d, ADR-0007 D4).
     *
     * 🔑 다중 배차 이후 한 신고에는 활성 지령이 여럿일 수 있다. 이 관계는 그중 «누가
     *    이 환자를 책임지는가»에 답한다 — 담당자 이름·전화(신고자 화면), 리포트 CSV 의
     *    담당자 칸이 전부 여기를 읽는다. 보조가 최신이라는 이유로 그 자리에 올라오면
     *    신고자는 주담당이 아닌 사람에게 전화를 건다.
     *
     * ⚠️ latestOfMany() 를 쓰지 않는다. one-of-many 서브쿼리로는 「is_primary 우선,
     *    동률이면 최신」을 표현하려면 제약을 서브쿼리와 바깥 쿼리에 «두 벌» 써야 하고,
     *    한쪽만 고치면 조용히 어긋난다. 정렬만으로도 지연로딩(first)과 이거로딩(키별 첫 행)
     *    모두 같은 행을 고른다.
     */
    public function activeDispatch(): HasOne
    {
        return $this->hasOne(Dispatch::class)
            ->whereIn('status', \App\Enums\DispatchStatus::activeValues())
            ->orderByDesc('is_primary')
            ->orderByDesc('id');
    }

    /**
     * 현재 활성 지령 «전부» (주담당 먼저, 그다음 최신순). ADR-0007 D4.
     */
    public function activeDispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class)
            ->whereIn('status', \App\Enums\DispatchStatus::activeValues())
            ->orderByDesc('is_primary')
            ->orderByDesc('id');
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

    /**
     * 이 신고를 «볼 수» 있는 사람인가 — 열람 권한의 단일 출처.
     *
     * 🔴 이 메서드가 생긴 이유: 웹 라우트 `GET /requests/{request}` 에 `auth` 말고는
     *    아무 검사가 없었다. 로그인만 하면 id 를 바꿔가며 **남의 신고 좌표·주소·담당
     *    대원 연락처를 그대로 읽을 수 있었다.** API 쪽(RequestService::getRequestById)
     *    에는 있던 검사가 웹에만 빠져 있었고, 규칙이 두 군데라 한쪽이 조용히 비었다.
     *    이제 양쪽이 여기를 읽는다.
     *
     * 볼 수 있는 사람:
     *  - 신고자 본인 (상태 추적)
     *  - 시스템 관리자
     *  - 그 행사의 상황실(controller) — 관제 판단에 필요
     *  - 그 신고에 배정된(또는 배정됐던) 대원 — 자기 출동 건이다
     *
     * 「모든 신고 열람」은 관리자만이다(2026-08-12). 예전에는 시스템 롤 rescuer 도
     * 전부 볼 수 있었는데, 그 롤이 사라지면서 상시 인력은 「상시 운영」 행사의 구급대가
     * 됐다 — 자기에게 배정된 건과 자기 행사만 보면 된다.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isOwner($user) || $user->hasRole('admin')) {
            return true;
        }

        if ($this->project && $user->eventRoleIn($this->project)?->canDispatch()) {
            return true;
        }

        return $this->dispatches()->where('paramedic_id', $user->id)->exists();
    }
}
