<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SectorKagawadForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sectors_table_has_a_foreign_key_on_kagawad_user_id(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('sectors'))
            ->filter(fn (array $foreignKey) => $foreignKey['columns'] === ['kagawad_user_id']);

        $this->assertCount(1, $foreignKeys, 'sectors.kagawad_user_id should have exactly one foreign key.');

        $foreignKey = $foreignKeys->first();

        $this->assertSame('users', $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertSame('set null', strtolower((string) $foreignKey['on_delete']));
    }

    public function test_kagawad_user_id_still_rejects_duplicate_assignments(): void
    {
        $kagawad = User::factory()->kagawad()->create();

        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $this->expectException(QueryException::class);

        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);
    }

    public function test_a_sector_can_be_created_without_a_kagawad(): void
    {
        $sector = Sector::factory()->create(['kagawad_user_id' => null]);

        $this->assertNull($sector->fresh()->kagawad_user_id);
    }

    public function test_deleting_a_kagawad_clears_the_sector_assignment_instead_of_orphaning_it(): void
    {
        $kagawad = User::factory()->kagawad()->create();
        $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $kagawad->delete();

        $this->assertNull($sector->fresh()->kagawad_user_id);
    }

    public function test_the_database_rejects_a_kagawad_user_id_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);

        DB::table('sectors')->insert([
            'sector_name' => 'Ghost Sector',
            'purok_name' => null,
            'kagawad_user_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_assigning_a_kagawad_updates_both_relationship_directions(): void
    {
        $secretary = User::factory()->secretary()->create();
        $kagawad = User::factory()->kagawad()->create();
        $sector = Sector::factory()->create(['kagawad_user_id' => null]);

        $this->actingAs($secretary)
            ->post(route('staff.settings.assign-kagawad'), [
                'kagawad_user_id' => $kagawad->id,
                'sector_id' => $sector->id,
            ])
            ->assertRedirect();

        $this->assertSame($sector->id, $kagawad->fresh()->assigned_sector_id);
        $this->assertTrue($kagawad->fresh()->isKagawad());
        $this->assertTrue($sector->fresh()->kagawad->is($kagawad));
        $this->assertTrue($kagawad->fresh()->reliefKagawadSectors->contains($sector));
    }
}
