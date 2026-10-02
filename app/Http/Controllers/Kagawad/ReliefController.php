<?php

namespace App\Http\Controllers\Kagawad;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A Kagawad's relief checklist.
 *
 * The only thing a Kagawad does in this system is work through the households on
 * a batch and record who actually signed for the goods. Everything here is
 * scoped to the sectors this Kagawad is responsible for, and the scope is
 * resolved once from sectors.kagawad_user_id.
 *
 * Two rules that the rest of the design leans on:
 *
 *  - A distribution outside the Kagawad's sectors is a 404, not a 403. A 403
 *    would confirm the row exists, which turns guessing ids into a way of
 *    learning who lives in the next purok.
 *  - Only an open batch accepts a mark. A closed batch is readable history, and
 *    writing to it would put a collection in a batch that officially ended.
 */
class ReliefController extends Controller
{
    /**
     * Every batch, with this Kagawad's share of each one.
     */
    public function index(Request $request): View
    {
        $kagawad = $request->user();
        $sectorIds = $kagawad->assignedSectorIds();

        $batches = ReliefBatch::query()
            ->withCount([
                'distributions as households_total' => fn ($query) => $query->forSectors($sectorIds),
                'distributions as households_received' => fn ($query) => $query
                    ->forSectors($sectorIds)
                    ->where('status', ReliefDistribution::STATUS_RECEIVED),
            ])
            ->latest('distribution_date')
            ->latest('id')
            ->paginate(10);

        return view('kagawad.relief.index', [
            'sectorIds' => $sectorIds,
            'sectors' => $kagawad->reliefKagawadSectors,
            'batches' => $batches,
        ]);
    }

    /**
     * One batch's checklist, this Kagawad's sectors only.
     *
     * Progress is one grouped read rather than a count per sector, because the
     * page shows every sector at once and N+1 counts would be the slowest thing
     * on the screen.
     */
    public function show(Request $request, ReliefBatch $reliefBatch): View
    {
        $kagawad = $request->user();
        $sectorIds = $kagawad->assignedSectorIds();

        $rows = ReliefDistribution::query()
            ->forSectors($sectorIds)
            ->where('relief_batch_id', $reliefBatch->getKey())
            ->whereIn('status', [
                ReliefDistribution::STATUS_PENDING,
                ReliefDistribution::STATUS_RECEIVED,
            ])
            ->orderBy('sector_id')
            ->orderBy('household_number')
            ->get();

        // Grouped in PHP from the one result set, so the sector list is exactly
        // the sectors that appear on the checklist. Building it from all sectors
        // instead would show rows for sectors this Kagawad cannot see.
        $bySector = $rows
            ->groupBy('sector_id')
            ->map(fn ($group, $sectorId): array => [
                'sector' => Sector::query()->find($sectorId),
                'total' => $group->count(),
                'received' => $group->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
                'rows' => $group,
            ])
            ->values();

        return view('kagawad.relief.show', [
            'batch' => $reliefBatch,
            'sectorIds' => $sectorIds,
            'bySector' => $bySector,
            'total' => $rows->count(),
            'received' => $rows->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'archivedHouseholds' => $this->archivedHouseholds($rows),
        ]);
    }

    /**
     * Which of this checklist's households have since been archived.
     *
     * relief_distributions stores the household number as a copy, with no
     * foreign key to the account, so the only way to tell that a row on an open
     * batch belongs to a household that has since been closed is to match the
     * numbers back to the profiles. ONE query for the whole checklist, keyed by
     * household number, so the view can ask "is this row archived" as an array
     * lookup: a lookup per row would be a query per household, on the one page a
     * Kagawad opens on a phone during a distribution.
     *
     * Only the numbers actually on this checklist are asked about, so the query
     * is bounded by the batch rather than by the barangay, and an archived
     * household that was never on this batch costs nothing.
     *
     * The sector is not part of the match. The checklist row is filed under the
     * household number alone - that is what insertOrIgnore deduplicated on - so
     * matching on the number as the row does is matching on the row's own key.
     *
     * @param  Collection<int, ReliefDistribution>  $rows
     * @return array<string, true> household numbers that are archived
     */
    private function archivedHouseholds($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        return ResidentProfile::query()
            ->whereIn('household_number', $rows->pluck('household_number')->unique()->all())
            ->whereHas('user', fn ($user) => $user->archived())
            ->pluck('household_number')
            ->flip()
            ->all();
    }

    /**
     * Record that a household collected.
     *
     * The name is optional and defaults to the Kagawad, but it is offered
     * because the signature is the point: the record is who took the goods, and
     * "the Kagawad signed it" is a weaker record of that.
     */
    public function received(Request $request, ReliefBatch $reliefBatch, string $distribution): RedirectResponse
    {
        abort_unless($reliefBatch->isActive(), 409, 'That batch is closed, so nothing can be added to its checklist.');

        $sectorIds = $request->user()->assignedSectorIds();

        // Scoped, so another sector's row is indistinguishable from one that does
        // not exist.
        $row = ReliefDistribution::query()
            ->forSectors($sectorIds)
            ->where('relief_batch_id', $reliefBatch->getKey())
            ->whereKey($distribution)
            ->firstOrFail();

        $validated = $request->validate([
            'received_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($row->markReceived($request->user(), $validated['received_by_name'] ?? null)) {
            return back()->with('success', sprintf('Household %s is marked as collected.', $row->household_number));
        }

        // markReceived() refused because it was already collected. Saying so is
        // better than a success message: the operator pressed a button that did
        // nothing, and the record they see already says who signed for it.
        return back()->with('error', sprintf(
            'Household %s was already marked collected by %s.',
            $row->household_number,
            $row->received_by_name ?? 'somebody'
        ));
    }
}
