<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who may open which area, and what a frozen account may still do.
 *
 * The restriction rule is the subtle one. A restricted account keeps its role
 * and loses its access, but it must still reach the screen where it changes its
 * own password and the one where it signs out - otherwise a frozen account with
 * a password it forgot has no way back, and the person administering the
 * restriction is the only way out.
 */
class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Wrong role
    // ---------------------------------------------------------------------

    /**
     * The areas that exist in Phase 1, and the roles allowed in them.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function areaProvider(): array
    {
        return [
            'staff dashboard' => ['staff.dashboard', ['admin', 'secretary']],
            'kagawad dashboard' => ['kagawad.dashboard', ['kagawad']],
        ];
    }

    #[Test]
    #[DataProvider('areaProvider')]
    public function test_a_permitted_role_is_admitted(string $route, array $roles): void
    {
        foreach ($roles as $role) {
            $this->actingAs($this->userFor($role))
                ->get(route($route))
                ->assertOk();
        }
    }

    #[Test]
    #[DataProvider('areaProvider')]
    public function test_every_other_role_is_refused_with_a_403(string $route, array $roles): void
    {
        $refused = array_diff(
            [User::ROLE_ADMIN, User::ROLE_SECRETARY, User::ROLE_KAGAWAD, User::ROLE_RESIDENT],
            $roles
        );

        foreach ($refused as $role) {
            $response = $this->actingAs($this->userFor($role))->get(route($route));

            // 403 and not a redirect: a redirect would hide that the page exists
            // and leave a Secretary wondering why their link does nothing.
            $response->assertForbidden();
        }
    }

    #[Test]
    public function test_a_resident_cannot_reach_the_staff_dashboard_by_url(): void
    {
        $this->actingAs($this->userFor('resident'))
            ->get('/staff/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function test_a_kagawad_cannot_reach_the_staff_dashboard_by_url(): void
    {
        $this->actingAs($this->userFor('kagawad'))
            ->get('/staff/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function test_a_secretary_cannot_reach_the_kagawad_dashboard(): void
    {
        $this->actingAs($this->userFor('secretary'))
            ->get(route('kagawad.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function test_a_guest_is_sent_to_login_rather_than_refused(): void
    {
        // Login is one step from where they were heading. A 403 would tell
        // somebody the area exists and tell them nothing about how to enter it.
        $this->get('/staff/dashboard')->assertRedirect(route('login'));
        $this->get('/kagawad/dashboard')->assertRedirect(route('login'));
    }

    #[Test]
    public function test_a_role_cannot_be_asserted_by_the_request(): void
    {
        // The role comes from the session, so guessing it in the query string
        // must do nothing.
        $this->actingAs($this->userFor('resident'))
            ->get('/staff/dashboard?role=admin&is_admin=1&verified=1')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Restricted accounts
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('areaProvider')]
    public function test_a_restricted_account_is_refused_its_own_area(string $route, array $roles): void
    {
        $user = $this->userFor($roles[0]);
        $user->update(['restricted_until' => now()->addWeek()]);

        $this->actingAs($user->fresh())->get(route($route))->assertForbidden();
    }

    #[Test]
    public function test_a_restricted_account_can_still_reach_my_account(): void
    {
        // Otherwise a frozen account whose password it has forgotten is stuck
        // until someone administers the restriction by hand.
        $user = $this->userFor('secretary');
        $user->update(['restricted_until' => now()->addWeek()]);

        $this->actingAs($user->fresh())
            ->get(route('account.edit'))
            ->assertOk();
    }

    #[Test]
    public function test_a_restricted_account_can_still_sign_out(): void
    {
        $user = $this->userFor('secretary');
        $user->update(['restricted_until' => now()->addWeek()]);

        $this->actingAs($user->fresh())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function test_a_restriction_that_has_expired_no_longer_refuses(): void
    {
        $user = $this->userFor('secretary');
        $user->update(['restricted_until' => now()->subMinute()]);

        $this->assertFalse($user->fresh()->isRestricted());

        $this->actingAs($user->fresh())
            ->get(route('staff.dashboard'))
            ->assertOk();
    }

    #[Test]
    public function test_an_account_with_no_restriction_is_never_refused(): void
    {
        $user = $this->userFor('secretary');

        $this->assertFalse($user->isRestricted());

        $this->actingAs($user)->get(route('staff.dashboard'))->assertOk();
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === 'resident') {
            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => Sector::factory()->create()->id,
                'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            ]);
        }

        if ($role === 'kagawad') {
            Sector::factory()->create(['kagawad_user_id' => $user->id]);
        }

        return $user;
    }
}
