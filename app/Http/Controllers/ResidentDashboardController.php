<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResidentDashboardController extends Controller
{
    /**
     * The resident's dashboard.
     *
     * The four stat cards are counted here rather than in the view so each
     * figure is computed once, in one place, and the view stays a layout rather
     * than a pile of queries.
     */
    public function index(): View
    {
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
         * Bounded at BOTH ends. A lower bound alone also counts notices
         * scheduled for the future, which are not out yet and would put a
         * resident's "Announcements" figure above the number of notices they
         * can actually read.
         */
        $announcementCount = Announcement::query()
            ->whereBetween('published_at', [now()->subDays(14), now()])
            ->count();

        $distributions = collect();
        if ($profile !== null) {
            $distributions = ReliefDistribution::with('batch', 'sector')
                ->where('household_number', $profile->household_number)
                ->latest()
                ->get();
        }

        return view('dashboards.resident', [
            'profile' => $profile,
            'activeCount' => $activeCount,
            'readyCount' => $readyCount,
            'announcementCount' => $announcementCount,
            'reliefCount' => $distributions->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'requests' => (clone $base)->with('user')->latest()->limit(5)->get(),
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->latest()->get(),
            'distributions' => $distributions,
            'upcomingEvents' => ResidentPortalController::upcomingEvents(),
        ]);
    }
}
