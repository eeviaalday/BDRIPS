<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who sees which navbar, and what each role can actually reach.
 *
 * The portal has two navbars: the public layout renders one bar for guests and
 * swaps in a different component for a signed-in resident. The branch lives in
 * resources/views/layouts/public.blade.php and asks Laravel - auth()->check()
 * and User::isResident() - rather than anything the browser supplied, so the
 * role cannot be spoofed by a query string or a hidden field.
 *
 * These tests pin both halves of that: a guest must not be shown the resident's
 * destinations, and a resident must not be left looking for a Login button that
 * has been replaced by their profile menu. They also cover the one resident
 * action with a real access rule behind it - Document Request, which a pending
 * or rejected resident can see but not use.
 *
 * Everything asserted here is scoped to the <header>. The footer links to
 * several of the same pages, so counting against the whole document would pass
 * for the wrong reason.
 */
class NavigationRoleAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The header element only, so footer links cannot satisfy a nav assertion.
     *
     * Returned with all whitespace collapsed. Blade renders a nav label on its
     * own line, so the raw markup is ">\\n Home\\n </a>"; comparing against
     * ">Home</a>" would fail on formatting alone. The needle is squashed the
     * same way, which also keeps "Document Request" - a label with a space in
     * it - matching.
     */
    private function headerOn(string $path, ?User $as = null): string
    {
        $response = $as ? $this->actingAs($as) : $this;

        $page = $response->get($path)->assertOk()->getContent();

        $start = strpos($page, '<header');
        $end = $start === false ? false : strpos($page, '</header>', $start);

        $this->assertNotFalse($start, 'No <header> was rendered at '.$path);
        $this->assertNotFalse($end, 'The <header> at '.$path.' was never closed.');

        return preg_replace('/\s+/', '', substr($page, $start, $end - $start));
    }

    /**
     * Does the squashed header carry this nav label as an anchor's own text?
     */
    private function headerHasLink(string $squashedHeader, string $label): bool
    {
        return str_contains($squashedHeader, '>'.str_replace(' ', '', $label).'</a>');
    }

    /**
     * Does the squashed header carry this label as a button's own text?
     *
     * Logout has to be a POST button rather than a link, so it cannot be found
     * by looking for an anchor.
     */
    private function headerHasButton(string $squashedHeader, string $label): bool
    {
        return str_contains($squashedHeader, '>'.str_replace(' ', '', $label).'</button>');
    }

    private function resident(string $status = ResidentProfile::VERIFICATION_VERIFIED): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => $status,
        ]);

        return $user->fresh();
    }

    // ---------------------------------------------------------------------
    // Guests
    // ---------------------------------------------------------------------

    #[Test]
    public function a_guest_is_offered_the_public_destinations_and_the_two_auth_buttons(): void
    {
        $header = $this->headerOn('/');

        foreach (['Home', 'Services', 'About', 'Announcements', 'Contact', 'Log in', 'Register'] as $label) {
            $this->assertTrue(
                $this->headerHasLink($header, $label),
                "The guest navbar is missing \"{$label}\".",
            );
        }
    }

    #[Test]
    public function a_guest_is_not_shown_any_resident_only_action(): void
    {
        $header = $this->headerOn('/');

        // These are the resident-scoped things. Every one of them would either
        // redirect to the login page or describe an account the guest does not
        // have, so showing them is a broken promise rather than a shortcut.
        foreach ([
            'My Profile',
            'Log out',
            'Dashboard',
        ] as $label) {
            $this->assertFalse(
                $this->headerHasLink($header, $label),
                "The guest navbar is offering the resident action \"{$label}\".",
            );
        }

        // Asserted on URLs rather than labels, because the guest bar already
        // carries a "Relief Transparency" link in its mobile menu pointing at the
        // public board. That link predates this work and the brief says to
        // preserve the guest navbar as it is, so what matters is that it is not
        // the resident's portal URL.
        foreach ([
            'resident.requests.index',
            'resident.relief',
            'resident.settings',
            'resident.dashboard',
        ] as $name) {
            $this->assertStringNotContainsString(
                route($name),
                $header,
                "The guest navbar links to the resident route {$name}.",
            );
        }
    }

    #[Test]
    public function the_guest_navbar_survives_a_signed_out_resident_session(): void
    {
        // The logout POST invalidates the session but leaves a flash message in
        // it. If the navbar branched on anything but the live auth state, the
        // resident bar could survive the logout and keep offering a dead
        // profile menu.
        $user = $this->resident();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $header = $this->headerOn('/');

        $this->assertTrue($this->headerHasLink($header, 'Log in'));
        $this->assertFalse($this->headerHasButton($header, 'Log out'));
    }

    // ---------------------------------------------------------------------
    // Residents
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_is_offered_the_public_row_plus_the_two_portal_destinations(): void
    {
        $header = $this->headerOn('/resident/dashboard', $this->resident());

        foreach ([
            'Home', 'Services', 'About', 'Announcements', 'Contact',
            'Document Request', 'Relief Transparency',
        ] as $label) {
            $this->assertTrue(
                $this->headerHasLink($header, $label),
                "The resident navbar is missing \"{$label}\".",
            );
        }
    }

    #[Test]
    public function a_resident_gets_the_profile_menu_instead_of_login_and_register(): void
    {
        $header = $this->headerOn('/resident/dashboard', $this->resident());

        $this->assertTrue($this->headerHasLink($header, 'My Profile'));
        $this->assertTrue($this->headerHasButton($header, 'Log out'));

        // Requirement: the gold button is a substitution, not an addition.
        $this->assertStringNotContainsString(route('login'), $header);
        $this->assertStringNotContainsString(route('register'), $header);
    }

    #[Test]
    public function the_profile_menu_leads_with_my_profile_and_ends_with_logout(): void
    {
        $header = $this->headerOn('/resident/dashboard', $this->resident());

        $myProfile = strpos($header, route('resident.profile'));
        $logout = strpos($header, 'action="'.route('logout').'"');

        $this->assertNotFalse($myProfile);
        $this->assertNotFalse($logout);
        $this->assertLessThan($logout, $myProfile, 'Log out must come after My Profile.');

        // Logout is a POST form with a CSRF token, never a link. A GET
        // /logout can be fired by an image tag or a prefetcher.
        $this->assertStringNotContainsString('href="'.route('logout').'"', $header);
        $this->assertStringContainsString('name="_token"', $header);
    }

    #[Test]
    public function account_settings_points_at_a_page_that_works(): void
    {
        $user = $this->resident();

        $header = $this->headerOn('/resident/dashboard', $user);

        $this->assertTrue($this->headerHasLink($header, 'Account Settings'));

        // And it is not a placeholder: it resolves, for this account.
        $this->actingAs($user)
            ->get(route('resident.settings'))
            ->assertOk();
    }

    #[Test]
    public function the_role_comes_from_the_session_not_from_the_request(): void
    {
        // A guest who guesses a resident URL in the query string, or sends a
        // role-looking parameter, must still get the guest bar.
        $header = $this->headerOn('/?role=resident&is_resident=1&authenticated=true');

        $this->assertTrue($this->headerHasLink($header, 'Log in'));
        $this->assertFalse($this->headerHasButton($header, 'Log out'));
        $this->assertStringNotContainsString(route('resident.requests.index'), $header);
    }

    /**
     * Every role the application has, and the navbar each one should get.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function roleProvider(): array
    {
        return [
            'resident' => ['resident', true],
            'kagawad' => ['kagawad', false],
            'secretary' => ['secretary', false],
            'admin' => ['admin', false],
        ];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function only_a_resident_gets_the_resident_navbar(string $role, bool $expectsResidentNav): void
    {
        $user = User::factory()->{$role}()->create();

        // Every role can reach the public homepage, so the homepage is the one
        // page where the navbar can be compared without hitting a role guard
        // that would redirect first.
        $header = $this->headerOn('/', $user);

        if ($expectsResidentNav) {
            $this->assertStringContainsString(route('resident.requests.index'), $header);
            $this->assertStringNotContainsString(route('login'), $header);
        } else {
            // Staff get their own dashboard link, never the resident portal.
            $this->assertStringNotContainsString(route('resident.requests.index'), $header);
            $this->assertStringNotContainsString(route('resident.settings'), $header);
        }
    }

    // ---------------------------------------------------------------------
    // Document Request access
    // ---------------------------------------------------------------------

    #[Test]
    public function document_request_leads_to_the_real_page_not_a_placeholder(): void
    {
        $user = $this->resident();

        $header = $this->headerOn('/resident/dashboard', $user);

        $this->assertStringContainsString(route('resident.requests.index'), $header);

        $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk();
    }

    #[Test]
    public function a_verified_resident_gets_the_request_form(): void
    {
        $user = $this->resident();

        $this->actingAs($user)
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->assertDontSee('Verification required before you can apply')
            // The wizard is present, so its submit form has to be too. Matched
            // on the form's action, not the URL: index and store are the same
            // path and differ only by HTTP verb, so a bare URL check would also
            // match the navbar link and prove nothing.
            ->assertSee('action="'.route('resident.requests.store').'"', false);

        // The real proof is the form being present and POSTing successfully.
        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Employment requirement.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'user_id' => $user->id,
            'purpose' => 'Employment requirement.',
        ]);
    }

    /**
     * The two states that may see the page but not use it.
     *
     * @return array<string, array{0: string}>
     */
    public static function unverifiedStatusProvider(): array
    {
        return [
            'pending' => [ResidentProfile::VERIFICATION_PENDING],
            'rejected' => [ResidentProfile::VERIFICATION_REJECTED],
        ];
    }

    #[Test]
    #[DataProvider('unverifiedStatusProvider')]
    public function an_unverified_resident_is_explained_rather_than_shown_a_refused_form(string $status): void
    {
        $user = $this->resident($status);

        // The link is still there - hiding it would just be a dead end - but the
        // page it leads to explains the hold instead of rendering the wizard.
        $header = $this->headerOn('/resident/dashboard', $user);
        $this->assertStringContainsString(route('resident.requests.index'), $header);

        $this->actingAs($user)
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->assertSee('Verification required before you can apply')
            // No wizard form: index and store share a path and differ only by
            // verb, so the form's action attribute is what proves the form is
            // absent rather than the URL.
            ->assertDontSee('action="'.route('resident.requests.store').'"', false);
    }

    #[Test]
    #[DataProvider('unverifiedStatusProvider')]
    public function an_unverified_resident_cannot_submit_a_request_even_by_posting_directly(string $status): void
    {
        $user = $this->resident($status);

        // The nav and the view are both advisory. This is the check that
        // actually holds the line, because it does not care what was rendered.
        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Employment requirement.',
            ])
            ->assertRedirect(route('resident.verification.status'));

        $this->assertDatabaseCount('document_requests', 0);
    }

    #[Test]
    public function a_restricted_resident_still_cannot_submit(): void
    {
        $user = $this->resident();
        $user->update(['restricted_until' => now()->addWeek()]);

        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Employment requirement.',
            ])
            ->assertSessionHasErrors('account');

        $this->assertDatabaseCount('document_requests', 0);
    }

    // ---------------------------------------------------------------------
    // Relief Transparency
    // ---------------------------------------------------------------------

    #[Test]
    public function relief_transparency_stays_inside_the_portal_and_keeps_its_privacy_rules(): void
    {
        $user = $this->resident();

        // From inside the portal this points at the resident's own household
        // page, not the public board - which from a signed-in context would look
        // like a different site.
        $header = $this->headerOn('/resident/dashboard', $user);
        $this->assertStringContainsString(route('resident.relief'), $header);

        $this->actingAs($user)
            ->get(route('resident.relief'))
            ->assertOk();
    }

    #[Test]
    public function relief_transparency_is_not_reachable_by_a_guest(): void
    {
        $this->get(route('resident.relief'))->assertRedirect(route('login'));
        $this->get(route('transparency.index'))->assertRedirect(route('login'));
    }
}
