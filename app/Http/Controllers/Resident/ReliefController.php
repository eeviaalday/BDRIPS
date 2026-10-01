<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\Sector;
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
            // a query it should not. The batch and the staff member who logged
            // each row are read up front because the Details dialog prints both,
            // and a dialog that lazy-loads is a dialog that stalls.
            'distributions' => $isVerified
                ? $base()
                    ->with(['batch', 'loggedBy'])
                    ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
                    ->get()
                : null,

            ...$this->upcoming($isVerified ? (string) $household : null, $profile?->sector_id),

            'statusFilter' => $key,
            'isFiltered' => $key !== null,
            'statusFilters' => self::statusFilters(),
        ]);
    }

    /**
     * The two cards above the table: what is coming, and how the barangay is doing.
     *
     * "Coming" is the open batch this household has not collected from yet - the
     * one they are actually being asked to turn up for. It is found by asking
     * which open batches have no received row for this household, so a household
     * that has already collected is not told to come back for the same goods.
     *
     * "How the barangay is doing" is counts and nothing else. ReliefBatch::progress()
     * runs one grouped query and returns three integers, and that is the whole of
     * what leaves this method for that card. No household number, no name, no
     * per-household status crosses this boundary - a resident reads their own row
     * from the table below, and the aggregate is the only thing that is anybody
     * else's business.
     *
     * @return array{nextBatch: ReliefBatch|null, batchProgress: array{total: int, received: int, pending: int}, sectorKagawad: ?string}
     */
    private function upcoming(?string $householdNumber, mixed $sectorId): array
    {
        $empty = [
            'nextBatch' => null,
            'batchProgress' => ['total' => 0, 'received' => 0, 'pending' => 0],
            'sectorKagawad' => null,
        ];

        if ($householdNumber === null) {
            return $empty;
        }

        // Ordered by date rather than by id, because the batch a resident has to
        // attend next is the soonest one, not the first one that was created.
        $open = ReliefBatch::query()
            ->active()
            ->whereDoesntHave('distributions', fn ($rows) => $rows
                ->where('household_number', $householdNumber)
                ->where('status', ReliefDistribution::STATUS_RECEIVED))
            ->orderByDesc('distribution_date')
            ->orderByDesc('id')
            ->first();

        // Nothing outstanding for this household. The progress card still has
        // something true to say, so it falls back to the most recent open batch
        // rather than showing a bar at zero when the barangay is mid-distribution.
        $batch = $open ?? ReliefBatch::query()->active()->orderByDesc('distribution_date')->orderByDesc('id')->first();

        if ($batch === null) {
            return $empty;
        }

        return [
            'nextBatch' => $open,
            'batchProgress' => $batch->progress(),
            'sectorKagawad' => $sectorId === null
                ? null
                : Sector::query()->with('kagawad')->find($sectorId)?->kagawad?->name,
        ];
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
