<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        // The home page, not /dashboard. This factory user is a resident, and a
        // resident's landing is named at the point of sign-in rather than reached
        // by way of the signpost. /dashboard is the staff and Kagawad route; the
        // per-role landings themselves are pinned in RoleDashboardTest, and the
        // resident side of the sign-in is pinned in ResidentLoginRedirectTest.
        $response->assertRedirect(route('home'));
    }

    /**
     * The staff and Kagawad half of the same decision, so that changing where a
     * resident lands cannot quietly move where a Secretary lands. /dashboard
     * remains the signpost for them and forwards to the role's own page.
     */
    public function test_a_staff_account_still_lands_through_the_dashboard_signpost(): void
    {
        $user = User::factory()->secretary()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $response->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();

        // The login screen, not the home page. Somebody who has just signed out
        // is not a visitor, and landing on the home page reads as though the
        // logout did not happen. Sending them to /login also makes the end of
        // the session unmistakable, which is the point of arriving here.
        $response->assertRedirect(route('login'));
    }
}
