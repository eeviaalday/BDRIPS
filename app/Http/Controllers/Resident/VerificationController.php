<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\ResidentProfile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A resident's verification state, and the details behind it.
 *
 * Two jobs on one page: saying where they are, and letting them put right what
 * the Secretary could not match. A resident whose details were rejected is not
 * locked out of their own page - they are told what to change and where to
 * change it.
 *
 * Saving does not verify anybody. It resets the state to pending, because the
 * whole point is that a change has to be looked at again by a human. A resident
 * who could reach "verified" from this page would be verifying themselves.
 */
class VerificationController extends Controller
{
    public function edit(Request $request): View
    {
        return view('resident.verification', [
            'profile' => $request->user()->residentProfile,
        ]);
    }

    /**
     * Where the verification has got to, read only.
     *
     * A separate page from the edit form because they answer different
     * questions. "What is happening to my registration" should not require
     * scrolling past a form to find out, and the profile tab that links here is
     * a status link, not an edit link.
     *
     * Every state says what happens next. A resident who can only see "pending"
     * learns nothing about whether they should be waiting or doing something,
     * and a hold they cannot act on is indistinguishable from one they can.
     */
    public function status(Request $request): RedirectResponse
    {
        // Verification Status is the third tab of My Profile now, so this URL
        // redirects rather than rendering a second page showing the same thing.
        // The old address stays because it is what the navbar, the profile menu
        // and anything already bookmarked points at.
        return redirect()->route('account.edit', ['tab' => 'verification']);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->residentProfile;

        abort_if($profile === null, 404);

        $validated = $request->validate([
            'household_number' => [
                'required',
                'string',
                'max:50',
                // Unique across the whole table, and excluding their own row so
                // resaving unchanged details is not a self-collision.
                Rule::unique('resident_profiles', 'household_number')->ignore($profile->getKey()),
            ],
            'national_or_voter_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('resident_profiles', 'national_or_voter_id')->ignore($profile->getKey()),
            ],
            'address' => ['required', 'string', 'max:255'],
            'sector_id' => ['required', 'integer', Rule::exists('sectors', 'id')],
            'id_photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ], [
            'household_number.required' => 'Your household reference number is required.',
            'household_number.unique' => 'That household number is already registered to another resident.',
            'national_or_voter_id.unique' => 'That national or voter ID number is already registered.',
            'id_photo.mimes' => 'Your valid ID must be a JPG, PNG or PDF file.',
            'id_photo.max' => 'Your valid ID must be 5MB or smaller.',
        ]);

        // The private disk, not the public one. An ID is a personal document: it
        // is served only through an authorised endpoint, never from a guessable
        // URL.
        $photoPath = null;

        if ($request->hasFile('id_photo')) {
            $photoPath = $request->file('id_photo')->store('id-photos', 'local');
        }

        $wasVerified = $profile->isVerified();

        $profile->fill([
            'household_number' => $validated['household_number'],
            'national_or_voter_id' => $validated['national_or_voter_id'] ?? null,
            'address' => $validated['address'],
            'sector_id' => $validated['sector_id'],
        ]);

        if ($photoPath !== null) {
            $profile->id_photo_path = $photoPath;
        }

        // Any change at all sends the profile back to pending. A verified
        // resident editing their address is now unverified, because the details
        // a human checked are no longer the details on file.
        $profile->verification_status = ResidentProfile::VERIFICATION_PENDING;
        $profile->rejection_reason = null;
        $profile->verified_at = null;
        $profile->save();

        if ($wasVerified) {
            $user->update(['is_verified' => false]);
        }

        app(AuditLogger::class)->log(
            actor: $user,
            action: 'resident.details_resubmitted',
            subject: $profile,
            description: sprintf('%s resubmitted their details for verification', $user->name),
            context: ['id_photo_replaced' => $photoPath !== null],
        );

        return redirect()
            ->route('resident.verification')
            ->with('success', 'Your details were saved. The Barangay Secretary will check them again.');
    }
}
