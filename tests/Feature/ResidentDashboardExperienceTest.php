<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the resident dashboard promises, per verification state.
 *
 * The dashboard's job when a resident cannot act yet is to say so accurately
 * and point somewhere useful, rather than to render the working dashboard with
 * a warning stapled on top. So these tests check three things: that the three
 * states are visibly different, that the lock explanation names the reason the
 * server would actually refuse, and that a locked dashboard never renders a
 * link to the request form the POST endpoint is going to reject.
 */
class ResidentDashboardExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function dashboardFor(User $user): string
    {
        return $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->getContent();
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user->fresh();
    }

    private function waitingResident(string $status = ResidentProfile::VERIFICATION_PENDING): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => $status,
        ]);

        return $user->fresh();
    }

    #[Test]
    public function a_verified_resident_is_greeted_by_name_and_badged_as_verified(): void
    {
        $user = $this->verifiedResident();

        $content = $this->dashboardFor($user);

        $this->assertStringContainsString('Welcome, '.explode(' ', $user->name)[0], $content);
        $this->assertStringContainsString('Verified', $content);
        $this->assertStringContainsString('Your resident record is verified', $content);
    }

    #[Test]
    public function a_waiting_resident_is_told_it_is_still_waiting(): void
    {
        $content = $this->dashboardFor($this->waitingResident());

        $this->assertStringContainsString('Pending Verification', $content);
        $this->assertStringContainsString('Your details are with the Barangay Secretary', $content);
    }

    #[Test]
    public function a_rejected_resident_is_offered_the_way_back_in(): void
    {
        $user = $this->waitingResident(ResidentProfile::VERIFICATION_REJECTED);
        $user->residentProfile->update([
            'rejection_reason' => 'The ID photograph was too blurry to read.',
        ]);

        $content = $this->dashboardFor($user->fresh());

        $this->assertStringContainsString('Verification Rejected', $content);
        $this->assertStringContainsString('The ID photograph was too blurry to read.', $content);
        $this->assertStringContainsString(route('resident.verification', absolute: false), $content);
    }

    #[Test]
    public function the_pending_dashboard_names_the_details_that_are_still_missing(): void
    {
        // The factory leaves the ID photo blank, which is the common case for a
        // profile that has not been submitted for verification yet.
        $content = $this->dashboardFor($this->waitingResident());

        $this->assertStringContainsString('Profile completion', $content);
        $this->assertStringContainsString('ID photo', $content);
        $this->assertStringContainsString('Missing', $content);
        $this->assertStringContainsString('Filled in', $content);
    }

    #[Test]
    public function the_profile_completion_card_says_so_when_nothing_is_outstanding(): void
    {
        $user = $this->waitingResident();
        $user->residentProfile->update(['id_photo_path' => 'id-photos/example.jpg']);

        $content = $this->dashboardFor($user->fresh());

        $this->assertStringContainsString('All required details are filled in', $content);
    }

    #[Test]
    public function a_verified_resident_is_offered_the_request_form(): void
    {
        $content = $this->dashboardFor($this->verifiedResident());

        // Unlocked, so the lock panel is absent and the form is reachable.
        $this->assertStringNotContainsString('Document requests are locked', $content);
        $this->assertStringContainsString(route('resident.requests.create', absolute: false), $content);
    }

    /**
     * The lock reasons, and the resident state that produces each.
     *
     * @return array<string, array{0: callable, 1: string}>
     */
    public static function lockedReasonProvider(): array
    {
        return [
            'no profile at all' => [
                fn () => User::factory()->resident()->create(['is_verified' => true]),
                'Set up your resident profile',
            ],
            'still being reviewed' => [
                fn () => self::waitingResidentState(ResidentProfile::VERIFICATION_PENDING),
                'still being reviewed',
            ],
            'sent back by the secretary' => [
                fn () => self::waitingResidentState(ResidentProfile::VERIFICATION_REJECTED),
                'was not approved',
            ],
        ];
    }

    private static function waitingResidentState(string $status): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => $status,
        ]);

        return $user->fresh();
    }

    #[Test]
    #[DataProvider('lockedReasonProvider')]
    public function a_locked_dashboard_states_the_reason_and_hides_the_form(callable $makeResident, string $expectedReason): void
    {
        $user = $makeResident();

        $content = $this->dashboardFor($user);

        $this->assertStringContainsString('Document requests are locked', $content);
        $this->assertStringContainsString($expectedReason, $content);

        // The form endpoint refuses these, so the dashboard must not be offering
        // it. Only the index is linked, which is where a resident can still read
        // whatever they already have.
        $this->assertStringNotContainsString(route('resident.requests.create', absolute: false), $content);
    }

    #[Test]
    public function a_resident_holding_the_full_allowance_is_told_it_is_the_cap_and_not_verification(): void
    {
        $user = $this->verifiedResident();

        DocumentRequest::factory()->count(DocumentRequest::OPEN_REQUEST_LIMIT)->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $content = $this->dashboardFor($user);

        $this->assertStringContainsString('Document requests are locked', $content);
        $this->assertStringContainsString(
            'You already have '.DocumentRequest::OPEN_REQUEST_LIMIT.' requests that are still open',
            $content,
        );
        $this->assertStringNotContainsString(route('resident.requests.create', absolute: false), $content);
    }

    #[Test]
    public function a_restricted_resident_is_told_the_restriction_rather_than_being_told_to_wait(): void
    {
        $user = $this->verifiedResident();
        $user->update(['restricted_until' => now()->addWeek()]);

        $content = $this->dashboardFor($user->fresh());

        $this->assertStringContainsString('temporarily restricted', $content);
        $this->assertStringNotContainsString(route('resident.requests.create', absolute: false), $content);
    }

    #[Test]
    public function collected_work_is_counted_separately_from_work_in_progress(): void
    {
        $user = $this->verifiedResident();

        DocumentRequest::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_COMPLETED,
        ]);
        DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->actingAs($user)
            ->get(route('resident.dashboard', absolute: false))
            ->assertOk()
            ->assertViewHas('completedCount', 2)
            ->assertViewHas('activeCount', 1);
    }

    #[Test]
    public function the_dashboard_previews_published_notices_and_nothing_scheduled(): void
    {
        Announcement::create([
            'title' => 'Out now',
            'body' => 'Water interruption today.',
            'published_at' => now()->subDay(),
        ]);
        Announcement::create([
            'title' => 'Still a draft',
            'body' => 'Not for residents.',
            'published_at' => null,
        ]);
        Announcement::create([
            'title' => 'Scheduled for later',
            'body' => 'Not out yet.',
            'published_at' => now()->addWeek(),
        ]);

        $content = $this->dashboardFor($this->verifiedResident());

        $this->assertStringContainsString('Out now', $content);
        $this->assertStringContainsString('Water interruption today.', $content);
        $this->assertStringNotContainsString('Still a draft', $content);
        $this->assertStringNotContainsString('Scheduled for later', $content);
    }

    #[Test]
    public function the_dashboard_says_so_when_nothing_has_been_published(): void
    {
        $content = $this->dashboardFor($this->verifiedResident());

        $this->assertStringContainsString('No announcements right now', $content);
    }

    #[Test]
    public function every_quick_action_is_rendered_not_just_the_first_one(): void
    {
        // This is here because the action list was built with the array union
        // operator, which keeps the left-hand keys and throws away every
        // same-numbered key from the right. Since $actions always held key 0,
        // all four shared actions vanished and the resident was left with one
        // tile and no indication that the others existed.
        $content = $this->dashboardFor($this->verifiedResident());

        foreach ([
            'Request a Document',
            'Track My Requests',
            'My Documents',
            'Relief Transparency',
            'My Profile',
        ] as $label) {
            $this->assertStringContainsString($label, $content);
        }
    }

    #[Test]
    public function a_locked_dashboard_still_offers_every_quick_action(): void
    {
        $content = $this->dashboardFor($this->waitingResident());

        foreach ([
            'Track My Requests',
            'My Documents',
            'Relief Transparency',
            'Verification Status',
        ] as $label) {
            $this->assertStringContainsString($label, $content);
        }
    }

    #[Test]
    public function a_restricted_verified_resident_is_not_told_to_wait_on_the_request_limit(): void
    {
        // Both locks can apply at once. The hint has to name the one that is
        // actually being enforced, and restriction is the one the server checks
        // first - so telling them they are "waiting" would be a lie.
        $user = $this->verifiedResident();
        $user->update(['restricted_until' => now()->addWeek()]);

        DocumentRequest::factory()->count(DocumentRequest::OPEN_REQUEST_LIMIT)->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $content = $this->dashboardFor($user->fresh());

        $this->assertStringContainsString('temporarily restricted', $content);
        $this->assertStringNotContainsString('Limit reached for now', $content);
    }
}
