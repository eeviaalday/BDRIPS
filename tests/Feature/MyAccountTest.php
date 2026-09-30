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
 * My Account, and the authorization around it.
 *
 * One screen serves all four roles, so these tests are mostly about the same
 * three things holding for each: the page is reachable, the role is displayed
 * but not editable, and a guest is sent to the login screen.
 */
class MyAccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'resident' => ['resident'],
            'kagawad' => ['kagawad'],
            'secretary' => ['secretary'],
            'admin' => ['admin'],
        ];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function test_every_role_can_reach_my_account(string $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('My Account');
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function test_the_change_password_destination_resolves_for_every_role(string $role): void
    {
        // It is a separate route because "Change Password" is one of the four
        // things in a resident's profile menu. A menu entry pointing nowhere is
        // the failure this guards.
        $this->actingAs($this->userFor($role))
            ->get(route('account.password'))
            ->assertOk();
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function test_my_account_shows_the_role_but_offers_no_way_to_change_it(string $role): void
    {
        $content = $this->actingAs($this->userFor($role))
            ->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringContainsString(ucfirst($role), $content);

        // An account that could set its own role could grant itself access to
        // anything, so there must be no control for it.
        $this->assertStringNotContainsString('name="role"', $content);
        $this->assertStringNotContainsString('name="is_verified"', $content);
        $this->assertStringNotContainsString('name="restricted_until"', $content);
    }

    #[Test]
    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->get(route('account.password'))->assertRedirect(route('login'));
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function test_a_resident_sees_the_public_layout_and_staff_see_the_sidebar(string $role): void
    {
        $content = $this->actingAs($this->userFor($role))
            ->get(route('account.edit'))->assertOk()->getContent();

        $isResident = $role === 'resident';

        // A resident travels with the public top navbar; staff and a Kagawad
        // arrive from the sidebar and keep it beside the page.
        $this->assertSame(
            $isResident,
            str_contains($content, 'aria-label="Main navigation"'),
            'The wrong layout wrapped the account screen for '.$role.'.',
        );

        $this->assertSame(
            ! $isResident,
            str_contains($content, 'aria-label="Portal"'),
            'The sidebar is missing for '.$role.'.',
        );
    }

    #[Test]
    public function test_a_resident_is_shown_their_verification_status_but_cannot_change_it(): void
    {
        $pending = User::factory()->resident()->create(['is_verified' => true]);
        ResidentProfile::factory()->create([
            'user_id' => $pending->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);

        $content = $this->actingAs($pending)->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('Pending review', $content);
        $this->assertStringNotContainsString('name="verification_status"', $content);
    }

    #[Test]
    public function test_details_can_be_saved(): void
    {
        $user = $this->userFor('resident');

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'name' => 'Ana Updated',
                'email' => $user->email,
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('success');

        $this->assertSame('Ana Updated', $user->fresh()->name);
    }

    #[Test]
    public function test_changing_the_address_clears_the_verified_flag(): void
    {
        // Otherwise somebody could claim an address they do not own and keep a
        // session that the old owner can no longer trace.
        $user = $this->userFor('resident');
        $this->assertTrue($user->hasVerifiedEmail());

        $this->actingAs($user)->patch(route('account.update'), [
            'name' => $user->name,
            'email' => 'somewhere-else@example.test',
        ])->assertRedirect(route('account.edit'));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function test_the_address_must_be_unique_regardless_of_case(): void
    {
        $taken = $this->userFor('resident');
        $other = $this->userFor('secretary');

        $this->actingAs($other)
            ->patch(route('account.update'), [
                'name' => $other->name,
                'email' => strtoupper($taken->email),
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($taken->email, $taken->fresh()->email);
    }

    #[Test]
    public function test_a_guest_cannot_update_details(): void
    {
        $this->patch(route('account.update'), [
            'name' => 'Nobody',
            'email' => 'nobody@example.test',
        ])->assertRedirect(route('login'));
    }

    #[Test]
    public function test_saving_is_recorded_in_the_audit_log(): void
    {
        $user = $this->userFor('secretary');

        $this->actingAs($user)->patch(route('account.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account.updated',
        ]);
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
