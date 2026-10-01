<?php

namespace Database\Seeders;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use App\View\Components\PublicLayout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    /**
     * The barangay's puroks, in the order the Secretary reads them out.
     *
     * This is the same list, in the same order, as the migration
     * 2026_09_29_151741_replace_sector_placeholders_with_purok_names that
     * renames the design-phase "Sector 1" placeholders on a database that
     * already has resident profiles pointing at them. Both have to agree, and
     * the list is written out in each rather than shared: a migration is a
     * frozen record of what happened at one point in time and must not start
     * reading from code that can change afterwards, or a deployment that runs
     * a newer seeder would rewrite history. SectorsSeederMatchesTheMigrationTest
     * is what keeps the two copies honest.
     *
     * @var list<string>
     */
    private const PUROKS = [
        'Actividad Economia',
        'Inner 1',
        'Inner 2',
        'Inner 3A',
        'Inner 3B',
        'Inner 4A',
        'Inner 4B',
        'Inner 4C',
        'Inner 4D',
        'Kasabaan',
        'Petron',
        'INC',
        'Benesa',
        'Capitulacion',
        'Kabildatan',
        'Palawan',
        'Yamaha',
    ];

    public function run(): void
    {
        $this->seedSettings();

        // The document catalogue. Seeded before the accounts so a resident can be
        // given a request type that already exists.
        $this->call(DocumentTypeSeeder::class);

        $sectors = $this->seedSectors();

        $this->seedAccounts($sectors);
        $this->seedResidents($sectors);

        // Public notices. The seeder tops the table up to the number the
        // homepage features rather than assuming an empty announcements table.
        $this->call(AnnouncementSeeder::class);

        $this->command?->info('BDRPIS seeded successfully.');
        $this->command?->info('Demo accounts (password: "password"):');
        $this->command?->info('  admin@bdrpis.test (Admin)');
        $this->command?->info('  secretary@bdrpis.test (Secretary)');
        $this->command?->info('  kagawad@bdrpis.test (Kagawad, assigned to the first sector)');
        $this->command?->info('  resident@bdrpis.test (Resident, verified)');
        $this->command?->info('  pending@bdrpis.test (Resident, verification pending)');
    }

    /**
     * One account per staff role, plus a Kagawad wired to a real sector.
     *
     * The Kagawad is assigned rather than left floating because
     * sectors.kagawad_user_id is the authorisation anchor for the whole Kagawad
     * area: an account with no sector must see nothing, which is correct
     * behaviour but makes a poor demo. A second, unassigned Kagawad is created
     * too, so that empty state is demonstrable rather than theoretical.
     */
    private function seedAccounts(Collection $sectors): void
    {
        User::factory()->admin()->create([
            'name' => 'Barangay Admin',
            'email' => 'admin@bdrpis.test',
        ]);

        User::factory()->secretary()->create([
            'name' => 'Liza C. Natividad',
            'email' => 'secretary@bdrpis.test',
        ]);

        $kagawad = User::factory()->kagawad()->create([
            'name' => 'Ramon P. Bautista',
            'email' => 'kagawad@bdrpis.test',
        ]);

        $first = $sectors->first();

        if ($first !== null) {
            $first->update(['kagawad_user_id' => $kagawad->id]);
            $kagawad->update(['assigned_sector_id' => $first->id]);
        }

        // Deliberately unassigned, so the "no sector yet" state can be seen.
        User::factory()->kagawad()->create([
            'name' => 'Unassigned Kagawad',
            'email' => 'kagawad-unassigned@bdrpis.test',
        ]);
    }

    /**
     * Two residents, one verified and one still pending, because the whole point
     * of the resident area is that those two states behave differently.
     */
    private function seedResidents(Collection $sectors): void
    {
        $sector = $sectors->first();

        if ($sector === null) {
            return;
        }

        $verified = User::factory()->resident()->create([
            'name' => 'Ana Dela Cruz',
            'email' => 'resident@bdrpis.test',
        ]);

        ResidentProfile::create([
            'user_id' => $verified->id,
            'household_number' => 'HH-1001',
            'address' => 'Zone 1, Bangued, Abra',
            'sector_id' => $sector->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);

        $verified->update([
            'is_verified' => true,
            'assigned_sector_id' => $sector->id,
        ]);

        $pending = User::factory()->resident()->create([
            'name' => 'Juan Dela Pending',
            'email' => 'pending@bdrpis.test',
        ]);

        ResidentProfile::create([
            'user_id' => $pending->id,
            'household_number' => 'HH-1002',
            'address' => 'Pending St., Zone 1, Bangued',
            'sector_id' => $sector->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);
    }

    /**
     * The settings the public pages read.
     *
     * These are the letterhead and contact values the header, footer, contact
     * page and officials directory all print. Every one of them has a fallback
     * in PublicController, so an unseeded database still renders - the seeder
     * exists so a demo install matches the real barangay rather than the
     * fallbacks.
     */
    private function seedSettings(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1', 'header', 'Official barangay name for letterheads');
        Setting::set('barangay_address', 'Zone 1, Bangued, Abra', 'header', 'Barangay address line');
        Setting::set('office_phone', '09663320659', 'header', 'Barangay contact number');
        Setting::set('office_email', 'floria123@yahoo.com', 'header', 'Barangay email address');
        Setting::set('office_facebook', PublicLayout::FACEBOOK_PAGE, 'header', 'Official Facebook page link');
        Setting::set('punong_barangay', 'Hon. [Punong Barangay]', 'header', 'Punong Barangay name for PDF signatures');
        Setting::set('secretary_name', '[Barangay Secretary]', 'header', 'Barangay Secretary name for PDF issuance');
        Setting::set('emergency_hotline', '09663320659', 'header', 'Public emergency hotline');
    }

    /**
     * Converge the sectors table on the barangay's real puroks.
     *
     * Keyed by id rather than by name, which is what makes this safe to run
     * against a database that already holds data. Ids 1 to 17 are the same rows
     * the migration renames and inserts into, so a database that has been
     * migrated is left exactly as it is, and a database where the table is
     * empty is filled in the intended order. Nothing is ever deleted: a
     * resident profile holds a foreign key to its sector, so removing a row
     * would either cascade the resident away or leave them pointing at nothing.
     *
     * purok_name is left null. It is a genuinely separate thing - a sub-purok
     * inside a cluster - and the barangay has not supplied that subdivision.
     * Copying the sector name into it would make the public directory print the
     * same word twice, and the view already falls back cleanly to "not set".
     *
     * @return Collection<int, Sector>
     */
    private function seedSectors(): Collection
    {
        return collect(self::PUROKS)->values()->map(function (string $name, int $index): Sector {
            $sector = Sector::find($index + 1);

            if ($sector === null) {
                return Sector::create(['sector_name' => $name]);
            }

            if ($sector->sector_name !== $name) {
                $sector->update(['sector_name' => $name]);
            }

            return $sector;
        });
    }
}
