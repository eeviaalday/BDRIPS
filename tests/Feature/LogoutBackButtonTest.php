<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Back button after logout.
 *
 * Logging out is only half of it. The other half is that a browser which has
 * the page in its own history must not be able to redisplay it afterwards, and
 * on a shared machine that page holds a name, a household number and document
 * references.
 *
 * Three things are asserted, because any one of them alone would be satisfied
 * by a page that still leaks:
 *
 *   1. the session is really gone, and a GET to a protected page now redirects
 *      to login;
 *   2. the responses carry no-store, so the browser is told not to keep them;
 *   3. the authenticated layout carries the pageshow reload, because a page
 *      restored from the back-forward cache can still be persisted even when
 *      the headers said not to store it.
 *
 * The protected pages used to be one per role, because each role had its own
 * area. Those areas are being rebuilt, so the role dimension is gone with them
 * and the sweep is over the pages that are still behind a sign-in.
 */
class LogoutBackButtonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The pages that still sit behind a sign-in.
     *
     * @return array<string, array{0: string}>
     */
    public static function protectedPages(): array
    {
        return [
            'email verification prompt' => ['verification.notice'],
            'password confirmation' => ['password.confirm'],
        ];
    }

    private function userFor(string $role = 'resident'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_verified' => true,
            // Unverified, so the verification prompt renders its page rather
            // than bouncing a verified account straight on.
            'email_verified_at' => null,
        ])->fresh();
    }

    #[DataProvider('protectedPages')]
    public function test_a_signed_in_user_can_still_reach_a_protected_page(string $routeName): void
    {
        $this->actingAs($this->userFor())
            ->get(route($routeName))
            ->assertOk();
    }

    #[DataProvider('protectedPages')]
    public function test_after_logout_the_page_redirects_to_login(string $routeName): void
    {
        $user = $this->userFor();

        $this->actingAs($user)->get(route($routeName))->assertOk();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('protectedPages')]
    public function test_the_protected_pages_carry_no_store_headers(string $routeName): void
    {
        $response = $this->actingAs($this->userFor())->get(route($routeName))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=0', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function test_the_authenticated_layout_reloads_on_a_persisted_pageshow(): void
    {
        // A signed-in user browsing the public pages is the case that remains:
        // the pageshow guard lives in the shared public layout, so it is what
        // stops a restored public page from being redisplayed after logout.
        $content = $this->actingAs($this->userFor())
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("window.addEventListener('pageshow'", $content);
        $this->assertStringContainsString('event.persisted', $content);
        $this->assertStringContainsString('window.location.reload()', $content);
    }

    public function test_a_public_page_does_not_reload_itself_for_a_guest(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The script is wrapped in @auth so a guest pays nothing for it, and
        // a public page must not carry it.
        $this->assertStringNotContainsString('window.location.reload()', $content);
    }

    public function test_the_logout_response_itself_is_not_stored(): void
    {
        $response = $this->actingAs($this->userFor())->post(route('logout'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_logout_invalidates_the_session_rather_than_only_clearing_the_user(): void
    {
        $user = $this->userFor();

        $this->actingAs($user);
        $before = session()->getId();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($before, session()->getId(), 'The session id was reused after logout.');
    }

    public function test_the_login_page_is_reachable_after_logout_and_stays_on_the_login_page(): void
    {
        $user = $this->userFor();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        // A logged-out visitor asking for the login screen gets the login
        // screen. It must not bounce them into a protected area, which is what
        // the guest middleware would do if the session had survived.
        $this->get(route('login'))->assertOk()->assertSee('Email', false);
    }

    public function test_a_guarded_page_redirects_to_login_rather_than_erroring(): void
    {
        foreach (array_column(self::protectedPages(), 0) as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
    }

    public function test_every_stub_for_a_page_being_rebuilt_sends_a_guest_to_login(): void
    {
        // The stubs have no middleware, so the branch that sends a guest to the
        // login screen is theirs to make. If one loses it, a guest gets a
        // redirect nobody wrote rather than the login screen.
        $stubs = [
            'dashboard',
            'resident.requests.create',
            'resident.verification.status',
            'transparency.index',
            'transparency.search',
            'requests.track',
        ];

        foreach ($stubs as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login'), $routeName.' no longer sends a guest to login.');
        }
    }
}
