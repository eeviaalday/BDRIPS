<?php

namespace App\Http\Controllers\Kagawad;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
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

        return view('kagawad.dashboard', [
            'sectorIds' => $sectorIds,
            'sectors' => $user->reliefKagawadSectors,
            'activeBatches' => ReliefBatch::query()->active()->count(),
            'pendingCount' => (clone $distributions)->where('status', ReliefDistribution::STATUS_PENDING)->count(),
            'receivedCount' => (clone $distributions)->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
        ]);
    }
}
