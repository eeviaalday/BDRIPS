<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveResidentRequest;
use App\Http\Requests\UpdateResidentProfileRequest;
use App\Models\DocumentRequest;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The residents directory: every registered resident, searchable.
 *
 * A staff member, unlike a resident, may look across households here. That is
 * the job. The scoping that protects residents from each other does not apply,
 * and pretending otherwise would make the directory useless.
 */
class ResidentDirectoryController extends Controller
{
    /**
     * Which records the directory is showing.
     *
     * Three values, and the middle one is the whole reason this filter exists.
     */
    public const RECORD_ACTIVE = 'active';

    public const RECORD_ARCHIVED = 'archived';

    public const RECORD_ALL = 'all';

    /**
     * The document request statuses that block an archive.
     *
     * Submitted and Under review are awaiting a decision; Ready for pickup is a
     * physical packet sitting in the office with the resident's name on it. None
     * of the three can survive a closed account, so an archive that ignored them
     * would strand work with no owner: a request that can never be approved
     * because the requester can no longer sign in, and a packet nobody is
     * allowed to hand over. Claimed and Rejected are finished and are left alone.
     *
     * @return list<string>
     */
    private const BLOCKING_REQUEST_STATUSES = [
        DocumentRequest::STATUS_SUBMITTED,
        DocumentRequest::STATUS_UNDER_REVIEW,
        DocumentRequest::STATUS_READY,
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $sectorId = $request->integer('sector_id') ?: null;

        /*
         * A filter value that is not one of the three is treated as Active rather
         * than passed through to the query. The parameter arrives in a URL, which
         * means a typo, a stale bookmark and a hand-edited link all land here, and
         * an unrecognised value that widened the list would be a way to make the
         * working directory show archived households while the filter bar still
         * reads "Active". Defaulting to Active fails towards the short list, which
         * is the safe direction: something is hidden and the filter says so.
         */
        $record = (string) $request->query('record', self::RECORD_ACTIVE);

        if (! in_array($record, [self::RECORD_ACTIVE, self::RECORD_ARCHIVED, self::RECORD_ALL], true)) {
            $record = self::RECORD_ACTIVE;
        }

        $query = ResidentProfile::query()
            ->with(['user', 'sector'])
            ->when($search !== '', function ($profiles) use ($search): void {
                $profiles->where(function ($inner) use ($search): void {
                    $inner->where('household_number', 'like', "%{$search}%")
                        ->orWhere('national_or_voter_id', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($sectorId !== null, fn ($profiles) => $profiles->where('sector_id', $sectorId))
            ->when($record === self::RECORD_ACTIVE, fn ($profiles) => $profiles->whereHas(
                'user',
                fn ($user) => $user->active(),
            ))
            ->when($record === self::RECORD_ARCHIVED, fn ($profiles) => $profiles->whereHas(
                'user',
                fn ($user) => $user->archived(),
            ))
            ->orderBy('household_number');

        return view('staff.residents.index', [
            'profiles' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'sectorId' => $sectorId,
            'sectors' => Sector::orderBy('id')->get(),
            'record' => $record,
            'recordOptions' => [
                self::RECORD_ACTIVE => 'Active',
                self::RECORD_ARCHIVED => 'Archived',
                self::RECORD_ALL => 'All',
            ],
            /*
             * Is there anything hidden right now? Only computed when the list is
             * hiding records, and only as an existence check - one extra query on
             * a page that has just said something is out of sight, in exchange for
             * never telling a Secretary to click a filter that leads to an empty
             * page. Ignores the search and sector filters on purpose: the hint is
             * about archived records existing at all, so it does not vanish and
             * reappear as the reader types in the search box.
             */
            'hiddenCount' => $record === self::RECORD_ACTIVE
                ? ResidentProfile::query()->whereHas('user', fn ($user) => $user->archived())->count()
                : 0,
        ]);
    }

    /**
     * One resident: their record, their requests, and their relief history.
     */
    public function show(ResidentProfile $profile): View
    {
        return view('staff.residents.show', [
            // archivedBy is loaded with the user rather than lazily read in the
            // banner: a record page states who archived a household, and a lazy
            // load on the detail page would put one query behind a value the
            // header is about to render.
            'profile' => $profile->load(['user.archivedBy', 'sector']),
            'requests' => DocumentRequest::query()
                ->whereBelongsTo($profile->user)
                ->with('documentType')
                ->latest()
                ->get(),
            'relief' => $profile->user === null
                ? collect()
                : ReliefDistribution::query()
                    ->where('household_number', $profile->household_number)
                    ->with('batch')
                    ->orderByDesc('relief_batch_id')
                    ->get(),
        ]);
    }

    /**
     * The correction form.
     *
     * Separate from show rather than an inline panel on it, because the form needs
     * its own URL: a browser refresh mid-edit, or a second tab opened to compare
     * against the record, both have to have something to point at.
     */
    public function edit(ResidentProfile $profile): View
    {
        $profile->loadMissing(['user', 'sector']);

        /*
         * An archived household is not editable, and the link is already gone from
         * the pages that lead here - this is the same rule reached by URL.
         *
         * Hiding the Edit control is not the whole of it: a URL in a bookmark, a
         * stale tab, or a Secretary working from muscle memory all reach this form
         * directly. A change saved to an archived record is a change to a household
         * that is off every working list, so it lands where nobody is looking and
         * contradicts the archive itself - the row says closed while the address
         * behind it keeps moving. Restoring first is the honest order, and it is one
         * click on the page the refusal sends them to.
         */
        abort_if(
            $profile->user?->isArchived() ?? false,
            409,
            sprintf('%s is archived. Restore the household before changing their details.', $profile->user?->name ?? 'This resident'),
        );

        $default = config('bdrpis.registration.default_nationality');
        $other = config('bdrpis.registration.other_nationality');
        $stored = $profile->user?->nationality;

        /*
         * The nationality column holds whatever the resident typed beside the
         * "Others" option rather than the word "Others" itself, so a stored value
         * like "American" is not one of the two options a select offers. Presenting
         * it as "Others" with the text box carrying the real value is how it was
         * captured, which means a Secretary who saves without touching it produces
         * no change at all rather than rewriting the column to "Others".
         */
        $isCustom = filled($stored) && $stored !== $default && $stored !== $other;

        return view('staff.residents.edit', [
            'profile' => $profile,
            'sectors' => Sector::orderBy('id')->get(),
            'defaultNationality' => $default,
            'otherNationality' => $other,
            'nationality' => $isCustom ? $other : $stored,
            'nationalityOther' => $isCustom ? $stored : null,
        ]);
    }

    /**
     * Correct a resident's own details.
     *
     * Every correction is audited, and a submission that changes nothing is not
     * treated as a correction at all: it writes nothing, logs nothing, and says so.
     * That distinction matters because the audit log is read as a record of what
     * staff actually did to a household record - an entry per Save press, whether
     * or not anything was different, would make the log a record of how many times
     * a button was pressed.
     */
    public function update(UpdateResidentProfileRequest $request, ResidentProfile $profile): RedirectResponse
    {
        $user = $profile->user;

        abort_if($user === null, 404, 'That resident has no account attached.');

        // The same rule the edit form refuses, enforced on the write as well as the
        // page: a POST that never visited the form is still a POST.
        abort_if(
            $user->isArchived(),
            409,
            sprintf('%s is archived. Restore the household before changing their details.', $user->name),
        );

        $attributes = $request->correctedAttributes();

        // Captured before the assignment below, because getDirty() reports only
        // the new values and the audit entry needs both ends of each change.
        $before = [
            'user' => $user->only(array_keys($attributes['user'])),
            'profile' => $profile->only(array_keys($attributes['profile'])),
        ];

        // fill() marks the model dirty and getDirty() reports it. Doing the
        // comparison this way rather than comparing raw input to stored strings is
        // what makes "sector 3" not look like a change from the integer 3, and a
        // resubmitted date not look like a change from a stored date.
        $user->fill($attributes['user']);
        $profile->fill($attributes['profile']);

        $userChanged = $user->getDirty();
        $profileChanged = $profile->getDirty();

        if ($userChanged === [] && $profileChanged === []) {
            return redirect()
                ->route('staff.residents.show', $profile)
                ->with('info', 'No changes to save.');
        }

        // Only the rows that actually moved are saved. Saving both would bump
        // updated_at on the untouched one, which would then read as a change to
        // anyone holding the form open and make the concurrency guard refuse an
        // edit that had not really conflicted with anything.
        if ($userChanged !== []) {
            $user->save();
        }

        if ($profileChanged !== []) {
            $profile->save();
        }

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'resident.updated',
            subject: $profile,
            description: sprintf(
                'Details corrected for %s (%s)',
                $user->name,
                $this->changedFieldList(array_merge(array_keys($userChanged), array_keys($profileChanged))),
            ),
            context: [
                'changed_fields' => array_merge(array_keys($userChanged), array_keys($profileChanged)),
                'changes' => [
                    ...$this->auditableChanges($userChanged, $before['user']),
                    ...$this->auditableChanges($profileChanged, $before['profile']),
                ],
            ],
        );

        return redirect()
            ->route('staff.residents.show', $profile)
            ->with('success', 'Resident details updated.');
    }

    /**
     * Archive a resident's account.
     *
     * SOFT, AND REVERSIBLE, AND NEVER A DELETE
     *
     * Four columns are set and the row stays. Everything this account touched -
     * its document requests, its relief history, its correction trail, the audit
     * entries naming it - keeps pointing at a live row, because the archive is a
     * statement about the account today, not a retraction of its history. A delete
     * would have to either cascade that history away or leave it orphaned against
     * an id nothing owns; both lose the record that a household was ever served
     * here, and the second would break every relief row that stores the household
     * number as a copy.
     *
     * WHY OPEN DOCUMENT REQUESTS REFUSE THE WHOLE ACTION
     *
     * Submitted and Under review are waiting on a decision and Ready for pickup is
     * a packet on a shelf. Closing the account under any of them strands work with
     * no owner: the request can never be approved, because the requester cannot
     * sign in to be told, and the packet cannot be released, because the household
     * it belongs to is no longer a household the office can find.
     *
     * So this refuses rather than archiving anyway. The refusal names the requests
     * and links to them, because "you cannot archive this yet" with no way forward
     * is a dead end - the Secretary needs to be sent to the exact rows that are in
     * the way. A pending VERIFICATION is not blocked, for the opposite reason: the
     * registration was never approved, there is no work in flight on it, and
     * archiving is how a staff member says "no" to a registration that should not
     * have been filed.
     *
     * The household number is deliberately left alone. The profile row survives, so
     * the number stays spoken for and a later restore cannot land on a household
     * that took it over.
     */
    public function archive(ArchiveResidentRequest $request, ResidentProfile $profile): RedirectResponse
    {
        $user = $profile->user;

        abort_if($user === null, 404, 'That resident has no account attached.');

        abort_if($user->isArchived(), 409, sprintf('%s is already archived.', $user->name));

        $blocking = $this->openRequests($user);

        if ($blocking->isNotEmpty()) {
            return back()
                ->with('error', $this->archiveRefusal($user, $blocking->count()))
                ->with('archive_blocked', $this->blockedRequestPayload($blocking));
        }

        $user->fill([
            'archived_at' => now(),
            'archived_by' => $request->user()->getKey(),
            'archive_reason' => $request->validated('archive_reason'),
            'archive_note' => $request->validated('archive_note'),
        ])->save();

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'resident.archived',
            subject: $profile,
            description: sprintf(
                '%s (%s) archived - %s',
                $user->name,
                $profile->household_number,
                $request->validated('archive_reason'),
            ),
            context: [
                'reason' => $request->validated('archive_reason'),
                'note' => $request->validated('archive_note'),
            ],
        );

        return redirect()
            ->route('staff.residents.show', $profile)
            ->with('success', sprintf('%s has been archived.', $user->name));
    }

    /**
     * Restore an archived resident.
     *
     * Clears all four archive columns together. None is left behind on purpose: an
     * active account carrying an archive note reads as a record that is somehow
     * still archived, and a stale archived_at is a false answer to isArchived() for
     * every query in the app. The reason and note are not lost by this - the
     * resident.archived audit entry keeps both, permanently, which is where
     * "why was this household archived, and who decided" belongs after the archive
     * itself has been undone.
     *
     * Verification status and sector are not written at all. An archive never
     * touched them - archiving a pending registration leaves it pending, and
     * restoring it therefore leaves it pending again - so the previous state is
     * preserved by not having an opinion about it. This is what makes the restore
     * of a never-verified household come back as "Pending" rather than as a fresh
     * registration.
     *
     * The collision check is the reason this is not just a second UPDATE. The
     * household number stays reserved while an account is archived, but the
     * registration wizard cannot enforce uniqueness - see
     * ResidentProfileRules::shared() - so a household registered by hand while
     * this one was away can hold the same number. Restoring on top of it would put
     * two profiles on one number, and relief_distributions is unique on
     * (relief_batch_id, household_number): the next batch would silently keep one
     * row and drop the other, and a family would receive nothing while the record
     * said they were served.
     */
    public function restore(Request $request, ResidentProfile $profile): RedirectResponse
    {
        $user = $profile->user;

        abort_if($user === null, 404, 'That resident has no account attached.');

        abort_if(! $user->isArchived(), 409, sprintf('%s is not archived.', $user->name));

        $clash = ResidentProfile::query()
            ->where('household_number', $profile->household_number)
            ->whereKeyNot($profile->getKey())
            ->with('user')
            ->first();

        if ($clash !== null) {
            return back()
                ->with('error', sprintf(
                    'Household number %s is now held by another household (%s). Correct that record before restoring this one.',
                    $profile->household_number,
                    $clash->user?->name ?? 'name unknown',
                ))
                ->with('restore_blocked', [
                    'name' => $clash->user?->name ?? 'the other household',
                    'url' => route('staff.residents.show', $clash),
                ]);
        }

        // Read before clearing, so the audit entry records what was undone rather
        // than only that something was.
        $previous = [
            'archived_at' => $user->archived_at,
            'archive_reason' => $user->archive_reason,
            'archive_note' => $user->archive_note,
        ];

        $user->fill([
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
            'archive_note' => null,
        ])->save();

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'resident.restored',
            subject: $profile,
            description: sprintf('%s (%s) restored from archive', $user->name, $profile->household_number),
            context: [
                'previously_archived_at' => $previous['archived_at']?->toDateTimeString(),
                'previous_reason' => $previous['archive_reason'],
                'previous_note' => $previous['archive_note'],
            ],
        );

        return redirect()
            ->route('staff.residents.show', $profile)
            ->with('success', sprintf('%s has been restored.', $user->name));
    }

    /**
     * The requests standing between this resident and an archive.
     *
     * Ordered oldest first, which is the order the office has been waiting in, so
     * the refusal lists the request that has been open longest at the top. Keyed by
     * id and read for the view to link each one - the message and the links have to
     * describe the same set, and resolving this query twice is how they stop doing
     * that.
     *
     * @return Collection<int, DocumentRequest>
     */
    private function openRequests(User $user)
    {
        return DocumentRequest::query()
            ->whereBelongsTo($user)
            ->whereIn('status', self::BLOCKING_REQUEST_STATUSES)
            ->with('documentType')
            ->oldest('created_at')
            ->oldest('id')
            ->get();
    }

    /**
     * The blocked requests as the scalars the next request needs to draw the list.
     *
     * A payload of arrays rather than of models. The refusal is read on the
     * redirect, so the rows would be serialised into the session and back out of
     * it: what the Secretary then sees is a snapshot of the request taken before
     * they looked at it, and a status badge rendered from a stale column. The
     * values that actually reach the page are a reference number, a label, a
     * status and a link, so those are what is carried.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DocumentRequest>  $blocking
     * @return list<array{url: string, reference_number: string, document: string, status: string}>
     */
    private function blockedRequestPayload(Collection $blocking): array
    {
        return $blocking
            ->map(fn (DocumentRequest $request): array => [
                'url' => route('staff.requests.show', $request),
                'reference_number' => $request->reference_number,
                'document' => $request->documentType?->label ?? 'Retired document',
                'status' => $request->status,
            ])
            ->values()
            ->all();
    }

    /**
     * The one sentence the Secretary is shown when open requests block the archive.
     *
     * Counted rather than described, so the reader can tell one request from five
     * before opening any of them, and phrased as the reason with the way out
     * attached - "resolve them first" rather than a refusal with no next step.
     */
    private function archiveRefusal(User $user, int $count): string
    {
        return sprintf(
            '%s cannot be archived yet. %s %s still open. Resolve %s first so no request or document is left stranded.',
            $user->name,
            $count,
            $count === 1 ? 'document request is' : 'document requests are',
            $count === 1 ? 'it' : 'them',
        );
    }

    /**
     * Pair each changed field's new value with the value it replaced.
     *
     * A field in the redaction list is recorded as having changed and nothing
     * more. The values this form writes are not currently sensitive, so the branch
     * is not reached today - it is here because the cost of adding a sensitive
     * column to this form later and finding the audit log already full of it is not
     * recoverable, while the cost of this check is one array lookup.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $before
     * @return array<string, array<string, mixed>>
     */
    private function auditableChanges(array $changed, array $before): array
    {
        $redacted = UpdateResidentProfileRequest::redactedFields();
        $changes = [];

        foreach ($changed as $field => $value) {
            $changes[$field] = in_array($field, $redacted, true)
                ? ['changed' => true]
                : ['from' => $before[$field] ?? null, 'to' => $value];
        }

        return $changes;
    }

    /**
     * "sector and household number", for the audit description.
     *
     * The field names rather than their values: a log line is read by a person
     * skimming for what was touched, and the values are one click away in the
     * entry's context.
     *
     * @param  list<string>  $fields
     */
    private function changedFieldList(array $fields): string
    {
        $labels = array_map(
            fn (string $field): string => str_replace('_', ' ', $field),
            $fields,
        );

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.$last;
    }
}
