<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Relief batches, driven from the staff side.
 *
 * A distribution row is keyed by household number and sector rather than by
 * resident profile, so these tests assert on household numbers. That is
 * deliberate: the checklist has to survive a resident deleting their account
 * without losing the record of who was given goods.
 *
 * Opening a batch is the one action that silently produces nothing if it is
 * given the wrong input, so the sector handling gets the most attention here.
 */
class ReliefBatchesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function opening_a_batch_collects_the_verified_households_of_its_sectors(): void
    {
        $sector = $this->sectorWithHouseholds('Purok 1', 2);
        $other = $this->sectorWithHouseholds('Purok 2', 1);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.store'), [
                'title' => 'Typhoon Relief Drive',
                'description' => 'Goods for the affected households.',
                'items_included' => "Rice 5kg\n\nCanned goods",
                'distribution_date' => now()->addWeek()->toDateString(),
                'sector_ids' => [$sector->id],
            ])
            ->assertRedirect();

        $batch = ReliefBatch::query()->latest('id')->firstOrFail();

        $this->assertSame(ReliefBatch::STATUS_ACTIVE, $batch->status);
        $this->assertSame(['Rice 5kg', 'Canned goods'], $batch->items(), 'Blank lines must not become a blank item.');
        $this->assertEqualsCanonicalizing(
            $sector->residents->pluck('household_number')->all(),
            $batch->distributions()->pluck('household_number')->all()
        );
        $this->assertSame(0, $batch->distributions()
            ->whereIn('household_number', $other->residents->pluck('household_number'))
            ->count());
    }

    /**
     * No ticks is not "nobody": it is the whole barangay. Reading an empty array
     * as an empty scope would quietly create a batch that covers nothing while
     * looking like a working distribution.
     */
    #[Test]
    public function an_unticked_sector_list_covers_the_whole_barangay(): void
    {
        $first = $this->sectorWithHouseholds('Purok 1', 1);
        $second = $this->sectorWithHouseholds('Purok 2', 1);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.store'), ['title' => 'Barangay-wide'])
            ->assertRedirect();

        $batch = ReliefBatch::query()->latest('id')->firstOrFail();
        $expected = $first->residents->pluck('household_number')
            ->merge($second->residents->pluck('household_number'));

        $this->assertEqualsCanonicalizing(
            $expected->all(),
            $batch->distributions()->pluck('household_number')->all()
        );
    }

    /**
     * Pending households are not on the list. Somebody who has not been verified
     * should not be receiving goods, and a batch is how goods are handed out.
     */
    #[Test]
    public function an_unverified_household_is_not_added(): void
    {
        $sector = Sector::factory()->create();
        $user = User::factory()->resident()->create(['is_verified' => false]);
        $pending = ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.store'), [
                'title' => 'Only verified',
                'sector_ids' => [$sector->id],
            ])
            ->assertRedirect();

        $batch = ReliefBatch::query()->latest('id')->firstOrFail();

        $this->assertSame(0, $batch->distributions()
            ->where('household_number', $pending->household_number)
            ->count());
    }

    /**
     * Within one batch a household number appears once.
     *
     * household_number is not unique on the profile, so two verified profiles in
     * different sectors can carry the same number, and seeding both would violate
     * the compound unique on (relief_batch_id, household_number). The insert
     * ignores the collision: the alternative is a duplicate-key error that rolls
     * the whole batch back, so one duplicated number would cost every other
     * household on the list their goods.
     */
    #[Test]
    public function a_duplicate_household_number_does_not_roll_back_the_whole_batch(): void
    {
        $first = $this->sectorWithHouseholds('Purok 1', 0);
        $second = $this->sectorWithHouseholds('Purok 2', 0);

        foreach ([$first, $second] as $sector) {
            $user = User::factory()->resident()->create(['is_verified' => true]);

            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => $sector->id,
                'household_number' => 'SHARED-01',
            ]);
        }

        // Plus an ordinary household, so the batch is not trivially empty.
        $this->sectorWithHouseholds('Purok 3', 1);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.store'), [
                'title' => 'Duplicate numbers',
                'sector_ids' => [$first->id, $second->id, Sector::query()->where('sector_name', 'Purok 3')->value('id')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $batch = ReliefBatch::query()->latest('id')->firstOrFail();
        $numbers = $batch->distributions()->pluck('household_number');

        $this->assertSame(1, $numbers->filter(fn (string $n) => $n === 'SHARED-01')->count());
        $this->assertSame(2, $batch->distributions()->count(), 'The unrelated household must still be on the list.');
    }

    /**
     * Across batches a household may reappear, because a later drive is a
     * separate handout. The per-batch unique says nothing about history, so this
     * test pins that boundary deliberately rather than by accident.
     */
    #[Test]
    public function a_household_can_appear_in_a_later_batch(): void
    {
        $sector = $this->sectorWithHouseholds('Purok 1', 1);
        $household = $sector->residents->first()->household_number;

        $this->newBatch('First', [$sector->id]);
        $second = $this->newBatch('Second', [$sector->id]);

        $this->assertSame(1, $second->distributions()
            ->where('household_number', $household)
            ->count());
    }

    #[Test]
    public function closing_a_batch_records_when_and_by_whom(): void
    {
        $secretary = $this->staff();
        $batch = $this->newBatch('Closing time', []);

        $this->actingAs($secretary)
            ->post(route('staff.batches.close', $batch))
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(ReliefBatch::STATUS_CLOSED, $batch->status);
        $this->assertNotNull($batch->closed_at);
    }

    /**
     * Closing twice is a 409 rather than a second silent success, because the
     * second click means the screen was stale and the operator believes
     * something happened that did not.
     */
    #[Test]
    public function closing_a_closed_batch_is_refused(): void
    {
        $batch = $this->newBatch('Already closed', [], ReliefBatch::STATUS_CLOSED);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.close', $batch))
            ->assertStatus(409);

        $this->assertSame(ReliefBatch::STATUS_CLOSED, $batch->fresh()->status);
    }

    #[Test]
    public function reopening_an_open_batch_is_refused(): void
    {
        $batch = $this->newBatch('Already open', []);

        $this->actingAs($this->staff())
            ->post(route('staff.batches.reopen', $batch))
            ->assertStatus(409);

        $this->assertSame(ReliefBatch::STATUS_ACTIVE, $batch->fresh()->status);
    }

    #[Test]
    public function the_progress_figures_add_up(): void
    {
        $sector = $this->sectorWithHouseholds('Purok 1', 3);
        $batch = $this->newBatch('Progress', [$sector->id]);

        $batch->distributions()->first()->update(['status' => ReliefDistribution::STATUS_RECEIVED]);

        $this->actingAs($this->staff())
            ->get(route('staff.batches.show', $batch))
            ->assertOk()
            ->assertSee('Purok 1');

        $progress = $batch->progress();

        $this->assertSame(3, $progress['total']);
        $this->assertSame(1, $progress['received']);
        $this->assertSame(2, $progress['pending']);
    }

    /**
     * Progress is read from the checklist, not from the households that still
     * exist, so a removed resident does not retroactively change what was
     * recorded as handed out.
     */
    #[Test]
    public function progress_ignores_households_that_are_no_longer_verified(): void
    {
        $sector = $this->sectorWithHouseholds('Purok 1', 2);
        $batch = $this->newBatch('Snapshot', [$sector->id]);

        $sector->residents->each(fn (ResidentProfile $profile) => $profile->update([
            'verification_status' => ResidentProfile::VERIFICATION_REJECTED,
        ]));

        $this->assertSame(2, $batch->fresh()->progress()['total']);
    }

    #[Test]
    public function opening_closing_and_reopening_a_batch_are_all_audited(): void
    {
        $secretary = $this->staff();
        $sector = $this->sectorWithHouseholds('Purok 1', 1);

        $this->actingAs($secretary)
            ->post(route('staff.batches.store'), ['title' => 'Audited', 'sector_ids' => [$sector->id]])
            ->assertRedirect();

        $batch = ReliefBatch::query()->latest('id')->firstOrFail();

        $this->actingAs($secretary)->post(route('staff.batches.close', $batch))->assertRedirect();
        $this->actingAs($secretary)->post(route('staff.batches.reopen', $batch))->assertRedirect();

        foreach (['relief.batch_created', 'relief.batch_closed', 'relief.batch_reopened'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $secretary->id,
                'action' => $action,
            ]);
        }
    }

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function a_non_staff_member_cannot_open_or_close_a_batch(string $role): void
    {
        $batch = $this->newBatch('Off limits', []);
        $user = $this->userFor($role);

        $this->actingAs($user)
            ->post(route('staff.batches.store'), ['title' => 'Sneaky'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('staff.batches.close', $batch))
            ->assertForbidden();

        $this->assertSame(ReliefBatch::STATUS_ACTIVE, $batch->fresh()->status);
        $this->assertNull(ReliefBatch::query()->where('title', 'Sneaky')->first());
    }

    /**
     * A batch is the record of who was given what, so a deletion would leave a
     * Kagawad holding a batch id that resolves to nothing.
     */
    #[Test]
    public function there_is_no_route_that_deletes_a_batch(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_starts_with($route->uri(), 'staff/batches')
                    && in_array('DELETE', $route->methods(), true)
            ),
            'A batch must be closable but never deletable.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonStaffProvider(): array
    {
        return ['resident' => ['resident'], 'kagawad' => ['kagawad']];
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function staff(): User
    {
        return User::factory()->secretary()->create(['is_verified' => true]);
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

        if ($role === 'kagawad') {
            Sector::factory()->create(['kagawad_user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * A sector holding $count verified households.
     */
    private function sectorWithHouseholds(string $name, int $count): Sector
    {
        $sector = Sector::factory()->create(['sector_name' => $name]);

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->resident()->create(['is_verified' => true]);

            ResidentProfile::factory()->create([
                'user_id' => $user->id,
                'sector_id' => $sector->id,
                'household_number' => sprintf('%s-%02d', strtoupper(str_replace(' ', '', $name)), $i + 1),
            ]);
        }

        return $sector->fresh('residents');
    }

    /**
     * @param  list<int>  $sectorIds
     */
    private function newBatch(string $title, array $sectorIds, string $status = ReliefBatch::STATUS_ACTIVE): ReliefBatch
    {
        $batch = ReliefBatch::factory()->create([
            'title' => $title,
            'status' => $status,
            'created_by_user_id' => $this->staff()->id,
        ]);

        $profiles = ResidentProfile::query()
            ->when($sectorIds !== [], fn ($query) => $query->whereIn('sector_id', $sectorIds))
            ->get();

        foreach ($profiles as $profile) {
            ReliefDistribution::factory()->create([
                'relief_batch_id' => $batch->id,
                'household_number' => $profile->household_number,
                'sector_id' => $profile->sector_id,
            ]);
        }

        return $batch->fresh();
    }
}
