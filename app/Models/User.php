<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_verified',
    'abandoned_count',
    'restricted_until',
    'assigned_sector_id',
    // Captured by the registration wizard, before any account exists.
    'date_of_birth',
    'gender',
    'civil_status',
    'nationality',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SECRETARY = 'secretary';

    public const ROLE_KAGAWAD = 'kagawad';

    public const ROLE_RESIDENT = 'resident';

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'abandoned_count' => 'integer',
            'restricted_until' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public function residentProfile(): HasOne
    {
        return $this->hasOne(ResidentProfile::class);
    }

    public function assignedSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'assigned_sector_id');
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function reliefKagawadSectors(): HasMany
    {
        return $this->hasMany(Sector::class, 'kagawad_user_id');
    }

    public function reliefLogs(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class, 'logged_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSecretary(): bool
    {
        return $this->role === self::ROLE_SECRETARY;
    }

    public function isKagawad(): bool
    {
        return $this->role === self::ROLE_KAGAWAD;
    }

    public function isResident(): bool
    {
        return $this->role === self::ROLE_RESIDENT;
    }

    public function isRestricted(): bool
    {
        return $this->restricted_until !== null && $this->restricted_until->isFuture();
    }
}
