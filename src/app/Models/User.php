<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'provider',
        'provider_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'formatted_phone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'assigned_rescuer_id');
    }

    /**
     * 이 사용자의 행사 참가 이력 (SPEC-03d).
     */
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /**
     * 해당 행사에서의 역할 — active 참가만 반환, 아니면 null (SPEC-03d).
     *
     * 채널 인가·미들웨어가 공통으로 쓰는 단일 진입점(중복 쿼리 방지).
     */
    public function eventRoleIn(Project $project): ?EventRole
    {
        $participant = $this->eventParticipations()
            ->where('project_id', $project->id)
            ->where('status', ParticipantStatus::ACTIVE)
            ->first();

        return $participant?->role;
    }

    /**
     * 지금 «활성 행사»에서 가진 역할 중 가장 높은 것 (없으면 null).
     *
     * 화면 구성(하단 탭·착지)의 판정 기준이다. 시스템 롤이 아니라 행사 역할을 보는 이유는
     * 행사가 끝나면 그 역할도 끝나기 때문이다 — 구급대원도 비번기엔 평범한 사용자다.
     *
     * 우선순위는 「지금 이 사람이 하는 일」 순: 상황실 > 구급대 > 그 외.
     */
    public function activeEventRole(): ?EventRole
    {
        $roles = $this->eventParticipations()
            ->where('status', ParticipantStatus::ACTIVE)
            ->whereHas('project', fn ($q) => $q->active())
            ->pluck('role')
            ->map(fn ($r) => $r instanceof EventRole ? $r : EventRole::tryFrom($r))
            ->filter();

        foreach ([EventRole::CONTROLLER, EventRole::PARAMEDIC] as $priority) {
            if ($roles->contains($priority)) {
                return $priority;
            }
        }

        return $roles->first();
    }

    /**
     * 지금 참가 중인 «실제» 행사가 정확히 하나면 그 행사 (아니면 null).
     *
     * 세 곳이 같은 질문을 한다 — 신고를 어느 행사에 붙일지(RequestService), 어디로
     * 착지시킬지(LandingResolver), 「구조요청」 탭을 어디로 보낼지(tab-bar).
     * 규칙이 흩어지면 한쪽만 고쳐져서 어긋난다.
     *
     * ⚠️ 「상시 운영」(is_default)은 «폴백 자리»이지 선택지가 아니다. 항상 활성이라
     *    여기서 세면, 상시 운영에 속한 사람이 실제 행사에 들어가는 순간 «2개»가 되어
     *    조용히 폴백된다.
     */
    public function soleActiveEvent(): ?Project
    {
        $projects = Project::query()
            ->active()
            ->where('is_default', false)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $this->id)
                ->where('status', ParticipantStatus::ACTIVE))
            ->get();

        return $projects->count() === 1 ? $projects->first() : null;
    }

    /**
     * 이 사람의 홈이 «출동 현황»인가 (= 구급 쪽 사람인가).
     *
     * 하단 탭과 /dashboard 리다이렉트가 같은 판정을 써야 한다 — 둘이 어긋나면
     * 탭이 보내는 곳과 실제로 열리는 곳이 달라진다.
     */
    public function usesDispatchHome(): bool
    {
        // 🔑 canReceiveDispatch()(구급대 + 자원봉사구급)가 아니라 isDispatchCandidate()
        //    (구급대만)로 판정한다. 자원봉사(구급)는 배정 후보에서 빠졌으므로 새 지령이
        //    갈 일이 없고, 현장 요구도 「자원봉사(구급) → 구조요청 화면」이다.
        //    자격(canReceiveDispatch)은 진행 중인 지령 화면 접근용으로만 남는다.
        return (bool) $this->activeEventRole()?->isDispatchCandidate();
    }

    /**
     * 지금 «위치를 공유 중»인 행사 참가 1건 (없으면 null).
     *
     * 위치 송신을 특정 화면에 묶어 두면, 그 화면을 떠나는 순간 watchPosition 이 죽어
     * 관제 지도의 그 사람 좌표가 그 자리에서 얼어붙는다. 실제로 일반 참가자는 행사
     * 입장 시각의 좌표 한 건으로 남아 있었다. 셸(레이아웃)이 이 값을 읽어, 사용자가
     * 앱 안 어느 화면에 있든 공유가 이어지게 한다.
     *
     * 🔑 «본인이 켠» 공유만 이어붙인다(sharing_location=true). 여기서 플래그를 켜지
     *    않는다 — 동의는 활동 화면에서 받는 것이고, 셸은 그 결정을 따를 뿐이다.
     */
    public function sharingParticipation(): ?EventParticipant
    {
        return $this->eventParticipations()
            ->where('status', ParticipantStatus::ACTIVE)
            ->where('sharing_location', true)
            ->whereHas('project', fn ($q) => $q->active())
            ->latest('last_seen_at')
            ->first();
    }

    /**
     * Get the formatted phone number.
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        // Remove any existing formatting
        $cleaned = preg_replace('/[^0-9]/', '', $this->phone);

        // Format as 010-0000-0000
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '010')) {
            return substr($cleaned, 0, 3).'-'.substr($cleaned, 3, 4).'-'.substr($cleaned, 7, 4);
        }

        // Return original value if it doesn't match expected format
        return $this->phone;
    }

    /**
     * Get the raw phone number (numbers only).
     */
    public function getRawPhoneAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $this->phone);
    }

    /**
     * Set the phone attribute (store without formatting).
     */
    public function setPhoneAttribute($value): void
    {
        // Store phone number without formatting (numbers only)
        $this->attributes['phone'] = preg_replace('/[^0-9]/', '', $value);
    }
}
