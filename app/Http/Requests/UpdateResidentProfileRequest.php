<?php

namespace App\Http\Requests;

use App\Models\ResidentProfile;
use App\Models\User;
use App\Support\ResidentProfileRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A staff correction to a resident's own details.
 *
 * This form is for the record being WRONG - a misspelled name, a household number
 * that was mistyped at signup, a resident who has moved to another sector. It is
 * not an account screen and not an identity screen, and the difference is enforced
 * by which fields are absent rather than by which fields are hidden.
 *
 * WHAT IS DELIBERATELY NOT EDITABLE HERE, AND WHY
 *
 *   email             An account credential. Changing it needs email verification
 *                     to run against the new address, and that is the account
 *                     area's job.
 *   password          Credential, and the form never receives it.
 *   national_or_voter_id
 *                     Not collected by the registration wizard at all: the number
 *                     is read off the uploaded document during review rather than
 *                     typed, so there is no wizard value to keep in step with. It is
 *                     staff-visible on the record and is never staff-writable here.
 *   id_photo_path     The identity evidence itself. Replacing it is identity
 *                     capture, which belongs to the verification flow - a
 *                     substitution here would leave a rejected document's photo and
 *                     its rejection reason describing two different people.
 *   verification_status, rejection_reason, verified_at
 *                     The verification decision. Approving or rejecting is its own
 *                     audited action; letting this form set the status would give
 *                     a data correction the power to look like a decision.
 *   role, is_verified, restricted_until, assigned_sector_id
 *                     Account administration, which the Admin area already owns.
 *
 * The eight fields below are exactly the fields the wizard collects for the same
 * person, read from ResidentProfileRules so the two forms cannot drift apart. Note
 * that the rules are NOT trimmed or otherwise normalised here: the wizard is not
 * either, and a correction that silently tidied a value the wizard had stored
 * verbatim would make the two forms disagree about what the stored value means.
 */
class UpdateResidentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role is enforced by the route's role middleware. This is the second
        // lock on the same door, and it is here because a form request is the one
        // place a controller cannot forget to call.
        return $this->user()?->isStaff() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $profile = $this->profile();

        $rules = ResidentProfileRules::shared();

        // The wizard cannot enforce this one - see ResidentProfileRules - so the
        // rule is added here rather than read from the shared array.
        $rules['household_number'] = ResidentProfileRules::uniqueHouseholdNumber(
            $profile?->getKey() ?? 0
        );

        return [
            ...$rules,

            // The two optimistic-concurrency tokens. Both tables are written by
            // this form - the name and the four identity fields live on the user,
            // the address, sector and household number on the profile - so
            // watching one table's timestamp would leave the other's columns
            // overwriteable. See withValidator().
            'profile_updated_at' => ['required', 'date'],
            'user_updated_at' => ['required', 'date'],
        ];
    }

    /**
     * Refuse the submission if the record moved while the form was open.
     *
     * A corrected record is not a document with a single author: two staff can
     * have the same resident's screen open at once, and whoever saves second
     * silently discards the first one's correction while both believe they
     * succeeded. Comparing the timestamps the form was rendered with against the
     * rows as they are now turns that into a refusal that says why.
     *
     * after() rather than a rule, because nothing here is about the shape of the
     * input. It is a statement about the database, and expressing it as a rule
     * would make it look like a claim about the request that could be satisfied by
     * sending the right value - which is the opposite of what it means.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $profile = $this->profile();

            if (! $profile instanceof ResidentProfile) {
                return;
            }

            // Reload both rows so the comparison is against committed state rather
            // than against whatever a previous action in this request left in the
            // identity map.
            $freshProfile = ResidentProfile::query()->find($profile->getKey());
            $freshUser = $freshProfile?->user()->first();

            if (! $this->timestampsMatch(
                $this->input('profile_updated_at'),
                $freshProfile?->updated_at,
            )) {
                $validator->errors()->add(
                    'profile_updated_at',
                    'This record was changed by someone else while you were editing it. '
                    .'Reload the page and make your changes again.'
                );

                return;
            }

            if ($freshUser instanceof User && ! $this->timestampsMatch(
                $this->input('user_updated_at'),
                $freshUser->updated_at,
            )) {
                $validator->errors()->add(
                    'user_updated_at',
                    "This resident's account was changed by someone else while you were editing. "
                    .'Reload the page and make your changes again.'
                );
            }
        });
    }

    /**
     * Two timestamps written with second precision, compared to the second.
     *
     * MySQL DATETIME stores no sub-second part, so a bare string comparison against
     * a Carbon instance would fail on formatting rather than on disagreement -
     * "2026-01-01 10:00:00" against "2026-01-01T10:00:00.000000Z". Both sides are
     * put through the same formatting so the comparison is about the value.
     */
    private function timestampsMatch(mixed $submitted, mixed $current): bool
    {
        if (! $current instanceof \DateTimeInterface) {
            // The row has no timestamp to contradict, so there is nothing to
            // overwrite and no refusal to make.
            return true;
        }

        if (! is_string($submitted) || $submitted === '') {
            return false;
        }

        try {
            $parsed = new \DateTimeImmutable($submitted);
        } catch (\Exception) {
            return false;
        }

        return $parsed->format('Y-m-d H:i:s') === $current->format('Y-m-d H:i:s');
    }

    /**
     * The profile being corrected, however the route named it.
     *
     * The route binds {profile}; this reads it from either name so the request
     * does not care which one the route table used.
     */
    private function profile(): ?ResidentProfile
    {
        $route = $this->route();

        if ($route === null) {
            return null;
        }

        foreach (['profile', 'resident'] as $parameter) {
            $bound = $route->parameter($parameter);

            if ($bound instanceof ResidentProfile) {
                return $bound;
            }

            if (is_numeric($bound)) {
                return ResidentProfile::query()->find((int) $bound);
            }
        }

        return null;
    }

    /**
     * The corrected values, split by the table they belong to.
     *
     * Two returns rather than one, because the two are written by two save() calls
     * and a caller that merged them would put a name onto a profile row.
     *
     * nationality is resolved the same way the wizard resolves it: "Others" is an
     * escape hatch in the dropdown, and the column holds what was typed beside it
     * so it always reads as a real nationality.
     *
     * @return array{user: array<string, string>, profile: array<string, mixed>}
     */
    public function correctedAttributes(): array
    {
        $other = config('bdrpis.registration.other_nationality');
        $nationality = $this->input('nationality');

        return [
            'user' => [
                'name' => $this->input('name'),
                'date_of_birth' => $this->input('date_of_birth'),
                'gender' => $this->input('gender'),
                'civil_status' => $this->input('civil_status'),
                'nationality' => $nationality === $other
                    ? $this->input('nationality_other')
                    : $nationality,
            ],
            'profile' => [
                'household_number' => $this->input('household_number'),
                'address' => $this->input('address'),
                'sector_id' => $this->input('sector_id'),
            ],
        ];
    }

    /**
     * The keys that must never appear in the audit trail, in full.
     *
     * The audit log is a permanent, widely-readable record, and these are the
     * values where a copy is worth more than the fact that it moved. The national
     * ID number is government identity evidence, the ID photo path resolves to the
     * document itself, and the email address is a login credential. When one of
     * these changes, the log records that it did and nothing about what to.
     *
     * Defined here rather than in the controller so the redaction travels with the
     * rule about what this form may write.
     *
     * @return list<string>
     */
    public static function redactedFields(): array
    {
        return [
            'national_or_voter_id',
            'id_photo_path',
            'valid_id_photo',
            'email',
            'password',
        ];
    }

    /**
     * Whether a field's value may appear in the audit trail.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A full name is required.',
            'date_of_birth.required' => 'A date of birth is required.',
            'date_of_birth.date' => 'Enter the date of birth as YYYY-MM-DD.',
            'date_of_birth.before' => 'Date of birth cannot be in the future.',
            'gender.required' => 'Select a gender.',
            'gender.in' => 'Select a gender.',
            'civil_status.required' => 'Select a civil status.',
            'civil_status.in' => 'Select a civil status.',
            'nationality.required' => 'Select a nationality.',
            'nationality.in' => 'Select a nationality.',
            'nationality_other.required_if' => 'Enter the nationality.',
            'address.required' => 'A complete address is required.',
            'address.max' => 'The address cannot be longer than 255 characters.',
            'sector_id.required' => 'Select a sector.',
            'sector_id.exists' => 'That sector does not exist.',
            'household_number.required' => 'A household reference number is required.',
            'household_number.max' => 'The household number cannot be longer than 50 characters.',
            'household_number.unique' => 'That household number is already registered to another resident.',
            'profile_updated_at.required' => 'The form has expired. Reload the page and try again.',
            'user_updated_at.required' => 'The form has expired. Reload the page and try again.',
        ];
    }

    /**
     * The names used in messages, so a key never leaks into a sentence.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'date_of_birth' => 'date of birth',
            'household_number' => 'household number',
            'sector_id' => 'sector',
            'nationality_other' => 'nationality',
        ];
    }

    /**
     * The model the audit entry is filed against.
     *
     * The profile rather than the user, because it is the household record the
     * correction is about, and because a staff member reading the audit trail is
     * looking for the resident they were looking at.
     */
    public function auditSubject(): ?Model
    {
        return $this->profile();
    }
}
