<?php

namespace App\Http\Controllers;

use App\Models\ResidentProfile;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileVerificationController extends Controller
{
    public function create(): View
    {
        return view('resident.verification', [
            'profile' => request()->user()->residentProfile,
            'sectors' => Sector::orderBy('id')->get(),
        ]);
    }

    /**
     * Attach or replace the ID document on the resident's profile.
     *
     * Registration now collects the valid ID, so for a wizard-era account this
     * is a replacement rather than a first submission - the view says so, and
     * the status is reset to pending so the Secretary reviews the newer file
     * rather than the one they may already have looked at.
     *
     * An account registered before the registration wizard existed has no
     * address, household number or sector on file, so for that case they are
     * still collected here.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->residentProfile;

        // Unchanged: this form has always been photographs only, and the "image"
        // rule is what keeps a renamed script from passing as a .png. Only the
        // registration form was widened to take a PDF.
        $data = $request->validate([
            'id_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $data['id_photo_path'] = $request->file('id_photo')->store('id-photos', 'local');
        $data['user_id'] = $user->id;
        $data['verification_status'] = ResidentProfile::VERIFICATION_PENDING;

        $data += $profile === null
            ? $this->residentDetails($request)
            : [
                'household_number' => $profile->household_number,
                'address' => $profile->address,
                'sector_id' => $profile->sector_id,
            ];

        ResidentProfile::updateOrCreate(['user_id' => $user->id], $data);

        return redirect()->route('resident.verification.status')
            ->with('success', 'Profile submitted for verification. The Barangay Secretary will validate your details.');
    }

    /**
     * The resident details, for a profile that does not exist yet.
     *
     * The national / voter ID is optional here for the same reason it is off the
     * registration form: the number is read off the uploaded document. It is
     * still checked for uniqueness when it IS given, so a pre-wizard account
     * cannot introduce a duplicate the registration path would have refused.
     *
     * @return array<string, mixed>
     */
    private function residentDetails(Request $request): array
    {
        return $request->validate([
            'household_number' => ['required', 'string', 'max:50'],
            'national_or_voter_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(ResidentProfile::class, 'national_or_voter_id'),
            ],
            'address' => ['required', 'string', 'max:255'],
            'sector_id' => ['required', 'exists:sectors,id'],
        ], [
            'national_or_voter_id.unique' => 'This National ID / Voter\'s ID is already registered to another verified account. Duplicate accounts with the same ID are not allowed.',
        ]);
    }

    public function status(): View
    {
        $profile = request()->user()->residentProfile;

        return view('resident.verification-status', ['profile' => $profile]);
    }
}
