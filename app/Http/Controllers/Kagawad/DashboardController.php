<?php

namespace App\Http\Controllers\Kagawad;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Support\KagawadSectorResidents;
use App\Support\NavigationBadges;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Kagawad's own dashboard.
 *
 * Exists in Phase 1 for the same reason as the staff dashboard: /dashboard sends
 * a Kagawad here, and without the route they would sign in to a 500.
 *
 * Everything on it is scoped to the sectors this Kagawad is responsible for,
 * resolved once from sectors.kagawad_user_id. An unassigned Kagawad gets an
 * empty list and therefore an empty page - not every sector, which is what
 * forgetting the scope would produce.
 *
 * The empty state says so explicitly. A Kagawad with no sector should be able to
 * tell "you have not been given a sector yet" from "there is no relief going
 * out", because the two need different conversations.
 *
 * WHAT IS AND IS NOT SCOPED
 *
 * The two batch *totals* (activeBatches) and the list of batches on the relief
 * index are barangay-wide, and are left that way on purpose: a Kagawad sees
 * every batch because a closed one is a record of a handout they worked, and
 * narrowing the two to match each other would either change the relief index or
 * make the dashboard disagree with it.
 *
 * Every *household* figure is sector-scoped, and those are the ones that would
 * leak: still to collect, collected, and the collected/total on each batch. A
 * Kagawad can learn that a neighbouring purok has 40 households outstanding from
 * the total alone, so no such figure is ever computed without forSectors().
 *
 * The "active distributions" list is scoped more tightly still: only batches
 * where this Kagawad actually has households. Listing a batch they have no rows
 * in would render an "Open checklist" button that leads to a page saying
 * nothing here is yours, which is a dead end presented as a task.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Resolved once, here, and passed down as ids. The scope is never
        // re-derived inside a query, so there is one place it could go wrong.
        $sectorIds = $user->assignedSectorIds();

        $distributions = ReliefDistribution::query()
            ->forSectors($sectorIds)
            ->whereIn('status', [
                ReliefDistribution::STATUS_PENDING,
                ReliefDistribution::STATUS_RECEIVED,
            ]);

        /*
         * Only active batches that contain at least one of this Kagawad's
         * households, with both counts resolved as subselects on the same query.
         *
         * withCount rather than fetching the rows: the dashboard needs two
         * integers per batch, and loading three batches' worth of household rows
         * to add them up in PHP would be reading the whole list to draw three
         * bars - the single most expensive thing on a page that is opened on a
         * phone with one bar of signal.
         */
        $activeDistributions = ReliefBatch::query()
            ->active()
            ->whereHas('distributions', fn ($query) => $query->forSectors($sectorIds))
            ->withCount([
                'distributions as kagawad_total' => fn ($query) => $query->forSectors($sectorIds),
                'distributions as kagawad_received' => fn ($query) => $query
                    ->forSectors($sectorIds)
                    ->where('status', ReliefDistribution::STATUS_RECEIVED),
            ])
            ->orderBy('distribution_date')
            ->orderBy('id')
            ->limit(3)
            ->get();

        // One extra count for the residents card, and it is scoped the same way as
        // every other household figure on this page. The "still to receive" half is
        // scoped a second time to verified residents, because an unverified profile
        // is not on a distribution and counting one as outstanding would put a
        // number on the dashboard that the list it links to cannot show.
        $sectorResidentCounts = KagawadSectorResidents::counts(
            $sectorIds,
            ReliefBatch::query()
                ->whereHas('distributions', fn ($query) => $query->forSectors($sectorIds))
                ->orderByDesc('distribution_date')
                ->orderByDesc('id')
                ->first(),
        );

        return view('kagawad.dashboard', [
            'sectorIds' => $sectorIds,
            'sectors' => $user->reliefKagawadSectors,
            'activeBatches' => ReliefBatch::query()->active()->count(),
            // The same figure the sidebar badge shows, from the same source, so the
            // card and the badge cannot disagree. See NavigationBadges::forKagawad -
            // it is also scoped to ACTIVE batches, which this inline count was not:
            // a pending row on a closed batch is history, not outstanding work.
            'pendingCount' => NavigationBadges::forUser($user)['kagawad.relief']['count'] ?? 0,
            'receivedCount' => (clone $distributions)->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'activeDistributions' => $activeDistributions,
            'sectorResidentCounts' => $sectorResidentCounts,
        ]);
    }
}
