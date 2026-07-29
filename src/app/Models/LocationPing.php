<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 위치 ping 이력 (SPEC-03b). append-only.
 */
class LocationPing extends Model
{
    use HasFactory;

    public $timestamps = false; // recorded_at 단일 시각

    protected $fillable = [
        'project_id', 'user_id', 'latitude', 'longitude',
        'accuracy', 'heading', 'speed', 'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
