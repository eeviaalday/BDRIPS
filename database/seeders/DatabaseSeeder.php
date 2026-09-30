<?php

namespace Database\Seeders;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use App\View\Components\PublicLayout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1', 'header', 'Official barangay name for letterheads');
        Setting::set('barangay_address', 'Zone 1, Bangued, Abra', 'header', 'Barangay address line');
        Setting::set('office_phone', '09663320659', 'header', 'Barangay contact number');
        Setting::set('office_email', 'floria123@yahoo.com', 'header', 'Barangay email address');
        Setting::set('office_facebook', PublicLayout::FACEBOOK_PAGE, 'header', 'Official Facebook page link');
        Setting::set('punong_barangay', 'Hon. [Punong Barangay]', 'header', 'Punong Barangay name for PDF signatures');
        Setting::set('secretary_name', '[Barangay Secretary]', 'header', 'Barangay Secretary name for PDF issuance');
        Setting::set('emergency_hotline', '09663320659', 'header', 'Public emergency hotline');
        Setting::set('clearance_fee', '100', 'fees', 'Barangay Clearance fee (PHP)');
        Setting::set('indigency_fee', '0', 'fees', 'Certificate of Indigency fee (PHP)');
        Setting::set('jobseeker_fee', '50', 'fees', 'First-Time Jobseeker Certificate fee (PHP)');
        Setting::set('residency_fee', '100', 'fees', 'Residency Certificate fee (PHP)');

        $admin = User::factory()->admin()->create([
            'name' => 'Barangay Admin',
            'email' => 'admin@bdrpis.test',
        ]);

        $secretary = User::factory()->secretary()->create([
            'name' => 'Liza C. Natividad',
            'email' => 'secretary@bdrpis.test',
        ]);

        $sectors = $this->seedSectors();

        $kagawadUsers = collect();
        foreach ($sectors as $index => $sector) {
            // One kagawad per purok, preserving the "every sector has one"
            // coverage the four-placeholder dataset had. An existing assignment
            // is left alone, so re-seeding does not orphan a kagawad who is
            // already linked to a sector.
            $kagawad = $sector->kagawad ?? User::factory()->kagawad()->create([
                'name' => 'Kag. '.fake()->lastName(),
                'email' => 'kagawad'.($index + 1).'@bdrpis.test',
            ]);

            if ($sector->kagawad_user_id === null) {
                $sector->update(['kagawad_user_id' => $kagawad->id]);
                $kagawad->update(['assigned_sector_id' => $sector->id]);
            }

            $kagawadUsers->push($kagawad);
        }

        $residents = collect();
        $residentUsers = collect();

        foreach (range(1, 24) as $i) {
            $sector = $sectors->random();
            $user = User::factory()->resident()->create([
                'name' => fake()->firstName().' '.fake()->lastName(),
                'email' => 'resident'.$i.'@bdrpis.test',
            ]);
            $household = 'HH-'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);

            $photoPath = $this->generatePlaceholderId($user->name, $household);

            $profile = ResidentProfile::create([
                'user_id' => $user->id,
                'household_number' => $household,
                'national_or_voter_id' => fake()->unique()->numerify('64####-######'),
                'address' => fake()->streetAddress().', Zone 1, Bangued, Abra',
                'sector_id' => $sector->id,
                'id_photo_path' => $photoPath,
                'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
                'verified_at' => now(),
            ]);

            $user->update(['is_verified' => true, 'assigned_sector_id' => $sector->id]);

            $residents->push($profile);
            $residentUsers->push($user);
        }

        // A few residents pending or rejected verification.
        $pendingUser = User::factory()->resident()->create(['email' => 'unverified@bdrpis.test', 'name' => 'Juan Dela Pending']);
        ResidentProfile::create([
            'user_id' => $pendingUser->id,
            'household_number' => 'HH-9001',
            'national_or_voter_id' => '64####-999999',
            'address' => 'Pending St., Zone 1, Bangued',
            'sector_id' => $sectors->first()->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);

        // Document requests in various states.
        foreach (range(1, 8) as $i) {
            DocumentRequest::create([
                'user_id' => $residentUsers->random()->id,
                'reference_number' => DocumentRequest::generateReferenceNumber(),
                'document_type' => collect([
                    DocumentRequest::TYPE_CLEARANCE,
                    DocumentRequest::TYPE_INDIGENCY,
                    DocumentRequest::TYPE_JOBSEEKER,
                    DocumentRequest::TYPE_RESIDENCY,
                ])->random(),
                'purpose' => fake()->randomElement([
                    'Employment requirement',
                    'Bank account opening',
                    'School enrollment',
                    'Government program application',
                    'Local employment abroad application',
                ]),
                'status' => fake()->randomElement([
                    DocumentRequest::STATUS_SUBMITTED,
                    DocumentRequest::STATUS_UNDER_REVIEW,
                    DocumentRequest::STATUS_READY,
                    DocumentRequest::STATUS_COMPLETED,
                ]),
                'ready_at' => now()->subDays(random_int(1, 5)),
                'created_at' => now()->subDays(random_int(1, 14)),
            ]);
        }

        // Relief batch mapped to all verified households.
        $batch = ReliefBatch::create([
            'title' => 'Typhoon Relief Drive - Sept '.now()->year,
            'description' => 'Family food packs for households affected by the recent typhoon.',
            'items_included' => ['5kg Rice', '6 Canned Goods', '3 Instant Noodles', 'Coffee Sachets'],
            'distribution_date' => now()->addDays(3)->toDateString(),
            'status' => ReliefBatch::STATUS_ACTIVE,
            'created_by_user_id' => $secretary->id,
        ]);

        foreach ($residents as $profile) {
            ReliefDistribution::create([
                'relief_batch_id' => $batch->id,
                'household_number' => $profile->household_number,
                'sector_id' => $profile->sector_id,
                'status' => fake()->randomElement([
                    ReliefDistribution::STATUS_RECEIVED,
                    ReliefDistribution::STATUS_PENDING,
                ]),
                'received_by_name' => fake()->boolean(50) ? fake()->name() : null,
                'claimed_at' => fake()->boolean(50) ? now()->subHour() : null,
                'logged_by_user_id' => fake()->boolean(50) ? $kagawadUsers->random()->id : null,
            ]);
        }

        ReliefBatch::create([
            'title' => 'Post-Flood Rice Assistance',
            'description' => '20kg rice packs for affected households.',
            'items_included' => ['20kg Rice'],
            'distribution_date' => now()->addMonth(),
            'status' => ReliefBatch::STATUS_ACTIVE,
            'created_by_user_id' => $secretary->id,
        ]);

        // Public notices. The seeder tops the table up to the number the
        // homepage features rather than assuming an empty announcements table.
        $this->call(AnnouncementSeeder::class);

        $this->command?->info('BDRPIS seeded successfully.');
        $this->command?->info('Demo accounts (password: "password"):');
        $this->command?->info('  admin@bdrpis.test (Admin)');
        $this->command?->info('  secretary@bdrpis.test (Secretary)');
        $this->command?->info('  kagawad1@bdrpis.test (Kagawad)');
        $this->command?->info('  ...through kagawad'.count(self::PUROKS).'@bdrpis.test, one per purok');
        $this->command?->info('  resident1@bdrpis.test (Resident)');
        $this->command?->info('  unverified@bdrpis.test (Pending resident)');
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
     * Copying the sector name into it would make the kagawad checklist and the
     * public directory print the same word twice, and the views already fall
     * back cleanly to "not set".
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

    private function generatePlaceholderId(string $name, string $household): ?string
    {
        try {
            $img = imagecreatetruecolor(400, 240);
            $bg = imagecolorallocate($img, 214, 240, 219);
            imagefill($img, 0, 0, $bg);
            $dark = imagecolorallocate($img, 22, 101, 52);
            $text = imagecolorallocate($img, 21, 128, 61);
            imagestring($img, 5, 20, 20, 'BARANGY ID (DEMO)', $dark);
            // Truncate overflowing names.
            $shortName = mb_substr($name, 0, 28);
            imagestring($img, 5, 20, 80, $shortName, $dark);
            imagestring($img, 4, 20, 120, $household, $text);
            imagestring($img, 4, 20, 150, 'Linasin, Bangued, Abra', $text);

            $path = 'id-photos/demo-'.uniqid().'.png';
            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);
            Storage::disk('local')->put($path, $png);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
