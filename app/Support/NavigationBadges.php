<?php

namespace App\Support;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The figures behind the sidebar count badges, in one place.
 *
 * WHY NOT IN RoleNavigation
 *
 * RoleNavigation is the permission model: "what may this role reach". It is a list
 * of PHP that a test can read without a database, and the whole reason it is one
 * file is that the answer to a permission question never needs a query. A badge
 * count is not a permission, it is a figure, and it needs a query per table. Mixing
 * the two would make the permission model untestable without fixtures.
 *
 * So the counts live here, and the sidebar asks for them by user.
 *
 * ONE SOURCE, USED BY THE THINGS THAT ALREADY COUNTED THE SAME THING
 *
 * The staff dashboard and the Kagawad dashboard both already computed three of
 * these four figures for their own cards. Left alone, a badge and a card two inches
 * apart would be free to disagree - the exact failure this project keeps meeting,
 * where a filter counts one way and a table reads another. So each figure has one
 * definition here, and the dashboards read it from here too.
 *
 * WHAT A BADGE IS *NOT*
 *
 * A badge is work that is waiting for this person, so it only appears on a
 * destination whose contents are a to-do:
 *
 *  - Relief Distribution for a Secretary or Admin carries NO badge. An open batch is
 *    not something they have to do; it is something a Kagawad has to do.
 *  - Announcements, User Accounts, Settings and Audit Logs carry no badge. A count
 *    on any of them would be a number about the system rather than about a task.
 *
 * A KAGAWAD'S COUNTS ARE SCOPED, ALWAYS
 *
 * The relief figure counts only the Kagawad's own sector, through forSectors() -
 * the same scope the checklist uses. A badge is read without being asked for, so a
 * barangay-wide number on one would tell a Kagawad the size of a neighbouring
 * purok's list just by opening any page. An unassigned Kagawad has no sector and so
 * gets no badge at all, rather than a zero that looks like a figure.
 */
final class NavigationBadges
{
    /**
     * The largest number a badge will print before it says "99+".
     *
     * Four digits on a 16rem-wide dark sidebar beside a label is a layout problem
     * to stop before it happens, and the exact figure does not change what the
     * reader does next.
     */
    public const CAP = 99;

    /**
     * Per-request memo, keyed by user id, and discarded when the request changes.
     *
     * The sidebar asks for the counts and so may a view or a composer added later,
     * so "once per request" is made true by construction rather than by there
     * happening to be one caller.
     *
     * Scoped to the Request instance rather than just keyed by user id, because a
     * bare static survives across requests inside one PHP process - which is
     * exactly what a test suite does, and it made a query-count test read a second
     * request as cheaper than the first. Under FPM each request is fresh anyway;
     * this makes the same true under any long-lived worker, and stops one user's
     * figures leaking into a later request from a different user id.
     *
     * @var array<int, array<string, array{count: int, label: string}>>
     */
    private static array $memo = [];

    private static ?Request $memoFor = null;

    /**
     * Badge counts for a user's sidebar, keyed by route name.
     *
     * Zero counts are absent rather than present-and-zero: a "0" on a nav item says
     * "you have nothing to do here", which is not the same as having no work, and it
     * is visual noise on every page.
     *
     * @return array<string, array{count: int, label: string}>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $request = request();

        if (self::$memoFor !== $request) {
            self::$memo = [];
            self::$memoFor = $request;
        }

        return self::$memo[$user->getKey()] ??= self::compute($user);
    }

    /**
     * The printed form of a count: the number, or "99+" at the cap.
     */
    public static function display(int $count): string
    {
        return $count > self::CAP ? self::CAP.'+' : (string) $count;
    }

    /**
     * Forget the memo. For tests, and for anything that changes the figures inside
     * one request.
     */
    public static function flush(): void
    {
        self::$memo = [];
        self::$memoFor = null;
    }

    /**
     * @return array<string, array{count: int, label: string}>
     */
    private static function compute(User $user): array
    {
        return match ($user->role) {
            User::ROLE_KAGAWAD => self::forKagawad($user),
            User::ROLE_SECRETARY, User::ROLE_ADMIN => self::forStaff($user),
            // A Resident has no staff sidebar, so there is nothing to badge.
            default => [],
        };
    }

    /**
     * The two shared staff to-dos, plus the Captain's third.
     *
     * Three aggregate queries, and three whatever the size of the tables - a
     * constant, not a per-row count. They are on three different tables, so one
     * query each is the floor; a UNION would buy one saved round-trip at the cost
     * of a figure that is no longer readable as the thing it counts.
     *
     * @return array<string, array{count: int, label: string}>
     */
    private static function forStaff(User $user): array
    {
        $pendingVerifications = ResidentProfile::query()
            ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
            ->count();

        $awaitingDecision = DocumentRequest::query()->awaitingDecision()->count();

        $badges = [];

        if ($pendingVerifications > 0) {
            $badges['staff.verifications.index'] = [
                'count' => $pendingVerifications,
                'label' => self::plural($pendingVerifications, 'pending verification', 'pending verifications'),
            ];
        }

        if ($awaitingDecision > 0) {
            $badges['staff.requests.index'] = [
                'count' => $awaitingDecision,
                'label' => self::plural($awaitingDecision, 'request awaiting a decision', 'requests awaiting a decision'),
            ];
        }

        // The Captain's own queue: sectors nobody has been put in charge of yet.
        if ($user->isAdmin()) {
            $unassigned = Sector::query()->whereNull('kagawad_user_id')->count();

            if ($unassigned > 0) {
                $badges['staff.sectors.index'] = [
                    'count' => $unassigned,
                    'label' => self::plural($unassigned, 'sector with no Kagawad', 'sectors with no Kagawad'),
                ];
            }
        }

        return $badges;
    }

    /**
     * A Kagawad's one figure: their own sector's outstanding collections.
     *
     * Restricted to ACTIVE batches, which is the definition the owner gave and is
     * also the correct one: a pending row on a closed batch is history, and counting
     * it as "still to collect" tells a Kagawad they have work to do on a handout
     * that officially ended.
     *
     * The Kagawad dashboard's "Still to collect" card reads this same method, so the
     * card and the badge cannot disagree.
     *
     * @return array<string, array{count: int, label: string}>
     */
    private static function forKagawad(User $user): array
    {
        $sectorIds = $user->assignedSectorIds();

        // No sector, no figure. Not a zero badge - no badge.
        if ($sectorIds === []) {
            return [];
        }

        $pending = ReliefDistribution::query()
            ->forSectors($sectorIds)
            ->where('status', ReliefDistribution::STATUS_PENDING)
            ->whereIn('relief_batch_id', ReliefBatch::query()
                ->active()
                ->select('id'))
            ->count();

        if ($pending === 0) {
            return [];
        }

        return [
            'kagawad.relief' => [
                'count' => $pending,
                'label' => self::plural($pending, 'household still to collect', 'households still to collect'),
            ],
        ];
    }

    /**
     * "1 request" / "3 requests", for an aria-label a screen reader reads as a
     * sentence rather than as a bare digit.
     */
    private static function plural(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
