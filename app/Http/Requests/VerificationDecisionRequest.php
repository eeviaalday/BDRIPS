<?php

namespace App\Http\Requests;

use App\Models\ResidentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Secretary's decision on a resident verification.
 *
 * A rejection has to carry a reason. It is the one state a resident cannot act
 * on, so it is the one that must not be reachable without an explanation - and
 * the resident's own page shows this text back to them verbatim.
 *
 * The reason is required only on a rejection. Requiring it on an approval would
 * be a form asking for something nobody has.
 */
class VerificationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role is enforced by the route's role middleware. This is the
        // second lock on the same door, and it is here because a form request is
        // the one place a controller cannot forget to call.
        return $this->user()?->isStaff() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => [
                Rule::requiredIf($this->boolean('rejecting')),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Give a reason, so the resident knows what to correct.',
            'rejection_reason.min' => 'Give the resident something they can act on, not two words.',
        ];
    }

    /**
     * Whether this submission is a rejection.
     *
     * Read from a field rather than from the route, so one form can post to
     * either action and the two cannot disagree about what they are.
     */
    public function isRejection(): bool
    {
        return $this->boolean('rejecting');
    }

    /**
     * The status this decision moves the profile to.
     */
    public function resultingStatus(): string
    {
        return $this->isRejection()
            ? ResidentProfile::VERIFICATION_REJECTED
            : ResidentProfile::VERIFICATION_VERIFIED;
    }
}
