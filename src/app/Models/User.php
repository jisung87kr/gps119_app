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
