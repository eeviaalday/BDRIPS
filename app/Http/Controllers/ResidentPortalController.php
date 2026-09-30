<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The resident's own pages, inside the portal.
 *
 * Everything here is deliberately a resident-scoped view of data the public site
 * already publishes, rather than a new source of truth. The directory shows the
 * same officials held in config, the relief page shows the same batches and
 * distributions the Transparency Board shows, and the announcements are the same
 * published rows.
 *
 * Why they exist at all, when public versions do: a resident who is signed in
 * should be able to reach this material without being thrown out of the portal
 * into the public layout and having to find their way back. A sidebar that
 * links out to /transparency or /directory and back is a worse experience than
 * one that stays put, and the links used to leave the portal entirely.
 */
class ResidentPortalController extends Controller
{
    /**
     * Public information: the officials' directory and the latest notices.
     */
    public function directory(): View
    {
        return view('resident.directory', [
            'notices' => Announcement::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * The resident's own relief assistance, plus the batches currently running.
     *
     * The household number on the profile is the join key, not the user id -
     * relief is issued per household, so every adult in one household sees the
     * same row. A resident with no profile has no household number yet, so they
     * get the batches but no history, rather than an error.
     */
    public function relief(Request $request): View
    {
        $profile = $request->user()->residentProfile;

        /*
         * The lookup form on this page runs for real, against the same scope the
         * public transparency board uses, so a resident who types a household
         * reference sees that household's rows here. It used to hand the view an
         * empty collection whatever was typed, which made the form look broken.
         *
         * Optional rather than required: arriving at the page with no query
         * parameter is the normal case, and validation must not turn that into an
         * error page.
         */
        $validated = $request->validate([
            'household_number' => ['nullable', ...TransparencyController::householdLookupRules()['household_number']],
        ]);

        $searchedNumber = $validated['household_number'] ?? null;

        return view('resident.relief', [
            // Passed explicitly so the page can gate on isVerified() and show
            // the household number, rather than reaching back into auth().
            'profile' => $profile,
            'distributions' => $profile === null
                ? collect()
                : ReliefDistribution::forHousehold($profile->household_number)->get(),
            'activeBatches' => ReliefBatch::active()
                ->withCount('distributions')
                ->latest()
                ->get(),
            'searchedNumber' => $searchedNumber,
            'result' => $searchedNumber === null
                ? null
                : ReliefDistribution::forHousehold($searchedNumber)->get(),
        ]);
    }

    /**
     * The resident's record, read-only.
     *
     * The editable half lives on /resident/settings. This page answers "what
     * does the barangay currently have on file for me", which is what a
     * resident checks before submitting a request and what staff are told to
     * verify against - so it deliberately shows the stored values rather than
     * offering a second place to change them.
     */
    public function profile(): View
    {
        $user = request()->user();

        return view('resident.profile', [
            'user' => $user,
            'profile' => $user->residentProfile,
        ]);
    }

    /**
     * Account settings: name, email and password.
     *
     * Renders the same partials as the framework's own profile page, so there
     * is one set of forms rather than two that can drift. Only the URL differs,
     * and it stays inside /resident so the sidebar never leads out of the
     * portal.
     */
    public function settings(): View
    {
        return view('resident.settings', [
            'user' => request()->user(),
        ]);
    }

    /**
     * The "Change Password" tab of My Profile.
     *
     * The form posts to the framework's existing password.update route, with its
     * own current_password rule, so the password is changed by exactly one piece
     * of code. This method only decides which page holds the form.
     */
    public function changePassword(): View
    {
        return view('resident.change-password', [
            'user' => request()->user(),
        ]);
    }

    /**
     * Help and FAQ for residents.
     *
     * Every answer is drawn from behaviour that already exists in the
     * controllers - the three-request cap, the seven-working-day window, the
     * three-strike restriction, the four document types. Nothing here states a
     * fee, a processing time or an office hour the backend does not already
     * enforce or publish, because a portal that promises something the system
     * does not do is worse than one that says nothing.
     */
    public function faq(): View
    {
        return view('resident.faq');
    }

    /**
     * The events panel shared by the dashboard and the events page.
     *
     * @return Collection<int, array<string, string>>
     */
    public static function upcomingEvents(): Collection
    {
        return collect(config('bdrpis.upcoming_events', []))
            ->filter(fn (array $event): bool => ($event['date'] ?? '') >= now()->toDateString())
            ->sortBy('date')
            ->values();
    }
}
