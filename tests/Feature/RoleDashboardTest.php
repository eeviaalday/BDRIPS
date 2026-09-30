<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Where each role lands after signing in.
 *
 * There is one route, /dashboard, answering per role. These tests pin the answer
 * for every role, because the failure mode is quiet: a role pointed at a page it
 * cannot reach is a 500 on the first sign-in, and a role pointed at the wrong
 * page is a redirect that works and shows the wrong thing.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The role, and the route it is sent to.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function landingProvider(): array
    {
        return [
            // A resident's home is the finished public home page, not a portal.
            'resident' => ['resident', 'home'],
            'secretary' => ['secretary', 'staff.dashboard'],
            'admin' => ['admin', 'staff.dashboard'],
            'kagawad' => ['kagawad', 'kagawad.dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('landingProvider')]
    public function test_a_role_is_landed_on_its_own_destination(string $role, string $expected): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('dashboard'))
            ->assertRedirect(route($expected));
    }

    #[Test]
    #[DataProvider('landingProvider')]
    public function test_the_landing_route_is_the_same_rule_the_middleware_uses(string $role, string $expected): void
    {
        // The route must not carry its own idea of where a role goes. If it did,
        // the navbar link and the sign-in redirect could disagree.
        $this->assertSame(
            route($expected),
            route(EnsureUserHasRole::landingFor($this->userFor($role))),
            'The /dashboard route and the middleware disagree about where a role lands.',
        );
    }

    #[Test]
    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function test_a_guest_is_sent_to_login_from_the_path_as_well_as_the_name(): void
    {
        // Named routes can be renamed; the URL is what a bookmark or a typed
        // address uses, and it must behave the same.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    #[Test]
    public function test_an_unverified_account_cannot_reach_the_dashboard(): void
    {
        // "verified" is on this route, so an account that has signed up but not
        // confirmed its address is sent to the prompt rather than to a landing.
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function test_a_resident_lands_on_the_public_home_page_and_sees_their_navbar(): void
    {
        $response = $this->actingAs($this->userFor('resident'))->get(route('dashboard'));

        $response->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('My Profile', false)
            ->assertDontSee('My Dashboard', false);
    }

    #[Test]
    public function test_a_secretary_can_reach_the_staff_dashboard_it_lands_on(): void
    {
        // A redirect to a route that 403s, or that does not exist, would be a
        // broken sign-in rather than a wrong one.
        $this->actingAs($this->userFor('secretary'))
            ->get(route('staff.dashboard'))
            ->assertOk();
    }

    #[Test]
    public function test_an_admin_can_reach_the_staff_dashboard_it_lands_on(): void
    {
        $this->actingAs($this->userFor('admin'))
            ->get(route('staff.dashboard'))
            ->assertOk();
    }

    #[Test]
    public function test_a_kagawad_can_reach_the_dashboard_it_lands_on(): void
    {
        $this->actingAs($this->userFor('kagawad'))
            ->get(route('kagawad.dashboard'))
            ->assertOk();
    }

    /**
     * A signed-in account with no resident profile, which is what a hand-made
     * staff account looks like before anyone attaches anything to it. It must
     * still be sent somewhere rather than erroring.
     */
    #[Test]
    public function test_an_account_without_a_resident_profile_still_lands_somewhere(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'resident']))
            ->get(route('dashboard'))
            ->assertRedirect(route('home'));
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === 'resident') {
            $sector = Sector::factory()->create();

            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => $sector->id,
                'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            ]);
        }

        if ($role === 'kagawad') {
            Sector::factory()->create(['kagawad_user_id' => $user->id]);
        }

        return $user;
    }
}
