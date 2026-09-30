<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Kagawad's relief checklist, and the sector split that holds it up.
 *
 * A Kagawad is the only role whose access is a data filter rather than a
 * yes/no, so most of this file is about what a Kagawad must NOT be able to see
 * or touch. The scope lives in the controller and the forSectors() scope; these
 * tests are what stops either of those quietly widening.
 */
class KagawadReliefTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The sector split
    // ---------------------------------------------------------------------

    #[Test]
    public function a_kagawad_sees_only_the_households_in_their_own_sectors(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $theirs = $this->kagawadWithSector('Purok 2');

        $batch = $this->newBatchWithHouseholds('Shared batch', [
            $mine['sector']->id => ['MINE-01', 'MINE-02'],
            $theirs['sector']->id => ['THEIRS-01'],
        ]);

        $content = $this->actingAs($mine['kagawad'])
            ->get(route('kagawad.relief.show', $batch))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('MINE-01', $content);
        $this->assertStringContainsString('MINE-02', $content);
        $this->assertStringNotContainsString(
            'THEIRS-01',
            $content,
            'A Kagawad must not see another sector household number.'
        );
    }

    #[Test]
    public function the_counts_on_a_batch_cover_only_my_sectors(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $theirs = $this->kagawadWithSector('Purok 2');

        $batch = $this->newBatchWithHouseholds('Mixed', [
            $mine['sector']->id => ['MINE-01', 'MINE-02'],
            $theirs['sector']->id => ['THEIRS-01'],
        ]);

        $batch->distributions()->where('household_number', 'MINE-01')->update([
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);

        $this->actingAs($mine['kagawad'])->get(route('kagawad.relief'))->assertOk();

        // 2 of mine, 1 of theirs. The totals must read 2, not 3.
        $listed = $this->actingAs($mine['kagawad'])
            ->get(route('kagawad.relief'))
            ->getContent();

        $this->assertStringContainsString('Mixed', $listed);
        $this->assertDatabaseCount('relief_distributions', 3);
    }

    /**
     * The check on the listing is on the numbers, not the string, because a
     * barangay-wide count here would tell a Kagawad the size of the next
     * purok's list - the one number the sector split exists to keep private.
     */
    #[Test]
    public function the_listing_progress_is_my_share_not_the_barangay_share(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $theirs = $this->kagawadWithSector('Purok 2');

        $batch = $this->newBatchWithHouseholds('Three total', [
            $mine['sector']->id => ['MINE-01'],
            $theirs['sector']->id => ['THEIRS-01', 'THEIRS-02'],
        ]);

        $rows = $this->actingAs($mine['kagawad'])
            ->get(route('kagawad.relief'))
            ->assertOk();

        // Read the row out of the response rather than re-querying, so this
        // asserts what the page actually shows.
        $rows->assertSee('Three total');
        $this->assertSame(
            1,
            ReliefBatch::query()->find($batch->id)->distributions()
                ->forSectors($mine['kagawad']->assignedSectorIds())
                ->count(),
            'Only one of those three households is this Kagawad responsibility.'
        );
    }

    // ---------------------------------------------------------------------
    // Marking a household received
    // ---------------------------------------------------------------------

    #[Test]
    public function a_kagawad_can_mark_a_household_collected(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Open batch', [$mine['sector']->id => ['MINE-01']]);
        $row = $batch->distributions()->where('household_number', 'MINE-01')->firstOrFail();

        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$batch, $row->id]), [
                'received_by_name' => 'Maria Santos',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();

        $this->assertSame(ReliefDistribution::STATUS_RECEIVED, $row->status);
        $this->assertSame('Maria Santos', $row->received_by_name);
        $this->assertSame($mine['kagawad']->id, $row->logged_by_user_id);
        $this->assertNotNull($row->claimed_at);
    }

    /**
     * The name defaults to the Kagawad, because a row with no name in it is
     * worse than one with a name that happens to be the officer.
     */
    #[Test]
    public function the_name_falls_back_to_the_kagawad(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Open batch', [$mine['sector']->id => ['MINE-01']]);
        $row = $batch->distributions()->where('household_number', 'MINE-01')->firstOrFail();

        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$batch, $row->id]))
            ->assertRedirect();

        $this->assertSame($mine['kagawad']->name, $row->fresh()->received_by_name);
    }

    #[Test]
    public function marking_collected_is_audited(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Open batch', [$mine['sector']->id => ['MINE-01']]);
        $row = $batch->distributions()->where('household_number', 'MINE-01')->firstOrFail();

        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$batch, $row->id]));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $mine['kagawad']->id,
            'action' => 'relief.received',
            'subject_id' => $row->id,
        ]);
    }

    /**
     * A double click must not overwrite the name of whoever actually signed.
     * The second attempt is a message, not a second write.
     */
    #[Test]
    public function a_second_mark_does_not_overwrite_the_first(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Open batch', [$mine['sector']->id => ['MINE-01']]);
        $row = $batch->distributions()->where('household_number', 'MINE-01')->firstOrFail();

        $this->actingAs($mine['kagawad'])->post(route('kagawad.relief.received', [$batch, $row->id]), [
            'received_by_name' => 'Maria Santos',
        ]);

        $claimedAt = $row->fresh()->claimed_at;

        $this->actingAs($mine['kagawad'])->post(route('kagawad.relief.received', [$batch, $row->id]), [
            'received_by_name' => 'Someone Else',
        ])->assertSessionHas('error');

        $row->refresh();

        $this->assertSame('Maria Santos', $row->received_by_name);
        $this->assertTrue($row->claimed_at->equalTo($claimedAt), 'The original time must stand too.');
    }

    // ---------------------------------------------------------------------
    // The boundaries
    // ---------------------------------------------------------------------

    /**
     * Another sector's row is a 404 rather than a 403. A 403 would confirm the
     * row exists, which turns guessing ids into a way of learning who lives in
     * the next purok.
     */
    #[Test]
    public function another_sectors_household_is_a_404_not_a_403(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $theirs = $this->kagawadWithSector('Purok 2');

        $batch = $this->newBatchWithHouseholds('Shared batch', [
            $mine['sector']->id => ['MINE-01'],
            $theirs['sector']->id => ['THEIRS-01'],
        ]);
        $foreign = $batch->distributions()->where('household_number', 'THEIRS-01')->firstOrFail();

        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$batch, $foreign->id]))
            ->assertNotFound();

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $foreign->fresh()->status);
    }

    #[Test]
    public function a_row_from_a_different_batch_is_a_404(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $first = $this->newBatchWithHouseholds('First', [$mine['sector']->id => ['MINE-01']]);
        $second = $this->newBatchWithHouseholds('Second', [$mine['sector']->id => ['MINE-02']]);
        $row = $first->distributions()->where('household_number', 'MINE-01')->firstOrFail();

        // My sector, but not this batch. Accepting it would put a collection in a
        // handout the household was not on.
        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$second, $row->id]))
            ->assertNotFound();

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $row->fresh()->status);
    }

    #[Test]
    public function a_closed_batch_accepts_nothing(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Closed', [$mine['sector']->id => ['MINE-01']]);
        $batch->update(['status' => ReliefBatch::STATUS_CLOSED, 'closed_at' => now()]);
        $row = $batch->distributions()->firstOrFail();

        $this->actingAs($mine['kagawad'])
            ->post(route('kagawad.relief.received', [$batch, $row->id]))
            ->assertStatus(409);

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $row->fresh()->status);
    }

    #[Test]
    #[DataProvider('nonKagawadProvider')]
    public function no_other_role_reaches_the_kagawad_area(string $role): void
    {
        $batch = $this->newBatchWithHouseholds('Off limits', []);

        $this->actingAs($this->userFor($role))
            ->get(route('kagawad.relief'))
            ->assertForbidden();

        $this->actingAs($this->userFor($role))
            ->get(route('kagawad.relief.show', $batch))
            ->assertForbidden();
    }

    #[Test]
    #[DataProvider('nonKagawadProvider')]
    public function no_other_role_can_mark_a_household_collected(string $role): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Open batch', [$mine['sector']->id => ['MINE-01']]);
        $row = $batch->distributions()->firstOrFail();

        $this->actingAs($this->userFor($role))
            ->post(route('kagawad.relief.received', [$batch, $row->id]))
            ->assertForbidden();

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $row->fresh()->status);
    }

    #[Test]
    public function a_guest_cannot_reach_the_kagawad_area(): void
    {
        $this->get(route('kagawad.relief'))->assertRedirect(route('login'));
    }

    /**
     * A Kagawad with no sector gets an empty list, never every sector. That is
     * the difference between "not assigned yet" and "everything is visible", and
     * the page has to be able to tell the operator which one it is.
     */
    #[Test]
    public function an_unassigned_kagawad_sees_nothing_rather_than_everything(): void
    {
        $theirs = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Not yours', [$theirs['sector']->id => ['THEIRS-01']]);

        $unassigned = $this->kagawad();

        $content = $this->actingAs($unassigned)
            ->get(route('kagawad.relief.show', $batch))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('THEIRS-01', $content);
        $this->assertStringContainsString('nothing', strtolower($content));
    }

    /**
     * sectors.kagawad_user_id is UNIQUE, so a Kagawad holds at most one sector.
     *
     * assignedSectorIds() and forSectors() are written for a list anyway. That is
     * deliberate: the set of sectors is resolved once and only the ids travel, so
     * a second sector would be handled by the same code path rather than needing
     * a second one. This test pins the rule, so the day the unique index is
     * dropped that change is a decision rather than an accident.
     */
    #[Test]
    public function a_kagawad_holds_at_most_one_sector(): void
    {
        $kagawad = $this->kagawad();
        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $this->assertCount(1, $kagawad->assignedSectorIds());
        $this->assertTrue($kagawad->fresh()->hasAssignedSector());

        $this->expectException(UniqueConstraintViolationException::class);

        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);
    }

    /**
     * "You have a sector but nothing is going out" is a different message from
     * "you have no sector", and conflating them would send the operator to the
     * wrong fix, so the page has to tell the two apart.
     */
    #[Test]
    public function a_kagawad_with_a_sector_sees_an_empty_batch_cleanly(): void
    {
        $mine = $this->kagawadWithSector('Purok 1');
        $batch = $this->newBatchWithHouseholds('Nothing for me', []);

        $this->actingAs($mine['kagawad'])
            ->get(route('kagawad.relief.show', $batch))
            ->assertOk()
            ->assertSee('nothing');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonKagawadProvider(): array
    {
        return [
            'resident' => ['resident'],
            'secretary' => ['secretary'],
            'admin' => ['admin'],
        ];
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function kagawad(): User
    {
        return User::factory()->kagawad()->create(['is_verified' => true]);
    }

    /**
     * @return array{kagawad: User, sector: Sector}
     */
    private function kagawadWithSector(string $name): array
    {
        $kagawad = $this->kagawad();
        $sector = Sector::factory()->create([
            'kagawad_user_id' => $kagawad->id,
            'sector_name' => $name,
        ]);

        return ['kagawad' => $kagawad, 'sector' => $sector];
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

        return $user;
    }

    /**
     * A batch with one distribution row per household number, grouped by sector.
     *
     * @param  array<int, list<string>>  $householdsBySector
     */
    private function newBatchWithHouseholds(string $title, array $householdsBySector): ReliefBatch
    {
        $batch = ReliefBatch::factory()->create([
            'title' => $title,
            'status' => ReliefBatch::STATUS_ACTIVE,
        ]);

        foreach ($householdsBySector as $sectorId => $numbers) {
            foreach ($numbers as $number) {
                ReliefDistribution::factory()->create([
                    'relief_batch_id' => $batch->id,
                    'sector_id' => $sectorId,
                    'household_number' => $number,
                    'status' => ReliefDistribution::STATUS_PENDING,
                ]);
            }
        }

        return $batch->fresh();
    }
}
