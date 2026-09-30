<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\RoleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What each role can see in the navigation, and what it must not.
 *
 * Two layers are asserted, because they answer different questions and either
 * one alone is satisfiable by a broken build:
 *
 *   1. defined() - the whole intended list, which is the specification. It does
 *      not care whether a route exists yet, so the permission model can be
 *      asserted in full while the areas are still being built.
 *   2. the rendered navbar - what actually appears. Filtered to routes that are
 *      registered, so a link to an unbuilt page cannot be a 500, and so the
 *      rendered output is checked against what exists.
 *
 * The Secretary/Admin split is the reason this file exists. The previous
 * navigation gave both roles one identical list, and its own comment recorded
 * that there was no route or middleware separating them.
 */
class RoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The specification: what each role is intended to reach
    // ---------------------------------------------------------------------

    #[Test]
    public function test_a_secretary_is_intended_to_reach_exactly_the_seven_destinations(): void
    {
        $this->assertSame(
            [
                'staff.dashboard',
                'staff.verifications.index',
                'staff.residents.index',
                'staff.requests.index',
                'staff.batches.index',
                'staff.announcements.index',
                'account.edit',
            ],
            $this->routesFor(User::ROLE_SECRETARY),
        );
    }

    #[Test]
    public function test_an_admin_is_intended_to_reach_everything_the_secretary_is_plus_five(): void
    {
        $secretary = $this->routesFor(User::ROLE_SECRETARY);
        $admin = $this->routesFor(User::ROLE_ADMIN);

        // The six destinations they share, in the same order, first.
        $this->assertSame(
            array_slice($secretary, 0, 6),
            array_slice($admin, 0, 6),
            'The destinations Secretary and Admin share are not identical, or their order differs.',
        );

        // Admin adds exactly five, and keeps My Account last as Secretary does.
        $this->assertSame(
            [
                'staff.accounts.index',
                'staff.sectors.index',
                'staff.settings.index',
                'staff.audit.index',
                'staff.status.index',
                'account.edit',
            ],
            array_slice($admin, 6),
        );

        $this->assertSame('account.edit', end($secretary), 'My Account should close the Secretary list.');
        $this->assertSame('account.edit', end($admin), 'My Account should close the Admin list.');
    }

    #[Test]
    public function test_a_kagawad_is_intended_to_reach_only_three_destinations(): void
    {
        $this->assertSame(
            ['kagawad.dashboard', 'kagawad.relief', 'account.edit'],
            $this->routesFor(User::ROLE_KAGAWAD),
        );
    }

    /**
     * The whole point of splitting the two staff roles. Each of these is a page a
     * Secretary must not be able to reach, named because a shared list is exactly
     * how one of them would end up on a Secretary's sidebar.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function adminOnlyProvider(): array
    {
        return [
            'user accounts' => ['staff.accounts.index', 'User Accounts'],
            'sectors and kagawad' => ['staff.sectors.index', 'Sectors & Kagawad'],
            'settings' => ['staff.settings.index', 'Settings'],
            'audit logs' => ['staff.audit.index', 'Audit Logs'],
            'system status' => ['staff.status.index', 'System Status'],
        ];
    }

    #[Test]
    #[DataProvider('adminOnlyProvider')]
    public function test_a_secretary_is_not_intended_to_reach_an_admin_only_page(string $route, string $label): void
    {
        $secretary = $this->routesFor(User::ROLE_SECRETARY);

        $this->assertNotContains($route, $secretary, "A Secretary is intended to reach [{$route}].");
        $this->assertContains($route, $this->routesFor(User::ROLE_ADMIN));
    }

    #[Test]
    #[DataProvider('adminOnlyProvider')]
    public function test_a_kagawad_is_not_intended_to_reach_any_staff_page(string $route, string $label): void
    {
        $kagawad = $this->routesFor(User::ROLE_KAGAWAD);

        $this->assertNotContains($route, $kagawad, "A Kagawad is intended to reach [{$route}].");
    }

    #[Test]
    public function test_a_kagawad_is_not_intended_to_see_residents_or_document_requests(): void
    {
        // Named together because both leak personal data: one is a directory of
        // residents, the other is their paperwork.
        foreach (['staff.residents.index', 'staff.requests.index', 'staff.verifications.index'] as $route) {
            $this->assertNotContains($route, $this->routesFor(User::ROLE_KAGAWAD));
        }
    }

    #[Test]
    public function test_my_account_is_reachable_by_every_role(): void
    {
        foreach ([User::ROLE_RESIDENT, User::ROLE_KAGAWAD, User::ROLE_SECRETARY, User::ROLE_ADMIN] as $role) {
            $this->assertContains(
                'account.edit',
                $role === User::ROLE_RESIDENT
                    ? array_column(RoleNavigation::residentBar()['profile'], 'route')
                    : $this->routesFor($role),
                "[{$role}] cannot reach My Account.",
            );
        }
    }

    // ---------------------------------------------------------------------
    // The resident's top navbar
    // ---------------------------------------------------------------------

    #[Test]
    public function test_the_resident_bar_is_the_public_row_plus_the_two_portal_destinations(): void
    {
        $this->assertSame(
            [
                'home' => 'Home',
                'services.index' => 'Services',
                'about' => 'About',
                'announcements.index' => 'Announcements',
                'contact' => 'Contact',
                'resident.requests.index' => 'Document Requests',
                'resident.relief' => 'Relief Transparency',
            ],
            collect(RoleNavigation::residentBar()['primary'])->pluck('label', 'route')->all(),
        );
    }

    #[Test]
    public function test_the_resident_profile_menu_holds_exactly_the_four_approved_actions(): void
    {
        $this->assertSame(
            [
                'account.edit' => 'Edit Profile',
                'account.password' => 'Change Password',
                'resident.verification.status' => 'Verification Status',
                'logout' => 'Log out',
            ],
            collect(RoleNavigation::residentBar()['profile'])->pluck('label', 'route')->all(),
        );
    }

    #[Test]
    public function test_the_resident_bar_has_no_dashboard_entry(): void
    {
        // The old navbar led with a Dashboard that duplicated the two portal
        // links below it. A resident's home is the public home page.
        $routes = array_column(RoleNavigation::residentBar()['primary'], 'route');

        $this->assertNotContains('resident.dashboard', $routes);
        $this->assertNotContains('Dashboard', array_column(RoleNavigation::residentBar()['primary'], 'label'));
    }

    #[Test]
    public function test_the_resident_profile_menu_has_no_account_settings_entry(): void
    {
        // My Account is one screen for every role. A second name for it was a
        // second place for the role split to be wrong.
        $labels = array_column(RoleNavigation::residentBar()['profile'], 'label');

        $this->assertNotContains('Account Settings', $labels);
        $this->assertNotContains('resident.settings', array_column(RoleNavigation::residentBar()['profile'], 'route'));
    }

    // ---------------------------------------------------------------------
    // What actually renders
    // ---------------------------------------------------------------------

    #[Test]
    public function test_a_resident_sees_their_own_navbar_and_no_login_buttons(): void
    {
        $user = $this->resident();

        $content = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('My Profile', $content);
        $this->assertStringContainsString('Verification Status', $content);
        $this->assertStringNotContainsString('My Dashboard', $content);

        // The guest bar's two auth links must be gone, not merely restyled.
        $this->assertStringNotContainsString('>Register<', $content);
    }

    #[Test]
    public function test_a_guest_still_sees_the_untouched_public_navbar(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('>Log in<', $content);
        $this->assertStringContainsString('>Register<', $content);
        $this->assertStringNotContainsString('My Profile', $content);
    }

    #[Test]
    public function test_logout_is_a_post_form_and_never_a_link(): void
    {
        // A GET /logout can be fired by an image tag or a link prefetcher.
        $content = $this->actingAs($this->resident())->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('logout').'"', $content);
        $this->assertStringNotContainsString('href="'.route('logout').'"', $content);
    }

    /**
     * The removed controls. Asserted as absences on a page that would otherwise
     * carry them, so a reintroduced bell is caught here rather than being
     * noticed by eye.
     *
     * @return list<array{0: string}>
     */
    public static function staffPageProvider(): array
    {
        return [['staff.dashboard'], ['kagawad.dashboard']];
    }

    #[Test]
    #[DataProvider('staffPageProvider')]
    public function test_the_staff_topbar_has_no_notification_bell(string $route): void
    {
        $content = $this->actingAs($this->userForRole(
            $route === 'staff.dashboard' ? 'secretary' : 'kagawad'
        ))->get(route($route))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'Recent updates and announcements',
            $content,
            'The notification bell is back. There is no notification system behind it.',
        );
        $this->assertStringNotContainsString(
            'aria-label="Settings"',
            $content,
            'The settings cog is back; System Settings is a single Admin destination in the sidebar.',
        );
    }

    #[Test]
    public function test_the_rendered_sidebar_only_shows_each_role_its_permitted_destinations(): void
    {
        // In Phase 1 only the dashboard and My Account have routes, so that is all
        // either role can see. The assertion that matters is not "the sidebar has
        // these two links" - it is that whatever the sidebar shows, this role is
        // permitted to have. That stays true as later phases add routes.
        $secretary = $this->actingAs($this->userForRole('secretary'))
            ->get(route('staff.dashboard'))->assertOk()->getContent();

        $admin = $this->actingAs($this->userForRole('admin'))
            ->get(route('staff.dashboard'))->assertOk()->getContent();

        foreach ([$secretary, $admin] as $content) {
            $this->assertStringContainsString('Dashboard', $content);
            $this->assertStringContainsString('My Account', $content);
        }

        // Nothing a Secretary may not reach is present in their sidebar. Checked
        // against the role's own list rather than a hardcoded pair, so this keeps
        // working as destinations are added.
        $permitted = $this->routesFor(User::ROLE_SECRETARY);
        $rendered = RoleNavigation::availableSidebarFor(User::ROLE_SECRETARY);

        foreach ($rendered as $link) {
            $this->assertContains($link['route'], $permitted, 'A Secretary is shown a destination they may not reach.');
        }

        $this->assertNotEmpty($rendered, 'The Secretary sidebar rendered nothing at all.');
    }

    #[Test]
    public function test_a_kagawad_sidebar_offers_only_their_own_destinations(): void
    {
        $content = $this->actingAs($this->userForRole('kagawad'))
            ->get(route('kagawad.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Dashboard', $content);
        $this->assertStringContainsString('My Account', $content);

        // Every rendered link must be one the Kagawad is permitted.
        foreach (RoleNavigation::availableSidebarFor(User::ROLE_KAGAWAD) as $link) {
            $this->assertContains($link['route'], $this->routesFor(User::ROLE_KAGAWAD));
        }

        // And nothing from either staff list may appear. Checked by label, taken
        // from the definition rather than hardcoded, and against the labels of
        // the routes a Kagawad is not permitted - which cannot be turned into a
        // URL here because those routes do not exist yet.
        $forbidden = array_column(
            array_merge(
                RoleNavigation::sidebarFor(User::ROLE_SECRETARY),
                RoleNavigation::sidebarFor(User::ROLE_ADMIN),
            ),
            'label'
        );

        $permittedLabels = array_column(RoleNavigation::sidebarFor(User::ROLE_KAGAWAD), 'label');

        foreach (array_unique($forbidden) as $label) {
            if (in_array($label, $permittedLabels, true)) {
                // Relief Distribution is on both lists, so its presence proves
                // nothing either way.
                continue;
            }

            $this->assertStringNotContainsString($label, $content, "A Kagawad's sidebar offers [{$label}].");
        }
    }

    // ---------------------------------------------------------------------
    // Filtering
    // ---------------------------------------------------------------------

    #[Test]
    public function test_a_destination_whose_route_does_not_exist_is_not_rendered(): void
    {
        // Phase 1: the staff areas beyond the dashboard have no routes yet, so
        // the sidebar renders only what exists. Without this filter a sidebar
        // link is not a dead link, it is a RouteNotFoundException on every page.
        $links = RoleNavigation::availableSidebarFor(User::ROLE_ADMIN);
        $routes = array_column($links, 'route');

        $this->assertContains('staff.dashboard', $routes, 'The dashboard route exists and should render.');
        $this->assertContains('account.edit', $routes);

        foreach ($routes as $route) {
            $this->assertTrue(
                Route::has($route),
                "[{$route}] is rendered but no such route is registered.",
            );
        }
    }

    #[Test]
    public function test_log_out_is_never_filtered_out_of_the_resident_menu(): void
    {
        // It is a POST form rather than a link, so it has no route lookup to fail
        // on - but it must still be in the list, because a resident who cannot
        // find the sign-out is stranded.
        $this->assertContains('logout', array_column(RoleNavigation::availableResidentBar()['profile'], 'route'));
    }

    /**
     * @return list<string>
     */
    private function routesFor(string $role): array
    {
        return array_column(RoleNavigation::sidebarFor($role), 'route');
    }

    private function resident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function userForRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === 'kagawad') {
            Sector::factory()->create(['kagawad_user_id' => $user->id]);
        }

        return $user;
    }
}
