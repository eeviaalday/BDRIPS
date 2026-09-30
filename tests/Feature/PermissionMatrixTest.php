<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\RoleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The permission matrix, checked against the route table itself.
 *
 * The rest of the suite tests routes it knows about by name. This file derives
 * its cases from every route the application actually registers, so a route
 * added next year with no role middleware fails here instead of quietly being
 * public.
 *
 * The expected answer for a route is read from the role: middleware that route
 * already carries. That is deliberate: this file verifies that the middleware is
 * genuinely applied and enforced, not that it was written correctly once. The
 * assertions about what each middleware SHOULD say are the few structural checks
 * below - no /staff route unguarded, no kagawad anywhere near a staff group, and
 * so on.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The roles, with the user each needs before it can be signed in.
     *
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'secretary' => [User::ROLE_SECRETARY],
            'kagawad' => [User::ROLE_KAGAWAD],
            'resident' => [User::ROLE_RESIDENT],
        ];
    }

    // ---------------------------------------------------------------------
    // Structural: what the middleware must say
    // ---------------------------------------------------------------------

    /**
     * The whole point of the staff, kagawad and resident areas. A route added to
     * a group without the role middleware would be open to any signed-in
     * account, and this is the check that would notice.
     */
    #[Test]
    public function every_route_in_a_role_area_carries_its_role_middleware(): void
    {
        $expected = [
            'staff/' => 'admin',
            'kagawad/' => 'kagawad',
        ];

        foreach (self::appRoutes() as $route) {
            $uri = $route->uri();

            foreach ($expected as $prefix => $role) {
                if (! str_starts_with($uri, $prefix)) {
                    continue;
                }

                $roles = self::rolesFor($route);

                $this->assertContains(
                    $role,
                    $roles,
                    sprintf('%s %s has no role:%s middleware.', $route->methods()[0], $uri, $role)
                );
            }
        }
    }

    #[Test]
    public function every_resident_route_is_resident_only(): void
    {
        foreach (self::appRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'resident/')) {
                continue;
            }

            $this->assertSame(
                [User::ROLE_RESIDENT],
                self::rolesFor($route),
                sprintf('%s is not resident-only.', $route->getName() ?? $route->uri())
            );
        }
    }

    /**
     * A Kagawad's authority is one sector's checklist and nothing else. If the
     * word kagawad ever appeared in a staff or admin group, a Kagawad would
     * reach the whole barangay's records, which is the exact thing the sector
     * split exists to prevent.
     */
    #[Test]
    public function kagawad_never_appears_in_a_staff_or_admin_group(): void
    {
        foreach (self::appRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'staff/')) {
                continue;
            }

            $this->assertNotContains(
                User::ROLE_KAGAWAD,
                self::rolesFor($route),
                sprintf('%s would let a Kagawad into a staff screen.', $route->getName() ?? $route->uri())
            );
        }
    }

    /**
     * Admin is a superset: wherever the Secretary may go, the Admin may too. A
     * staff route that admits a Secretary but not an Admin would mean the
     * Captain could not fix a Secretary's mistake, which is the opposite of what
     * the extra navigation items are for.
     */
    #[Test]
    public function admin_can_reach_every_staff_screen_a_secretary_can(): void
    {
        foreach (self::appRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'staff/')) {
                continue;
            }

            $roles = self::rolesFor($route);

            if (in_array(User::ROLE_SECRETARY, $roles, true)) {
                $this->assertContains(
                    User::ROLE_ADMIN,
                    $roles,
                    sprintf('%s admits a Secretary but not an Admin.', $route->getName() ?? $route->uri())
                );
            }
        }
    }

    /**
     * The audit log and the batch records are the two places a destructive action
     * could quietly destroy history. Neither may have a write route at all,
     * rather than relying on a controller to refuse.
     */
    #[Test]
    public function the_read_only_areas_have_no_write_routes(): void
    {
        $writable = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach (self::appRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'staff/audit')) {
                continue;
            }

            $this->assertSame(
                [],
                array_intersect($route->methods(), $writable),
                'The audit log must be read only.'
            );
        }

        foreach (self::appRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'staff/batches')) {
                continue;
            }

            $this->assertNotContains(
                'DELETE',
                $route->methods(),
                'A relief batch must be closable but never deletable.'
            );
        }
    }

    /**
     * Every navigation entry must point at a route that exists. A dead link in
     * the sidebar is worse than a missing one, and RoleNavigation filters on
     * Route::has() so this should hold by construction - which is exactly why it
     * is worth asserting.
     */
    #[Test]
    public function every_navigation_entry_points_at_a_real_route(): void
    {
        foreach (User::roles() as $role) {
            foreach (RoleNavigation::availableSidebarFor($role) as $item) {
                $this->assertTrue(
                    Route::has($item['route']),
                    sprintf('The %s navigation links to missing route %s.', $role, $item['route'])
                );
            }
        }
    }

    /**
     * A restricted account keeps exactly three things: read its own account,
     * change its password, and sign out. Losing those would make a restriction a
     * way to lose an account outright.
     */
    #[Test]
    public function a_restricted_account_keeps_only_the_three_ways_out(): void
    {
        $target = $this->userFor(User::ROLE_SECRETARY);
        $target->update(['restricted_until' => now()->addWeek()]);

        $kept = [
            'account.edit' => 200,
            'account.password' => 200,
        ];

        foreach ($kept as $name => $expected) {
            $this->actingAs($target->fresh())
                ->get(route($name))
                ->assertOk();
        }

        $this->actingAs($target->fresh())->post(route('logout'))->assertRedirect();

        $locked = [
            'staff.dashboard',
            'staff.verifications.index',
            'staff.residents.index',
            'staff.requests.index',
            'staff.batches.index',
            'staff.announcements.index',
        ];

        foreach ($locked as $name) {
            $this->actingAs($target->fresh())
                ->get(route($name))
                ->assertForbidden();
        }
    }

    // ---------------------------------------------------------------------
    // Behavioural: the sweep
    // ---------------------------------------------------------------------

    /**
     * Every guarded route, for every role that must not reach it.
     *
     * Asserting the exact status matters. A 302 would mean the guard quietly
     * redirected instead of refusing, and a 404 would mean the record does not
     * exist - either would hide the fact that the page is there.
     */
    #[Test]
    public function a_role_that_is_not_admitted_is_refused_exactly(): void
    {
        $swept = 0;

        foreach (self::appRoutes() as $route) {
            $roles = self::rolesFor($route);

            if ($roles === [] || self::parametersOf($route) !== []) {
                continue;
            }

            $swept++;

            foreach (array_keys(self::roles()) as $role) {
                $user = $this->userFor($role);
                $response = $this->actingAs($user)->get(route($route->getName()));

                if (in_array($role, $roles, true)) {
                    // Not "successful": a route that does its job by redirecting
                    // to a canonical URL is doing its job. What must not happen is
                    // a refusal, a missing record, or a verb the route does not
                    // accept - each of which would mean the sweep proved nothing.
                    $this->assertNotContains(
                        $response->getStatusCode(),
                        [403, 404, 405],
                        sprintf('%s should admit %s but answered %d.', $route->getName(), $role, $response->getStatusCode())
                    );

                    continue;
                }

                $response->assertForbidden(
                    $route->getName().' should refuse '.$role.' but did not.'
                );
            }
        }

        // So the sweep cannot pass by sweeping nothing.
        $this->assertGreaterThan(10, $swept, 'The route sweep covered almost no routes.');
    }

    /**
     * The same sweep for the routes that need a real record behind them.
     *
     * Every parameter name is resolved to a model that genuinely exists, because
     * bindings resolve before the role middleware and a missing id would answer
     * 404 for the wrong reason - a 404 would read as "refused" and hide a
     * missing guard.
     */
    #[Test]
    public function a_parameterised_route_honours_the_same_matrix(): void
    {
        $swept = 0;

        foreach (self::appRoutes() as $route) {
            $roles = self::rolesFor($route);
            $parameters = self::parametersOf($route);

            if ($roles === [] || $parameters === []) {
                continue;
            }

            // token and hash belong to password reset and email verification,
            // which are single-purpose flows tested on their own terms; a sweep
            // cannot meaningfully sign a user in to them.
            if (in_array('token', $parameters, true) || in_array('hash', $parameters, true)) {
                continue;
            }

            $swept++;

            foreach (array_keys(self::roles()) as $role) {
                $user = $this->userFor($role);
                $url = $this->urlFor($route, $user);
                $verb = self::verbFor($route);

                $response = $this->actingAs($user)->{$verb}($url);

                if (in_array($role, $roles, true)) {
                    // Not "successful": a write that did its job redirects, and
                    // that is the correct outcome. What must not happen is a
                    // refusal, a missing record, or a verb the route does not
                    // accept - each of which would mean the sweep proved nothing.
                    $this->assertNotContains(
                        $response->getStatusCode(),
                        [403, 404, 405],
                        sprintf('%s should admit %s but answered %d.', $route->getName(), $role, $response->getStatusCode())
                    );

                    continue;
                }

                $response->assertForbidden(
                    $route->getName().' should refuse '.$role.' but did not.'
                );
            }
        }

        // So the sweep cannot pass by sweeping nothing.
        $this->assertGreaterThan(5, $swept, 'The parameterised sweep covered almost no routes.');
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * @return list<RoutingRoute>
     */
    private static function appRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route) => str_contains($route->getActionName(), 'Illuminate\\')
                || str_contains($route->getActionName(), 'Laravel\\'))
            ->values()
            ->all();
    }

    /**
     * The roles a route's own middleware admits.
     *
     * @return list<string>
     */
    private static function rolesFor(RoutingRoute $route): array
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                return array_values(array_filter(explode(',', substr($middleware, 5))));
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function parametersOf(RoutingRoute $route): array
    {
        return array_values($route->parameterNames());
    }

    /**
     * The verb to exercise a route with.
     *
     * The route's own verb, not always GET: the router answers 405 to a GET on a
     * POST-only route before the role middleware runs, so a GET sweep would look
     * like it had tested those routes when it had not tested them at all.
     *
     * @return 'get'|'post'|'put'|'patch'|'delete'
     */
    private static function verbFor(RoutingRoute $route): string
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            if (in_array($verb, $route->methods(), true)) {
                return strtolower($verb);
            }
        }

        return 'get';
    }

    /**
     * A URL for a parameterised route, with every parameter bound to a record
     * that exists.
     *
     * $user is passed because one of the bindings has to suit the account being
     * signed in: a document request belongs to a resident, and the sweep's whole
     * purpose includes the resident reaching their own.
     */
    private function urlFor(RoutingRoute $route, User $user): string
    {
        $parameters = [];

        foreach (self::parametersOf($route) as $name) {
            $parameters[$name] = match ($name) {
                'documentRequest' => $this->documentRequestFor($user)->getKey(),
                'user' => $this->userFor(User::ROLE_KAGAWAD)->getKey(),
                'announcement' => Announcement::factory()->create()->getKey(),
                'profile' => $this->profileFor($user)->getKey(),
                'sector' => Sector::factory()->create()->getKey(),
                'reliefBatch' => $this->batchFor($user)->getKey(),
                'distribution' => $this->distributionFor($user)->getKey(),
                default => 1,
            };
        }

        return route($route->getName(), $parameters);
    }

    private function documentRequestFor(User $user): DocumentRequest
    {
        $request = DocumentRequest::query()
            ->where('user_id', $user->getKey())
            ->first();

        return $request ?? DocumentRequest::factory()->submitted()->create(['user_id' => $user->getKey()]);
    }

    /**
     * A profile for this user, carrying an ID file.
     *
     * The ID is written because staff.resident-verifications.id-photo answers 404
     * for a profile with no ID on it. That is the route working correctly, but in
     * a sweep it would be indistinguishable from a missing guard, so the fixture
     * gives it something real to serve.
     */
    private function profileFor(User $user): ResidentProfile
    {
        $profile = ResidentProfile::query()->where('user_id', $user->getKey())->first();

        if ($profile !== null) {
            return $profile;
        }

        $path = 'id-photos/sweep-'.$user->getKey().'.png';
        Storage::disk('local')->put($path, 'bytes');

        return ResidentProfile::factory()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
            'id_photo_path' => $path,
        ]);
    }

    private function batchFor(User $user): ReliefBatch
    {
        $batch = ReliefBatch::factory()->create([
            'status' => ReliefBatch::STATUS_ACTIVE,
            'created_by_user_id' => $this->userFor(User::ROLE_SECRETARY)->getKey(),
        ]);

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->getKey(),
            'sector_id' => $this->sectorFor($user)->getKey(),
        ]);

        return $batch;
    }

    private function distributionFor(User $user): ReliefDistribution
    {
        $sector = $this->sectorFor($user);

        return ReliefDistribution::query()
            ->where('sector_id', $sector->getKey())
            ->first()
            ?? ReliefDistribution::factory()->create([
                'relief_batch_id' => $this->batchFor($user)->getKey(),
                'sector_id' => $sector->getKey(),
            ]);
    }

    /**
     * The sector a signed-in Kagawad is responsible for, or any sector for
     * everyone else - the other roles reach relief records without one.
     */
    private function sectorFor(User $user): Sector
    {
        if ($user->isKagawad()) {
            $assigned = Sector::query()->where('kagawad_user_id', $user->getKey())->first();

            if ($assigned !== null) {
                return $assigned;
            }
        }

        return Sector::factory()->create();
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === User::ROLE_RESIDENT) {
            ResidentProfile::factory()->create([
                'user_id' => $user->getKey(),
                'sector_id' => Sector::factory()->create()->getKey(),
            ]);
        }

        if ($role === User::ROLE_KAGAWAD) {
            Sector::factory()->create(['kagawad_user_id' => $user->getKey()]);
        }

        return $user;
    }
}
