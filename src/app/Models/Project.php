<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'is_active',
        'status',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
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
                    $project->slug = 'project-' . Str::random(8);
                }

                // slug가 중복되는 경우 번호 추가
                $originalSlug = $project->slug;
                $count = 1;
                while (static::where('slug', $project->slug)->exists()) {
                    $project->slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }

            // status 자동 설정
            $project->status = $project->getComputedStatus();
        });
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('status', 'active');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
