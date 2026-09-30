<?php

namespace App\Models;

use Database\Factories\ReliefDistributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliefDistribution extends Model
{
    /** @use HasFactory<ReliefDistributionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    protected $fillable = [
        'relief_batch_id',
        'household_number',
        'sector_id',
        'status',
        'received_by_name',
        'claimed_at',
        'logged_by_user_id',
    ];

    public function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ReliefBatch::class, 'relief_batch_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
