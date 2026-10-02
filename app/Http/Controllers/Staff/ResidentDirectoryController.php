<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateResidentProfileRequest;
use App\Models\DocumentRequest;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $sectorId = $request->integer('sector_id') ?: null;

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
            ->orderBy('household_number');

        return view('staff.residents.index', [
            'profiles' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'sectorId' => $sectorId,
            'sectors' => Sector::orderBy('id')->get(),
        ]);
    }

    /**
     * One resident: their record, their requests, and their relief history.
     */
    public function show(ResidentProfile $profile): View
    {
        return view('staff.residents.show', [
            'profile' => $profile->load(['user', 'sector']),
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
