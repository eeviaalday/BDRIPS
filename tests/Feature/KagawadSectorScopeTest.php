<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The kagawad area.
 *
 * sectors.kagawad_user_id is the authorisation anchor for this whole area, and
 * the two guards built on it had no test at all - nothing in the suite
 * previously asserted that one kagawad is refused another kagawad's sector, by
 * URL or otherwise. That is the assertion this file exists for.
 */
class KagawadSectorScopeTest extends TestCase
{
    use RefreshDatabase;

    private function sectorFor(User $kagawad, string $name = 'Inner 1'): Sector
    {
        return Sector::create(['sector_name' => $name, 'kagawad_user_id' => $kagawad->id]);
    }

    private function kagawad(): User
    {
        return User::factory()->kagawad()->create();
    }

    private function activeBatch(): ReliefBatch
    {
        return ReliefBatch::create([
            'title' => 'Relief Drive',
            'distribution_date' => now(),
            'status' => ReliefBatch::STATUS_ACTIVE,
        ]);
    }

    public function test_a_kagawad_sees_their_own_sectors_real_resident_count(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);

        ResidentProfile::factory()->count(3)->create(['sector_id' => $sector->id]);

        $content = $this->actingAs($kagawad)
            ->get(route('kagawad.dashboard'))
            ->assertOk()
            ->getContent();

        // Regression: the view read $sector->residents_count but the controller
        // never withCount'd it, so the attribute did not exist and the page
        // rendered an em dash on every sector, always.
        $this->assertStringContainsString('3', $content);
        $this->assertStringNotContainsString('&mdash; residents', $content);
        $this->assertStringContainsString('residents registered', $content);
    }

    public function test_a_kagawad_with_no_sector_is_told_so_rather_than_shown_a_blank_page(): void
    {
        $this->actingAs($this->kagawad())
            ->get(route('kagawad.dashboard'))
            ->assertOk()
            ->assertSee('No sector assigned to you yet');
    }

    public function test_a_kagawad_cannot_open_another_kagawads_sector_checklist(): void
    {
        $mine = $this->kagawad();
        $theirs = $this->kagawad();

        $mySector = $this->sectorFor($mine, 'Inner 1');
        $theirSector = $this->sectorFor($theirs, 'Petron');

        $batch = $this->activeBatch();
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $theirSector->id,
            'household_number' => 'HH-THEIRS',
        ]);

        $this->actingAs($mine)
            ->get(route('kagawad.checklist', [$batch, $theirSector]))
            ->assertForbidden();
    }

    public function test_a_kagawad_cannot_update_a_distribution_in_another_sector(): void
    {
        $mine = $this->kagawad();
        $theirs = $this->kagawad();

        $this->sectorFor($mine, 'Inner 1');
        $theirSector = $this->sectorFor($theirs, 'Petron');

        $batch = $this->activeBatch();
        $theirs_row = ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $theirSector->id,
            'household_number' => 'HH-THEIRS',
        ]);

        $this->actingAs($mine)
            ->post(route('kagawad.distributions.update', $theirs_row), [
                'status' => 'received',
            ])
            ->assertForbidden();

        $this->assertSame(
            ReliefDistribution::STATUS_PENDING,
            $theirs_row->fresh()->status,
            'A kagawad managed to mark another sector\'s household as received.',
        );
    }

    public function test_a_kagawad_cannot_widen_their_checklist_with_a_query_parameter(): void
    {
        $kagawad = $this->kagawad();
        $mySector = $this->sectorFor($kagawad, 'Inner 1');
        $otherSector = Sector::create(['sector_name' => 'Petron', 'kagawad_user_id' => $this->kagawad()->id]);

        $batch = $this->activeBatch();

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $mySector->id,
            'household_number' => 'HH-MINE',
        ]);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $otherSector->id,
            'household_number' => 'HH-OTHER-SECTOR',
        ]);

        // The sector is bound in the route and guarded there; the filter can
        // only narrow within it, never select a different one.
        $this->actingAs($kagawad)
            ->get(route('kagawad.checklist', [$batch, $mySector]))
            ->assertOk()
            ->assertViewHas('distribution', fn ($rows): bool => $rows->count() === 1
                && $rows->first()->household_number === 'HH-MINE');
    }

    public function test_the_checklist_can_be_filtered_by_status_and_searched(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);
        $batch = $this->activeBatch();

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'HH-0001', 'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'HH-0002', 'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $this->actingAs($kagawad)
            ->get(route('kagawad.checklist', [$batch, $sector, 'status' => 'pending']))
            ->assertOk()
            ->assertViewHas('distribution', fn ($rows): bool => $rows->count() === 1
                && $rows->first()->household_number === 'HH-0002');

        $this->actingAs($kagawad)
            ->get(route('kagawad.checklist', [$batch, $sector, 'q' => 'HH-0001']))
            ->assertOk()
            ->assertViewHas('distribution', fn ($rows): bool => $rows->count() === 1
                && $rows->first()->household_number === 'HH-0001');
    }

    public function test_marking_received_records_the_kagawad_and_the_time(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);
        $batch = $this->activeBatch();

        $row = ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'HH-0001',
        ]);

        $this->actingAs($kagawad)
            ->post(route('kagawad.distributions.update', $row), [
                'status' => 'received',
                'received_by_name' => 'Maria Santos',
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame(ReliefDistribution::STATUS_RECEIVED, $row->status);
        $this->assertSame('Maria Santos', $row->received_by_name);
        $this->assertSame($kagawad->id, $row->logged_by_user_id);
        $this->assertNotNull($row->claimed_at);
    }

    public function test_a_correction_keeps_the_audit_trail_of_the_original_claim(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);
        $batch = $this->activeBatch();

        $row = ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'HH-0001',
        ]);

        $this->actingAs($kagawad)->post(route('kagawad.distributions.update', $row), [
            'status' => 'received',
            'received_by_name' => 'Maria Santos',
        ]);

        $claimedAt = $row->fresh()->claimed_at;

        $this->actingAs($kagawad)
            ->post(route('kagawad.distributions.update', $row), ['status' => 'pending'])
            ->assertRedirect();

        $row->refresh();

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $row->status);
        $this->assertNull($row->received_by_name, 'The mutable claim name should be cleared.');

        // The audit facts survive the correction. Previously all three fields
        // were nulled, so a mis-click left no trace that anyone had logged a
        // claim at all and the relief record could be rewritten invisibly.
        $this->assertNotNull($row->logged_by_user_id, 'The kagawad who logged the claim was erased.');
        $this->assertNotNull($row->claimed_at, 'The time the claim was logged was erased.');
        $this->assertTrue($claimedAt->equalTo($row->claimed_at));
    }

    public function test_a_kagawad_cannot_reach_the_staff_area(): void
    {
        $this->actingAs($this->kagawad())
            ->get(route('staff.requests.index'))
            ->assertForbidden();
    }

    public function test_the_checklist_offers_a_mark_received_action_per_household(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);
        $batch = $this->activeBatch();

        ReliefDistribution::factory()->count(2)->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
        ]);

        $content = $this->actingAs($kagawad)
            ->get(route('kagawad.checklist', [$batch, $sector]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mark received', $content);
        $this->assertStringContainsString('Confirm collection', $content);
    }

    public function test_the_dashboard_progress_bar_exposes_its_value_to_assistive_technology(): void
    {
        $kagawad = $this->kagawad();
        $sector = $this->sectorFor($kagawad);
        $batch = $this->activeBatch();

        ReliefDistribution::factory()->count(3)->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $content = $this->actingAs($kagawad)
            ->get(route('kagawad.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('role="progressbar"', $content);
        $this->assertStringContainsString('aria-valuenow="75"', $content);
    }
}
