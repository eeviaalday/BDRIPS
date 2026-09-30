<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     *
     * A resident reaches this as the "Edit Profile" tab of the resident portal,
     * so they get the tabbed page in the public layout rather than the
     * framework's own sidebar page. Every other role keeps the original view.
     * The forms inside are the same partials either way.
     */
    public function edit(Request $request): View
    {
        if ($request->user()->isResident()) {
            return view('resident.profile-edit', ['user' => $request->user()]);
        }

        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        /*
         * A resident saves from the "Edit Profile" tab of the resident portal,
         * so they are returned to that tab. Redirecting to profile.edit instead
         * would drop them onto the framework's own /profile page and out of the
         * portal they were working in. Every other role keeps the original
         * destination.
         */
        return Redirect::route($request->user()->isResident() ? 'resident.profile.edit' : 'profile.edit')
            ->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
