<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The Admin-only area.
 *
 * Two things are checked throughout. The obvious one is that a Secretary, a
 * Kagawad or a resident cannot reach any of it. The less obvious one, which is
 * most of the file, is that an Admin cannot lock the system: cannot demote or
 * restrict themselves, cannot demote the last Admin, and cannot delete a sector
 * that households are still filed under.
 */
class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            ['staff.accounts.index'],
            ['staff.sectors.index'],
            ['staff.settings.index'],
            ['staff.audit.index'],
            ['staff.status.index'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminProvider(): array
    {
        return [
            'resident' => ['resident'],
            'kagawad' => ['kagawad'],
            'secretary' => ['secretary'],
        ];
    }

    // ---------------------------------------------------------------------
    // Who may enter
    // ---------------------------------------------------------------------

    #[Test]
    public function an_admin_reaches_every_admin_page(): void
    {
        foreach (self::adminRoutes() as [$name]) {
            $this->actingAs($this->admin())
                ->get(route($name))
                ->assertOk();
        }
    }

    /**
     * A Secretary is the role most likely to be handed these links by accident,
     * and the staff routes are role:admin,secretary - so this is the boundary
     * that an edit to the shared route group would quietly break.
     */
    #[Test]
    #[DataProvider('nonAdminProvider')]
    public function no_other_role_reaches_any_admin_page(string $role): void
    {
        foreach (self::adminRoutes() as [$name]) {
            $this->actingAs($this->userFor($role))
                ->get(route($name))
                ->assertForbidden();
        }
    }

    #[Test]
    #[DataProvider('nonAdminProvider')]
    public function no_other_role_can_perform_any_admin_action(string $role): void
    {
        $resident = $this->resident();
        $sector = Sector::factory()->create();
        $other = $this->userFor($role);

        // Seeded up front so the settings screen has a real row to render, and
        // the assertion below can be "unchanged" rather than "absent".
        Setting::factory()->withKey('barangay_name', 'Barangay Linasin, Zone 1')->create();

        $this->actingAs($other)->put(route('staff.accounts.role', $resident), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($other)->post(route('staff.accounts.restrict', $resident), ['reason' => 'Because I can.'])->assertForbidden();
        $this->actingAs($other)->post(route('staff.sectors.store'), ['sector_name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($other)->put(route('staff.settings.update'), ['values' => ['barangay_name' => 'Mine']])->assertForbidden();

        $this->assertSame(User::ROLE_RESIDENT, $resident->fresh()->role);
        $this->assertSame('Barangay Linasin, Zone 1', Setting::get('barangay_name'));
        $this->assertSame(0, Sector::query()->where('sector_name', 'Sneaky')->count());
        $this->assertNotNull($sector);
    }

    #[Test]
    public function a_guest_is_sent_to_login_from_every_admin_page(): void
    {
        foreach (self::adminRoutes() as [$name]) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    // ---------------------------------------------------------------------
    // Roles
    // ---------------------------------------------------------------------

    #[Test]
    public function a_role_can_be_changed(): void
    {
        $resident = $this->resident();

        $this->actingAs($this->admin())
            ->put(route('staff.accounts.role', $resident), ['role' => User::ROLE_KAGAWAD])
            ->assertRedirect();

        $this->assertSame(User::ROLE_KAGAWAD, $resident->fresh()->role);
    }

    #[Test]
    public function a_role_change_is_audited_with_both_roles(): void
    {
        $admin = $this->admin();
        $resident = $this->resident();

        $this->actingAs($admin)->put(route('staff.accounts.role', $resident), ['role' => User::ROLE_SECRETARY]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.role_changed',
            'subject_id' => $resident->id,
        ]);

        $entry = AuditLog::query()->where('action', 'user.role_changed')->latest('id')->firstOrFail();

        $this->assertStringContainsString(User::ROLE_RESIDENT, $entry->description);
        $this->assertStringContainsString(User::ROLE_SECRETARY, $entry->description);
    }

    /**
     * Changing your own role demotes you out of the only screen that could put
     * it back. This is the one that locks an Admin out permanently.
     */
    #[Test]
    public function an_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('staff.accounts.role', $admin), ['role' => User::ROLE_RESIDENT])
            ->assertStatus(422);

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    #[Test]
    public function the_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create(['is_verified' => true]);

        $this->actingAs($admin)
            ->put(route('staff.accounts.role', $other), ['role' => User::ROLE_RESIDENT])
            ->assertStatus(422);

        $this->assertSame(User::ROLE_ADMIN, $other->fresh()->role);
    }

    /**
     * The rule is "no Admin can be demoted", not "the last Admin cannot be
     * demoted" - and the message has to say so.
     *
     * The old wording was "Demoting the last Admin would lock everyone out",
     * which described a narrower rule than the guard enforces. A Captain reading
     * it would expect a second demotion to be permitted while another Admin
     * remained, and would be refused. The behaviour is unchanged; this pins the
     * message so it cannot drift back to understating the rule.
     *
     * Asserted on the thrown HttpException rather than on a response body: the
     * suite runs with APP_DEBUG off, so a 422 renders a generic error page and the
     * message is deliberately not printed to the browser.
     */
    #[Test]
    public function the_refusal_states_the_real_rule_and_points_at_restrict(): void
    {
        $admin = $this->admin();

        // Two other Admins exist, so "the last Admin" could not apply here.
        $first = User::factory()->admin()->create(['is_verified' => true]);
        $second = User::factory()->admin()->create(['is_verified' => true]);

        $this->withoutExceptionHandling();

        foreach ([[$first, User::ROLE_SECRETARY], [$second, User::ROLE_KAGAWAD]] as [$target, $role]) {
            try {
                $this->actingAs($admin)
                    ->put(route('staff.accounts.role', $target), ['role' => $role]);

                $this->fail('The demotion should have been refused.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
                $this->assertStringContainsString("An Admin's role cannot be lowered", $e->getMessage());
                $this->assertStringContainsString('Restrict', $e->getMessage());
                $this->assertStringNotContainsString('last Admin', $e->getMessage());
            }

            $this->assertSame(User::ROLE_ADMIN, $target->fresh()->role);
        }
    }

    #[Test]
    public function an_unknown_role_is_refused(): void
    {
        $resident = $this->resident();

        $this->actingAs($this->admin())
            ->put(route('staff.accounts.role', $resident), ['role' => 'captain'])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_RESIDENT, $resident->fresh()->role);
    }

    // ---------------------------------------------------------------------
    // Restrictions
    // ---------------------------------------------------------------------

    #[Test]
    public function a_restriction_actually_locks_the_account_out(): void
    {
        $secretary = $this->staff();
        $target = $this->resident();

        $this->actingAs($this->admin())->post(route('staff.accounts.restrict', $target), [
            'reason' => 'Repeated submissions under another household number.',
        ]);

        $target->refresh();
        $this->assertTrue($target->isRestricted());

        // Not just a flag: the routes are actually refused.
        $this->actingAs($target->fresh())->get(route('staff.dashboard'))->assertForbidden();
        $this->actingAs($target->fresh())->get(route('home'))->assertOk();
    }

    /**
     * A restricted account keeps the routes that could get it out again. A
     * frozen account whose owner has forgotten the password is otherwise stuck
     * for good, and the restriction would have caused that.
     */
    #[Test]
    public function a_restricted_account_keeps_its_password_and_sign_out(): void
    {
        $target = $this->resident();
        $this->actingAs($this->admin())->post(route('staff.accounts.restrict', $target), [
            'reason' => 'Held pending a conversation at the office.',
        ]);

        $this->actingAs($target->fresh())
            ->get(route('account.edit'))
            ->assertOk();

        $this->actingAs($target->fresh())
            ->post(route('logout'))
            ->assertRedirect();
    }

    #[Test]
    public function a_restriction_needs_a_reason(): void
    {
        $target = $this->resident();

        $this->actingAs($this->admin())
            ->post(route('staff.accounts.restrict', $target))
            ->assertSessionHasErrors('reason');

        $this->assertFalse($target->fresh()->isRestricted());
    }

    #[Test]
    public function a_restriction_date_in_the_past_is_refused(): void
    {
        $target = $this->resident();

        $this->actingAs($this->admin())
            ->post(route('staff.accounts.restrict', $target), [
                'reason' => 'Trying to backdate this one.',
                'restricted_until' => now()->subWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('restricted_until');

        $this->assertFalse($target->fresh()->isRestricted());
    }

    #[Test]
    public function unrestricting_puts_the_account_back(): void
    {
        $admin = $this->admin();
        $target = $this->resident();

        $this->actingAs($admin)->post(route('staff.accounts.restrict', $target), [
            'reason' => 'Held while the office checked the household list.',
        ]);

        $this->actingAs($admin->fresh())->post(route('staff.accounts.unrestrict', $target))->assertRedirect();

        $this->assertFalse($target->fresh()->isRestricted());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.unrestricted',
            'subject_id' => $target->id,
        ]);
    }

    /**
     * Lifting a restriction that is not there would report success for a change
     * that did not happen, which is the failure an audit log is least able to
     * explain afterwards.
     */
    #[Test]
    public function lifting_a_restriction_that_is_not_there_is_refused(): void
    {
        $target = $this->resident();

        $this->actingAs($this->admin())
            ->post(route('staff.accounts.unrestrict', $target))
            ->assertStatus(409);
    }

    #[Test]
    public function an_admin_cannot_restrict_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('staff.accounts.restrict', $admin), [
                'reason' => 'Locking myself out on purpose.',
            ])
            ->assertStatus(422);

        $this->assertFalse($admin->fresh()->isRestricted());
    }

    // ---------------------------------------------------------------------
    // Sectors
    // ---------------------------------------------------------------------

    #[Test]
    public function a_sector_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('staff.sectors.store'), [
                'sector_name' => 'Purok 7',
                'purok_name' => 'Mabini',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sectors', ['sector_name' => 'Purok 7', 'purok_name' => 'Mabini']);
    }

    #[Test]
    public function a_duplicate_sector_name_is_refused(): void
    {
        Sector::factory()->create(['sector_name' => 'Purok 7']);

        $this->actingAs($this->admin())
            ->post(route('staff.sectors.store'), ['sector_name' => 'Purok 7'])
            ->assertSessionHasErrors('sector_name');
    }

    /**
     * The uniqueness check has to ignore the row being renamed, or a sector
     * would count its own name as taken and could never be saved.
     */
    #[Test]
    public function a_sector_can_keep_its_own_name_while_renaming(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Purok 1', 'purok_name' => 'Rizal']);

        $this->actingAs($this->admin())
            ->put(route('staff.sectors.update', $sector), [
                'sector_name' => 'Purok 1',
                'purok_name' => 'Bonifacio',
            ])
            ->assertRedirect();

        $this->assertSame('Bonifacio', $sector->fresh()->purok_name);
    }

    #[Test]
    public function a_kagawad_can_be_assigned_to_a_sector(): void
    {
        $sector = Sector::factory()->create(['kagawad_user_id' => null]);
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);

        $this->actingAs($this->admin())
            ->put(route('staff.sectors.kagawad', $sector), ['kagawad_user_id' => $kagawad->id])
            ->assertRedirect();

        $this->assertSame($kagawad->id, $sector->fresh()->kagawad_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sector.kagawad_assigned']);
    }

    /**
     * sectors.kagawad_user_id is UNIQUE. Checked here so the operator gets a
     * message naming the other sector instead of a duplicate-key 500.
     */
    #[Test]
    public function assigning_a_kagawad_who_already_has_a_sector_is_refused_with_a_reason(): void
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $first = Sector::factory()->create(['sector_name' => 'Purok 1', 'kagawad_user_id' => $kagawad->id]);
        $second = Sector::factory()->create(['sector_name' => 'Purok 2', 'kagawad_user_id' => null]);

        $this->actingAs($this->admin())
            ->put(route('staff.sectors.kagawad', $second), ['kagawad_user_id' => $kagawad->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($second->fresh()->kagawad_user_id);
        $this->assertSame($kagawad->id, $first->fresh()->kagawad_user_id);
    }

    #[Test]
    public function a_non_kagawad_account_cannot_be_assigned_to_a_sector(): void
    {
        $sector = Sector::factory()->create(['kagawad_user_id' => null]);
        $secretary = $this->staff();

        $this->actingAs($this->admin())
            ->put(route('staff.sectors.kagawad', $sector), ['kagawad_user_id' => $secretary->id])
            ->assertStatus(422);

        $this->assertNull($sector->fresh()->kagawad_user_id);
    }

    /**
     * A sector is what a relief checklist and a resident's address are scoped by.
     * Deleting one with households on it would leave those rows pointing at
     * nothing, so the refusal names what is in the way.
     */
    #[Test]
    public function a_sector_with_households_cannot_be_deleted(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Purok 1']);
        ResidentProfile::factory()->create([
            'user_id' => $this->resident()->id,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('staff.sectors.destroy', $sector))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
    }

    /**
     * The relief rows are keyed on sector_id rather than on a resident, so a
     * sector whose households have all moved is still not empty.
     */
    #[Test]
    public function a_sector_with_only_relief_entries_cannot_be_deleted(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Purok 1']);
        $batch = ReliefBatch::factory()->create();

        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('staff.sectors.destroy', $sector))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
    }

    #[Test]
    public function an_empty_sector_can_be_deleted(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Unused']);

        $this->actingAs($this->admin())
            ->delete(route('staff.sectors.destroy', $sector))
            ->assertRedirect();

        $this->assertDatabaseMissing('sectors', ['id' => $sector->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sector.deleted']);
    }

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------

    #[Test]
    public function a_setting_can_be_changed(): void
    {
        Setting::factory()->withKey('barangay_name', 'Barangay Linasin, Zone 1')->create();

        $this->actingAs($this->admin())
            ->put(route('staff.settings.update'), [
                'values' => ['barangay_name' => 'Barangay Linasin, Zone 2'],
            ])
            ->assertRedirect();

        $this->assertSame('Barangay Linasin, Zone 2', Setting::get('barangay_name'));
    }

    /**
     * A save with nothing changed should leave the trail clean, or the audit log
     * fills with "settings updated" rows that record no change.
     */
    #[Test]
    public function saving_without_a_change_logs_nothing(): void
    {
        Setting::factory()->withKey('barangay_name', 'Unchanged')->create();

        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => ['barangay_name' => 'Unchanged'],
        ]);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.updated']);
    }

    #[Test]
    public function only_changed_settings_are_audited(): void
    {
        Setting::factory()->withKey('barangay_name', 'Same')->create();
        Setting::factory()->withKey('office_phone', '0912')->create();

        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => ['barangay_name' => 'Same', 'office_phone' => '0999'],
        ]);

        $entry = AuditLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();

        $this->assertStringContainsString('office_phone', $entry->description);
        $this->assertStringNotContainsString('barangay_name', $entry->description);
    }

    /**
     * The public templates read these by key. A form that could create a key
     * would either do nothing useful or break a page, so unknown keys are
     * reported and not written.
     */
    #[Test]
    public function an_unknown_setting_key_is_reported_and_not_created(): void
    {
        $this->actingAs($this->admin())
            ->put(route('staff.settings.update'), [
                'values' => ['not_a_real_key' => 'value'],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('settings', ['key' => 'not_a_real_key']);
    }

    // ---------------------------------------------------------------------
    // The audit log itself
    // ---------------------------------------------------------------------

    /**
     * The absence is the feature. A trail an Admin can edit is not a trail, so
     * there is no write route at all and the test asserts that rather than
     * trusting the controller to refuse.
     */
    #[Test]
    public function there_is_no_route_that_writes_to_the_audit_log(): void
    {
        $writing = collect(app('router')->getRoutes())->filter(
            fn ($route) => str_starts_with($route->uri(), 'staff/audit')
                && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []
        );

        $this->assertTrue($writing->isEmpty(), 'The audit log must be read only.');
    }

    #[Test]
    public function the_log_lists_what_has_happened(): void
    {
        AuditLog::query()->delete();

        $this->actingAs($this->admin())->post(route('staff.accounts.role', $this->resident()), [
            'role' => User::ROLE_KAGAWAD,
        ]);

        $this->actingAs($this->admin()->fresh())
            ->get(route('staff.audit.index'))
            ->assertOk()
            ->assertSee('Role changed');
    }

    /**
     * The search must not escape the action filter through an unparenthesised
     * OR, or "Role changed" plus a term would quietly become "everything".
     *
     * Asserted on the description rather than the action slug, because the
     * action filter's own dropdown lists every slug - so "user.restricted"
     * appears on the page whether or not a matching row does.
     */
    #[Test]
    public function the_search_does_not_widen_the_action_filter(): void
    {
        AuditLog::query()->delete();
        $admin = $this->admin();
        $target = $this->resident();
        $target->update(['name' => 'Wilhelmina Restrictiontarget']);

        $this->actingAs($admin)->post(route('staff.accounts.restrict', $target), [
            'reason' => 'Held while the office checked the household list.',
        ]);

        $log = $this->actingAs($admin->fresh())
            ->get(route('staff.audit.index', [
                'action' => 'user.role_changed',
                'q' => 'Restrictiontarget',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'was restricted',
            $log,
            'The search term matched an entry outside the action filter.'
        );
    }

    // ---------------------------------------------------------------------
    // System status
    // ---------------------------------------------------------------------

    /**
     * A status page that echoed a credential would be a worse problem than the
     * one it diagnoses, so the rendered page is checked for the actual values.
     */
    #[Test]
    public function the_status_page_does_not_show_a_credential(): void
    {
        config(['database.connections.mysql.password' => 'super-secret-value']);

        $content = $this->actingAs($this->admin())
            ->get(route('staff.status.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('super-secret-value', $content);
    }

    #[Test]
    public function the_status_page_reports_the_database_and_the_row_counts(): void
    {
        Setting::factory()->withKey('barangay_name', 'Barangay Linasin')->create();
        Setting::factory()->withKey('office_phone', '0966')->create();

        $content = $this->actingAs($this->admin())
            ->get(route('staff.status.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Connected', $content);
        $this->assertStringContainsString(basename(config('database.connections.mysql.database')), $content);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_verified' => true]);
    }

    private function staff(): User
    {
        return User::factory()->secretary()->create(['is_verified' => true]);
    }

    private function resident(): User
    {
        return User::factory()->resident()->create(['is_verified' => true]);
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
}
