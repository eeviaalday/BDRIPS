<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The secretary's verification desk.
 *
 * Two things are asserted here that nothing else covered.
 *
 * First, the queue. profileQueue() combines a status predicate with a search
 * predicate, and an ungrouped orWhere() would let the search clause match every
 * profile regardless of status - so the combination is tested, not each filter
 * alone.
 *
 * Second, the detail screen. That screen only works if the route parameter is
 * actually bound. A controller parameter whose name does not match the route
 * segment is not bound at all - it is container-injected as a fresh empty model,
 * which renders a page full of nulls instead of erroring. A page that renders
 * 200 is therefore not evidence that binding worked, so the household number of
 * the requested profile is asserted to be on the page.
 */
class SecretaryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function secretary(): User
    {
        return User::factory()->secretary()->create();
    }

    private function pendingProfile(string $household = 'HH-0001', string $name = 'Ana Dela Cruz'): ResidentProfile
    {
        $user = User::factory()->resident()->create(['name' => $name]);

        return ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => Sector::factory()->create()->id,
        ]);
    }

    public function test_the_queue_lists_pending_and_verified_profiles_but_not_rejected(): void
    {
        $this->pendingProfile('HH-PENDING', 'Pending Person');
        ResidentProfile::factory()->create([
            'user_id' => User::factory()->resident()->create(['name' => 'Verified Person'])->id,
            'household_number' => 'HH-VERIFIED',
        ]);
        ResidentProfile::factory()->create([
            'user_id' => User::factory()->resident()->create()->id,
            'household_number' => 'HH-REJECTED',
            'verification_status' => ResidentProfile::VERIFICATION_REJECTED,
        ]);

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.verifications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-PENDING', $content);
        $this->assertStringContainsString('HH-VERIFIED', $content);
        $this->assertStringNotContainsString('HH-REJECTED', $content);
    }

    public function test_search_and_status_filters_apply_together(): void
    {
        $this->pendingProfile('HH-MATCH', 'Rosario Match');

        $decoy = ResidentProfile::factory()->create([
            'user_id' => User::factory()->resident()->create(['name' => 'Someone Else'])->id,
            'household_number' => 'HH-DECOY',
        ]);

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.verifications.index', ['q' => 'Match', 'status' => ResidentProfile::VERIFICATION_PENDING]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-MATCH', $content);
        $this->assertStringNotContainsString($decoy->household_number, $content);
    }

    public function test_a_search_term_only_shows_profiles_of_the_requested_status(): void
    {
        $verified = ResidentProfile::factory()->create([
            'user_id' => User::factory()->resident()->create(['name' => 'Twin Name'])->id,
            'household_number' => 'HH-VERIFIED-TWIN',
        ]);
        $this->pendingProfile('HH-PENDING-TWIN', 'Twin Name');

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.verifications.index', [
                'q' => 'Twin Name',
                'status' => ResidentProfile::VERIFICATION_VERIFIED,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($verified->household_number, $content);
        $this->assertStringNotContainsString('HH-PENDING-TWIN', $content);
    }

    public function test_the_detail_screen_binds_the_requested_profile(): void
    {
        $profile = $this->pendingProfile('HH-BOUND-CHECK');

        $this->actingAs($this->secretary())
            ->get(route('staff.verifications.show', $profile))
            ->assertOk()
            ->assertSee('HH-BOUND-CHECK')
            ->assertSee(route('staff.verifications.update', $profile), escape: false);
    }

    public function test_the_detail_screen_shows_the_resident_recent_requests(): void
    {
        $profile = $this->pendingProfile('HH-WITH-REQUEST');

        $request = DocumentRequest::factory()->create([
            'user_id' => $profile->user_id,
            'reference_number' => 'BDR-REF-DETAIL',
        ]);

        $this->actingAs($this->secretary())
            ->get(route('staff.verifications.show', $profile))
            ->assertOk()
            ->assertSee($request->reference_number);
    }

    public function test_an_admin_may_also_open_the_detail_screen(): void
    {
        $profile = $this->pendingProfile('HH-ADMIN-VIEW');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('staff.verifications.show', $profile))
            ->assertOk()
            ->assertSee('HH-ADMIN-VIEW');
    }

    public function test_a_resident_or_kagawad_is_refused_the_queue_and_the_detail_screen(): void
    {
        $profile = $this->pendingProfile('HH-PRIVATE');

        foreach (['resident', 'kagawad'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create())
                ->get(route('staff.verifications.index'))
                ->assertForbidden();

            $this->actingAs(User::factory()->{$state}()->create())
                ->get(route('staff.verifications.show', $profile))
                ->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('staff.verifications.index'))->assertRedirect(route('login'));
    }

    public function test_the_detail_screen_links_the_stored_id_photo_and_says_so_when_there_is_none(): void
    {
        $profile = $this->pendingProfile('HH-PHOTO');
        $profile->update(['id_photo_path' => $profile->id.'/valid-id.jpg']);

        $this->actingAs($this->secretary())
            ->get(route('staff.verifications.show', $profile))
            ->assertOk()
            ->assertSee(route('staff.resident-verifications.id-photo', $profile), escape: false);

        $profile->update(['id_photo_path' => null]);

        $this->actingAs($this->secretary())
            ->get(route('staff.verifications.show', $profile))
            ->assertOk()
            ->assertSee('No ID on file');
    }

    public function test_a_kagawad_cannot_stream_another_users_id_photo(): void
    {
        Storage::fake('local');

        $profile = $this->pendingProfile('HH-ID-IMAGE');
        $profile->update(['id_photo_path' => $profile->id.'/valid-id.jpg']);
        Storage::disk('local')->put($profile->id.'/valid-id.jpg', 'id-bytes');

        $this->actingAs($this->secretary())
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertOk();

        $this->actingAs(User::factory()->kagawad()->create())
            ->get(route('staff.resident-verifications.id-photo', $profile))
            ->assertForbidden();
    }

    public function test_verifying_from_the_detail_screen_updates_profile_and_user(): void
    {
        $profile = $this->pendingProfile('HH-APPROVE-ME');

        $this->actingAs($this->secretary())
            ->from(route('staff.verifications.show', $profile))
            ->post(route('staff.verifications.update', $profile), ['action' => ResidentProfile::VERIFICATION_VERIFIED])
            ->assertRedirect(route('staff.verifications.show', $profile))
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertSame(ResidentProfile::VERIFICATION_VERIFIED, $profile->verification_status);
        $this->assertNotNull($profile->verified_at);
        $this->assertTrue($profile->user->refresh()->is_verified);
        $this->assertSame($profile->sector_id, $profile->user->assigned_sector_id);
    }

    public function test_rejecting_from_the_detail_screen_stores_the_reason_and_clears_verification(): void
    {
        $profile = $this->pendingProfile('HH-REJECT-ME');

        $this->actingAs($this->secretary())
            ->from(route('staff.verifications.show', $profile))
            ->post(route('staff.verifications.update', $profile), [
                'action' => ResidentProfile::VERIFICATION_REJECTED,
                'rejection_reason' => 'Household number is not on the master list.',
            ])
            ->assertRedirect(route('staff.verifications.show', $profile));

        $profile->refresh();
        $this->assertSame(ResidentProfile::VERIFICATION_REJECTED, $profile->verification_status);
        $this->assertSame('Household number is not on the master list.', $profile->rejection_reason);
        $this->assertNull($profile->verified_at);
        $this->assertFalse($profile->user->refresh()->is_verified);
    }

    public function test_an_unknown_verification_action_changes_nothing(): void
    {
        $profile = $this->pendingProfile('HH-BAD-ACTION');

        $this->actingAs($this->secretary())
            ->from(route('staff.verifications.show', $profile))
            ->post(route('staff.verifications.update', $profile), ['action' => 'delete-everything'])
            ->assertSessionHasErrors('action');

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->refresh()->verification_status);
    }
}
