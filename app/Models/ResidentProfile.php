<?php

namespace App\Models;

use Database\Factories\ResidentProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentProfile extends Model
{
    /** @use HasFactory<ResidentProfileFactory> */
    use HasFactory;

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'household_number',
        'national_or_voter_id',
        'address',
        'sector_id',
        'id_photo_path',
        'verification_status',
        'rejection_reason',
        'verified_at',
    ];

    public function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * The three states a profile can be in, as a closed list.
     *
     * A closed set rather than whatever is in the column, so the queue's status
     * filter is a fixed set of choices and a value nobody expected cannot widen
     * it into "everything".
     *
     * @return array<string, string> value => label
     */
    public static function verificationStates(): array
    {
        return [
            self::VERIFICATION_PENDING => 'Pending review',
            self::VERIFICATION_VERIFIED => 'Verified',
            self::VERIFICATION_REJECTED => 'Rejected',
        ];
    }

    public function statusLabel(): string
    {
        return static::verificationStates()[$this->verification_status] ?? ucfirst((string) $this->verification_status);
    }

    /**
     * Whether a decision is still worth making.
     *
     * A verified profile is not in the queue, so the approve and reject controls
     * are not offered for one. The server checks the same thing: an approval is
     * refused on a profile that is already verified, because otherwise a second
     * click would quietly re-verify somebody and re-stamp the time.
     */
    public function isAwaitingDecision(): bool
    {
        return $this->verification_status !== self::VERIFICATION_VERIFIED;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    /**
     * The details a verification submission must carry, in the order the
     * verification form asks for them, mapped to the label shown to the
     * resident when one is still missing.
     *
     * @return array<string, string>
     */
    public static function requiredDetailLabels(): array
    {
        return [
            'household_number' => 'Household reference number',
            'national_or_voter_id' => 'National or voter ID',
            'address' => 'House address',
            'sector_id' => 'Sector',
            'id_photo_path' => 'ID photo',
        ];
    }

    /**
     * Which of the required details are still blank, keyed by column.
     *
     * Used to tell a resident what is outstanding before they submit, instead
     * of discovering it through a rejected request.
     *
     * @return array<string, string> column => human label
     */
    public function missingRequiredDetails(): array
    {
        return collect(static::requiredDetailLabels())
            ->reject(fn (string $label, string $column) => filled($this->{$column}))
            ->all();
    }

    public function hasCompleteDetails(): bool
    {
        return $this->missingRequiredDetails() === [];
    }
}
