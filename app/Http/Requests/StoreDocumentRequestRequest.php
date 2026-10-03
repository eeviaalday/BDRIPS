<?php

namespace App\Http\Requests;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validating a new document request.
 *
 * Four of the five rules here are authorization rather than shape, and they are
 * here rather than in the controller so that they are checked before anything is
 * written. A rule in a controller is a rule somebody can reach by posting to a
 * different action.
 *
 * The four are independent, and a form that reports only the first is a dead
 * end: an unverified resident is told to get verified and not that their account
 * is frozen, because fixing one and submitting again reveals the next. So all
 * four are collected and returned together.
 */
class StoreDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'required',
                'integer',
                // A retired type is refused, so a type that only exists because
                // something was filed under it cannot be chosen again.
                Rule::exists('document_types', 'id')->where('is_active', true),
            ],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type_id.required' => 'Choose a document you need.',
            'document_type_id.exists' => 'That document is not available to request.',
            'document_type_id.in' => 'That document is not available to request.',
        ];
    }

    /**
     * Refuse the request outright when the resident may not submit one.
     *
     * withValidator() rather than a rule, because none of these are about the
     * shape of the input: they are about the person making it. Each returns
     * after reporting, so a pending resident is not also told their three slots
     * are full - a true statement, but not the one they need to act on.
     *
     * Read the refusal wording from the model rather than writing it here. The
     * resident's own requests page warns with the same sentence before they reach
     * this form, and two copies of an end date would be two chances to tell
     * somebody the wrong day.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User) {
                return;
            }

            // The gate is the profile, not the account. users.is_verified records
            // that the email address was confirmed; the profile records that a
            // person was checked against the master list. Keying off the account
            // would promise a door the server does not open.
            if ($user->residentProfile === null || ! $user->residentProfile->isVerified()) {
                $validator->errors()->add(
                    'account',
                    'Verification required before you can apply. Your details are being checked.'
                );

                return;
            }

            if ($user->isRestricted()) {
                $validator->errors()->add(
                    'account',
                    'This account is temporarily restricted. Please contact the barangay office.'
                );

                return;
            }

            // Checked before the slot count, and it replaces it: a suspended
            // resident is refused for the suspension whatever their slot count
            // happens to be, and telling them the cap is the one they have to act
            // on would be a true statement about the wrong problem.
            //
            // The wording is built by the model rather than here, because the
            // resident's own page warns with the same sentence moments before
            // they reach this form. Two copies of a date would be two chances to
            // tell somebody the wrong day.
            if ($suspension = DocumentRequest::suspensionMessageFor($user)) {
                $validator->errors()->add('account', $suspension);

                return;
            }

            $open = DocumentRequest::openCountFor($user);

            if ($open >= DocumentRequest::OPEN_REQUEST_LIMIT) {
                $validator->errors()->add('account', sprintf(
                    'You already have %d of the %d requests you may have open at once. '
                    .'Wait for one to be collected or rejected before applying for another.',
                    $open,
                    DocumentRequest::OPEN_REQUEST_LIMIT
                ));
            }
        });
    }

    /**
     * The open slot count, for the page to show the same figure the server
     * enforces. Reads the same constant rather than a copy of the number.
     */
    public function openSlotsRemaining(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return max(0, DocumentRequest::OPEN_REQUEST_LIMIT - DocumentRequest::openCountFor($user));
    }

    /**
     * The types a resident may pick from, for the same reason the rule uses them.
     *
     * @return Collection<int, DocumentType>
     */
    public function availableTypes()
    {
        return DocumentType::query()->active()->get();
    }
}
