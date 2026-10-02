<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Resident ID photos must only ever leave the server through the authorized route.
 *
 * This is the most sensitive file in the application: an uploaded photograph of a
 * government-issued identity document, belonging to someone who did not choose to
 * give it to the barangay. The only correct answer to "who can see this" is
 * "a staff member reviewing a verification, and nobody else, ever".
 *
 * There are two ways it could leak, and they are independent:
 *
 *  1. Through the serving route being too permissive - the route that streams the
 *     bytes, and the role middleware on it.
 *  2. Through the file being reachable by URL, with no route involved at all. This
 *     is the one that is easy to miss, because the local disk has
 *     `'serve' => true`, which registers an unauthenticated-looking
 *     `GET /storage/{path}`.
 *
 * On (2): that route is NOT a public file server. Laravel's ServeFile checks
 * hasValidSignature() first, and for a disk whose visibility is 'private' - which
 * is what `local` defaults to when the key is absent, as it is here - an unsigned
 * request is refused (403 in development, 404 in production) and a traversing path
 * is a 404. So the configuration is safe, and this test is what stops that being
 * an assumption: it asserts the refusal, so a future edit to
 * filesystems.disks.local that adds a visibility, or moves the photos, fails here
 * rather than in production.
 */
class ResidentIdPhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_secretary_and_the_captain_can_read_a_photo_through_the_authorized_route(): void
    {
        [$profile, $bytes] = $this->profileWithPhoto();

        foreach (['secretary', 'admin'] as $role) {
            $response = $this->actingAs($this->staff($role))
                ->get(route('staff.resident-verifications.id-photo', $profile));

            $response->assertOk();
            $this->assertSame($bytes, $response->getContent(), "A {$role} should receive the photo bytes.");

            // The headers are part of the answer, not decoration: inline so a
            // reviewer does not download it on every check, and no-store plus
            // nosniff so an identity document is not cached by a shared machine or
            // reinterpreted by a browser.
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedRoleProvider(): array
    {
        return [
            'kagawad' => ['kagawad'],
            'resident' => ['resident'],
        ];
    }

    #[Test]
    #[DataProvider('refusedRoleProvider')]
    public function no_other_role_can_read_a_photo(string $role): void
    {
        [$profile] = $this->profileWithPhoto();

        $this->actingAs($this->staff($role))
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    #[Test]
    public function a_guest_is_redirected_rather_than_shown_a_login_form_with_a_photo(): void
    {
        [$profile] = $this->profileWithPhoto();

        $this->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function a_photo_is_not_reachable_at_its_storage_url(): void
    {
        [$profile, $bytes] = $this->profileWithPhoto();

        // The real web path, with no session and no signed URL. This is the request
        // that matters: everything else goes through a route, this one does not.
        $this->get('/storage/'.$profile->id_photo_path)->assertForbidden();

        // And the bytes really are on the disk, so the refusal above is a refusal
        // rather than a 404 for a file that was never written.
        $this->assertSame($bytes, Storage::disk('local')->get($profile->id_photo_path));
    }

    #[Test]
    public function the_local_disk_stays_private(): void
    {
        // The single configuration value that refusal depends on. Absent means
        // private, and that is what ServeFile falls back to when deciding whether
        // an unsigned request is allowed.
        $this->assertNotSame(
            'public',
            config('filesystems.disks.local.visibility'),
            'filesystems.disks.local must not be public, or /storage would serve ID photos to anyone.',
        );

        // serve => true is what registers /storage/{path} at all. It is safe only
        // while the disk above is private, so the two are asserted together: this
        // is the pair that must never drift.
        $this->assertTrue(
            (bool) config('filesystems.disks.local.serve'),
            'This test assumes the local disk is served; if serve is turned off, /storage is gone and the assertion above is moot.',
        );
    }

    #[Test]
    public function no_photo_is_written_under_the_public_directory(): void
    {
        [$profile] = $this->profileWithPhoto();

        $this->assertFalse(
            Storage::disk('public')->exists($profile->id_photo_path),
            'An ID photo must never land on the public disk.',
        );

        // Belt and braces: not under public/ either, where the webserver alone
        // would serve it with no Laravel involvement at all.
        $absolute = Storage::disk('local')->path($profile->id_photo_path);
        $resolved = realpath($absolute) ?: $absolute;

        $this->assertStringStartsNotWith(
            realpath(public_path()).DIRECTORY_SEPARATOR,
            $resolved,
            'An ID photo resolved to a path inside public/, which the webserver serves without any application check.',
        );
    }

    #[Test]
    public function a_profile_with_no_photo_is_a_404_not_a_leak(): void
    {
        $profile = ResidentProfile::factory()->create(['id_photo_path' => null]);

        $this->actingAs($this->staff('secretary'))
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertNotFound();
    }

    #[Test]
    public function a_photo_that_was_removed_from_disk_is_a_404_not_an_empty_response(): void
    {
        [$profile] = $this->profileWithPhoto();

        Storage::disk('local')->delete($profile->id_photo_path);

        $this->actingAs($this->staff('secretary'))
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertNotFound();
    }

    /**
     * A profile with a real file behind it, and the bytes of that file.
     *
     * @return array{0: ResidentProfile, 1: string}
     */
    private function profileWithPhoto(): array
    {
        $bytes = "\x89PNG\r\n\x1a\n".'not-a-real-png-but-real-bytes';

        Storage::fake('local');
        Storage::disk('local')->put('id-photos/test-photo.png', $bytes);

        $profile = ResidentProfile::factory()->create([
            'id_photo_path' => 'id-photos/test-photo.png',
        ]);

        return [$profile, $bytes];
    }

    private function staff(string $role): User
    {
        return match ($role) {
            'kagawad' => User::factory()->kagawad()->create(['is_verified' => true]),
            'resident' => User::factory()->resident()->create(['is_verified' => true]),
            'secretary' => User::factory()->secretary()->create(['is_verified' => true]),
            default => User::factory()->admin()->create(['is_verified' => true]),
        };
    }
}
