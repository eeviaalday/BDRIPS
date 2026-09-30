<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResidentDashboardController extends Controller
{
    /**
     * How many notices the dashboard previews. The full list lives on the public
     * announcements page, which the "View all" link points at.
     */
    private const ANNOUNCEMENT_PREVIEW = 3;

    /**
     * The resident's dashboard.
     *
     * The figures are counted here rather than in the view so each is computed
     * once, in one place, and the view stays a layout rather than a pile of
     * queries.
     *
     * A resident sees one of three real states - no profile, waiting on
     * verification, or verified - and the controller supplies the lock reason
     * for whichever applies, so the dashboard cannot offer a request the store()
     * endpoint is going to refuse.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $profile = $user->residentProfile;

        $base = DocumentRequest::where('user_id', $user->id);

        /*
         * "Active" means still moving: submitted, under review, or waiting to be
         * collected. A completed, rejected or abandoned request is finished
         * either way and would otherwise keep the card's number high with
         * nothing left to act on.
         */
        $activeCount = (clone $base)
            ->whereIn('status', [
                DocumentRequest::STATUS_SUBMITTED,
                DocumentRequest::STATUS_UNDER_REVIEW,
                DocumentRequest::STATUS_READY,
            ])
            ->count();

        $readyCount = (clone $base)->where('status', DocumentRequest::STATUS_READY)->count();

        /*
         * Finished work is worth showing separately from work in progress: a
         * resident who has had three certificates collected should be able to
         * see that history, not just an "active" figure that has fallen to zero.
         */
        $completedCount = (clone $base)->where('status', DocumentRequest::STATUS_COMPLETED)->count();

        /*
         * The same three statuses store() counts against its cap, so the figure
         * on screen and the ceiling enforced on submit can never disagree.
         */
        $openCount = (clone $base)->whereIn('status', DocumentRequest::OPEN_STATUSES)->count();

        /*
         * Bounded at BOTH ends. A lower bound alone also counts notices
         * scheduled for the future, which are not out yet and would put a
         * resident's "Announcements" figure above the number of notices they
         * can actually read. The preview below reuses Announcement::published(),
         * the same visibility rule the public page uses, so a scheduled notice
         * cannot appear on one and not the other.
         */
        $announcementCount = Announcement::published()
            ->whereBetween('published_at', [now()->subDays(14), now()])
            ->count();

        $distributions = collect();
        if ($profile !== null) {
            $distributions = ReliefDistribution::with('batch', 'sector')
                ->where('household_number', $profile->household_number)
                ->latest()
                ->get();
        }

        $state = $this->verificationState($profile);

        return view('dashboards.resident', [
            'profile' => $profile,
            'verificationState' => $state,
            'activeCount' => $activeCount,
            'readyCount' => $readyCount,
            'completedCount' => $completedCount,
            'openCount' => $openCount,
            'announcementCount' => $announcementCount,
            'reliefCount' => $distributions->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'requests' => (clone $base)->with('user')->latest()->limit(5)->get(),
            'latestAnnouncements' => Announcement::published()
                ->latest('published_at')
                ->limit(self::ANNOUNCEMENT_PREVIEW)
                ->get(),
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->latest()->get(),
            'distributions' => $distributions,
            'upcomingEvents' => ResidentPortalController::upcomingEvents(),
            'missingProfileDetails' => $profile?->missingRequiredDetails() ?? [],
            'documentLockReason' => $this->documentLockReason($user, $state, $openCount),
            'isRestricted' => $user->isRestricted(),
        ]);
    }

    /**
     * Which of the dashboard's three experiences this resident gets.
     *
     * Derived once here so the view never re-reads the status column to decide
     * which panels to show, and so the badge, the panels and the lock reason
     * below are all describing the same state.
     *
     * @return 'none'|'pending'|'rejected'|'verified'
     */
    private function verificationState(?ResidentProfile $profile): string
    {
        return match ($profile?->verification_status) {
            ResidentProfile::VERIFICATION_VERIFIED => 'verified',
            ResidentProfile::VERIFICATION_REJECTED => 'rejected',
            ResidentProfile::VERIFICATION_PENDING => 'pending',
            default => 'none',
        };
    }

    /**
     * Why the resident cannot open a new document request right now, or null
     * when they can.
     *
     * Ordered the way store() refuses: a restricted account is turned away
     * before anything is read, and the three-open-request cap is checked after
     * verification. Mirroring that order means the explanation the dashboard
     * gives is the reason the form would actually give.
     */
    private function documentLockReason(User $user, string $state, int $openCount): ?string
    {
        if ($user->isRestricted()) {
            return 'Your online request privileges are temporarily restricted after repeated abandoned requests. Please visit the barangay hall to follow up.';
        }

        if ($state === 'none') {
            return 'Set up your resident profile and submit it for verification to request a document.';
        }

        if ($state === 'rejected') {
            return 'Your verification was not approved. Correct your details and submit them again to request a document.';
        }

        if ($state !== 'verified') {
            return 'Your verification is still being reviewed. Document requests open once the Barangay Secretary approves it.';
        }

        if ($openCount >= DocumentRequest::OPEN_REQUEST_LIMIT) {
            return 'You already have '.$openCount.' requests that are still open. Wait for them to be completed before requesting another.';
        }

        return null;
    }
}
