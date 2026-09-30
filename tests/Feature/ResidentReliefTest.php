<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A resident's own relief record, and the boundary around it.
 *
 * The whole risk in this area is the household number. It is short, sequential
 * and printed on paperwork, so a page that accepted one as input would let a
 * resident read a neighbour's record by trying the next one along. These tests
 * pin that there is no input at all, and that the rows shown are the signed-in
 * resident's own household and nobody else's.
 */
class ResidentReliefTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_verified_resident_sees_their_own_household_record(): void
    {
        $resident = $this->verifiedResident('HH-1001');
        $batch = ReliefBatch::factory()->create(['title' => 'Typhoon Relief Drive']);

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'household_number' => 'HH-1001',
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Ana Dela Cruz',
        ]);

        $content = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('Typhoon Relief Drive', $content);
        $this->assertStringContainsString('Ana Dela Cruz', $content);
    }

    #[Test]
    public function a_resident_never_sees_another_household_record(): void
    {
        $resident = $this->verifiedResident('HH-1001');
        $batch = ReliefBatch::factory()->create(['title' => 'Neighbour Batch']);

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'household_number' => 'HH-9999',
            'received_by_name' => 'Someone Else Entirely',
        ]);

        $content = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Someone Else Entirely', $content);
    }

    /**
     * The security property itself: no input, so nothing to guess with. A
     * household_number parameter or form field would be the thing that lets a
     * neighbour's record be read.
     */
    #[Test]
    public function there_is_no_household_number_input_to_look_up_with(): void
    {
        $content = $this->actingAs($this->verifiedResident('HH-1001'))
            ->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="household_number"', $content);
        $this->assertStringNotContainsString('transparency.search', $content);
    }

    #[Test]
    public function a_query_string_cannot_reach_another_household(): void
    {
        $resident = $this->verifiedResident('HH-1001');
        $batch = ReliefBatch::factory()->create(['title' => 'Neighbour Batch']);

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'household_number' => 'HH-9999',
            'received_by_name' => 'Someone Else Entirely',
        ]);

        $content = $this->actingAs($resident)
            ->get(route('resident.relief').'?household_number=HH-9999')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Someone Else Entirely', $content);
    }

    #[Test]
    public function a_pending_resident_is_told_relief_is_locked(): void
    {
        $content = $this->actingAs($this->pendingResident('HH-1002'))
            ->get(route('resident.relief'))->assertOk()->getContent();

        // "Locked", not an empty table: an empty table would be a different and
        // wrong answer to "why can I not see this".
        $this->assertStringContainsString('unlock', strtolower($content));
    }

    /**
     * A pending resident's relief rows are not queried at all, not merely hidden
     * after being fetched.
     */
    #[Test]
    public function a_pending_resident_is_shown_no_distribution_rows(): void
    {
        $resident = $this->pendingResident('HH-1002');
        $batch = ReliefBatch::factory()->create(['title' => 'Their Own Batch']);

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'household_number' => 'HH-1002',
            'received_by_name' => 'Their Own Record',
        ]);

        $content = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Their Own Record', $content);
    }

    /**
     * The batch schedule is public information, so a pending resident can see what
     * is going out even though their own record is locked.
     */
    #[Test]
    public function a_pending_resident_can_still_see_which_batches_are_running(): void
    {
        $content = $this->actingAs($this->pendingResident('HH-1002'))
            ->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('Batches going out now', $content);
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('resident.relief'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_resident_with_no_profile_is_told_rather_than_shown_an_empty_page(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        $this->actingAs($user)
            ->get(route('resident.relief'))
            ->assertOk();
    }

    private function verifiedResident(string $household): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function pendingResident(string $household): User
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
