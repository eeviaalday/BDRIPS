<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory;

    protected $fillable = [
        'sector_name',
        'purok_name',
        'kagawad_user_id',
    ];

    public function kagawad(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kagawad_user_id');
    }

    public function residents(): HasMany
    {
        return $this->hasMany(ResidentProfile::class);
    }

    public function reliefDistributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class, 'sector_id');
    }
}
