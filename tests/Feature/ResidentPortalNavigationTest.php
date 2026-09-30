<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The resident's portal navigation.
 *
 * Two things are being defended here. First, that every sidebar link keeps a
 * signed-in resident inside /resident - the sidebar used to link out to the
 * public transparency and directory pages, which dropped them into the public
 * layout with no way back. Second, that the links people reach for are real
 * routes: the verification page referenced resident.profile.edit, which was
 * never defined and threw a RouteNotFoundException for any verified profile.
 */
class ResidentPortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user->fresh();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function portalPages(): array
    {
        return [
            ['resident.dashboard', '/resident/dashboard'],
            ['resident.directory', '/resident/directory'],
            ['resident.relief', '/resident/relief'],
            ['resident.profile', '/resident/profile'],
            ['resident.settings', '/resident/settings'],
            ['resident.requests.index', '/resident/requests'],
            ['resident.requests.create', '/resident/requests/create'],
        ];
    }

    #[DataProvider('portalPages')]
    public function test_the_portal_page_renders_for_a_resident(string $name, string $path): void
    {
        $this->actingAs($this->resident())
            ->get($path)
            ->assertOk();
    }

    #[DataProvider('portalPages')]
    public function test_the_portal_page_is_closed_to_a_guest(string $name, string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    #[DataProvider('portalPages')]
    public function test_the_portal_page_is_closed_to_another_role(string $name, string $path): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($path)
            ->assertForbidden();
    }

    public function test_the_resident_navbar_offers_the_resident_destinations(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        // The three things a resident signs in to do join the public row, and the
        // gold button becomes the profile menu.
        //
        // "My Dashboard" is deliberately absent as a *label*: the tab reads
        // "Dashboard". The assertion below checks the destination instead, which
        // is the part that actually matters - a label can be reworded, a nav
        // that cannot reach the signed-in landing page is broken.
        $this->assertStringContainsString('Document Request', $content);
        $this->assertStringContainsString('Relief Transparency', $content);
        $this->assertStringContainsString('My Profile', $content);
        $this->assertStringNotContainsString('My Dashboard', $content);
    }

    public function test_the_resident_navbar_can_get_back_to_the_dashboard(): void
    {
        // There used to be no link to the dashboard at all, so a resident who
        // wandered into the portal had no way back to their own page except the
        // logo, which goes to the public homepage.
        //
        // It moved out of the primary row and into the profile menu when the
        // resident navbar was aligned with the public one, so this now asserts
        // the destination is still offered - not that it sits in the top row.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.directory', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dashboard', $content);
        $this->assertStringContainsString(route('resident.dashboard', absolute: false), $content);
    }

    public function test_the_resident_primary_row_mirrors_the_public_one(): void
    {
        // The guest row plus the two things a resident signs in to do. The
        // dashboard used to lead this row, which made the bar a different shape
        // from the public one and pushed its desktop breakpoint to xl.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        foreach ([
            'Home', 'Services', 'About', 'Announcements', 'Contact',
            'Document Request', 'Relief Transparency',
        ] as $label) {
            // The label sits on its own line inside the anchor, so this allows
            // surrounding whitespace rather than assuming ">Home</a>".
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*<\/a>/',
                $content,
                "The resident navbar does not offer \"{$label}\".",
            );
        }
    }

    public function test_the_profile_menu_reaches_account_settings(): void
    {
        // /resident/settings is a working page that reuses the framework's own
        // name-and-password forms, but it was linked only from the staff
        // sidebar - which no resident view renders. So in practice a resident
        // could not reach their own account settings at all.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Account Settings', $content);
        $this->assertStringContainsString(route('resident.settings', absolute: false), $content);
    }

    public function test_every_profile_menu_item_works_for_a_signed_in_resident(): void
    {
        // The dropdown was written out twice, once per breakpoint, which is how
        // the two drifted apart. Both now loop one array, and each entry has to
        // resolve - a menu link to a route that 404s is worse than no link.
        foreach ([
            'resident.dashboard',
            'resident.profile',
            'resident.profile.edit',
            'resident.settings',
            'resident.profile.change-password',
            'resident.verification.status',
        ] as $name) {
            $this->assertTrue(
                Route::has($name),
                "The profile menu links to {$name}, which is not a route.",
            );
        }

        $this->actingAs($this->resident())
            ->get(route('resident.settings'))
            ->assertOk();
    }

    public function test_the_profile_menu_is_offered_on_both_breakpoints(): void
    {
        // "Desktop" and "mobile" here are the two renderings of the same header:
        // the desktop dropdown is xl:block and the mobile panel xl:hidden. Both
        // are in the HTML at once, so a link present in only one of them is
        // invisible to whichever resident is on that viewport.
        //
        // Counted inside the <header> only. The page body links to the same
        // destinations - the dashboard's own quick actions, the verification
        // banner - and including those would make the count meaningless.
        $page = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $start = strpos($page, '<header');
        $end = $start === false ? false : strpos($page, '</header>', $start);

        $this->assertNotFalse($start, 'The resident navbar did not render a <header>.');
        $this->assertNotFalse($end, 'The resident navbar did not close its <header>.');

        $header = substr($page, $start, $end - $start + strlen('</header>'));

        // Two occurrences of each account destination inside the header: once in
        // the dropdown, once in the mobile panel. route() in a template emits an
        // absolute URL, so that is what has to be counted.
        foreach ([
            route('resident.profile'),
            route('resident.settings'),
            route('resident.dashboard'),
            route('resident.profile.change-password'),
            route('resident.verification.status'),
        ] as $url) {
            $this->assertSame(
                2,
                substr_count($header, 'href="'.$url.'"'),
                "{$url} should appear once in the profile menu and once in the mobile panel.",
            );
        }
    }

    public function test_the_relief_tab_stays_inside_the_portal(): void
    {
        // It used to point at the public transparency board, which threw the
        // resident out of the portal layout to answer a question the portal
        // page answers directly.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.relief', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('resident.relief', absolute: false), $content);
        $this->assertStringContainsString('aria-current="page"', $content);
    }

    public function test_a_guest_still_gets_the_original_public_navbar(): void
    {
        $content = $this->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Log in', $content);
        $this->assertStringContainsString('Register', $content);

        // None of the signed-in resident's affordances, so the guest bar really
        // is untouched.
        $this->assertStringNotContainsString('My Profile', $content);
    }

    public function test_a_signed_in_resident_is_not_offered_log_in_or_register(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>Register<', $content);
        $this->assertStringNotContainsString('>Log in<', $content);
    }

    public function test_the_profile_menu_carries_all_three_tabs_and_a_csrf_logout(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Edit Profile', $content);
        $this->assertStringContainsString('Change Password', $content);
        $this->assertStringContainsString('Verification Status', $content);

        // Logout is a POST carrying a token. A GET link can be triggered by any
        // image tag on any page a resident visits.
        $this->assertStringContainsString('action="'.route('logout').'"', $content);
        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="[^"]*\/logout">\s*<input type="hidden" name="_token"/',
            $content,
        );
    }

    public function test_the_navbar_is_the_same_for_a_pending_and_a_verified_resident(): void
    {
        $pending = User::factory()->resident()->create(['is_verified' => true]);
        ResidentProfile::factory()->pending()->create(['user_id' => $pending->id]);

        $verifiedHtml = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();
        $pendingHtml = $this->actingAs($pending->fresh())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        $nav = static function (string $html): string {
            preg_match('#<header.*?</header>#s', $html, $m);

            return $m[0] ?? '';
        };

        // Same layout and same navbar. The only difference is the gate banner
        // under it, which is not part of the header.
        $this->assertSame($nav($verifiedHtml), $nav($pendingHtml));
        $this->assertStringContainsString('verification is pending', $pendingHtml);
        $this->assertStringNotContainsString('verification is pending', $verifiedHtml);
    }

    public function test_the_back_to_homepage_link_is_gone_for_every_role(): void
    {
        foreach (['resident', 'kagawad', 'admin'] as $role) {
            $user = $role === 'resident' ? $this->resident() : User::factory()->create(['role' => $role]);

            $path = match ($role) {
                'kagawad' => route('kagawad.dashboard', absolute: false),
                'admin' => route('staff.dashboard', absolute: false),
                default => route('resident.dashboard', absolute: false),
            };

            $content = $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'Back to Homepage',
                $content,
                "The \"Back to Homepage\" link is still on the {$role} sidebar.",
            );
        }
    }

    public function test_the_official_directory_is_reachable_from_the_resident_navbar(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        // The sidebar's "Official Directory" label is gone, and the public
        // "Officials" link that replaced it resolves to the public directory -
        // which a signed-in resident can now reach without being thrown out of
        // the portal, because the resident navbar and the public navbar are the
        // same bar.
        $this->assertStringNotContainsString('Official Directory', $content);
        $this->assertStringContainsString('Officials', $content);
    }

    public function test_the_account_links_are_not_offered_to_staff(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get(route('staff.dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        // Staff manage the system, not a resident profile, so the resident-only
        // group must not render for them.
        $this->assertStringNotContainsString('Resident Profile', $content);
        $this->assertStringNotContainsString('Account Settings', $content);
    }

    public function test_the_verification_page_no_longer_calls_a_route_that_does_not_exist(): void
    {
        // resident.profile.edit was referenced twice by the verification view and
        // was never defined, so any verified profile who opened that page got a
        // RouteNotFoundException instead of a page.
        $content = $this->actingAs($this->resident())
            ->get(route('resident.verification', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('resident.profile.edit', $content);
    }

    public function test_the_directory_shows_the_officials_and_the_published_notices(): void
    {
        Announcement::create([
            'title' => 'Clean-up drive on Saturday',
            'body' => 'Barangay volunteers meet at the plaza at seven in the morning.',
            'published_at' => now()->subDay(),
        ]);

        $content = $this->actingAs($this->resident())
            ->get(route('resident.directory', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Barangay Officials', $content);
        $this->assertStringContainsString('Clean-up drive on Saturday', $content);
    }

    public function test_an_unpublished_notice_is_not_shown_to_a_resident(): void
    {
        Announcement::create([
            'title' => 'Draft notice that must not leak',
            'body' => 'Not published yet.',
            'published_at' => null,
        ]);

        $content = $this->actingAs($this->resident())
            ->get(route('resident.directory', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Draft notice that must not leak', $content);
    }

    public function test_the_relief_page_shows_only_the_residents_own_household(): void
    {
        $user = $this->resident();
        $profile = $user->residentProfile;

        $other = User::factory()->resident()->create(['is_verified' => true]);
        $otherProfile = ResidentProfile::factory()->create([
            'user_id' => $other->id,
            'household_number' => 'HH-OTHER',
        ]);

        $mine = ReliefDistribution::factory()->create([
            'household_number' => $profile->household_number,
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);
        ReliefDistribution::factory()->create([
            'household_number' => $otherProfile->household_number,
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);

        $response = $this->actingAs($user)
            ->get(route('resident.relief', absolute: false))
            ->assertOk();

        // The other household's row must not appear. Asserted on the data rather
        // than the markup, so this is a real scoping check.
        $response->assertViewHas('distributions', function ($distributions) use ($mine): bool {
            return $distributions->count() === 1 && $distributions->first()->is($mine);
        });
    }

    public function test_the_relief_page_works_for_a_resident_with_no_profile(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        $this->actingAs($user)
            ->get(route('resident.relief', absolute: false))
            ->assertOk()
            ->assertViewHas('distributions', fn ($distributions): bool => $distributions->isEmpty());
    }

    public function test_the_settings_page_reuses_the_shared_profile_forms(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.settings', absolute: false))
            ->assertOk()
            ->getContent();

        // One set of forms, at a URL inside the portal. A second copy of these
        // forms is how two versions of the same field come to disagree.
        $this->assertStringContainsString('name="name"', $content);
        $this->assertStringContainsString('name="email"', $content);
        $this->assertStringContainsString('name="current_password"', $content);
    }

    public function test_the_settings_page_does_not_offer_editing_the_resident_record(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.settings', absolute: false))
            ->assertOk()
            ->getContent();

        // Household, address, sector and the submitted ID belong to the resident
        // record and are verified by staff - not to the account.
        foreach (['household_number', 'address', 'sector_id'] as $field) {
            $this->assertStringNotContainsString("name=\"{$field}\"", $content);
        }
    }

    public function test_the_profile_page_shows_the_residents_own_record(): void
    {
        $user = User::factory()->resident()->create([
            'is_verified' => true,
            'name' => 'Juan Dela Cruz',
            'nationality' => 'Filipino',
        ]);
        $profile = ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'household_number' => 'HH-1234',
            'address' => 'Purok 3, Barangay Linasin',
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.profile', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Juan Dela Cruz', $content);
        $this->assertStringContainsString('HH-1234', $content);
        $this->assertStringContainsString('Purok 3, Barangay Linasin', $content);
        $this->assertStringContainsString($profile->national_or_voter_id, $content);
    }
}
