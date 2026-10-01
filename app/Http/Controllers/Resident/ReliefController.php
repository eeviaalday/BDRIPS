<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\ReliefDistribution;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A resident's own relief record.
 *
 * Scoped by the household number on their own profile, never by a number typed
 * into a form. A household number is short and sequential, so an open lookup
 * would let somebody read a neighbour's record by trying the next one along.
 * There is no search box here and there is not going to be one: a resident reads
 * their own row, and the staff side is where a household number is looked up by
 * someone with a reason to.
 *
 * A pending resident is told relief is locked rather than shown an empty page.
 * An empty page and a page saying "not for you" are different answers, and only
 * one of them is true.
 */
class ReliefController extends Controller
{
    /**
     * The chips, and the status each one selects.
     *
     * One list, so the chips and the query filter cannot disagree.
     *
     * @return array<string, array{label: string, statuses: list<string>}>
     */
    public static function statusFilters(): array
    {
        return [
            'pending' => [
                'label' => 'Pending',
                'statuses' => [ReliefDistribution::STATUS_PENDING],
            ],
            'received' => [
                'label' => 'Received',
                'statuses' => [ReliefDistribution::STATUS_RECEIVED],
            ],
        ];
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->residentProfile;

        $household = $profile?->household_number;
        $isVerified = $profile?->isVerified() === true;

        $key = $isVerified ? $this->statusFilter($request->query('status')) : null;
        $statuses = $key === null ? [] : self::statusFilters()[$key]['statuses'];

        $base = fn () => ReliefDistribution::query()->forHousehold((string) $household);

        return view('resident.relief', [
            'isVerified' => $isVerified,
            'householdNumber' => $household,

            // The rows the table shows. Null while locked, so the view never runs
            // a query it should not.
            'distributions' => $isVerified
                ? $base()->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))->get()
                : null,

            /*
             * The three figures, counted over every batch this household has ever
             * been on.
             *
             * Counted from the unfiltered base rather than from the collection
             * being rendered: a count that changed when a chip was pressed would
             * be a figure that lies, and the whole reason the cards are also the
             * filter is that they are read together.
             */
            'totalCount' => $isVerified ? $base()->count() : 0,
            'pendingCount' => $isVerified
                ? $base()->where('status', ReliefDistribution::STATUS_PENDING)->count()
                : 0,
            'receivedCount' => $isVerified
                ? $base()->where('status', ReliefDistribution::STATUS_RECEIVED)->count()
                : 0,

            'statusFilter' => $key,
            'isFiltered' => $key !== null,
            'statusFilters' => self::statusFilters(),
        ]);
    }

    /**
     * Reduce a requested filter to one that exists, or to none.
     *
     * An unrecognised value shows everything rather than an empty page: a stale
     * or hand-edited URL should show a resident their record, not an error.
     */
    private function statusFilter(mixed $requested): ?string
    {
        $key = is_string($requested) ? $requested : null;

        return ($key !== null && array_key_exists($key, self::statusFilters())) ? $key : null;
    }
}
