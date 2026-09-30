<?php

namespace App\Models;

use Database\Factories\ReliefBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReliefBatch extends Model
{
    /** @use HasFactory<ReliefBatchFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title',
        'description',
        'items_included',
        'distribution_date',
        'status',
        'created_by_user_id',
    ];

    public function casts(): array
    {
        return [
            'items_included' => 'array',
            'distribution_date' => 'date',
        ];
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class);
    }

    public function getItemsAttribute(): array
    {
        return is_array($this->items_included) ? $this->items_included : [];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
