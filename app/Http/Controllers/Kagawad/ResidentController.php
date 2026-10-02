<?php

namespace App\Http\Controllers\Kagawad;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Support\KagawadSectorResidents;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The residents of this Kagawad's own sectors, read-only.
 *
 * This is the one Kagawad page that shows people rather than households. It exists
 * so a Kagawad can answer "who in my sector still needs collecting?" without being
 * given the resident queue, document requests, or anything else a staff member
 * sees.
 *
 * Three things are load-bearing here, and all three are enforced by the query
 * rather than by the view:
 *
 *  - The sector scope. resolved once from sectors.kagawad_user_id and handed to
 *    KagawadSectorResidents as a list of ids, so there is exactly one place the
 *    scope could be wrong and one test that proves it is not.
 *  - No unscoped fallback. A Kagawad with no sector gets the same unassigned
 *    empty state as the dashboard and the relief list, and no query is run at all.
 *  - No write path. The route is GET and this controller has one method, so
 *    "read-only" is a property of the route table rather than a rule inside a
 *    method that could be forgotten.
 *
 * The privacy rule is enforced by what is selected, not by what is hidden: the
 * query returns a name, a household number, a sector, a verification state and a
 * relief state. There is no national ID, birthdate, contact number or address in
 * the result set to leak, so no view-level omission is load-bearing. That is also
 * why the columns are written out in with() rather than left as *: a reviewer
 * should be able to see the allow-list without reading the query.
 *
 * WHY THE FILTERS ARE RE-RESOLVED EACH REQUEST
 *
 * The batch selector defaults to the latest batch this Kagawad has rows on, and a
 * batch is chosen by id from the query string. A guessed id would be a batch
 * outside their sectors, so the id is resolved out of this Kagawad's own list and
 * anything unrecognised falls back to the default. A bad id narrows nothing; it
 * cannot widen anything.
 */
class ResidentController extends Controller
{
    public function index(Request $request): View
    {
        $kagawad = $request->user();

        // Resolved once, here, and passed down as ids.
        $sectorIds = $kagawad->assignedSectorIds();

        if (! KagawadSectorResidents::hasScope($sectorIds)) {
            return view('kagawad.residents.index', [
                'sectorIds' => $sectorIds,
                'sectors' => $kagawad->reliefKagawadSectors,
                'profiles' => null,
                'counts' => ['residents' => 0, 'outstanding' => 0],
                'batches' => null,
                'batch' => null,
                'sectorOptions' => [],
                'statusFilters' => KagawadSectorResidents::statusFilters(),
                'search' => '',
                'sectorId' => null,
                'status' => '',
            ]);
        }

        $batches = KagawadSectorResidents::batchesFor($sectorIds);

        $batch = $this->resolveBatch($batches, $request->integer('batch_id') ?: null);

        $filters = [
            // trim(), because KagawadSectorResidents trims before testing, so an
            // untrimmed 'q' of "  " would pass the view's "filters are active" test
            // and offer a Clear link, while the query applied no search at all.
            'q' => trim((string) $request->query('q', '')),
            'sector_id' => $this->resolveSectorFilter($sectorIds, $request),
            'status' => $this->resolveStatus($request),
        ];

        return view('kagawad.residents.index', [
            'sectorIds' => $sectorIds,
            'sectors' => $kagawad->reliefKagawadSectors,
            'profiles' => KagawadSectorResidents::paginate($sectorIds, $batch, $filters),
            'counts' => KagawadSectorResidents::counts($sectorIds, $batch),
            'batches' => $batches,
            'batch' => $batch,
            'sectorOptions' => KagawadSectorResidents::filterOptions($sectorIds),
            'statusFilters' => KagawadSectorResidents::statusFilters(),
            'search' => $filters['q'],
            'sectorId' => $filters['sector_id'],
            'status' => $filters['status'],
        ]);
    }

    /**
     * The chosen batch, or this Kagawad's latest open one.
     *
     * Looked up in the Kagawad's own batch list rather than globally, so an id for
     * a batch they have no rows on is treated as no choice at all instead of
     * producing a page about someone else's distribution.
     *
     * The fallback is the latest ACTIVE batch, not simply the latest one. A closed
     * batch can carry a later distribution_date than any open batch, and defaulting
     * to it would open the page on a record nobody can act on - the list would show
     * a column of collected dates for goods that went out months ago, which is
     * history rather than a worklist. The selector still offers closed batches,
     * because a Kagawad checking on a past handout is a reasonable thing to do.
     *
     * @param  Collection<int, ReliefBatch>  $batches
     */
    private function resolveBatch($batches, ?int $requestedId): ?ReliefBatch
    {
        if ($requestedId !== null) {
            $match = $batches->firstWhere('id', $requestedId);

            if ($match !== null) {
                return $match;
            }
        }

        // A closure, not firstWhere('isActive', true): isActive() is a method, and
        // data_get on a method name yields null rather than calling it.
        return $batches->first(fn (ReliefBatch $batch): bool => $batch->isActive()) ?? $batches->first();
    }

    /**
     * The sector filter, narrowed to a sector this Kagawad actually holds.
     *
     * An id outside their sectors becomes null rather than an empty result, so a
     * hand-edited query string shows the full own-sector list instead of a page
     * that looks like an accident.
     *
     * @param  list<int>  $sectorIds
     */
    private function resolveSectorFilter(array $sectorIds, Request $request): ?int
    {
        $requested = $request->integer('sector_id') ?: null;

        if ($requested === null) {
            return null;
        }

        return in_array($requested, $sectorIds, true) ? $requested : null;
    }

    /**
     * The status filter, as one of the three known values.
     */
    private function resolveStatus(Request $request): string
    {
        $requested = (string) $request->query('status', '');

        return array_key_exists($requested, KagawadSectorResidents::statusFilters()) ? $requested : '';
    }
}
