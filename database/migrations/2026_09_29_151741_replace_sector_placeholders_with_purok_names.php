<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The barangay's actual puroks, in the order the Secretary reads them out.
     *
     * The table previously held four rows reading "Sector 1" through "Sector 4",
     * which were placeholders from the design phase rather than real boundaries.
     *
     * These rows are RENAMED rather than deleted and reinserted. Twenty-five
     * resident_profiles hold a foreign key into this table, so dropping the rows
     * would either cascade away those residents or leave them pointing at
     * nothing. Renaming keeps every existing profile attached to the same physical
     * part of the barangay, and only the label it displays changes.
     *
     * The four placeholders are mapped onto the first four entries in order, then
     * the remainder are inserted. Because the ids therefore run 1..17 in exactly
     * the order above, the sector dropdowns order by id to reproduce that list
     * rather than sorting it alphabetically - "Kasabaan, Petron, INC, Benesa" is
     * the sequence the barangay uses, and an alphabetical sort would not be it.
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

    public function up(): void
    {
        $existing = DB::table('sectors')->orderBy('id')->pluck('id');

        foreach ($existing as $index => $id) {
            if (! isset(self::PUROKS[$index])) {
                continue;
            }

            DB::table('sectors')->where('id', $id)->update(['sector_name' => self::PUROKS[$index]]);
        }

        $alreadyNamed = array_slice(self::PUROKS, 0, $existing->count());

        $toInsert = array_values(array_diff(self::PUROKS, $alreadyNamed));

        if ($toInsert !== []) {
            DB::table('sectors')->insert(array_map(
                fn (string $name): array => ['sector_name' => $name],
                $toInsert,
            ));
        }
    }

    public function down(): void
    {
        // Only the names this migration introduced are undone. Rows created
        // after the migration ran are left alone rather than deleted, because a
        // profile may already be holding a foreign key to one of them.
        $restored = ['Sector 1', 'Sector 2', 'Sector 3', 'Sector 4'];

        foreach (DB::table('sectors')->orderBy('id')->limit(count($restored))->pluck('id') as $index => $id) {
            DB::table('sectors')->where('id', $id)->update(['sector_name' => $restored[$index]]);
        }

        DB::table('sectors')
            ->whereNotIn('id', DB::table('sectors')->orderBy('id')->limit(count($restored))->pluck('id'))
            ->whereIn('sector_name', array_slice(self::PUROKS, count($restored)))
            ->delete();
    }
};
