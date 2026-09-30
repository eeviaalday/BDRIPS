<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return match ($user->role) {
            'admin', 'secretary' => $this->adminDashboard(),
            'kagawad' => redirect()->route('kagawad.dashboard'),
            default => redirect()->route('resident.dashboard'),
        };
    }

    /**
     * The staff dashboard, for both admin and secretary.
     *
     * The two roles share every route and permission in this application - there
     * is no middleware, policy or route that separates them - so this gives them
     * the same actions in a different ORDER rather than different capabilities.
     * That is the honest split available:
     *
     *   SECRETARY's job is the queue: profiles waiting on a human decision, and
     *   document requests waiting on a decision. Those two lead.
     *
     *   ADMIN's job is the system: who is registered, what is running, and the
     *   configuration. The queues are shown but after the system-wide figures,
     *   because an admin is not the one clearing them day to day.
     *
     * Inventing a permission difference here would mean showing a control the
     * backend would then refuse, or hiding one the backend would allow.
     */
    private function adminDashboard(): View
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin';

        $queuedRequests = DocumentRequest::whereIn('status', [
            DocumentRequest::STATUS_SUBMITTED,
            DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $pendingVerifications = ResidentProfile::where(
            'verification_status', ResidentProfile::VERIFICATION_PENDING
        )->count();

        return view('dashboards.admin', [
            'isAdmin' => $isAdmin,

            // The two queues, in the order this role cares about them.
            'pendingVerifications' => $pendingVerifications,
            'pendingRequests' => (clone $queuedRequests)->count(),
            'pendingRequestsList' => (clone $queuedRequests)
                ->with('user.residentProfile')
                ->latest()
                ->limit(6)
                ->get(),
            'verificationsList' => ResidentProfile::with('user', 'sector')
                ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
                ->latest()
                ->limit(6)
                ->get(),

            // Items already released and waiting to be collected.
            'readyForPickup' => DocumentRequest::where('status', DocumentRequest::STATUS_READY)->count(),
            'readyList' => DocumentRequest::with('user')
                ->where('status', DocumentRequest::STATUS_READY)
                ->oldest('ready_at')
                ->limit(6)
                ->get(),

            // System-wide figures. These are for the admin's overview; they are
            // also what the Secretary would see on the same page, because there
            // is one dashboard view.
            'residentCount' => User::where('role', User::ROLE_RESIDENT)->count(),
            'verifiedCount' => ResidentProfile::where('verification_status', ResidentProfile::VERIFICATION_VERIFIED)->count(),
            'sectorCount' => Sector::count(),
            'unassignedSectors' => Sector::whereNull('kagawad_user_id')->count(),
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->latest()->get(),
            'reliefReceived' => ReliefDistribution::where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'reliefPending' => ReliefDistribution::where('status', ReliefDistribution::STATUS_PENDING)->count(),
            'announcementCount' => Announcement::whereNotNull('published_at')->count(),
            'latestAnnouncements' => Announcement::whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
