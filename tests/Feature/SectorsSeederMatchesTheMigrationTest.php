<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeder and the sector migration have to leave a database in the same
 * state, and they each carry their own copy of the purok list.
 *
 * A migration is a frozen record of a moment in time and must not read from
 * code that can change after it shipped - otherwise deploying a newer seeder
 * silently rewrites what a past migration did. That leaves two written-out
 * copies of the same seventeen names, which is exactly the kind of duplication
 * that drifts, so this is the test that holds them together.
 */
class SectorsSeederMatchesTheMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_leaves_the_seventeen_puroks_in_order(): void
    {
        $this->assertSame($this->expectedPuroks(), $this->sectorNames());
    }

    public function test_the_seeder_reaches_the_same_state_the_migration_does(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            $this->expectedPuroks(),
            $this->sectorNames(),
            'The seeder does not produce the sectors the migration produces.',
        );
    }

    public function test_seeding_after_the_migration_does_not_add_a_second_set(): void
    {
        // The defect this guards: the seeder used to create four rows named
        // "Sector 1" to "Sector 4" unconditionally, so running it against an
        // already-migrated database appended four more rows and the
        // registration dropdown offered the placeholders again alongside the
        // real puroks.
        $before = Sector::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            $before,
            Sector::count(),
            'Re-seeding added sectors on top of the ones the migration had already renamed.',
        );
        $this->assertSame($this->expectedPuroks(), $this->sectorNames());
    }

    public function test_the_seeder_never_reintroduces_the_placeholder_names(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['Sector 1', 'Sector 2', 'Sector 3', 'Sector 4'] as $placeholder) {
            $this->assertFalse(
                Sector::where('sector_name', $placeholder)->exists(),
                "The seeder reintroduced the design-phase placeholder \"{$placeholder}\".",
            );
        }
    }

    public function test_seeding_converges_even_when_a_sector_has_been_renamed_by_hand(): void
    {
        Sector::find(1)->update(['sector_name' => 'Sector 1']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($this->expectedPuroks(), $this->sectorNames());
    }

    public function test_seeding_keeps_residents_attached_to_their_purok(): void
    {
        // The whole reason the sectors are keyed by id: a profile holds a
        // foreign key, so converging the table must never delete a row.
        $kagawad = User::factory()->kagawad()->create();
        $profile = ResidentProfile::factory()->create(['sector_id' => 1]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, $profile->fresh()->sector_id);
        $this->assertSame(
            'Actividad Economia',
            Sector::find($profile->fresh()->sector_id)->sector_name,
            'A resident profile lost the purok it was recorded against.',
        );
    }

    public function test_the_kagawad_relationship_resolves_from_the_public_directory_side(): void
    {
        // The public officials directory reads Sector::kagawad() for a guest with
        // no session at all, so the relationship has to resolve on a sector that
        // was never touched by the seeder's own assignment.
        $this->seed(DatabaseSeeder::class);

        $sector = Sector::orderBy('id')->firstOrFail();
        $kagawad = User::factory()->kagawad()->create();

        $sector->update(['kagawad_user_id' => $kagawad->id]);
        $kagawad->update(['assigned_sector_id' => $sector->id]);

        $this->assertTrue($sector->fresh()->kagawad->is($kagawad));
        $this->assertTrue($kagawad->fresh()->reliefKagawadSectors->contains($sector));
        $this->assertTrue($kagawad->fresh()->isKagawad());
    }

    public function test_the_seeder_wires_one_kagawad_to_a_real_sector(): void
    {
        // sectors.kagawad_user_id is the authorisation anchor for the Kagawad
        // area, so a seeded Kagawad with no sector would demonstrate nothing: the
        // correct behaviour for an unassigned Kagawad is to see nothing, so a
        // demo account that showed an empty screen would look like a bug.
        //
        // Exactly one sector is appointed, not all seventeen, and both directions
        // of the link agree - a Kagawad whose sector does not point back at them
        // would be able to see a sector the officials directory attributes to
        // somebody else.
        $this->seed(DatabaseSeeder::class);

        $appointed = Sector::whereNotNull('kagawad_user_id')->get();

        $this->assertCount(1, $appointed, 'The demo should appoint exactly one kagawad, not one per purok.');

        $sector = $appointed->first();
        $kagawad = $sector->kagawad;

        $this->assertNotNull($kagawad, 'The appointed sector has no kagawad on the far side of the relation.');
        $this->assertTrue($kagawad->isKagawad());
        $this->assertSame($sector->id, $kagawad->assigned_sector_id, 'The two halves of the kagawad link disagree.');
        $this->assertTrue($kagawad->reliefKagawadSectors->contains($sector));
        $this->assertSame([$sector->id], $kagawad->assignedSectorIds());
    }

    public function test_the_seeder_also_leaves_one_kagawad_unassigned(): void
    {
        // The counterpart above. An unassigned Kagawad is a real state the area
        // has to handle, so the demo carries one rather than leaving that path
        // undemonstrated.
        $this->seed(DatabaseSeeder::class);

        $unassigned = User::role(User::ROLE_KAGAWAD)
            ->where(fn ($query) => $query->whereNull('assigned_sector_id'))
            ->get();

        $this->assertNotEmpty($unassigned, 'The demo should include a kagawad with no sector.');

        foreach ($unassigned as $kagawad) {
            $this->assertSame([], $kagawad->assignedSectorIds());
            $this->assertFalse($kagawad->hasAssignedSector());
        }
    }

    public function test_the_seeder_leaves_purok_name_unset_rather_than_repeating_the_sector_name(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            Sector::whereNull('purok_name')->exists(),
            'purok_name is a separate thing from the sector name and should not be filled with a copy of it.',
        );
    }

    /**
     * The seventeen boundaries, as supplied by the barangay.
     *
     * @return list<string>
     */
    private function expectedPuroks(): array
    {
        return [
            'Actividad Economia', 'Inner 1', 'Inner 2', 'Inner 3A', 'Inner 3B',
            'Inner 4A', 'Inner 4B', 'Inner 4C', 'Inner 4D', 'Kasabaan', 'Petron',
            'INC', 'Benesa', 'Capitulacion', 'Kabildatan', 'Palawan', 'Yamaha',
        ];
    }

    /**
     * @return list<string>
     */
    private function sectorNames(): array
    {
        return Sector::orderBy('id')->pluck('sector_name')->all();
    }
}
