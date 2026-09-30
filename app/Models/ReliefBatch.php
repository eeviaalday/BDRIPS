<?php

namespace App\Models;

use App\Services\AuditLogger;
use Database\Factories\ReliefBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One relief distribution event.
 *
 * Open or closed. Closing is a status change rather than a delete, so the record
 * of what was handed out survives and a Kagawad can still read the history of a
 * finished batch.
 */
class ReliefBatch extends Model
{
    /** @use HasFactory<ReliefBatchFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title',
        'description',
        'items_included',
        'distribution_date',
        'status',
        'created_by_user_id',
        'closed_at',
    ];

    public function casts(): array
    {
        return [
            'items_included' => 'array',
            'distribution_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The goods, always as a list, so a view never has to handle the difference
     * between a stored list and a stored sentence.
     *
     * @return list<string>
     */
    public function items(): array
    {
        return is_array($this->items_included) ? array_values($this->items_included) : [];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Progress for this batch, as counts.
     *
     * Read in one grouped query rather than per sector, because the monitor page
     * shows every sector at once and N+1 counts here would be the slowest thing
     * on the screen.
     *
     * @return array{total: int, received: int, pending: int}
     */
    public function progress(): array
    {
        $counts = $this->distributions()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $received = (int) ($counts[ReliefDistribution::STATUS_RECEIVED] ?? 0);
        $total = $counts->sum();

        return [
            'total' => (int) $total,
            'received' => $received,
            'pending' => (int) $total - $received,
        ];
    }

    public function recordClosed(User $actor): void
    {
        app(AuditLogger::class)->log(
            actor: $actor,
            action: 'relief.batch_closed',
            subject: $this,
            description: sprintf('Relief batch "%s" was closed', $this->title),
        );
    }
}
