<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every route, checked against ROLES.md.
 *
 * WHY THIS FILE EXISTS ALONGSIDE PermissionMatrixTest
 *
 * PermissionMatrixTest derives each route's expected roles from the route's own
 * `role:` middleware. That makes it a proof that the middleware is applied and
 * enforced - valuable - but it is circular for the question "is this the right
 * middleware?". docs/STAFF_UI_AUDIT.md recorded that as finding M4: a route with
 * the WRONG role, or no role at all, could not be caught, because the expectation
 * moved with the mistake.
 *
 * ROLES.md is now the owner's confirmed source of truth, so the expectation can be
 * written down independently. The two files then have to agree, and when they
 * disagree this one fails and the disagreement has to be resolved deliberately -
 * by correcting the code or by amending ROLES.md and this file together. Neither
 * is done silently.
 *
 * The data below is a transcription of ROLES.md's route tables. If you add a
 * route, or change who may reach one, this file and ROLES.md must both change;
 * `every_named_route_appears_in_the_matrix` is what makes forgetting fail loudly
 * instead of quietly leaving the new route unchecked.
 */
class RolesMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route name => the roles its `role:` middleware must admit.
     *
     * From ROLES.md: "Staff — role:admin,secretary", "Staff, Admin only —
     * role:admin", "Kagawad — role:kagawad", "Resident — role:resident".
     *
     * @var array<string, string>
     */
    private const ROLE_GUARDED = [
        // Kagawad - role:kagawad
        'kagawad.dashboard' => 'kagawad',
        'kagawad.relief' => 'kagawad',
        'kagawad.relief.received' => 'kagawad',
        'kagawad.relief.show' => 'kagawad',
        'kagawad.residents.index' => 'kagawad',

        // Resident - role:resident
        'resident.requests.cancel' => 'resident',
        'resident.requests.document.download' => 'resident',
        'resident.relief' => 'resident',
        'resident.requests.create' => 'resident',
        'resident.requests.index' => 'resident',
        'resident.requests.show' => 'resident',
        'resident.requests.store' => 'resident',
        'resident.verification' => 'resident',
        'resident.verification.status' => 'resident',
        'resident.verification.update' => 'resident',

        // Staff - role:admin,secretary
        'staff.announcements.create' => 'admin,secretary',
        'staff.announcements.destroy' => 'admin,secretary',
        'staff.announcements.edit' => 'admin,secretary',
        'staff.announcements.index' => 'admin,secretary',
        'staff.announcements.pin' => 'admin,secretary',
        'staff.announcements.publish' => 'admin,secretary',
        'staff.announcements.store' => 'admin,secretary',
        'staff.announcements.unpin' => 'admin,secretary',
        'staff.announcements.update' => 'admin,secretary',
        'staff.batches.close' => 'admin,secretary',
        'staff.batches.create' => 'admin,secretary',
        'staff.batches.index' => 'admin,secretary',
        'staff.batches.reopen' => 'admin,secretary',
        'staff.batches.show' => 'admin,secretary',
        'staff.batches.store' => 'admin,secretary',
        'staff.dashboard' => 'admin,secretary',
        'staff.requests.claim' => 'admin,secretary',
        'staff.requests.excuse' => 'admin,secretary',
        'staff.requests.index' => 'admin,secretary',
        'staff.requests.ready' => 'admin,secretary',
        'staff.requests.reject' => 'admin,secretary',
        'staff.requests.review' => 'admin,secretary',
        'staff.requests.show' => 'admin,secretary',
        'staff.requests.unclaim' => 'admin,secretary',
        'staff.resident-verifications.id-photo' => 'admin,secretary',
        'staff.residents.index' => 'admin,secretary',
        'staff.residents.show' => 'admin,secretary',
        'staff.residents.archive' => 'admin,secretary',
        'staff.residents.edit' => 'admin,secretary',
        'staff.residents.restore' => 'admin,secretary',
        'staff.residents.update' => 'admin,secretary',
        'staff.verifications.approve' => 'admin,secretary',
        'staff.verifications.index' => 'admin,secretary',
        'staff.verifications.reject' => 'admin,secretary',
        'staff.verifications.show' => 'admin,secretary',

        // Captain only - role:admin
        'staff.accounts.index' => 'admin',
        'staff.accounts.restrict' => 'admin',
        'staff.accounts.role' => 'admin',
        'staff.accounts.show' => 'admin',
        'staff.accounts.unrestrict' => 'admin',
        'staff.audit.index' => 'admin',
        'staff.sectors.destroy' => 'admin',
        'staff.sectors.index' => 'admin',
        'staff.sectors.kagawad' => 'admin',
        'staff.sectors.store' => 'admin',
        'staff.sectors.update' => 'admin',
        'staff.settings.index' => 'admin',
        'staff.settings.update' => 'admin',
    ];

    /**
     * Routes carrying `auth` but deliberately NO role: middleware.
     *
     * ROLES.md "Signed in, any role". The last three are the escape hatch that
     * EnsureUserHasRole's restricted-account check explicitly preserves, and
     * `dashboard` is the signpost that forwards to a role-guarded destination.
     *
     * This list is the whole point of `no_authenticated_route_lacks_a_role_guard`:
     * a route that is not named here, and is not public or guest-only, is
     * authenticated and unguarded - which is how a page ends up readable by any
     * signed-in account, and how the `restricted` flag stops meaning anything.
     *
     * @var list<string>
     */
    private const SHARED_NO_ROLE = [
        'account.edit',
        'account.password',
        'account.update',
        'dashboard',
        'logout',
        'password.confirm',
        'password.update',
        'verification.notice',
        'verification.send',
        'verification.verify',
    ];

    /**
     * Public pages: no `auth` and no `role:`. ROLES.md "Public".
     *
     * @var list<string>
     */
    private const PUBLIC = [
        'about',
        'announcements.index',
        'contact',
        'home',
        'public.directory',
        'requests.track',
        'services.index',
        'transparency.index',
    ];

    /**
     * Guest-only sign-in and password-reset pages. ROLES.md "Guest-only".
     *
     * @var list<string>
     */
    private const GUEST_ONLY = [
        'login',
        'password.email',
        'password.request',
        'password.reset',
        'password.store',
        'register',
    ];

    /** The five roles ROLES.md is written around. */
    private const ROLES = ['admin', 'secretary', 'kagawad', 'resident', 'guest'];

    // -----------------------------------------------------------------
    // Completeness
    // -----------------------------------------------------------------

    /**
     * A new route with no row here would otherwise be silently unchecked.
     */
    #[Test]
    public function every_named_route_appears_in_the_matrix(): void
    {
        $known = array_merge(
            array_keys(self::ROLE_GUARDED),
            self::SHARED_NO_ROLE,
            self::PUBLIC,
            self::GUEST_ONLY,
        );

        $registered = $this->namedRoutes();

        $this->assertSame(
            [],
            array_values(array_diff($registered, $known)),
            'These routes are registered but have no row in RolesMatrixTest. Add them, and to ROLES.md, with a decision about who may reach them.',
        );

        $this->assertSame(
            [],
            array_values(array_diff($known, $registered)),
            'These routes are listed in RolesMatrixTest but are not registered. Remove them, or restore the route.',
        );
    }

    // -----------------------------------------------------------------
    // Task 5b - the structural guard
    // -----------------------------------------------------------------

    /**
     * No authenticated route may be left without a role guard.
     *
     * The one structural rule the old self-referential test could not express: a
     * route with `auth` and no `role:` is readable by every signed-in account
     * regardless of role, and its `restricted` flag is never checked. Today the
     * only such routes are the deliberate ones in SHARED_NO_ROLE, and each of them
     * is either the signpost or part of the escape hatch a frozen account needs.
     */
    #[Test]
    public function no_authenticated_route_lacks_a_role_guard(): void
    {
        $exempt = array_merge(self::SHARED_NO_ROLE, self::GUEST_ONLY, self::PUBLIC);

        $unguarded = [];

        foreach ($this->namedRoutes() as $name) {
            $route = Route::getRoutes()->getByName($name);
            $middleware = $this->middlewareOf($route);

            if ($this->has($middleware, 'auth') && ! $this->has($middleware, 'role:') && ! in_array($name, $exempt, true)) {
                $unguarded[] = $name;
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            'These routes require a session but carry no role: middleware, so any signed-in role can open them and the restricted flag is never checked. '
            .'Either add a role: guard, or add the name to SHARED_NO_ROLE with a reason.',
        );
    }

    /**
     * And the corollary: everything genuinely role-scoped must actually declare it.
     *
     * Catches a route that kept its name but lost its middleware - the failure
     * that would be invisible to a test which reads its expectations from the
     * middleware.
     */
    #[Test]
    public function every_route_the_matrix_says_is_guarded_really_is(): void
    {
        foreach (self::ROLE_GUARDED as $name => $expected) {
            $middleware = $this->middlewareOf(Route::getRoutes()->getByName($name));

            $this->assertTrue(
                $this->has($middleware, 'auth'),
                "[{$name}] is role-scoped in ROLES.md but carries no auth middleware.",
            );

            $this->assertTrue(
                $this->has($middleware, 'role:'),
                "[{$name}] is role-scoped in ROLES.md but carries no role: middleware.",
            );
        }
    }

    // -----------------------------------------------------------------
    // Task 5a - the matrix itself, route by route
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleGuardedProvider(): array
    {
        $cases = [];

        foreach (self::ROLE_GUARDED as $name => $expected) {
            $cases[$name] = [$name, $expected];
        }

        return $cases;
    }

    /**
     * The middleware must admit exactly the roles ROLES.md lists, no more and no
     * fewer. A typo in the guard is caught here rather than discovered later.
     */
    #[Test]
    #[DataProvider('roleGuardedProvider')]
    public function each_route_admits_exactly_the_roles_rol_es_md_gives_it(string $name, string $expected): void
    {
        $middleware = $this->middlewareOf(Route::getRoutes()->getByName($name));

        $actual = null;

        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'role:')) {
                $actual = substr($entry, strlen('role:'));
            }
        }

        $this->assertNotNull($actual, "[{$name}] has no role: middleware.");

        $this->assertSame(
            $this->sorted($expected),
            $this->sorted($actual),
            "[{$name}] is guarded by [{$actual}] but ROLES.md says [{$expected}].",
        );
    }

    /**
     * The decision 1 / decision 3 consequences, which the per-route test cannot
     * see because they are about the shape of the whole set rather than one route.
     */
    #[Test]
    public function the_captain_is_a_strict_superset_of_the_secretary(): void
    {
        // ROLES.md owner decision 1.
        $secretary = $this->routesGuarding(['admin', 'secretary']);
        $captain = $this->routesGuarding(['admin']);
        $captainAlso = $this->routesGuarding(['admin', 'secretary']);

        $captainReaches = array_merge($captain, $captainAlso);

        $this->assertSame(
            [],
            array_values(array_diff($secretary, $captainReaches)),
            'The Captain cannot reach a page a Secretary can, which breaks decision 1.',
        );

        $this->assertNotSame([], array_values(array_diff($captain, $secretary)), 'The Captain has no exclusive pages, which breaks decision 1.');
    }

    #[Test]
    public function the_role_areas_do_not_overlap(): void
    {
        // ROLES.md: an Admin or Secretary gets 403 in the Kagawad area, and a
        // Kagawad gets 403 in both staff areas. Asserted on the route names, since
        // a shared route name would be the shape of the bug.
        $kagawad = $this->routesGuarding(['kagawad']);
        $staff = array_merge(
            $this->routesGuarding(['admin', 'secretary']),
            $this->routesGuarding(['admin']),
        );

        $this->assertSame([], array_values(array_intersect($kagawad, $staff)), 'A route is reachable by both a Kagawad and a staff member.');

        $resident = $this->routesGuarding(['resident']);

        $this->assertSame([], array_values(array_intersect($resident, $staff)), 'A route is reachable by both a Resident and a staff member.');
        $this->assertSame([], array_values(array_intersect($resident, $kagawad)), 'A route is reachable by both a Resident and a Kagawad.');
    }

    // -----------------------------------------------------------------
    // Behaviour - the guard actually refuses
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function refusalProvider(): array
    {
        return [
            // route, role that must be refused, role that must be allowed
            'staff dashboard refuses a kagawad' => ['staff.dashboard', 'kagawad', 'secretary'],
            'resident directory refuses a resident' => ['staff.residents.index', 'resident', 'admin'],
            'verifications queue refuses a kagawad' => ['staff.verifications.index', 'kagawad', 'secretary'],
            'kagawad dashboard refuses the captain' => ['kagawad.dashboard', 'admin', 'kagawad'],
            'kagawad residents refuses a secretary' => ['kagawad.residents.index', 'secretary', 'kagawad'],
            'kagawad relief refuses the captain' => ['kagawad.relief', 'admin', 'kagawad'],
        ];
    }

    #[Test]
    #[DataProvider('refusalProvider')]
    public function the_server_refuses_what_the_matrix_says_it_refuses(string $route, string $refused, string $allowed): void
    {
        $this->seedFor($route);

        $this->actingAs($this->userFor($refused))
            ->get(route($route))
            ->assertForbidden();

        $this->actingAs($this->userFor($allowed))
            ->get(route($route))
            ->assertOk();
    }

    #[Test]
    public function a_guest_is_refused_or_redirected_everywhere_the_matrix_denies_them(): void
    {
        $roleGuarded = array_keys(self::ROLE_GUARDED);
        $sample = array_merge(
            ['staff.dashboard', 'staff.accounts.index', 'kagawad.dashboard', 'resident.requests.index'],
            $roleGuarded
        );

        foreach (array_unique($sample) as $name) {
            $route = Route::getRoutes()->getByName($name);

            /*
             * Parameterised routes are skipped here. Answering one needs a real
             * record, and the point of this sweep is the guard in front of the
             * route, which runs before any binding. Those routes are covered by
             * `each_route_admits_exactly_the_roles_rol_es_md_gives_it`, which
             * checks all of them, and by the refusal cases above.
             */
            if ($route->parameterNames() !== []) {
                continue;
            }

            $response = $this->get(route($name));

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403],
                "[{$name}] answered a guest with {$response->getStatusCode()}. Every role-guarded route must refuse a guest.",
            );
        }
    }

    #[Test]
    public function a_guest_may_still_reach_the_public_and_guest_pages(): void
    {
        $this->seedFor('kagawad.dashboard');

        // public.directory and requests.track are permanent redirects by design
        // (ROLES.md "Public"), so they are asserted as redirects rather than pages.
        $redirects = ['public.directory', 'requests.track'];

        foreach (array_diff(self::PUBLIC, $redirects) as $name) {
            $this->get(route($name))->assertSuccessful();
        }

        foreach ($redirects as $name) {
            $this->get(route($name))->assertStatus(301);
        }

        foreach (['login', 'register'] as $name) {
            $this->get(route($name))->assertSuccessful();
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function namedRoutes(): array
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || $name === '' || $name === 'up') {
                continue;
            }

            // Vendor tooling, not the application's own surface.
            if (str_starts_with($name, 'boost.') || str_starts_with($name, 'storage.')) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function middlewareOf(RoutingRoute $route): array
    {
        return array_values(array_map(
            fn ($m): string => is_string($m) ? $m : $m::class,
            $route->gatherMiddleware(),
        ));
    }

    /**
     * @param  list<string>  $middleware
     */
    private function has(array $middleware, string $needle): bool
    {
        if ($needle === 'role:') {
            foreach ($middleware as $m) {
                if (str_starts_with($m, 'role:')) {
                    return true;
                }
            }

            return false;
        }

        return in_array($needle, $middleware, true);
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function routesGuarding(array $roles): array
    {
        $wanted = $this->sorted(implode(',', $roles));
        $found = [];

        foreach (self::ROLE_GUARDED as $name => $expected) {
            if ($this->sorted($expected) === $wanted) {
                $found[] = $name;
            }
        }

        sort($found);

        return $found;
    }

    private function sorted(string $csv): string
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)));
        sort($parts);

        return implode(',', $parts);
    }

    private function userFor(string $role): User
    {
        return match ($role) {
            'admin' => User::factory()->admin()->create(['is_verified' => true]),
            'secretary' => User::factory()->secretary()->create(['is_verified' => true]),
            'kagawad' => $this->kagawadWithSector(),
            default => User::factory()->resident()->create(['is_verified' => true]),
        };
    }

    private function kagawadWithSector(): User
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);

        Sector::factory()->create(['kagawad_user_id' => $kagawad->getKey()]);

        return $kagawad;
    }

    /**
     * A Kagawad's own pages need a sector and a batch, or they render the
     * unassigned state and 200 for the wrong reason. Staff pages need nothing.
     */
    private function seedFor(string $route): void
    {
        if (! str_starts_with($route, 'kagawad.')) {
            return;
        }

        $kagawad = User::where('role', User::ROLE_KAGAWAD)->first();

        if ($kagawad === null) {
            return;
        }

        $sector = Sector::where('kagawad_user_id', $kagawad->getKey())->first();

        if ($sector === null) {
            return;
        }

        if (ReliefBatch::query()->whereHas('distributions')->doesntExist()) {
            $batch = ReliefBatch::factory()->create();

            ReliefDistribution::factory()->create([
                'relief_batch_id' => $batch->getKey(),
                'sector_id' => $sector->getKey(),
            ]);
        }
    }
}
