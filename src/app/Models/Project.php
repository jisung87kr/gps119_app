<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'join_code',
        'description',
        'start_date',
        'end_date',
        'is_active',
        'is_default',
        'status',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name);

                // 한글 등으로 slug가 빈 문자열이 된 경우 랜덤 문자열 생성
                if (empty($project->slug)) {
                    $project->slug = 'project-'.Str::random(8);
                }

                // slug가 중복되는 경우 번호 추가
                $originalSlug = $project->slug;
                $count = 1;
                while (static::where('slug', $project->slug)->exists()) {
                    $project->slug = $originalSlug.'-'.$count;
                    $count++;
                }
            }

            // join_code 자동 발급(slug 발급 직후). 충돌 시 재생성 루프.
            if (empty($project->join_code)) {
                $project->join_code = static::generateUniqueJoinCode();
            }

            // status 자동 설정
            $project->status = $project->getComputedStatus();
        });

        // ADR-0005: 기본 행사("상시 운영")는 삭제 불가 — 일반 신고의 귀속처.
        static::deleting(function ($project) {
            if ($project->is_default) {
                return false;
            }
        });
    }

    /**
     * ADR-0005: "상시 운영" 기본 행사. 행사 미지정 신고의 귀속처(항상 활성).
     * 없으면 생성한다(멱등). created_by 는 admin 우선, 없으면 아무 유저.
     */
    public static function defaultEvent(): self
    {
        return static::firstOrCreate(
            ['is_default' => true],
            [
                'name' => '상시 운영',
                'description' => '행사에 속하지 않은 일반 구조요청이 귀속되는 기본 행사(항상 활성).',
                'start_date' => now()->subYear(),
                'end_date' => now()->addYears(50),
                'is_active' => true,
                'created_by' => \App\Models\User::role('admin')->value('id') ?? \App\Models\User::query()->value('id'),
            ]
        );
    }

    /**
     * 입장 코드 생성: 6자리 대문자 영숫자(혼동문자 0/O/1/I 제외), 유니크 보장.
     */
    public static function generateUniqueJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 0,O,1,I 제외

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('join_code', $code)->exists());

        return $code;
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests()
    {
        return $this->hasMany(Request::class);
    }

    public function participants()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class);
    }

    // Accessors & Mutators
    public function getIsActiveAttribute($value)
    {
        // 종료일이 지났으면 자동으로 비활성화
        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return $value;
    }

    // Helper Methods
    public function getComputedStatus()
    {
        $now = now();

        if ($this->start_date > $now) {
            return 'pending';
        } elseif ($this->end_date < $now) {
            return 'completed';
        } else {
            return 'active';
        }
    }

    public function updateStatus()
    {
        $this->status = $this->getComputedStatus();
        $this->save();
    }

    public function isActive()
    {
        return $this->is_active && $this->status === 'active';
    }

    public function getUrl()
    {
        return route('request.create.project', ['slug' => $this->slug]);
    }

    /**
     * 행사 «입장» 링크 — QR 이 담는 주소 (2026-08-12 일원화).
     *
     * 🔑 getUrl() 은 신고 작성 화면으로 «직행»한다. 그 QR 을 찍은 사람은 행사에 들어오지
     *    않으므로 역할도, 위치 공유도, 관제 지도 표시도 없다. 현장에 QR 을 두 개 붙이면
     *    반드시 헷갈리므로 QR 은 입장 링크 하나로 통일한다 — 들어온 뒤 신고 화면은
     *    앱 안에서 바로 갈 수 있어 기능 손실이 없다.
     */
    public function getJoinUrl(): string
    {
        return route('events.join.code', ['joinCode' => $this->join_code]);
    }

    // Scopes
    public function scopeActive($query)
    {
        // 자동 비활성화(getIsActiveAttribute)·getComputedStatus 와 일치하도록 날짜로 판정한다.
        // status 컬럼은 생성 시점값이라 종료된 행사에 'active'로 stale하게 남으므로 신뢰하지 않는다.
        $now = now();

        return $query->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
