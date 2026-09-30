<?php

namespace Tests\Feature;

use App\Console\Commands\EnforceAbandonmentPolicy;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BdrpisFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedResident(): User
    {
        $sector = Sector::factory()->create();
        $user = User::factory()->resident()->create();

        ResidentProfile::create([
            'user_id' => $user->id,
            'household_number' => 'HH-4242',
            'national_or_voter_id' => '1111-1111-1111',
            'address' => 'Test St., San Isidro',
            'sector_id' => $sector->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);
        $user->update(['is_verified' => true, 'assigned_sector_id' => $sector->id]);

        return $user;
    }

    public function test_unverified_resident_cannot_submit_document_requests(): void
    {
        $user = User::factory()->resident()->create();

        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Employment',
            ])
            ->assertRedirect(route('resident.verification.status'));

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_verified_resident_can_submit_and_track_a_request_with_reference_number(): void
    {
        $user = $this->verifiedResident();

        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Employment requirement',
            ])
            ->assertRedirect(route('resident.requests.index'));

        $request = DocumentRequest::firstOrFail();

        $this->assertStringStartsWith('BRGY-'.now()->year.'-', $request->reference_number);
        $this->assertEquals(DocumentRequest::STATUS_SUBMITTED, $request->status);

        $this->get(route('requests.track', ['reference' => $request->reference_number]))
            ->assertOk()
            ->assertSee($request->reference_number);
    }

    public function test_restricted_resident_is_blocked_from_new_requests(): void
    {
        $user = $this->verifiedResident();
        $user->update(['restricted_until' => now()->addDays(10)]);

        $this->actingAs($user->fresh())
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Purpose',
            ])
            ->assertSessionHasErrors('account');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_abandonment_policy_applies_three_strikes_and_restricts_for_thirty_days(): void
    {
        $user = $this->verifiedResident();

        foreach (range(1, 3) as $i) {
            DocumentRequest::factory()->create([
                'user_id' => $user->id,
                'status' => DocumentRequest::STATUS_READY,
                'ready_at' => now()->subWeekdays(10),
                'reference_number' => DocumentRequest::generateReferenceNumber(),
            ]);
        }

        $this->artisan(EnforceAbandonmentPolicy::class)->assertSuccessful();

        $user->refresh();

        $this->assertEquals(3, $user->abandoned_count);
        $this->assertNotNull($user->restricted_until);
        $this->assertTrue($user->restricted_until->isFuture());
        $this->assertEquals(
            0,
            DocumentRequest::where('user_id', $user->id)
                ->where('status', DocumentRequest::STATUS_READY)
                ->count(),
        );
    }

    public function test_role_middleware_blocks_kagawad_from_staff_area(): void
    {
        $kagawad = User::factory()->kagawad()->create();

        $this->actingAs($kagawad)
            ->get(route('staff.requests.index'))
            ->assertForbidden();
    }

    public function test_relief_batch_maps_all_verified_households_and_transparency_lookup_works(): void
    {
        $sector = Sector::factory()->create();
        $secretary = User::factory()->secretary()->create();

        $user = User::factory()->resident()->create();
        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'household_number' => 'HH-9999',
            'sector_id' => $sector->id,
        ]);
        $user->update(['is_verified' => true]);

        $this->actingAs($secretary)
            ->post(route('staff.batches.store'), [
                'title' => 'Test Relief Batch',
                'distribution_date' => now()->addDay()->toDateString(),
                'status' => ReliefBatch::STATUS_ACTIVE,
                'items' => '5kg Rice, Canned Goods',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('relief_distributions', 1);
        $this->assertEquals('HH-9999', ReliefDistribution::first()->household_number);

        $this->get(route('transparency.search', ['household_number' => 'HH-9999']))
            ->assertOk()
            ->assertSee('HH-9999');
    }
}
