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
 * The two cards above the household's relief history.
 *
 * They exist because the three count cards they replaced answered neither of the
 * two questions a resident opens this page with, and they carry the one part of
 * the page that is about the barangay rather than about the reader - which is
 * exactly why the privacy test here matters more than the rendering ones.
 */
class ResidentReliefCardsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // What is coming
    // ---------------------------------------------------------------------

    #[Test]
    public function it_names_the_open_batch_the_household_has_not_collected_from(): void
    {
        $resident = $this->verifiedResident();
        $batch = ReliefBatch::factory()->create([
            'title' => 'Typhoon Relief Drive',
            'description' => 'For households affected by the typhoon.',
            'items_included' => ['Rice 5kg', 'Canned goods'],
            'distribution_date' => '2026-10-10',
        ]);
        $this->pendingDistribution($resident, $batch);

        $html = $this->page($resident);

        $this->assertStringContainsString('Next distribution for your household', $html);
        $this->assertStringContainsString('Typhoon Relief Drive', $html);
        $this->assertStringContainsString('For households affected by the typhoon.', $html);
        $this->assertStringContainsString('Rice 5kg', $html);
        $this->assertStringContainsString('Canned goods', $html);
        $this->assertStringContainsString('10 October 2026', $html);
        $this->assertStringContainsString('Bring', $html);
        $this->assertStringContainsString('A valid ID', $html);
    }

    /**
     * A household that has already collected should not be told to come back for
     * the same goods. This is the whole reason the card is found by asking which
     * open batches have no received row, rather than by asking which batch is
     * newest.
     */
    #[Test]
    public function it_does_not_nag_a_household_that_has_already_collected_from_the_open_batch(): void
    {
        $resident = $this->verifiedResident();
        $batch = ReliefBatch::factory()->create(['title' => 'Typhoon Relief Drive']);
        $this->receivedDistribution($resident, $batch);

        $html = $this->page($resident);

        $this->assertStringNotContainsString('Next distribution for your household', $html);
    }

    #[Test]
    public function it_names_the_households_sector_kagawad_when_there_is_one(): void
    {
        $kagawad = User::factory()->kagawad()->create(['name' => 'Ramon P. Bautista']);
        $resident = $this->verifiedResident($kagawad);
        $this->pendingDistribution($resident, ReliefBatch::factory()->create());

        $html = $this->page($resident);

        $this->assertStringContainsString('Your sector Kagawad', $html);
        $this->assertStringContainsString('Ramon P. Bautista', $html);
    }

    // ---------------------------------------------------------------------
    // Barangay-wide progress
    // ---------------------------------------------------------------------

    #[Test]
    public function it_reports_the_batch_progress_as_a_sentence_and_a_bar(): void
    {
        $resident = $this->verifiedResident();
        $batch = ReliefBatch::factory()->create(['title' => 'Typhoon Relief Drive']);

        // Three distinct households, because a batch lists each household once.
        // Two of the three have received, and only one of them is this reader's -
        // so the figure on the card cannot have come from the reader's own rows.
        $this->pendingDistribution($resident, $batch);
        $this->distribution($resident, $batch, [
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'household_number' => 'HH-2002',
        ]);
        $this->distribution($resident, $batch, [
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'household_number' => 'HH-3003',
        ]);

        $html = $this->page($resident);

        $this->assertStringContainsString('Barangay-wide progress', $html);
        $this->assertStringContainsString('households have received their relief', $html);
        $this->assertStringContainsString('2', $html);
        $this->assertStringContainsString('3', $html);
        $this->assertStringContainsString('role="img"', $html, 'The bar needs a text equivalent.');
    }

    // ---------------------------------------------------------------------
    // The privacy boundary
    // ---------------------------------------------------------------------

    /**
     * The one test in this file that would matter if it broke.
     *
     * A household number is short and sequential, so any page that renders another
     * household's row - or lets one be asked for - is a page a resident can walk.
     * The progress card is the only figure here that is not about the reader, and
     * it is built from an aggregate precisely so that it cannot become a lookup.
     */
    #[Test]
    public function no_other_household_appears_anywhere_on_the_page(): void
    {
        $resident = $this->verifiedResident();
        $batch = ReliefBatch::factory()->create(['title' => 'Typhoon Relief Drive']);

        $this->pendingDistribution($resident, $batch);
        $this->distribution($resident, $batch, [
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'household_number' => 'HH-2002',
            'received_by_name' => 'NEIGHBOUR SENTINEL NAME',
        ]);

        $html = $this->page($resident);

        $this->assertStringNotContainsString('NEIGHBOUR SENTINEL NAME', $html);
        $this->assertStringNotContainsString('HH-2002', $html);

        // The reader's own household number is not printed either. It is a key
        // into other people's records; there is no reason to show it to the person
        // who already knows it.
        $this->assertStringNotContainsString('HH-1001', $html);
    }

    /**
     * And there is still no way to ask the page about somebody else.
     */
    #[Test]
    public function the_page_offers_no_household_lookup(): void
    {
        $resident = $this->verifiedResident();
        $this->pendingDistribution($resident, ReliefBatch::factory()->create());

        $html = $this->page($resident);

        $this->assertStringNotContainsString('name="household_number"', $html);
        $this->assertStringNotContainsString('Search households', $html);

        // A household number in the query string changes nothing.
        $this->actingAs($resident)
            ->get(route('resident.relief', ['household_number' => 'HH-2002']))
            ->assertOk()
            ->assertDontSee('NEIGHBOUR SENTINEL NAME');
    }

    // ---------------------------------------------------------------------
    // The three count cards are gone
    // ---------------------------------------------------------------------

    #[Test]
    public function the_page_still_has_no_household_lookup_form(): void
    {
        $resident = $this->verifiedResident();
        $this->pendingDistribution($resident, ReliefBatch::factory()->create());

        $html = $this->page($resident);

        // The chips are the only filter, and they filter the household's own rows.
        $this->assertStringContainsString('aria-label="Filter relief records"', $html);
        $this->assertSame(
            1,
            substr_count($html, 'aria-label="Filter relief records"'),
            'The page grew a second filter.',
        );
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    private function page(User $resident): string
    {
        return $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();
    }

    private function pendingDistribution(User $resident, ReliefBatch $batch): ReliefDistribution
    {
        return $this->distribution($resident, $batch, ['status' => ReliefDistribution::STATUS_PENDING]);
    }

    private function receivedDistribution(User $resident, ReliefBatch $batch): ReliefDistribution
    {
        return $this->distribution($resident, $batch, [
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Juan Dela Cruz',
            'claimed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function distribution(User $resident, ReliefBatch $batch, array $overrides = []): ReliefDistribution
    {
        return ReliefDistribution::factory()->create(array_merge([
            'relief_batch_id' => $batch->id,
            'household_number' => 'HH-1001',
            'sector_id' => $this->sector($resident)->id,
        ], $overrides));
    }

    private function verifiedResident(?User $kagawad = null): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => $this->sector($user, $kagawad)->id,
            'household_number' => 'HH-1001',
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function sector(User $user, ?User $kagawad = null): Sector
    {
        $sector = Sector::factory()->create();

        $sector->update([
            'kagawad_user_id' => ($kagawad ?? User::factory()->kagawad()->create())->id,
        ]);

        return $sector;
    }
}
