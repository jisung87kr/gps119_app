<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

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
     * Get the formatted phone number.
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        // Remove any existing formatting
        $cleaned = preg_replace('/[^0-9]/', '', $this->phone);

        // Format as 010-0000-0000
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '010')) {
            return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 4) . '-' . substr($cleaned, 7, 4);
        }

        // Return original value if it doesn't match expected format
        return $this->phone;
    }

    /**
     * Get the raw phone number (numbers only).
     */
    public function getRawPhoneAttribute(): ?string
    {
        if (!$this->phone) {
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
