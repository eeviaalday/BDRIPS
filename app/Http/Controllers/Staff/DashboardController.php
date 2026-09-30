<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ResidentProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The staff dashboard: the page a Secretary and an Admin land on after signing
 * in.
 *
 * It exists in Phase 1 for one reason. /dashboard answers per role, and without
 * a staff.dashboard route a Secretary would sign in and be redirected to a
 * route that does not exist - a 500 on the first sign-in. The landing page is a
 * dependency of the navigation, not part of the staff area's own content.
 *
 * It carries the role middleware for admin,secretary, so a resident or a
 * Kagawad arriving here by URL is refused rather than shown staff figures. The
 * per-page Admin-only split is done on those routes, not here: this one page is
 * genuinely shared.
 *
 * The counts are the queue figures, because that is what a Secretary opens the
 * portal to see. They are counted with the cheapest possible query each, and
 * nothing on this page is scoped to a sector - a staff member sees the whole
 * barangay.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('staff.dashboard', [
            'pendingVerifications' => ResidentProfile::query()
                ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
                ->count(),

            'awaitingDecision' => DocumentRequest::query()
                ->whereIn('status', [
                    DocumentRequest::STATUS_SUBMITTED,
                    DocumentRequest::STATUS_UNDER_REVIEW,
                ])
                ->count(),

            'readyForPickup' => DocumentRequest::query()
                ->where('status', DocumentRequest::STATUS_READY)
                ->count(),

            'activeBatches' => ReliefBatch::query()
                ->where('status', ReliefBatch::STATUS_ACTIVE)
                ->count(),
        ]);
    }
}
