<?php

namespace App\Http\Controllers;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\Sector;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The public relief transparency board.
 *
 * A resident should be able to see that relief is being handed out, without
 * asking anybody and without seeing anybody else's.
 *
 * That shapes the whole design. The board shows aggregates - how many households
 * a batch covers, how many have collected - and never a household number. A
 * batch is a roll call, so publishing one would turn a transparency page into a
 * directory of who is poor enough to need help.
 *
 * There is deliberately no household-number search here. A household number is a
 * short, guessable set, and a public endpoint that answers "is HH-0142 on a
 * list" is an endpoint that gets enumerated. Asking about your own household is
 * a signed-in resident's business, and resident.relief answers it, scoped to
 * their own profile by the route rather than by a parameter they supply.
 *
 * Aggregates are cached briefly. They are read on every public page load, and
 * recounting a few thousand rows each time would be the slowest thing on the
 * site for a number that changes at most a few dozen times a day.
 */
class TransparencyController extends Controller
{
    /**
     * How long an aggregate stays cached. Long enough to matter for a busy
     * public page, short enough that a batch visibly moves during a handout.
     */
    private const AGGREGATE_TTL_SECONDS = 120;

    /**
     * The board.
     */
    public function index(): View
    {
        $batches = ReliefBatch::query()
            ->withCount([
                'distributions as households_total',
                'distributions as households_received' => fn ($query) => $query
                    ->where('status', ReliefDistribution::STATUS_RECEIVED),
            ])
            ->orderByDesc('status')
            ->latest('distribution_date')
            ->latest('id')
            ->get();

        return view('transparency.index', [
            'batches' => $batches,
            'sectors' => Sector::query()
                ->withCount('residents')
                ->orderBy('sector_name')
                ->get(),
            'barangayName' => (string) Setting::get('barangay_name', 'the barangay'),
            'totals' => $this->totals(),
        ]);
    }

    /**
     * The headline numbers, cached.
     *
     * @return array{households: int, batches: int, received: int}
     */
    private function totals(): array
    {
        return Cache::remember('transparency.totals', self::AGGREGATE_TTL_SECONDS, function (): array {
            return [
                'households' => (int) Cache::remember(
                    'transparency.households',
                    self::AGGREGATE_TTL_SECONDS,
                    fn (): int => ReliefDistribution::query()->count()
                ),
                'batches' => (int) ReliefBatch::query()->count(),
                'received' => (int) ReliefDistribution::query()
                    ->where('status', ReliefDistribution::STATUS_RECEIVED)
                    ->count(),
            ];
        });
    }
}
