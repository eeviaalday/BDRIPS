<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A resident's verification state and the details behind it.
 *
 * The rule that matters: saving never verifies anybody. A resident who could
 * reach "verified" from this page would be verifying themselves, so any change
 * sends the profile back to pending for a human to look at again - including a
 * change made by somebody who was already verified.
 */
class ResidentVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_pending_resident_sees_their_state_and_why(): void
    {
        $content = $this->actingAs($this->pendingResident())
            ->get(route('resident.verification'))->assertOk()->getContent();

        $this->assertStringContainsString('Pending', $content);
        $this->assertStringContainsString('master list', $content);
    }

    #[Test]
    public function a_verified_resident_sees_that_they_are_verified(): void
    {
        $content = $this->actingAs($this->verifiedResident())
            ->get(route('resident.verification'))->assertOk()->getContent();

        $this->assertStringContainsString('Verified', $content);
    }

    #[Test]
    public function a_rejected_resident_is_shown_the_reason(): void
    {
        $resident = $this->pendingResident();
        $resident->residentProfile->update([
            'verification_status' => ResidentProfile::VERIFICATION_REJECTED,
            'rejection_reason' => 'The household number does not match the master list.',
        ]);

        $content = $this->actingAs($resident->fresh())
            ->get(route('resident.verification'))->assertOk()->getContent();

        $this->assertStringContainsString('The household number does not match', $content);
    }

    #[Test]
    public function a_resident_can_correct_their_details(): void
    {
        $resident = $this->pendingResident();

        $this->actingAs($resident)
            ->patch(route('resident.verification.update'), [
                'household_number' => 'HH-2002',
                'address' => '12 Kapitulacion St.',
                'sector_id' => $resident->residentProfile->sector_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('resident.verification'));

        $profile = $resident->residentProfile->fresh();

        $this->assertSame('HH-2002', $profile->household_number);
        $this->assertSame('12 Kapitulacion St.', $profile->address);
    }

    /**
     * The rule this page exists to hold. Saving is not deciding.
     */
    #[Test]
    public function saving_details_never_verifies_anybody(): void
    {
        $resident = $this->verifiedResident();

        $this->actingAs($resident)
            ->patch(route('resident.verification.update'), [
                'household_number' => $resident->residentProfile->household_number,
                'address' => 'A completely new address.',
                'sector_id' => $resident->residentProfile->sector_id,
            ])
            ->assertRedirect();

        $profile = $resident->residentProfile->fresh();

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->verification_status);
        $this->assertNull($profile->verified_at);
        $this->assertFalse($resident->fresh()->is_verified);
    }

    /**
     * A rejected profile is cleared back to pending, because the reason it was
     * rejected is the thing the resident has just corrected.
     */
    #[Test]
    public function resubmitting_clears_the_rejection(): void
    {
        $resident = $this->pendingResident();
        $resident->residentProfile->update([
            'verification_status' => ResidentProfile::VERIFICATION_REJECTED,
            'rejection_reason' => 'Illegible.',
        ]);

        $this->actingAs($resident->fresh())
            ->patch(route('resident.verification.update'), [
                'household_number' => $resident->residentProfile->household_number,
                'address' => 'Corrected address.',
                'sector_id' => $resident->residentProfile->sector_id,
            ])
            ->assertRedirect();

        $profile = $resident->residentProfile->fresh();

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->verification_status);
        $this->assertNull($profile->rejection_reason);
    }

    #[Test]
    public function an_id_upload_replaces_the_one_on_file(): void
    {
        Storage::fake('local');

        $resident = $this->pendingResident();
        $before = $resident->residentProfile->id_photo_path;

        $this->actingAs($resident)
            ->patch(route('resident.verification.update'), [
                'household_number' => $resident->residentProfile->household_number,
                'address' => $resident->residentProfile->address,
                'sector_id' => $resident->residentProfile->sector_id,
                'id_photo' => UploadedFile::fake()->image('id.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $after = $resident->residentProfile->fresh()->id_photo_path;

        $this->assertNotSame($before, $after);
        Storage::disk('local')->assertExists($after);
    }

    /**
     * An ID is a personal document, so it goes to the private disk. A file
     * readable from storage/app/public would be readable by anyone who guessed
     * the path.
     */
    #[Test]
    public function an_uploaded_id_never_lands_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $resident = $this->pendingResident();

        $this->actingAs($resident)->patch(route('resident.verification.update'), [
            'household_number' => $resident->residentProfile->household_number,
            'address' => $resident->residentProfile->address,
            'sector_id' => $resident->residentProfile->sector_id,
            'id_photo' => UploadedFile::fake()->image('id.jpg'),
        ])->assertSessionHasNoErrors();

        $path = $resident->residentProfile->fresh()->id_photo_path;

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function a_household_number_already_taken_by_another_resident_is_refused(): void
    {
        $mine = $this->pendingResident('HH-1001');
        $theirs = $this->pendingResident('HH-2002');

        $this->actingAs($mine)
            ->patch(route('resident.verification.update'), [
                'household_number' => 'HH-2002',
                'address' => 'Mine.',
                'sector_id' => $mine->residentProfile->sector_id,
            ])
            ->assertSessionHasErrors('household_number');

        $this->assertSame('HH-1001', $mine->residentProfile->fresh()->household_number);
    }

    /**
     * Resaving unchanged details must not collide with the resident's own row,
     * or correcting one field would be impossible.
     */
    #[Test]
    public function resaving_the_same_household_number_is_allowed(): void
    {
        $resident = $this->pendingResident('HH-1001');

        $this->actingAs($resident)
            ->patch(route('resident.verification.update'), [
                'household_number' => 'HH-1001',
                'address' => 'A different address, same household.',
                'sector_id' => $resident->residentProfile->sector_id,
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_guest_cannot_read_or_change_verification(): void
    {
        $this->get(route('resident.verification'))->assertRedirect(route('login'));
        $this->patch(route('resident.verification.update'), [])->assertRedirect(route('login'));
    }

    #[Test]
    public function a_resident_with_no_profile_is_not_shown_a_broken_form(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        $this->actingAs($user)
            ->get(route('resident.verification'))
            ->assertOk()
            ->assertSee('No resident record yet');
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'household_number' => 'HH-1001',
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    /**
     * A pending resident, with the household number given.
     *
     * The number is a parameter rather than fixed because the uniqueness test
     * needs two residents with different numbers - and a helper that ignored its
     * argument would hand both of them the same one and quietly not test
     * anything.
     */
    private function pendingResident(string $household = 'HH-3003'): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => Sector::factory()->create()->id,
        ]);

        return $user;
    }
}
