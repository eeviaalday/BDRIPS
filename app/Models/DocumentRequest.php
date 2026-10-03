<?php

namespace App\Models;

use App\Services\AuditLogger;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A resident's request for a barangay document.
 *
 * Seven statuses. Five are what a Secretary can do with a request; the other two
 * are what time and the resident do to it. Collecting the document is the
 * terminal state, so there is no separate "completed".
 *
 * The open cap is OPEN_REQUEST_LIMIT rather than a number inline, so the count
 * on the page, the number the server enforces, and the wording of a refusal all
 * read the same figure. It lives on the model for exactly that reason.
 *
 * THE UNCLAIMED POLICY IS DERIVED, NOT STORED
 *
 * A resident's strike count and their suspension are both computed from the rows
 * in this table at the moment they are asked about, rather than held in a counter
 * or a suspended_until column. Nothing here caches either, which means excusing
 * a strike lifts a suspension on the very next request with no second write to
 * propagate and no state in which a counter and the requests behind it disagree.
 *
 * Every number that policy uses is read from config('document-requests'), so the
 * refusal a resident reads, the warning on their page and the tests that pin the
 * behaviour cannot drift from one another. The reasoning for deriving rather than
 * storing is in the migration that added unclaimed_at.
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

    /**
     * A document made ready, then not collected inside the window.
     *
     * Not a dead end: the resident may still turn up and collect, so a Secretary
     * can still mark it claimed from here. What the status costs is a strike, and
     * the strike - not the status - is what eventually pauses new requests.
     */
    public const STATUS_UNCLAIMED = 'unclaimed';

    /**
     * Withdrawn by the resident before it was ready for collection.
     *
     * A separate status rather than a deleted row or a rejected one, because all
     * three of those are lies: nothing was deleted, and nothing was refused. It
     * is not in OPEN_STATUSES, so a cancellation frees its slot immediately.
     */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
        self::STATUS_CLAIMED,
        self::STATUS_REJECTED,
        self::STATUS_UNCLAIMED,
        self::STATUS_CANCELLED,
    ];

    /**
     * The statuses in which a request occupies one of the open slots FOREVER.
     *
     * A claimed, rejected or cancelled request is finished and frees its slot.
     *
     * NOTE WHAT IS NOT HERE. Unclaimed is absent, and that is deliberate rather
     * than an omission: an unclaimed request holds a slot only until its hold
     * expires, which is a date-dependent question this flat list of statuses
     * cannot express. Putting it here would make the cap permanent, and omitting
     * it without a replacement would release the slot the moment it was marked.
     * The truth is `holdsSlot()`, and `openCountFor()` counts that instead - see
     * the note there.
     *
     * The chooser and the counter both read openCountFor(), so a resident can
     * never be shown a free slot the server then refuses.
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

    /**
     * The five policy numbers, read from one place.
     *
     * Cast to int because config() is untyped: a value that arrived as a string
     * would make `subDays()` and `<` comparisons behave inconsistently between
     * the query builder and the plain PHP beside it, which is the kind of
     * difference that shows up as a test passing on one boundary and failing on
     * another.
     */
    public static function readyDaysBeforeUnclaimed(): int
    {
        return (int) config('document-requests.ready_days_before_unclaimed');
    }

    public static function strikesToSuspend(): int
    {
        return (int) config('document-requests.strikes_to_suspend');
    }

    public static function strikeWindowDays(): int
    {
        return (int) config('document-requests.strike_window_days');
    }

    public static function suspensionDays(): int
    {
        return (int) config('document-requests.suspension_days');
    }

    public static function unclaimedSlotHoldDays(): int
    {
        return (int) config('document-requests.unclaimed_slot_hold_days');
    }

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
        'unclaimed_at',
        'unclaimed_excused_at',
        'cancelled_at',
    ];

    public function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'ready_at' => 'datetime',
            'claimed_at' => 'datetime',
            'unclaimed_at' => 'datetime',
            'unclaimed_excused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The excuse on this request, with the note and who wrote it.
     *
     * The note is not a column on this table: it is free text written at the desk
     * and only ever read again as "what did we decide here, and who decided it".
     * It lives in the audit entry, which already snapshots the actor's name and
     * timestamp, so keeping a second copy on the row would mean two answers that
     * can disagree. latestOfMany because an excuse can be superseded by a re-mark
     * and the newest note is the one that describes the current state.
     */
    public function latestExcuse(): MorphOne
    {
        return $this->morphOne(AuditLog::class, 'subject')
            ->where('action', 'request.excused')
            ->latestOfMany();
    }

    /**
     * The excuse note, for display. Empty string rather than null so a caller
     * printing it does not have to guard - but an excuse always has a note, since
     * the route requires one, so this returning '' means "not excused".
     */
    public function excuseNote(): string
    {
        return (string) ($this->latestExcuse?->context['reason'] ?? '');
    }

    /**
     * Who excused it. Falls back to the name snapshotted in the audit context when
     * the account has since been deleted, so an excuse never renders as "nobody".
     */
    public function excusedByName(): string
    {
        $excuse = $this->latestExcuse;

        if ($excuse === null) {
            return '';
        }

        return $excuse->user?->name
            ?? (string) ($excuse->context['excused_by'] ?? 'a staff member');
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
     * Whether this request is holding one of the resident's open slots right now.
     *
     * This is the single authority on the question, and openCountFor() is built
     * from it rather than re-deriving the same rule as SQL. Two answers to "does
     * this hold a slot" would be two chances to disagree, and the disagreement
     * would show a resident a free slot the server then refuses.
     */
    public function holdsSlot(): bool
    {
        if (in_array($this->status, self::OPEN_STATUSES, true)) {
            return true;
        }

        if ($this->status !== self::STATUS_UNCLAIMED) {
            return false;
        }

        $expires = $this->slotHoldExpiresAt();

        return $expires !== null && now()->lessThan($expires);
    }

    /**
     * When this request stops holding a slot, or null if it never will.
     *
     * Null for a request that holds no slot - cancelled, claimed, rejected - and
     * null for an unclaimed row whose unclaimed_at is missing, which cannot
     * happen through the interface and would otherwise read as an eternal hold.
     */
    public function slotHoldExpiresAt(): ?Carbon
    {
        return $this->unclaimed_at?->copy()->addDays(self::unclaimedSlotHoldDays());
    }

    /**
     * How many open requests this user is holding.
     *
     * Counted in SQL from the same two arms holdsSlot() uses: the always-open
     * statuses, plus an unclaimed request whose hold has not yet lapsed.
     *
     * The boundary on the second arm is STRICT. A request marked unclaimed
     * exactly hold-days ago has had the whole hold, so its slot is free; on the
     * preceding second it is still held. `>=` would hold it for one extra day and
     * refuse a resident a fourth... sorry, a first request they were entitled to,
     * which is the failure mode the cap must not have.
     */
    public static function openCountFor(User $user): int
    {
        return static::where('user_id', $user->getKey())
            ->where(function (Builder $query): void {
                $query->whereIn('status', self::OPEN_STATUSES)
                    ->orWhere(function (Builder $unclaimed): void {
                        $unclaimed->where('status', self::STATUS_UNCLAIMED)
                            ->whereNotNull('unclaimed_at')
                            ->where('unclaimed_at', '>', now()->subDays(self::unclaimedSlotHoldDays()));
                    });
            })
            ->count();
    }

    /**
     * This resident's un-excused, unclaimed requests inside the strike window.
     *
     * The base of both the strike count and the suspension, so the two cannot be
     * computed from different definitions of a strike.
     *
     * Excused requests are excluded rather than zero-weighted: an excuse is the
     * record saying "this one was not the resident's fault", and a count that
     * quietly subtracted it would still be counting the incident.
     *
     * @return Builder<DocumentRequest>
     */
    public static function activeStrikesQuery(User $user): Builder
    {
        return static::query()
            ->where('user_id', $user->getKey())
            // Keyed on unclaimed_at, NOT on status = unclaimed. A strike is an
            // event - the document sat ready and was not collected - and the
            // resident may still turn up and collect it later. Filtering on the
            // status instead meant the one action that fixed the underlying
            // problem, collecting the document, silently erased the strike it had
            // caused, so a resident could clear their record by being patient and
            // a Secretary's late-collection button would have quietly undone the
            // accountability the whole policy exists to provide.
            ->whereNull('unclaimed_excused_at')
            ->whereNotNull('unclaimed_at')
            ->where('unclaimed_at', '>=', now()->subDays(self::strikeWindowDays()));
    }

    /**
     * How many active strikes this resident is carrying.
     */
    public static function strikeCountFor(User $user): int
    {
        return static::activeStrikesQuery($user)->count();
    }

    /**
     * The newest active strike, or null when there is none.
     *
     * The suspension clock runs from this row, not from the threshold crossing as
     * a stored instant, which is why excusing an earlier strike cannot silently
     * move the end date: the clock follows the newest strike that still counts.
     */
    public static function latestStrikeFor(User $user): ?self
    {
        return static::activeStrikesQuery($user)
            ->latest('unclaimed_at')
            ->latest('id')
            ->first();
    }

    /**
     * When this resident's suspension ends, if they are suspended.
     *
     * Returns null when they are not suspended at all. When the strike count has
     * reached the threshold but the newest strike is already older than the
     * suspension length, the date returned is in the past - that is the honest
     * answer, and isSuspended() is what turns it into a yes or no. Pair the two;
     * never show this date without asking isSuspended() first.
     */
    public static function suspensionEndFor(User $user): ?Carbon
    {
        if (static::strikeCountFor($user) < self::strikesToSuspend()) {
            return null;
        }

        return static::latestStrikeFor($user)?->unclaimed_at?->copy()->addDays(self::suspensionDays());
    }

    /**
     * Whether new requests are currently paused for this resident.
     *
     * Both halves are required and neither is implied by the other: reaching the
     * threshold alone would suspend somebody for an incident long past, and a
     * recent strike alone would not pause anybody who is two strikes from the
     * limit.
     */
    public static function isSuspended(User $user): bool
    {
        $ends = static::suspensionEndFor($user);

        return $ends !== null && now()->lessThan($ends);
    }

    /**
     * The rule in one sentence, for the resident to read wherever they meet it.
     *
     * "After 3, new requests pause for 90 days." Built from config rather than
     * written out, because this is the sentence most likely to be quoted back at
     * the barangay by a resident who has read it on screen, and a sentence that
     * outlasted the setting it describes would be the worst kind of stale copy:
     * an authoritative-sounding one.
     */
    public static function suspensionPolicySentence(): string
    {
        return sprintf(
            'After %d, new requests pause for %d days.',
            static::strikesToSuspend(),
            static::suspensionDays()
        );
    }

    /**
     * The whole refusal, or null when the resident is not suspended.
     *
     * One sentence, built in one place, and read by the FormRequest that refuses
     * the submission AND by the page that warns beforehand. Those two are shown
     * to the same person minutes apart and must not be able to disagree about
     * the date they have to wait until, so neither of them formats anything.
     */
    public static function suspensionMessageFor(User $user): ?string
    {
        $ends = static::suspensionEndFor($user);

        if ($ends === null || ! now()->lessThan($ends)) {
            return null;
        }

        return sprintf(
            'Your document requests are on hold until %s. %s You can still collect any document '
            .'that is ready for pickup. %s',
            $ends->format('F j, Y'),
            static::suspensionPolicySentence(),
            static::strikeCountFor($user) === 1
                ? 'One document was not collected.'
                : sprintf('%d documents were not collected.', static::strikeCountFor($user))
        );
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
            self::STATUS_UNCLAIMED => 'Unclaimed',
            self::STATUS_CANCELLED => 'Cancelled',
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
     * Ready for longer than the policy allows, so nobody has looked in a while.
     *
     * The queue's answer to "which documents are about to become unclaimed",
     * derived rather than stored: nothing writes an overdue flag, so a request
     * cannot be overdue in the database and not on the screen, and the list needs
     * no nightly job to refresh itself.
     *
     * @param  Builder<DocumentRequest>  $query
     * @return Builder<DocumentRequest>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now()->subDays(self::readyDaysBeforeUnclaimed()));
    }

    /**
     * Requests still occupying one of this resident's request slots because they
     * were never collected.
     *
     * Mirrors holdsSlot() as a predicate so a count on a resident page and the
     * cap enforced on the create form are computed from one definition. The
     * boundary is the strict comparison in holdsSlot(): a hold that expires exactly
     * now has released the slot, and a scope that used >= here would disagree with
     * it by the width of a second.
     *
     * @param  Builder<DocumentRequest>  $query
     * @return Builder<DocumentRequest>
     */
    public function scopeHoldingSlot(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNCLAIMED)
            ->whereNotNull('unclaimed_at')
            ->where(
                'unclaimed_at',
                '>',
                now()->subDays(self::unclaimedSlotHoldDays())
            );
    }

    /**
     * Whether this request may be marked unclaimed.
     *
     * True only once the window has actually passed. A Secretary who marks one
     * early is refused by the server, not merely warned in the interface, because
     * an early strike is a strike the resident did not earn.
     */
    public function canBeMarkedUnclaimed(): bool
    {
        return $this->isOverdue();
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->ready_at !== null
            && $this->ready_at->lessThanOrEqualTo(now()->subDays(self::readyDaysBeforeUnclaimed()));
    }

    /**
     * How many days this request has been waiting, for the queue's sort and the
     * overdue badge. Null when it was never made ready.
     */
    public function daysReady(): ?int
    {
        return $this->ready_at?->diffInDays(now());
    }

    /**
     * Whether a Secretary may excuse this request's strike.
     *
     * Keyed on unclaimed_at, not on the status, for the same reason the strike
     * query is. The obvious version - `status === unclaimed` - closes the door on
     * the one excuse that matters most: the resident who was away, came back, and
     * collected. That is precisely when the office learns the absence was not the
     * resident's habit, and it is the case a Secretary has the clearest reason to
     * forgive. Requiring the request to still be sitting unclaimed would mean
     * collecting a document destroyed the chance to ever be forgiven for it, so
     * the strike would be permanent and unappealable - which is not the same as
     * enforceable, because the desk would simply stop marking them at all.
     *
     * Excusing twice is refused, so the audit trail cannot accumulate a second
     * "excused this" for a request nobody actually struck again.
     */
    public function canBeExcused(): bool
    {
        return $this->unclaimed_at !== null && ! $this->isExcused();
    }

    public function isExcused(): bool
    {
        return $this->unclaimed_excused_at !== null;
    }

    /**
     * Whether the resident may still withdraw this request themselves.
     *
     * Not once it is ready: from that moment the document exists and is waiting
     * at the hall, so the resident's remedy is to collect it rather than to
     * withdraw it and leave a made document with nobody expecting it.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
        ], true);
    }

    /**
     * Whether a Secretary may still record collection.
     *
     * Unclaimed is included on purpose. A resident who comes back on day 40 has
     * not forfeited the document, and refusing to mark it collected would leave a
     * document that was handed over with no record that it was.
     */
    public function canBeClaimed(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_UNCLAIMED], true);
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
     *
     * $extraContext is merged after the reason so a caller can add the facts a
     * particular transition needs - how overdue a request was when it was
     * struck, for instance - without this method having to know about them.
     *
     * @param  array<string, mixed>  $extraContext
     */
    public function recordStatusChange(User $actor, string $from, string $to, ?string $reason = null, array $extraContext = []): void
    {
        app(AuditLogger::class)->log(
            actor: $actor,
            action: 'request.'.match ($to) {
                self::STATUS_UNDER_REVIEW => 'under_review',
                self::STATUS_READY => 'ready',
                self::STATUS_CLAIMED => 'claimed',
                self::STATUS_REJECTED => 'rejected',
                self::STATUS_UNCLAIMED => 'unclaimed',
                self::STATUS_CANCELLED => 'cancelled',
                default => 'approved',
            },
            subject: $this,
            description: sprintf(
                'Request %s moved from %s to %s',
                $this->reference_number,
                $from,
                $this->statusLabel()
            ),
            context: $reason === null && $extraContext === []
                ? null
                : array_filter([
                    ...($reason === null ? [] : ['reason' => $reason]),
                    ...$extraContext,
                ], fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Record an excuse, which is not a status change and so has no transition.
     *
     * The note is required by the caller rather than optional here, and it is
     * kept in the audit context beside the note of who excused it and when - the
     * three questions this action is ever asked. There is deliberately no column
     * for it: an excuse does not change what happened to the request, only what
     * the resident is accountable for, and the audit trail is where a record of
     * somebody's judgement belongs.
     */
    public function recordExcuse(User $actor, string $note): void
    {
        app(AuditLogger::class)->log(
            actor: $actor,
            action: 'request.excused',
            subject: $this,
            description: sprintf(
                'Strike on request %s excused by %s',
                $this->reference_number,
                $actor->name
            ),
            context: [
                'reason' => $note,
                'excused_by' => $actor->name,
                'unclaimed_at' => $this->unclaimed_at?->toDateTimeString(),
            ],
        );
    }
}
