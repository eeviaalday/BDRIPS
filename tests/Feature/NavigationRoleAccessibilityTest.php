<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who sees what in the navbar, and what each role can actually reach.
 *
 * The portal has one navbar. The public layout used to swap in a separate
 * resident component for a signed-in resident; that branch went with the
 * resident area, so every visitor now gets the one finished public bar, and the
 * only thing that changes with the session is the auth slot in it - "Log in /
 * Register" for a guest, "My Dashboard" for anybody signed in.
 *
 * The branch asks Laravel - auth state and the role on the session - rather
 * than anything the browser supplied, so a role cannot be spoofed by a query
 * string or a hidden field. These tests pin that: a guest is offered the way
 * in and is never shown an authenticated action, every role gets the same bar
 * on the pages it can reach, and signing out really does return the guest bar.
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
     * own line, so the raw markup is ">\n Home\n </a>"; comparing against
     * ">Home</a>" would fail on formatting alone. The needle is squashed the
     * same way, which also keeps a label with a space in it matching.
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
    public function a_guest_is_not_shown_any_authenticated_action(): void
    {
        $header = $this->headerOn('/');

        // Each of these would either redirect the guest to the login page or
        // describe an account they do not have, so showing them is a broken
        // promise rather than a shortcut.
        foreach (['My Profile', 'Log out', 'Dashboard'] as $label) {
            $this->assertFalse(
                $this->headerHasLink($header, $label),
                "The guest navbar is offering the authenticated action \"{$label}\".",
            );
        }
    }

    #[Test]
    public function the_guest_navbar_survives_a_signed_out_session(): void
    {
        // The logout POST invalidates the session but leaves a flash message in
        // it. If the navbar branched on anything but the live auth state, the
        // signed-in slot could survive the logout and keep offering a dead
        // dashboard button to someone who is now a guest.
        $user = $this->resident();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $header = $this->headerOn('/');

        $this->assertTrue($this->headerHasLink($header, 'Log in'));
        $this->assertTrue($this->headerHasLink($header, 'Register'));
        $this->assertFalse($this->headerHasLink($header, 'My Dashboard'));
        $this->assertFalse($this->headerHasButton($header, 'Log out'));
    }

    #[Test]
    public function the_role_comes_from_the_session_not_from_the_request(): void
    {
        // A guest who guesses a resident URL in the query string, or sends a
        // role-looking parameter, must still get the guest bar.
        $header = $this->headerOn('/?role=resident&is_resident=1&authenticated=true');

        $this->assertTrue($this->headerHasLink($header, 'Log in'));
        $this->assertFalse($this->headerHasLink($header, 'My Dashboard'));
        $this->assertFalse($this->headerHasButton($header, 'Log out'));
    }

    // ---------------------------------------------------------------------
    // Signed in
    // ---------------------------------------------------------------------

    #[Test]
    public function a_signed_in_staff_member_gets_the_dashboard_button_instead_of_login_and_register(): void
    {
        // A staff member reading a public notice sees the public bar with the
        // auth slot filled by "My Dashboard". A resident does not see this bar at
        // all - they get the resident navbar instead, which is covered by
        // RoleNavigationTest.
        $header = $this->headerOn('/', User::factory()->secretary()->create());

        $this->assertTrue($this->headerHasLink($header, 'My Dashboard'));

        // Requirement: the gold button is a substitution, not an addition. A
        // signed-in user must not still be invited to register.
        $this->assertStringNotContainsString(route('login'), $header);
        $this->assertStringNotContainsString(route('register'), $header);
    }

    /**
     * The staff roles, which all share the public bar.
     *
     * A resident is deliberately absent: they get the resident navbar, which is
     * a superset of this row rather than the same bar. That difference is
     * asserted in RoleNavigationTest.
     *
     * @return array<string, array{0: string}>
     */
    public static function staffRoleProvider(): array
    {
        return [
            'kagawad' => ['kagawad'],
            'secretary' => ['secretary'],
            'admin' => ['admin'],
        ];
    }

    #[Test]
    #[DataProvider('staffRoleProvider')]
    public function every_staff_role_is_offered_the_same_public_row(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        // Every role can reach the public homepage, so the homepage is the one
        // page where the navbar can be compared without hitting a role guard
        // that would redirect first.
        $header = $this->headerOn('/', $user);

        foreach (['Home', 'Services', 'About', 'Announcements', 'Contact'] as $label) {
            $this->assertTrue(
                $this->headerHasLink($header, $label),
                "The navbar for a {$role} is missing \"{$label}\".",
            );
        }

        $this->assertTrue($this->headerHasLink($header, 'My Dashboard'));
    }

    #[Test]
    #[DataProvider('staffRoleProvider')]
    public function no_staff_role_is_offered_a_way_to_sign_out_from_the_public_header(string $role): void
    {
        // On a public page, signing out lives in the staff top bar, not the
        // public navbar. A resident's navbar does carry a sign-out, in its
        // profile menu - that is the difference between the two bars.
        $header = $this->headerOn('/', User::factory()->{$role}()->create());

        $this->assertFalse($this->headerHasButton($header, 'Log out'));
        $this->assertStringNotContainsString('action="'.route('logout').'"', $header);
    }

    // ---------------------------------------------------------------------
    // Destinations that are being rebuilt
    // ---------------------------------------------------------------------

    #[Test]
    public function the_stubbed_destinations_are_reachable_from_the_navbar_and_resolve(): void
    {
        // The navbar and footer still link to Relief Transparency and Track a
        // Request, and the homepage's quick-services grid still links to the
        // resident request form. Those route names survive as stubs, so every
        // one of them must resolve rather than throwing.
        $header = $this->headerOn('/');

        foreach (['transparency.index', 'requests.track'] as $name) {
            $this->assertStringContainsString(
                route($name),
                $header,
                "The navbar no longer links to {$name}, which the finished pages still use.",
            );
        }

        foreach (['dashboard', 'resident.requests.create', 'resident.verification.status', 'transparency.index', 'transparency.search', 'requests.track'] as $name) {
            $this->assertNotNull(
                route($name, absolute: false),
                "The stub route {$name} no longer resolves.",
            );
        }
    }
}
