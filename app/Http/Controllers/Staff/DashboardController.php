<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\NavigationBadges;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The staff dashboard: the page a Secretary and an Admin land on after signing
 * in.
 *
 * It exists in Phase 1 for one reason. /dashboard answers per role, and without
 * a staff.dashboard route a Secretary would sign in and be redirected to a
 * route that does not exist - a 500 on the first sign-in. The landing page is a
 * dependency of the navigation, not part of the staff area's own content.
 *
 * It carries the role middleware for admin,secretary, so a resident or a
 * Kagawad arriving here by URL is refused rather than shown staff figures. The
 * per-page Admin-only split is done on those routes, not here: this one page is
 * genuinely shared.
 *
 * The counts are the queue figures, because that is what a Secretary opens the
 * portal to see. They are counted with the cheapest possible query each, and
 * nothing on this page is scoped to a sector - a staff member sees the whole
 * barangay.
 *
 * QUERY BUDGET
 *
 * Every widget is a fixed number of queries regardless of how many rows it
 * returns, which is the property StaffDashboardWidgetsTest asserts. The two
 * places it would otherwise have been lost:
 *
 *  - ReliefBatch::progress() issues a grouped query *per batch*. Calling it for
 *    the three batches below would be three queries, so the counts are fetched
 *    for all three at once instead, in one grouped query.
 *  - Every list eager-loads the relations it renders. person-cell reads
 *    user.residentProfile and sector, and a list of five would otherwise be
 *    fifteen queries.
 *
 * The Admin strip is built only when the viewer is an Admin, and every card on
 * it links to an Admin-only read route the sidebar already exposes:
 * /staff/audit, /staff/accounts, /staff/sectors and /staff/settings. Nothing on
 * it performs an action a Secretary could not, because a Secretary never sees it.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::now('Asia/Manila');

        $pending = ResidentProfile::query()
            ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
            ->oldest('created_at')
            ->oldest('id');

        // awaitingDecision() already orders oldest first, because that is the
        // order a Secretary works the queue in. id is a tiebreak only: several
        // requests can share a created_at to the second.
        $awaiting = DocumentRequest::query()
            ->awaitingDecision()
            ->oldest('id');

        $ready = DocumentRequest::query()
            ->where('status', DocumentRequest::STATUS_READY)
            ->oldest('ready_at')
            ->oldest('id');

        $batches = ReliefBatch::query()
            ->active()
            ->oldest('distribution_date')
            ->oldest('id')
            ->limit(3)
            ->get();

        return view('staff.dashboard', [
            // Greeting
            'greeting' => $this->greeting($today),
            'today' => $today->format('l, j F Y'),

            // Stat row
            // One source for the sidebar badge and this card, so the two cannot
            // drift apart the way two copies of a count eventually do.
            'pendingVerifications' => NavigationBadges::forUser($user)['staff.verifications.index']['count'] ?? 0,
            'awaitingDecision' => NavigationBadges::forUser($user)['staff.requests.index']['count'] ?? 0,
            'readyForPickup' => (clone $ready)->count(),
            'activeBatches' => ReliefBatch::query()->active()->count(),
            'oldestPendingDays' => $this->oldestPendingDays($today),

            // Needs attention
            'latestPending' => (clone $pending)
                ->with(['user', 'sector'])
                ->limit(5)
                ->get(),
            'latestAwaiting' => (clone $awaiting)
                ->with(['user.residentProfile', 'documentType'])
                ->limit(5)
                ->get(),

            // Ready for pickup
            'readyForPickupList' => (clone $ready)
                ->with(['user.residentProfile'])
                ->limit(5)
                ->get(),

            // Relief right now
            'activeBatchList' => $batches,
            'batchProgress' => $this->batchProgress($batches),

            // Admin-only strip
            'isAdmin' => $user->role === User::ROLE_ADMIN,
            // No eager-load: audit_logs keeps user_name on the row precisely so
            // the activity list can be read without a join back to users.
            'recentAudit' => $user->role === User::ROLE_ADMIN
                ? AuditLog::query()->latest()->limit(5)->get()
                : collect(),
            'roleCounts' => $user->role === User::ROLE_ADMIN ? $this->roleCounts() : [],
            // Sectors, for the Admin strip. Counted here rather than in the view so
            // a figure on the dashboard has the same shape as every other one, and
            // so it costs one query only when an Admin is looking.
            'sectorSummary' => $user->role === User::ROLE_ADMIN ? $this->sectorSummary() : [],
        ]);
    }

    /**
     * How many sectors there are, and how many still have nobody assigned.
     *
     * @return array{total: int, assigned: int, unassigned: int}
     */
    private function sectorSummary(): array
    {
        $total = Sector::query()->count();

        return [
            'total' => $total,
            'assigned' => Sector::query()->whereNotNull('kagawad_user_id')->count(),
            'unassigned' => Sector::query()->whereNull('kagawad_user_id')->count(),
        ];
    }

    private function greeting(Carbon $today): string
    {
        return match (true) {
            $today->hour < 12 => 'Good morning',
            $today->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * How long the oldest pending verification has been waiting, in whole days.
     *
     * Read with min(created_at) rather than by loading the list, so the hint does
     * not add a query: the pending list is already ordered oldest-first, but a
     * LIMIT 5 would hide a record that is 40 days old.
     */
    private function oldestPendingDays(Carbon $today): ?int
    {
        $oldest = ResidentProfile::query()
            ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
            ->min('created_at');

        if ($oldest === null) {
            return null;
        }

        // diffInDays is signed: asking it in that direction answers "how far away
        // is the past from today" and returns -3 for something three days old.
        // Measured the other way round it is positive, which is what "has been
        // waiting" means.
        return max(0, (int) Carbon::parse($oldest, 'Asia/Manila')->diffInDays($today));
    }

    /**
     * Collected / total households for each of the given batches, in one query.
     *
     * ReliefBatch::progress() is the right answer for a single batch but issues a
     * query per call, and the dashboard asks about three of them at once.
     *
     * @param  Collection<int, ReliefBatch>  $batches
     * @return array<int, array{total: int, received: int, pending: int}>
     */
    private function batchProgress($batches): array
    {
        if ($batches->isEmpty()) {
            return [];
        }

        $ids = $batches->pluck('id')->all();

        // Seed every batch at zero so a batch with no distributions yet renders a
        // 0% bar instead of vanishing from the array.
        $progress = array_fill_keys($ids, ['total' => 0, 'received' => 0, 'pending' => 0]);

        $counts = ReliefDistribution::query()
            ->whereIn('relief_batch_id', $ids)
            ->selectRaw('relief_batch_id, status, COUNT(*) as aggregate')
            ->groupBy('relief_batch_id', 'status')
            ->get();

        // Accumulated, not assigned. The query returns one row per batch *per
        // status*, so a batch with 3 received and 1 pending arrives as two rows;
        // writing the entry each time would leave whichever status came last
        // standing and silently discard the other.
        foreach ($counts as $row) {
            $batchId = (int) $row->relief_batch_id;
            $count = (int) $row->aggregate;

            $progress[$batchId]['total'] += $count;

            if ($row->status === ReliefDistribution::STATUS_RECEIVED) {
                $progress[$batchId]['received'] += $count;
            }
        }

        foreach ($progress as $batchId => $entry) {
            $progress[$batchId]['pending'] = $entry['total'] - $entry['received'];
        }

        return $progress;
    }

    /**
     * Accounts per role, for the Admin strip.
     *
     * Grouped in one query rather than one count() per role, and ordered by the
     * same order as the roles are listed on the accounts page.
     *
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        $counts = User::query()
            ->selectRaw('role, COUNT(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $ordered = [];

        foreach (User::roles() as $role) {
            $ordered[$role] = $counts[$role] ?? 0;
        }

        return $ordered;
    }
}
