<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * The validation rules for the fields a resident and a household have in common.
 *
 * WHY THIS EXISTS
 *
 * Two forms now write the same columns: the five-step registration wizard a
 * resident fills in, and the staff correction form a Secretary uses when a record
 * is wrong. Both describe one person - a name, a birth date, an address, a sector,
 * a household number - so both must accept the same set of values, or the same
 * resident can be refused on the staff screen after being accepted at signup.
 *
 * Left as two rule arrays, that agreement is a coincidence rather than a fact: the
 * day somebody widens the wizard's household number to 60 characters, or drops a
 * gender option, the staff form silently keeps the old rule and the two disagree
 * with nothing failing. One array read by both forms makes the agreement the
 * default.
 *
 * WHAT IS DELIBERATELY NOT HERE
 *
 * The account fields. email and password are collected once, at signup, and belong
 * to the account area; a staff correction is not a way to change them. Likewise
 * valid_id_photo: the wizard's identity step is an upload, and substituting a
 * staff correction form for it would quietly re-open identity capture, which is
 * the verification flow's job and not a data correction.
 *
 * THE CLOSED LISTS ARE READ FROM CONFIG, NOT COPIED
 *
 * Genders, civil statuses and nationalities come from config('bdrpis.registration')
 * because that is what the wizard's dropdowns are built from. Copying the values
 * here would create the exact drift this class exists to remove - a new civil
 * status added to the config and offered on the wizard, and still rejected here.
 */
class ResidentProfileRules
{
    /**
     * The rules for the eight shared fields.
     *
     * Order matters in one place only, and it is preserved here: email's
     * "lowercase" must precede its unique lookup so the query sees the normalised
     * address. Nothing in this set depends on ordering, so a caller may safely
     * append to or replace an individual field.
     *
     * Note what is absent for household_number: a uniqueness rule. The wizard
     * cannot have one, because every resident registers before any other and the
     * table does not carry the constraint. See uniqueHouseholdNumber() for the
     * rule the correcting form adds on top of this array.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function shared(): array
    {
        $other = config('bdrpis.registration.other_nationality');

        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after_or_equal:'.config('bdrpis.registration.oldest_acceptable_birth_date')],
            'gender' => ['required', Rule::in(array_keys(config('bdrpis.registration.genders')))],
            'civil_status' => ['required', Rule::in(array_keys(config('bdrpis.registration.civil_statuses')))],
            'nationality' => ['required', Rule::in([config('bdrpis.registration.default_nationality'), $other])],

            // Only collected when the resident picks "Others"; the controller
            // stores whichever of the two ends up filled.
            'nationality_other' => ['nullable', 'required_if:nationality,'.$other, 'string', 'max:64'],

            'address' => ['required', 'string', 'max:255'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')],
            'household_number' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * household_number, unique across every other profile.
     *
     * Split out rather than folded into shared() because the rule cannot be true
     * for the wizard and cannot be false for the correcting form.
     *
     * WHY IT CANNOT BE IN shared()
     *
     * At signup there is nothing to be unique against yet in any meaningful sense:
     * the profile row does not exist until this very submission is saved, and two
     * people registering at the same moment both pass. The wizard therefore
     * accepts a duplicate and leaves it to the Secretary during review - the same
     * answer the wizard already reaches for with the national ID number, which it
     * no longer captures at all.
     *
     * WHY IT MUST BE ON THE CORRECTING FORM
     *
     * relief_distributions is unique on (relief_batch_id, household_number) and
     * stores the household number as a copy rather than a foreign key. Two profiles
     * sharing one number would therefore collide on the next relief batch opened -
     * insertOrIgnore would silently keep one row and drop the other household, and
     * one family would receive nothing while the record says they were served.
     * A duplicate is a data-entry mistake that has to be refused at the point it is
     * made, not discovered during a distribution.
     *
     * Ignoring the profile being corrected, because saving a form without touching
     * the household number must not fail against that same row's own number.
     */
    public static function uniqueHouseholdNumber(int|string $profileKey): array
    {
        return [
            'required',
            'string',
            'max:50',
            Rule::unique('resident_profiles', 'household_number')->ignore($profileKey),
        ];
    }
}
