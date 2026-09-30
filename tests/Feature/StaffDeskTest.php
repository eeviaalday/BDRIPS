<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shared staff area: resident verification, the residents directory and the
 * document review desk.
 *
 * Secretary and Admin have the same permissions here, so the role assertions
 * are written once against a data provider rather than repeated. What separates
 * the two roles is tested in the Admin-only file, not here.
 */
class StaffDeskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function staffProvider(): array
    {
        return ['secretary' => ['secretary'], 'admin' => ['admin']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonStaffProvider(): array
    {
        return ['resident' => ['resident'], 'kagawad' => ['kagawad']];
    }

    /**
     * Every staff route, so the role middleware cannot be missing from one of them
     * without this file noticing.
     *
     * @return list<array{0: string}>
     */
    public static function staffRoutes(): array
    {
        return [
            ['staff.verifications.index'],
            ['staff.residents.index'],
            ['staff.requests.index'],
            ['staff.batches.index'],
            ['staff.announcements.index'],
        ];
    }

    #[Test]
    #[DataProvider('staffProvider')]
    public function both_staff_roles_reach_every_shared_page(string $role): void
    {
        foreach (self::staffRoutes() as [$name]) {
            $this->actingAs($this->staff($role))
                ->get(route($name))
                ->assertOk();
        }
    }

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function neither_resident_nor_kagawad_reaches_any_staff_page(string $role): void
    {
        foreach (self::staffRoutes() as [$name]) {
            $this->actingAs($this->userFor($role))
                ->get(route($name))
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_guest_is_sent_to_login_from_every_staff_page(): void
    {
        foreach (self::staffRoutes() as [$name]) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    // ---------------------------------------------------------------------
    // Verification queue
    // ---------------------------------------------------------------------

    #[Test]
    public function the_queue_lists_pending_profiles_first(): void
    {
        $secretary = $this->staff('secretary');
        $pending = $this->pendingProfile('HH-1001');
        $this->verifiedProfile('HH-1002');

        $content = $this->actingAs($secretary)
            ->get(route('staff.verifications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($pending->user->name, $content);
    }

    /**
     * The status predicate and the search predicate are combined, not either/or.
     * An ungrouped OR would let the search term match every profile regardless of
     * status, so "pending" plus a name would quietly become "everything".
     */
    #[Test]
    public function a_search_never_widens_the_status_filter(): void
    {
        $secretary = $this->staff('secretary');
        $outside = $this->verifiedProfile('HH-1001');   // matches the search
        $outside->user->update(['name' => 'Aurelio Outsidefilter']);
        $inside = $this->pendingProfile('HH-9999');      // matches the status
        $inside->user->update(['name' => 'Aurelio Insidefilter']);

        $content = $this->actingAs($secretary)
            ->get(route('staff.verifications.index', [
                'status' => 'pending',
                'q' => 'Aurelio',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Aurelio Insidefilter', $content);
        $this->assertStringNotContainsString(
            'Aurelio Outsidefilter',
            $content,
            'The search term matched a profile outside the status filter.'
        );
    }

    #[Test]
    #[DataProvider('staffProvider')]
    public function a_staff_member_can_approve_a_verification(string $role): void
    {
        $profile = $this->pendingProfile('HH-1001');

        $this->actingAs($this->staff($role))
            ->post(route('staff.verifications.approve', $profile))
            ->assertRedirect(route('staff.verifications.index'));

        $profile->refresh();

        $this->assertSame(ResidentProfile::VERIFICATION_VERIFIED, $profile->verification_status);
        $this->assertNotNull($profile->verified_at);
        $this->assertNull($profile->rejection_reason);
        $this->assertTrue($profile->user->fresh()->is_verified);
    }

    #[Test]
    public function a_rejection_without_a_reason_is_refused(): void
    {
        $profile = $this->pendingProfile('HH-1001');

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.verifications.reject', $profile), ['rejecting' => 1])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->fresh()->verification_status);
    }

    #[Test]
    public function a_rejection_carries_the_reason_to_the_resident(): void
    {
        $profile = $this->pendingProfile('HH-1001');

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.verifications.reject', $profile), [
                'rejecting' => 1,
                'rejection_reason' => 'The household number does not match the master list.',
            ])
            ->assertRedirect(route('staff.verifications.index'));

        $profile->refresh();

        $this->assertSame(ResidentProfile::VERIFICATION_REJECTED, $profile->verification_status);
        $this->assertSame('The household number does not match the master list.', $profile->rejection_reason);
        $this->assertNull($profile->verified_at);
        $this->assertFalse($profile->user->fresh()->is_verified);
    }

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function a_non_staff_member_cannot_decide_a_verification(string $role): void
    {
        $profile = $this->pendingProfile('HH-1001');

        $this->actingAs($this->userFor($role))
            ->post(route('staff.verifications.approve', $profile))
            ->assertForbidden();

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->fresh()->verification_status);
    }

    // ---------------------------------------------------------------------
    // ID photos
    // ---------------------------------------------------------------------

    #[Test]
    public function a_staff_member_can_read_a_residents_id_from_the_private_disk(): void
    {
        Storage::fake('local');
        // put() returns a bool, so the path is named here rather than read back.
        $path = 'id-photos/household-id.png';
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $profile = $this->pendingProfile('HH-1001');
        $profile->update(['id_photo_path' => $path]);

        $response = $this->actingAs($this->staff('secretary'))
            ->get(route('staff.resident-verifications.id-photo', $profile));

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->getContent());
        // Symfony appends its own cache directives, so the property that matters
        // is that no-store is present: a personal document must never be cached.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * A resident's ID is a personal document, so the file is never on a disk the
     * web server can reach without the route authorising the read.
     */
    #[Test]
    public function a_resident_cannot_read_their_own_id_through_the_staff_route(): void
    {
        Storage::fake('local');
        $path = 'id-photos/household-id.png';
        Storage::disk('local')->put($path, 'bytes');
        $profile = $this->pendingProfile('HH-1001');
        $profile->update(['id_photo_path' => $path]);

        $this->actingAs($profile->user)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    #[Test]
    public function a_kagawad_cannot_read_any_residents_id(): void
    {
        Storage::fake('local');
        $path = 'id-photos/household-id.png';
        Storage::disk('local')->put($path, 'bytes');
        $profile = $this->pendingProfile('HH-1001');
        $profile->update(['id_photo_path' => $path]);

        $this->actingAs($this->userFor('kagawad'))
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    #[Test]
    public function a_profile_with_no_id_is_a_404_not_an_error(): void
    {
        $profile = $this->pendingProfile('HH-1001');

        $this->actingAs($this->staff('secretary'))
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertNotFound();
    }

    #[Test]
    public function viewing_an_id_is_written_to_the_audit_log(): void
    {
        Storage::fake('local');
        $path = 'id-photos/household-id.png';
        Storage::disk('local')->put($path, 'bytes');
        $profile = $this->pendingProfile('HH-1001');
        $profile->update(['id_photo_path' => $path]);

        $secretary = $this->staff('secretary');

        $this->actingAs($secretary)
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $secretary->id,
            'action' => 'resident.id_viewed',
            'subject_id' => $profile->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Document review
    // ---------------------------------------------------------------------

    #[Test]
    public function a_request_can_be_taken_for_review(): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.review', $documentRequest))
            ->assertRedirect();

        $this->assertSame(DocumentRequest::STATUS_UNDER_REVIEW, $documentRequest->fresh()->status);
    }

    #[Test]
    public function a_request_can_be_marked_ready_and_then_collected(): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();
        $staff = $this->staff('secretary');

        $this->actingAs($staff)->post(route('staff.requests.ready', $documentRequest))->assertRedirect();
        $this->assertSame(DocumentRequest::STATUS_READY, $documentRequest->fresh()->status);

        $this->actingAs($staff)->post(route('staff.requests.claim', $documentRequest))->assertRedirect();

        $documentRequest->refresh();
        $this->assertSame(DocumentRequest::STATUS_CLAIMED, $documentRequest->status);
        $this->assertNotNull($documentRequest->claimed_at);
    }

    #[Test]
    public function a_rejected_request_needs_a_reason(): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.reject', $documentRequest))
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(DocumentRequest::STATUS_SUBMITTED, $documentRequest->fresh()->status);
    }

    #[Test]
    public function a_rejected_request_keeps_the_reason(): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.reject', $documentRequest), [
                'rejection_reason' => 'Please state the receiving office instead.',
            ])
            ->assertRedirect();

        $documentRequest->refresh();

        $this->assertSame(DocumentRequest::STATUS_REJECTED, $documentRequest->status);
        $this->assertSame('Please state the receiving office instead.', $documentRequest->rejection_reason);
    }

    /**
     * The buttons are hidden for a state that does not support the action, and
     * the server refuses just as firmly. 409 rather than a silent no-op, because
     * a screen that "succeeded" at nothing would leave a Secretary believing a
     * request was approved.
     */
    #[Test]
    public function an_action_the_state_does_not_support_is_refused(): void
    {
        $documentRequest = DocumentRequest::factory()->claimed()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.ready', $documentRequest))
            ->assertStatus(409);

        $this->assertSame(DocumentRequest::STATUS_CLAIMED, $documentRequest->fresh()->status);
    }

    #[Test]
    public function a_collected_request_cannot_be_rejected(): void
    {
        $documentRequest = DocumentRequest::factory()->claimed()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.reject', $documentRequest), [
                'rejection_reason' => 'Changed my mind about this one.',
            ])
            ->assertStatus(409);
    }

    #[Test]
    public function a_status_change_is_written_to_the_audit_log_with_the_real_transition(): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();

        $this->actingAs($this->staff('secretary'))
            ->post(route('staff.requests.ready', $documentRequest))
            ->assertRedirect();

        $entry = AuditLog::query()
            ->where('action', 'request.ready')
            ->latest('id')
            ->firstOrFail();

        // The transition is read BEFORE the write, so it must not say "ready to
        // ready" - which is exactly the kind of wrong record a trail exists to
        // prevent.
        $this->assertStringContainsString('submitted', $entry->description);
        $this->assertStringContainsString('Ready for pickup', $entry->description);
    }

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function a_non_staff_member_cannot_act_on_a_request(string $role): void
    {
        $documentRequest = DocumentRequest::factory()->submitted()->create();

        $this->actingAs($this->userFor($role))
            ->post(route('staff.requests.ready', $documentRequest))
            ->assertForbidden();

        $this->assertSame(DocumentRequest::STATUS_SUBMITTED, $documentRequest->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Residents directory
    // ---------------------------------------------------------------------

    #[Test]
    public function the_directory_lists_every_resident(): void
    {
        $this->verifiedProfile('HH-1001');
        $this->pendingProfile('HH-2002');

        $content = $this->actingAs($this->staff('secretary'))
            ->get(route('staff.residents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-1001', $content);
        $this->assertStringContainsString('HH-2002', $content);
    }

    #[Test]
    public function the_directory_searches_by_name(): void
    {
        $found = $this->verifiedProfile('HH-1001');
        $this->verifiedProfile('HH-2002');

        $content = $this->actingAs($this->staff('secretary'))
            ->get(route('staff.residents.index', ['q' => $found->user->name]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-1001', $content);
        $this->assertStringNotContainsString('HH-2002', $content);
    }

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function the_directory_is_not_reachable_by_a_non_staff_member(string $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('staff.residents.index'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_verified' => true]);
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        if ($role === 'resident') {
            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => Sector::factory()->create()->id,
            ]);
        }

        if ($role === 'kagawad') {
            Sector::factory()->create(['kagawad_user_id' => $user->id]);
        }

        return $user;
    }

    private function pendingProfile(string $household): ResidentProfile
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        return ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => Sector::factory()->create()->id,
        ]);
    }

    private function verifiedProfile(string $household): ResidentProfile
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        return ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);
    }
}
