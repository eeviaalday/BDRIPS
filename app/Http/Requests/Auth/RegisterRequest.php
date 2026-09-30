<?php

namespace App\Http\Requests\Auth;

use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The whole wizard, validated as one submission.
     *
     * The five steps are a client-side convenience layered over a single POST:
     * every field is present in the request whichever step the resident pressed
     * "Next" on, so the rules cannot be scoped to a step the way a multi-request
     * wizard would. A step is a way of filling the form in, not a separate save.
     *
     * The account fields land on the user; the address, household and ID fields
     * are the resident profile, created here as pending so the Secretary has the
     * whole submission to review. Rules are read from config('bdrpis.registration')
     * so the closed lists cannot drift from the options the stepper renders.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $other = config('bdrpis.registration.other_nationality');

        return [
            // Step 1 - personal information.
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after_or_equal:'.config('bdrpis.registration.oldest_acceptable_birth_date')],
            'gender' => ['required', Rule::in(array_keys(config('bdrpis.registration.genders')))],
            'civil_status' => ['required', Rule::in(array_keys(config('bdrpis.registration.civil_statuses')))],
            'nationality' => ['required', Rule::in([config('bdrpis.registration.default_nationality'), $other])],
            // Only collected when the resident picks "Others"; the controller
            // stores whichever of the two ends up filled.
            'nationality_other' => ['nullable', 'required_if:nationality,'.$other, 'string', 'max:64'],

            // Step 2 - address.
            'address' => ['required', 'string', 'max:255'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')],

            // Step 3 - contact.
            //
            // "lowercase" is not decoration: it normalises the address before
            // the unique rule queries for it, so Juan@Example.com cannot register
            // alongside juan@example.com and quietly become a second account for
            // the same person. The rule ordering below is what guarantees the
            // lookup sees the normalised value.
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],

            // Step 4 - household.
            'household_number' => ['required', 'string', 'max:50'],

            // Step 5 - identity and security.
            //
            // A photograph or scan of any valid ID, not a typed number: plenty of
            // residents hold an ID the barangay has no number recorded for, and
            // the Secretary reads the number off the document rather than off a
            // form the resident could mistype.
            //
            // Deliberately NOT the "image" rule. It admits only jpg, jpeg, png,
            // gif, bmp, webp and svg, so pairing it with a pdf in the mime list
            // makes a PDF unuploadable - the two rules contradict each other and
            // every PDF would be rejected by "image" before "mimes" was reached.
            // The label offers a photo OR a document, so mimes is what states the
            // accepted set.
            //
            // WHAT THIS GIVES UP: the old step-5 field was a national / voter ID
            // number, unique across every profile, and that was the check which
            // stopped one person holding two accounts with two different email
            // addresses. A file cannot carry that constraint - the same ID
            // photographed twice produces two different paths, so there is
            // nothing to compare - and the number is no longer captured at all.
            // Uniqueness at signup is now carried by the email address alone;
            // duplicate registration has to be caught by the Secretary during
            // review of the uploaded document.
            'valid_id_photo' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Messages written in the same words as the field labels beside them.
     *
     * Laravel's default messages name a field by its key, so a resident would
     * be told "The household number field is required" next to a box labelled
     * "Household reference number".
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your full name is required.',
            'date_of_birth.required' => 'Your date of birth is required.',
            'date_of_birth.date' => 'Enter your date of birth as YYYY-MM-DD.',
            'date_of_birth.before' => 'Date of birth cannot be in the future.',
            'gender.required' => 'Select your gender.',
            'civil_status.required' => 'Select your civil status.',
            'nationality.required' => 'Select your nationality.',
            'nationality_other.required_if' => 'Enter your nationality.',
            'address.required' => 'Your complete address is required.',
            'sector_id.required' => 'Select your sector or purok.',
            'email.required' => 'Your email address is required.',
            'email.unique' => 'This email address is already registered. Please use another email or log in.',
            'household_number.required' => 'Your household reference number is required.',
            'valid_id_photo.required' => 'Please attach a photo or scan of a valid ID.',
            'valid_id_photo.mimes' => 'Your valid ID must be a JPG, PNG or PDF file.',
            'valid_id_photo.max' => 'Your valid ID must be 5MB or smaller. Please upload a smaller file or a lower-resolution photo.',
            'password.required' => 'Choose a password.',
        ];
    }

    /**
     * The account columns, with nationality resolved.
     *
     * "Others" is stored as what the resident typed rather than as the literal
     * escape-hatch value, so the column always holds a real nationality.
     *
     * @return array<string, string>
     */
    public function accountAttributes(): array
    {
        $nationality = $this->input('nationality');

        return [
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'date_of_birth' => $this->input('date_of_birth'),
            'gender' => $this->input('gender'),
            'civil_status' => $this->input('civil_status'),
            'nationality' => $nationality === config('bdrpis.registration.other_nationality')
                ? $this->input('nationality_other')
                : $nationality,
        ];
    }

    /**
     * The resident profile columns, still pending verification.
     *
     * The uploaded ID is NOT included here. Storing a file is a side effect, and
     * this method only describes the row; the controller writes id_photo_path
     * once it has a stored path in hand, which also means a rejected submission
     * never leaves a file behind. national_or_voter_id is likewise absent - the
     * number is read off the uploaded document during review rather than typed.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        return [
            'household_number' => $this->input('household_number'),
            'address' => $this->input('address'),
            'sector_id' => $this->input('sector_id'),
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ];
    }

    /**
     * The wizard step a rejected submission should reopen on.
     *
     * The first step holding a field that failed, so the resident is returned to
     * the question they got wrong rather than to step one. A password mismatch
     * is attributed to the password itself rather than its confirmation, which
     * is the field the resident did not type.
     */
    public function failingStep(): int
    {
        return self::stepForErrors($this->validator->errors());
    }

    /**
     * Which step a given set of errors belongs to.
     *
     * Flashed to the next request rather than recomputed in the view, so the
     * step a resident lands on is the same answer the request gave and can be
     * asserted directly.
     */
    public static function stepForErrors(mixed $errors): int
    {
        foreach (config('bdrpis.registration.steps') as $index => $step) {
            $fields = array_map(
                fn (string $field): string => $field === 'password_confirmation' ? 'password' : $field,
                $step['fields'],
            );

            if ($errors->hasAny($fields)) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * Remember which step to reopen when this submission is rejected.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        session()->flash('registration_step', $this->failingStep());

        parent::failedValidation($validator);
    }
}
