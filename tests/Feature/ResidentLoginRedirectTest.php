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
 * Where a signed-in Resident lands.
 *
 * The requirement is that a Resident arrives on the public home page. The
 * redirect used to name /dashboard for every role and let that route forward
 * them, so the destination was correct but was reached by way of a signpost
 * whose whole job is to forward somebody else. These tests pin the destination
 * to the resident's actual landing rather than to the intermediate hop, because
 * a test that only asserts "/dashboard" passes just as happily when a resident
 * is being sent somewhere that route happens to forward to today.
 *
 * The staff and Kagawad destinations are pinned here too, in the same file, so
 * that changing the resident's landing cannot quietly move theirs.
 */
class ResidentLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every shape of resident account, because the landing must not depend on a
     * profile state the login flow has no business reading.
     *
     * 'verified' here means a confirmed email address AND a verified profile: both
     * are required for the resident to be sent straight to the home page, so an
     * account missing either one is a case of its own.
     */
    #[Test]
    #[DataProvider('residentFactoryProvider')]
    public function a_resident_lands_on_the_public_home_page(string $shape): void
    {
        $resident = $this->resident($shape);

        $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ])->assertRedirect($this->expectedLanding($shape));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function residentFactoryProvider(): array
    {
        return [
            'verified profile' => ['verified', 'home'],
            'pending profile' => ['pending', 'home'],
            'rejected profile' => ['rejected', 'home'],
            'no profile at all' => ['none', 'home'],
            // Still a resident, but the address is unconfirmed, so the signpost
            // holds them at the verification prompt.
            'unconfirmed address' => ['unconfirmed', 'dashboard'],
        ];
    }

    private function expectedLanding(string $shape): string
    {
        return $shape === 'unconfirmed' ? route('dashboard') : route('home');
    }

    /**
     * The negative half of the requirement, stated as its own test: a Resident is
     * not sent to My Profile. /account is where a resident edits their own name
     * and password, and it is not a landing page.
     */
    #[Test]
    public function a_resident_is_not_landed_on_their_profile_page(): void
    {
        $resident = $this->resident();

        $response = $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ]);

        $this->assertNotSame(route('account.edit'), $response->headers->get('Location'));
    }

    /**
     * A resident must not be routed through the signpost either. /dashboard
     * forwards a resident to the home page, so landing there would arrive at the
     * right address, but it means the resident's destination is decided in two
     * places and can disagree with itself.
     */
    #[Test]
    public function a_resident_is_not_routed_through_the_dashboard_signpost(): void
    {
        $resident = $this->resident();

        $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ])->assertRedirect(route('home'));
    }

    /**
     * The home page itself has to render for a signed-in resident, or the
     * redirect is a redirect to an error.
     */
    #[Test]
    public function the_home_page_is_reachable_after_a_resident_signs_in(): void
    {
        $resident = $this->resident();

        $response = $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ]);

        $this->get($response->headers->get('Location'))->assertOk();
    }

    /**
     * The other three roles keep the signpost, and so keep their own dashboards.
     */
    #[Test]
    #[DataProvider('staffLandingProvider')]
    public function the_staff_roles_keep_their_own_destination(string $state): void
    {
        $user = User::factory()->{$state}()->create(['is_verified' => true]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $expected = $state === 'kagawad' ? 'kagawad.dashboard' : 'staff.dashboard';

        $this->get($response->headers->get('Location'))
            ->assertRedirect(route($expected));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function staffLandingProvider(): array
    {
        return [
            'secretary' => ['secretary'],
            'admin' => ['admin'],
            'kagawad' => ['kagawad'],
        ];
    }

    // ---------------------------------------------------------------------
    // The intended redirect, which must survive
    // ---------------------------------------------------------------------

    /**
     * A guest who was refused a protected page is returned to it after signing
     * in. This is the one case where a resident does NOT go to the home page,
     * and it is the reason the resident landing cannot simply be hard-coded over
     * the top of intended().
     */
    #[Test]
    public function a_guest_refused_the_profile_page_still_reaches_it_after_signing_in(): void
    {
        $resident = $this->resident();

        // Being refused stores the page they were trying to reach.
        $this->get(route('account.edit'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ])->assertRedirect(route('account.edit'));
    }

    /**
     * The same for a resident-area page, so the behaviour is not specific to
     * /account by accident.
     */
    #[Test]
    public function a_guest_refused_the_document_requests_page_still_reaches_it(): void
    {
        $resident = $this->resident();

        $this->get(route('resident.requests.index'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'password',
        ])->assertRedirect(route('resident.requests.index'));
    }

    /**
     * An unverified account is held at the verification prompt rather than sent
     * to any landing. Registration and e-mail verification behaviour is
     * deliberately untouched by the resident landing change, and this is what
     * "untouched" has to mean in practice.
     *
     * The factory's unverified() state is what this uses, because the "verified"
     * middleware reads email_verified_at and not the is_verified column. A test
     * that only cleared is_verified would pass against a change that had quietly
     * stopped honouring the prompt.
     */
    #[Test]
    public function an_unverified_account_is_still_held_at_the_verification_prompt(): void
    {
        $user = User::factory()->resident()->unverified()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    /**
     * Authentication itself is unchanged: bad credentials do not sign anybody in
     * and do not redirect anywhere.
     */
    #[Test]
    public function a_wrong_password_still_signs_nobody_in(): void
    {
        $resident = $this->resident();

        $this->post(route('login'), [
            'email' => $resident->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function resident(string $shape = 'verified'): User
    {
        $factory = User::factory();

        if ($shape === 'unconfirmed') {
            $factory = $factory->unverified();
        }

        $user = $factory->create(['role' => 'resident', 'is_verified' => true]);

        if ($shape === 'none') {
            return $user;
        }

        $status = match ($shape) {
            'pending' => ResidentProfile::VERIFICATION_PENDING,
            'rejected' => ResidentProfile::VERIFICATION_REJECTED,
            default => ResidentProfile::VERIFICATION_VERIFIED,
        };

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => $status,
        ]);

        return $user;
    }
}
