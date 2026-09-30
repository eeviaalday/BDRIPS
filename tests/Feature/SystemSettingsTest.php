<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The system settings page.
 *
 * The page posts from two separate forms - one for the letterhead, one for the
 * fee rates - to the same action. They do not carry each other's fields, so
 * barangay_name being a blanket 'required' made the fee form impossible to
 * submit: it was rejected for a field it does not contain. These tests pin
 * both halves of that, so the split cannot quietly go back to being broken.
 *
 * The page is also not a user-management screen, and the test that says so is
 * the one that will fail loudly if someone later wires account editing into it
 * without telling anyone.
 */
class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_fee_rates_can_be_saved_without_resending_the_letterhead(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin');
        Setting::set('barangay_address', 'Zone 1, Bangued, Abra');

        $this->actingAs($this->admin())
            ->from(route('staff.settings.index'))
            ->post(route('staff.settings.update'), [
                'clearance_fee' => 50,
                'indigency_fee' => 0,
                'jobseeker_fee' => 20,
                'residency_fee' => 30,
            ])
            ->assertRedirect(route('staff.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('50', (string) Setting::get('clearance_fee'));
        $this->assertSame('20', (string) Setting::get('jobseeker_fee'));

        // The letterhead the fee form never sent must survive.
        $this->assertSame('Barangay Linasin', Setting::get('barangay_name'));
        $this->assertSame('Zone 1, Bangued, Abra', Setting::get('barangay_address'));
    }

    public function test_the_letterhead_can_be_saved_without_resending_the_fees(): void
    {
        Setting::set('clearance_fee', 75);

        $this->actingAs($this->admin())
            ->from(route('staff.settings.index'))
            ->post(route('staff.settings.update'), [
                'barangay_name' => 'Barangay Linasin',
                'barangay_address' => 'Zone 1, Bangued, Abra',
                'punong_barangay' => 'Juan Dela Cruz',
                'secretary_name' => 'Maria Santos',
                'emergency_hotline' => '0917 000 0000',
            ])
            ->assertRedirect(route('staff.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Juan Dela Cruz', Setting::get('punong_barangay'));
        $this->assertSame('75', (string) Setting::get('clearance_fee'));
    }

    public function test_a_submitted_letterhead_still_must_not_be_blank(): void
    {
        $this->actingAs($this->admin())
            ->from(route('staff.settings.index'))
            ->post(route('staff.settings.update'), [
                'barangay_name' => '',
                'barangay_address' => 'Zone 1, Bangued, Abra',
            ])
            ->assertSessionHasErrors('barangay_name');
    }

    public function test_a_negative_fee_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('staff.settings.index'))
            ->post(route('staff.settings.update'), ['clearance_fee' => -1])
            ->assertSessionHasErrors('clearance_fee');
    }

    public function test_the_page_does_not_claim_the_fee_rates_are_applied_automatically(): void
    {
        $content = $this->actingAs($this->admin())
            ->get(route('staff.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('not yet applied automatically', $content);
        $this->assertStringContainsString('no audit trail', $content);
    }

    public function test_assigning_a_kagawad_anchors_the_sector_and_sets_the_role(): void
    {
        $sector = Sector::create(['sector_name' => 'Purok 3']);
        $kagawad = User::factory()->resident()->create();

        $this->actingAs($this->admin())
            ->from(route('staff.settings.index'))
            ->post(route('staff.settings.assign-kagawad'), [
                'kagawad_user_id' => $kagawad->id,
                'sector_id' => $sector->id,
            ])
            ->assertRedirect(route('staff.settings.index'))
            ->assertSessionHas('success');

        $this->assertSame(User::ROLE_KAGAWAD, $kagawad->refresh()->role);
        $this->assertSame($kagawad->id, $sector->refresh()->kagawad_user_id);
    }

    public function test_assigning_warns_that_it_changes_a_role(): void
    {
        $content = $this->actingAs($this->admin())
            ->get(route('staff.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('role to kagawad', $content);
        $this->assertStringContainsString('lose access to the staff area', $content);
    }

    public function test_a_resident_cannot_reach_the_settings_page_or_post_to_it(): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->get(route('staff.settings.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->kagawad()->create())
            ->post(route('staff.settings.update'), ['barangay_name' => 'Hijacked'])
            ->assertForbidden();
    }
}
