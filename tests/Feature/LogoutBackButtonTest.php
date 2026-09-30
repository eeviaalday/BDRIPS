<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
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
 *   1. the session is really gone, and a GET to a protected page of each role
 *      now redirects to login;
 *   2. the responses carry no-store, so the browser is told not to keep them;
 *   3. the authenticated layouts carry the pageshow reload, because a page
 *      restored from the back-forward cache can still be persisted even when
 *      the headers said not to store it.
 */
class LogoutBackButtonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One protected page per role, so all three groups are covered.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedPages(): array
    {
        return [
            'resident' => ['resident', 'resident.dashboard'],
            'kagawad' => ['kagawad', 'kagawad.dashboard'],
            'secretary' => ['secretary', 'staff.dashboard'],
            'admin' => ['admin', 'staff.dashboard'],
        ];
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === 'resident') {
            Sector::factory()->create();
            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            ]);
        }

        return $user->fresh();
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function roleAndRoute(): \Generator
    {
        foreach (self::protectedPages() as $label => $pair) {
            yield $label => [$pair[0], $pair[1]];
        }
    }

    #[DataProvider('roleAndRoute')]
    public function test_a_signed_in_role_can_still_reach_its_own_page(string $role, string $routeName): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route($routeName))
            ->assertOk();
    }

    #[DataProvider('roleAndRoute')]
    public function test_after_logout_the_page_redirects_to_login(string $role, string $routeName): void
    {
        $user = $this->userFor($role);

        $this->actingAs($user)->get(route($routeName))->assertOk();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('roleAndRoute')]
    public function test_the_protected_pages_carry_no_store_headers(string $role, string $routeName): void
    {
        $response = $this->actingAs($this->userFor($role))->get(route($routeName))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=0', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    #[DataProvider('roleAndRoute')]
    public function test_the_authenticated_layouts_reload_on_a_persisted_pageshow(string $role, string $routeName): void
    {
        $content = $this->actingAs($this->userFor($role))
            ->get(route($routeName))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("window.addEventListener('pageshow'", $content);
        $this->assertStringContainsString('event.persisted', $content);
        $this->assertStringContainsString('window.location.reload()', $content);
    }

    public function test_a_public_page_does_not_reload_itself(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The script is wrapped in @auth so a guest pays nothing for it, and
        // a public page must not carry it.
        $this->assertStringNotContainsString('window.location.reload()', $content);
    }

    public function test_the_logout_response_itself_is_not_stored(): void
    {
        $response = $this->actingAs($this->userFor('resident'))->post(route('logout'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_logout_invalidates_the_session_rather_than_only_clearing_the_user(): void
    {
        $user = $this->userFor('resident');

        $this->actingAs($user);
        $before = session()->getId();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($before, session()->getId(), 'The session id was reused after logout.');
    }

    public function test_the_login_page_is_reachable_after_logout_and_stays_on_the_login_page(): void
    {
        $user = $this->userFor('resident');

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        // A logged-out visitor asking for the login screen gets the login
        // screen. It must not bounce them into a protected area, which is what
        // the guest middleware would do if the session had survived.
        $this->get(route('login'))->assertOk()->assertSee('Email', false);
    }

    public function test_a_guarded_page_redirects_to_login_rather_than_erroring(): void
    {
        $this->get(route('resident.dashboard'))->assertRedirect(route('login'));
        $this->get(route('kagawad.dashboard'))->assertRedirect(route('login'));
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }
}
