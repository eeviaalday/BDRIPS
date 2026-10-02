<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for archiving a resident account.
 *
 * SEPARATE FROM UpdateResidentProfileRequest ON PURPOSE
 *
 * The correction form and the archive action both write to users, and it would be
 * tidier to have one request class. They are not the same action and merging them
 * would be wrong in a way that only shows up later: the correction form is about
 * getting a household's details right, and its rule set is the shared
 * ResidentProfileRules that the registration wizard also obeys. The archive
 * action writes four account columns and touches none of those, so sharing the
 * rule set would drag the wizard's eight required fields into an archive - which
 * has no name, birth date or sector to submit.
 *
 * WHAT IS VALIDATED, AND WHY SO LITTLE
 *
 * A reason and an optional note. That is the whole input.
 *
 *  - The reason is required and must be one of the four in
 *    User::archiveReasons(). Required, because an archive with no stated reason
 *    is a household removed from every list with nothing on the record to explain
 *    it, and the first question asked of this table is always "why". Closed, so
 *    the reasons stay countable - that list is what makes "Moved away: 34,
 *    Deceased: 6" a report instead of a pile of sentences.
 *  - The note is optional and capped. Optional, because the reason alone is a
 *    complete answer for most households. Capped, because archive_note is a TEXT
 *    column that the audit log will carry a copy of, and an uncapped field on a
 *    form a Secretary fills in a hurry is where a pasted essay ends up.
 *
 * No confirm field. The confirm is the dialog that submits this form; a second
 * "yes I am sure" checkbox inside a dialog the reader already answered "yes" to
 * trains people to click through the ones that matter.
 */
class ArchiveResidentRequest extends FormRequest
{
    /**
     * A Secretary or an Admin may archive. The route already carries
     * role:admin,secretary, so this is the same rule stated where the form is
     * validated rather than relied on from the route list alone.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isStaff();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'archive_reason' => ['required', 'string', Rule::in(User::archiveReasons())],
            'archive_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'archive_reason' => 'reason',
            'archive_note' => 'note',
        ];
    }
}
