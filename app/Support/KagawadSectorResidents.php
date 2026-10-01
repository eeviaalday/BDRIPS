<?php

namespace App\Support;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The query behind a Kagawad's own-sector resident list.
 *
 * WHY THIS IS NOT A SCOPE ON ResidentProfile
 *
 * A Kagawad's "own sector" has no column. The link runs the other way - a sector
 * row points at its Kagawad through sectors.kagawad_user_id - so the filter cannot
 * live on the resident without reaching sideways into Sector on every call, and the
 * models are the one thing in this area that must not be edited to accommodate a
 * new staff screen. It also takes a list of sector ids rather than a single
 * sector, which makes it a caller concern rather than a property of a resident.
 *
 * WHAT "SCOPED" MEANS HERE, EXACTLY
 *
 * The sector filter is a whereIn on resident_profiles.sector_id, applied before
 * anything else and never widened. Two consequences, and they are the whole point
 * of the page:
 *
 *  - A household in another sector is not in the result set, so it is not in the
 *    page source. Filtering in the view would hide the row while still shipping the
 *    household number to the browser, which is the leak this avoids.
 *  - An unassigned Kagawad resolves to an empty sector list, and whereIn on an
 *    empty array matches nothing. A Kagawad with no sector therefore sees an empty
 *    page and never an unscoped one, with no separate branch to forget.
 *
 * WHY A CORRELATED SUBQUERY AND NOT A JOIN
 *
 * The batch link is a subselect rather than a join. A join gives a resident one row
 * per batch they appear on, which turns a 20-resident list into 60 rows and makes
 * the pagination lie about how many people are on the page. The subquery keeps one
 * row per resident, costs one extra query in total rather than one per row, and is
 * the same shape Laravel already uses for withCount() elsewhere in this area.
 *
 * The subquery joins on household_number AND sector_id, not household number alone.
 * A household number is short and guessable, and nothing stops the same number
 * appearing in two sectors; matching on the number alone would let a Kagawad be
 * shown a neighbour's collection because the two numbers happened to collide.
 *
 * The status filter reuses that same subquery through whereExists, so the rows a
 * filter keeps and the status a row displays are computed by one expression. A
 * filter on one join and a display read from another is how a list ends up saying
 * "received" on a row the filter counted as pending.
 */
final class KagawadSectorResidents
{
    /**
     * The three status-filter choices, as value => label.
     *
     * A closed set, so a value nobody expected cannot widen the filter into
     * "everything" - the same reasoning as ResidentProfile::verificationStates().
     *
     * @return array<string, string>
     */
    public static function statusFilters(): array
    {
        return [
            '' => 'All',
            'received' => 'Received',
            'pending' => 'Not yet received',
        ];
    }

    /**
     * Whether this Kagawad can see any residents at all.
     *
     * Checked before any query runs, so the controller can return the unassigned
     * empty state without touching the database.
     *
     * @param  list<int>  $sectorIds
     */
    public static function hasScope(array $sectorIds): bool
    {
        return $sectorIds !== [];
    }

    /**
     * The sectors this Kagawad may filter by, as id => name.
     *
     * Built from the caller's own sector ids rather than from all sectors, so a
     * Kagawad can never be offered a sector they are not responsible for. A single
     * sector returns an empty array, because a one-option filter is a control that
     * cannot do anything.
     *
     * @param  list<int>  $sectorIds
     * @return array<int, string>
     */
    public static function filterOptions(array $sectorIds): array
    {
        if (count($sectorIds) < 2) {
            return [];
        }

        return Sector::query()
            ->whereIn('id', $sectorIds)
            ->orderBy('sector_name')
            ->pluck('sector_name', 'id')
            ->all();
    }

    /**
     * Batches this Kagawad can meaningfully be shown.
     *
     * Restricted to batches with at least one of this Kagawad's own households on
     * the checklist, for the reason the dashboard gives: offering a batch they have
     * no rows in produces a page saying nothing here is theirs, which is a dead end
     * presented as a choice.
     *
     * @param  list<int>  $sectorIds
     * @return Collection<int, ReliefBatch>
     */
    public static function batchesFor(array $sectorIds): Collection
    {
        if (! self::hasScope($sectorIds)) {
            return new Collection;
        }

        return ReliefBatch::query()
            ->whereHas('distributions', fn (Builder $query) => $query->forSectors($sectorIds))
            ->orderByDesc('distribution_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Two counts for the dashboard card: how many residents this Kagawad is
     * responsible for, and how many of them are still on the selected batch's
     * checklist without having collected.
     *
     * Both are sector-scoped, and the second is scoped a second time to verified
     * residents - an unverified profile has no place on a distribution, so counting
     * it as outstanding would put a number on the dashboard that the list it links
     * to cannot show.
     *
     * @param  list<int>  $sectorIds
     * @return array{residents: int, outstanding: int}
     */
    public static function counts(array $sectorIds, ?ReliefBatch $batch): array
    {
        if (! self::hasScope($sectorIds)) {
            return ['residents' => 0, 'outstanding' => 0];
        }

        $verified = fn (Builder $query): Builder => $query->where('verification_status', ResidentProfile::VERIFICATION_VERIFIED);

        $residents = ResidentProfile::query()->whereIn('sector_id', $sectorIds)->count();

        $outstanding = 0;

        if ($batch !== null) {
            /*
             * Outstanding means on the checklist AND not collected. Both halves are
             * needed: the whereExists alone would count somebody who already signed
             * for the goods, and the status alone would count a resident who was
             * never on this batch.
             *
             * STATUS_PENDING rather than "not received", so a status nobody expected
             * is not silently counted as outstanding. The checklist only ever holds
             * these two, but a third value should read as neither.
             */
            $outstanding = ResidentProfile::query()
                ->whereIn('sector_id', $sectorIds)
                ->where($verified)
                ->whereExists(self::checklistQuery($sectorIds, $batch)
                    ->where('relief_distributions.status', ReliefDistribution::STATUS_PENDING)
                    ->whereColumn(
                        'relief_distributions.household_number',
                        'resident_profiles.household_number',
                    ))
                ->count();
        }

        return ['residents' => $residents, 'outstanding' => $outstanding];
    }

    /**
     * Residents of the given sectors, one row each, with the relief status for one
     * batch selected onto each row.
     *
     * @param  list<int>  $sectorIds
     * @param  array{q: string, sector_id: int|null, status: string}  $filters
     * @return LengthAwarePaginator<int, ResidentProfile>
     */
    public static function paginate(
        array $sectorIds,
        ?ReliefBatch $batch,
        array $filters,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $search = trim($filters['q']);
        $sectorFilter = $filters['sector_id'];
        $status = $filters['status'];

        // The scope. Applied first, never removed.
        $query = ResidentProfile::query()->whereIn('sector_id', $sectorIds);

        // The name lives on users, so the search has to reach through the relation.
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('household_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        // Intersected with the scope rather than replacing it. A sector_id that is
        // not one of the Kagawad's own cannot widen the result, it can only empty
        // it - so a hand-edited query string buys nothing.
        if ($sectorFilter !== null) {
            $query->whereIn('sector_id', [$sectorFilter]);
        }

        if ($batch !== null) {
            // Only "Received" narrows the result. "Not yet received" is deliberately
            // left alone, and the reason is on the page: a resident who is not on
            // the checklist has no row to mark, and showing them as outstanding
            // would be a claim about them that the batch cannot support. The label
            // is "Not yet received" rather than "Pending" for the same reason.
            if ($status === 'received') {
                $query->whereExists(self::checklistQuery($sectorIds, $batch)
                    ->where('relief_distributions.status', 'received')
                    ->whereColumn(
                        'relief_distributions.household_number',
                        'resident_profiles.household_number',
                    ));
            }

            /*
             * One subselect for every row, so the whole table costs one extra query
             * rather than one per resident.
             *
             * The whereColumn is the load-bearing part of each: without it the
             * subquery is uncorrelated and returns the FIRST checklist row's status
             * for every resident in the list, so a page of mixed results renders as
             * if everyone had collected. It is written out on both subselects rather
             * than factored into one helper because the pair has to stay visibly
             * symmetrical - a helper that took the column name would let the two
             * drift apart, which is the bug this comment exists to prevent.
             */
            $query->addSelect([
                'relief_status' => self::checklistQuery($sectorIds, $batch)
                    ->select('relief_distributions.status')
                    ->whereColumn('relief_distributions.household_number', 'resident_profiles.household_number')
                    ->limit(1),
                'relief_claimed_at' => self::checklistQuery($sectorIds, $batch)
                    ->select('relief_distributions.claimed_at')
                    ->whereColumn('relief_distributions.household_number', 'resident_profiles.household_number')
                    ->limit(1),
            ]);
        }

        return $query
            ->with(['user', 'sector'])
            ->orderBy('household_number')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * This Kagawad's checklist rows for one batch, as a correlated subquery.
     *
     * Scoped to the Kagawad's own sectors inside the subquery as well as outside
     * it, so a row reached through a household-number collision in another sector
     * cannot be picked up even if the outer query were widened by mistake.
     *
     * @param  list<int>  $sectorIds
     * @return Builder<ReliefDistribution>
     */
    private static function checklistQuery(array $sectorIds, ReliefBatch $batch): Builder
    {
        return ReliefDistribution::query()
            ->forSectors($sectorIds)
            ->where('relief_batch_id', $batch->getKey());
    }
}
