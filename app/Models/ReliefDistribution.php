<?php

namespace App\Models;

use App\Services\AuditLogger;
use Database\Factories\ReliefDistributionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One household's place in one relief batch.
 *
 * sector_id is the authorisation anchor for the Kagawad area. A Kagawad may
 * only see and act on rows whose sector is one they are assigned to through
 * sectors.kagawad_user_id, so every read and every write in that area is scoped
 * through scopeForSector(). The column is denormalised onto the row rather than
 * joined through the resident profile so that scope is a single indexed
 * predicate that cannot be bypassed by an eager load elsewhere.
 */
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
        'logged_by_user_id',
        'claimed_at',
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

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Restrict to the sectors a Kagawad is responsible for.
     *
     * Takes the sector ids rather than the user so a caller cannot pass a user
     * and have the scope quietly widen: the set of sectors is resolved once, by
     * the controller that is allowed to resolve it, and only the ids travel.
     *
     * @param  Builder<ReliefDistribution>  $query
     * @param  list<int>  $sectorIds
     * @return Builder<ReliefDistribution>
     */
    public function scopeForSectors(Builder $query, array $sectorIds): Builder
    {
        return $query->whereIn('sector_id', $sectorIds);
    }

    /**
     * One household's relief history, newest batch first.
     *
     * Every reader of a household's relief record goes through here: the
     * resident's own page and the Admin correction screen. Two copies of this
     * query is how those two would come to disagree about what a household
     * number returns.
     *
     * @param  Builder<ReliefDistribution>  $query
     * @return Builder<ReliefDistribution>
     */
    public function scopeForHousehold(Builder $query, string $householdNumber): Builder
    {
        return $query->with(['batch', 'sector'])
            ->where('household_number', trim($householdNumber))
            ->latest('relief_batch_id');
    }

    /**
     * Mark this household as having collected, once.
     *
     * Returns false when the row was already received, so a double click or a
     * retried request cannot overwrite the name of whoever actually signed for
     * the goods. The caller turns that into a message rather than a silent
     * success.
     */
    public function markReceived(User $actor, ?string $name = null): bool
    {
        if ($this->isReceived()) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_RECEIVED,
            'received_by_name' => $name !== null && trim($name) !== '' ? trim($name) : $actor->name,
            'logged_by_user_id' => $actor->getKey(),
            'claimed_at' => now(),
        ])->save();

        app(AuditLogger::class)->log(
            actor: $actor,
            action: 'relief.received',
            subject: $this,
            description: sprintf(
                'Household %s marked as having received relief',
                $this->household_number
            ),
            context: ['relief_batch_id' => $this->relief_batch_id, 'sector_id' => $this->sector_id],
        );

        return true;
    }
}
