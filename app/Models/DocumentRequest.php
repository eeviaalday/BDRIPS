<?php

namespace App\Models;

use App\Services\AuditLogger;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resident's request for a barangay document.
 *
 * Five statuses, matching the five things a Secretary can do with it. Collecting
 * the document is the terminal state, so there is no separate "completed", and
 * there is no "abandoned" because the ageing policy that used to write it is not
 * part of the agreed behaviour.
 *
 * The open cap is OPEN_REQUEST_LIMIT rather than a number inline, so the count
 * on the page, the number the server enforces, and the wording of a refusal all
 * read the same figure. It lives on the model for exactly that reason.
 */
class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_READY = 'ready_for_pickup';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
        self::STATUS_CLAIMED,
        self::STATUS_REJECTED,
    ];

    /**
     * The statuses in which a request still occupies one of the open slots.
     *
     * A claimed or rejected request is finished and frees its slot. The chooser
     * and the counter both read this list, so a resident can never be shown a
     * free slot the server then refuses.
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
    ];

    /**
     * How many open requests one resident may hold.
     */
    public const OPEN_REQUEST_LIMIT = 3;

    protected $fillable = [
        'user_id',
        'document_type_id',
        'reference_number',
        'purpose',
        'status',
        'secretary_comment',
        'rejection_reason',
        'reviewed_at',
        'ready_at',
        'claimed_at',
    ];

    public function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'ready_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * A reference number a resident can be given over the phone and that cannot
     * collide with one already issued.
     *
     * Checked rather than assumed: the space is four digits inside a year, so
     * collisions are rare but not impossible, and a duplicate would be a
     * duplicate primary key rather than a warning.
     */
    public static function generateReferenceNumber(): string
    {
        do {
            $reference = 'BRGY-'.now()->year.'-'.strtoupper((string) random_int(1000, 9999));
        } while (static::where('reference_number', $reference)->exists());

        return $reference;
    }

    /**
     * How many open requests this user is holding.
     */
    public static function openCountFor(User $user): int
    {
        return static::where('user_id', $user->getKey())
            ->whereIn('status', self::OPEN_STATUSES)
            ->count();
    }

    /**
     * The status a resident sees, in their own words rather than the internal
     * slug. Written out because it is quoted in the UI and in the refusal
     * message, and "ready_for_pickup" is not what a resident reads.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_UNDER_REVIEW => 'Under review',
            self::STATUS_READY => 'Ready for pickup',
            self::STATUS_CLAIMED => 'Claimed',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * Whether a rejection still needs its reason written. A rejected request
     * with no reason is the one state a resident cannot act on, so it is the one
     * that must not be reachable without one.
     */
    public function needsRejectionReason(): bool
    {
        return $this->status === self::STATUS_REJECTED
            && blank($this->rejection_reason);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /**
     * The queue a Secretary works through: the requests still waiting on a
     * decision, oldest first so the one that has waited longest is next.
     *
     * @param  Builder<DocumentRequest>  $query
     * @return Builder<DocumentRequest>
     */
    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW])
            ->oldest('created_at');
    }

    /**
     * Record that a status changed. Kept beside the model so the verb, the
     * description and the subject cannot drift from the states themselves.
     */
    public function recordStatusChange(User $actor, string $from, string $to, ?string $reason = null): void
    {
        app(AuditLogger::class)->log(
            actor: $actor,
            action: 'request.'.match ($to) {
                self::STATUS_UNDER_REVIEW => 'under_review',
                self::STATUS_READY => 'ready',
                self::STATUS_CLAIMED => 'claimed',
                self::STATUS_REJECTED => 'rejected',
                default => 'approved',
            },
            subject: $this,
            description: sprintf(
                'Request %s moved from %s to %s',
                $this->reference_number,
                $from,
                $this->statusLabel()
            ),
            context: $reason === null ? null : ['reason' => $reason],
        );
    }
}
