<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentIdPhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private function profileWithStoredIdPhoto(): ResidentProfile
    {
        Storage::fake('local');
        Storage::fake('public');

        $sector = Sector::factory()->create();
        $user = User::factory()->resident()->create();

        $path = UploadedFile::fake()->image('id-front.jpg')->store('id-photos', 'local');

        $profile = ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
            'verified_at' => null,
            'id_photo_path' => $path,
        ]);

        return $profile->fresh();
    }

    public function test_guests_cannot_access_a_resident_id_photo(): void
    {
        $profile = $this->profileWithStoredIdPhoto();

        $this->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertRedirect(route('login'));
    }

    public function test_a_resident_cannot_access_another_residents_id_photo(): void
    {
        $profile = $this->profileWithStoredIdPhoto();
        $otherResident = User::factory()->resident()->create();

        $this->actingAs($otherResident)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    public function test_a_resident_cannot_access_their_own_id_photo_through_the_staff_endpoint(): void
    {
        $profile = $this->profileWithStoredIdPhoto();

        $this->actingAs($profile->user)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    public function test_a_kagawad_cannot_access_resident_id_photos(): void
    {
        $profile = $this->profileWithStoredIdPhoto();
        $kagawad = User::factory()->kagawad()->create();

        $this->actingAs($kagawad)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    public function test_a_secretary_can_access_a_resident_id_photo(): void
    {
        $profile = $this->profileWithStoredIdPhoto();
        $secretary = User::factory()->secretary()->create();

        $response = $this->actingAs($secretary)
            ->get(route('staff.resident-verifications.id-photo', $profile));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/jpeg');
        $this->assertSame(
            Storage::disk('local')->get($profile->id_photo_path),
            $response->streamedContent()
        );
    }

    public function test_an_admin_can_access_a_resident_id_photo(): void
    {
        $profile = $this->profileWithStoredIdPhoto();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertOk();
    }

    public function test_a_profile_without_a_stored_photo_returns_not_found(): void
    {
        $profile = ResidentProfile::factory()->create(['id_photo_path' => null]);
        $secretary = User::factory()->secretary()->create();

        $this->actingAs($secretary)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertNotFound();
    }

    public function test_a_profile_pointing_at_a_missing_file_returns_not_found(): void
    {
        $profile = ResidentProfile::factory()->create([
            'id_photo_path' => 'id-photos/does-not-exist.jpg',
        ]);
        $secretary = User::factory()->secretary()->create();

        $this->actingAs($secretary)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertNotFound();
    }

    public function test_an_unknown_profile_returns_not_found(): void
    {
        $secretary = User::factory()->secretary()->create();

        $this->actingAs($secretary)
            ->get('/staff/resident-verifications/999999/id-photo')
            ->assertNotFound();
    }

    public function test_unverified_email_cannot_access_a_resident_id_photo(): void
    {
        $profile = $this->profileWithStoredIdPhoto();
        $secretary = User::factory()->secretary()->unverified()->create();

        $this->actingAs($secretary)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_id_photos_are_stored_on_the_private_disk_and_never_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $sector = Sector::factory()->create();
        $user = User::factory()->resident()->create();

        $this->actingAs($user)
            ->post(route('resident.verification.store'), [
                'household_number' => 'LIN-Z1-HH-0001',
                'national_or_voter_id' => '1234-5678-9012',
                'address' => '123 Purok San Roque, Zone 1, Bangued, Abra',
                'sector_id' => $sector->id,
                'id_photo' => UploadedFile::fake()->image('my-id.jpg')->size(64),
            ])
            ->assertRedirect(route('resident.verification.status'));

        $profile = $user->fresh()->residentProfile;

        $this->assertNotNull($profile->id_photo_path);
        $this->assertStringStartsWith('id-photos/', $profile->id_photo_path);
        $this->assertTrue(Storage::disk('local')->exists($profile->id_photo_path));
        $this->assertFalse(Storage::disk('public')->exists($profile->id_photo_path));
        $this->assertStringNotContainsString(
            $user->name,
            $profile->id_photo_path,
            'The stored ID photo path must not embed the original filename.'
        );
    }
}
