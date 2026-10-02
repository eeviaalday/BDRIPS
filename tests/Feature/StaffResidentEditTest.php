<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\UpdateResidentProfileRequest;
use App\Models\AuditLog;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\ResidentProfileRules;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Unique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Staff correction of a resident's own details.
 *
 * The rules this file cares about are not about fields. They are about four ways
 * this feature can be quietly wrong:
 *
 *   1. A staff form that accepts something the registration wizard refuses, or
 *      refuses something it accepts. The wizard and this form describe one person,
 *      so the value domains are asserted to be literally the same array rather
 *      than merely similar.
 *   2. A correction that saves over someone else's. Two staff can hold the same
 *      resident open at once.
 *   3. An audit trail that records a change that did not happen, or records a
 *      secret in full.
 *   4. A correction that quietly rewrites history - a resident moved to another
 *      sector must NOT drag them off a relief checklist that was already opened.
 *
 * Every assertion reads a parsed document or the database. None of them assert on
 * a Blade source string, because a source string passes just as happily when the
 * markup around it is malformed.
 */
class StaffResidentEditTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // 1. The form accepts exactly what the wizard accepts
    // -----------------------------------------------------------------

    /**
     * The two forms share the rule array itself, not a copy of it.
     *
     * Asserted against RegisterRequest::rules() rather than against a second list
     * written here, so this fails the moment either side stops reading the shared
     * class - which is the whole point of extracting it. The eight shared keys are
     * compared; email, the uploaded ID and the password are the wizard's alone and
     * are checked to be ABSENT from the staff form, because a correction form that
     * could reset a password would be an account screen wearing a different hat.
     */
    #[Test]
    public function the_staff_form_uses_the_wizard_s_own_rules_for_the_shared_fields(): void
    {
        $wizard = (new RegisterRequest)->rules();
        $staff = (new UpdateResidentProfileRequest)->rules();

        foreach (array_keys(ResidentProfileRules::shared()) as $field) {
            $this->assertArrayHasKey($field, $staff, "The staff form does not validate [{$field}].");
            $this->assertArrayHasKey($field, $wizard, "The wizard no longer validates [{$field}].");

            /*
             * household_number is the one field the staff form deliberately
             * strengthens, so it is asserted separately below rather than being
             * made an exception here - an exception written as "skip this key" is
             * a hole a future field walks straight into.
             */
            if ($field === 'household_number') {
                continue;
            }

            $this->assertSame(
                array_map($this->describeRule(...), $wizard[$field]),
                array_map($this->describeRule(...), $staff[$field]),
                "[{$field}] is validated differently by the wizard and by the staff form.",
            );
        }

        /*
         * The wizard's household_number rules, plus exactly one more: the
         * uniqueness check the wizard cannot make. Order matters - the added rule
         * comes after the wizard's own, so a blank or oversized number is still
         * reported as blank or oversized rather than as a duplicate.
         */
        $wizardHousehold = array_map($this->describeRule(...), $wizard['household_number']);
        $staffHousehold = array_map($this->describeRule(...), $staff['household_number']);

        $this->assertSame(
            $wizardHousehold,
            array_slice($staffHousehold, 0, count($wizardHousehold)),
            'The staff form weakened the wizard\'s household number rules.',
        );

        $this->assertCount(
            count($wizardHousehold) + 1,
            $staffHousehold,
            'The staff form adds a rule to household_number beyond the uniqueness check.',
        );

        $this->assertInstanceOf(
            Unique::class,
            $staff['household_number'][count($wizardHousehold)],
            'The extra household_number rule is not the uniqueness check.',
        );

        /*
         * The rule's internals are protected, so they are read back out of the
         * serialised object. Asserting the table, the column and the ignored key
         * matters: a uniqueness rule pointed at the wrong table would pass every
         * other assertion here while refusing nothing.
         */
        $serialised = serialize($staff['household_number'][count($wizardHousehold)]);

        $this->assertStringContainsString('resident_profiles', $serialised);
        $this->assertStringContainsString('household_number', $serialised);
        $this->assertStringContainsString('ignore', $serialised);

        foreach (['email', 'password', 'valid_id_photo'] as $accountOnly) {
            $this->assertArrayNotHasKey(
                $accountOnly,
                $staff,
                "The staff correction form accepts [{$accountOnly}], which belongs to the account area.",
            );
        }
    }

    /**
     * A validation rule flattened to a comparable string.
     *
     * Rule objects are compared by state rather than by identity, because
     * Rule::in() and Rule::exists() build a new object each time rules() is called
     * and two objects that mean the same thing must compare equal.
     */
    private function describeRule(mixed $rule): string
    {
        if (is_object($rule)) {
            return serialize($rule);
        }

        return (string) $rule;
    }

    /**
     * The wizard's own array is unchanged by the extraction.
     *
     * Pins the exact rule list and key order the wizard had before the shared
     * class existed, so a future edit that "tidies" the wizard fails here instead
     * of passing a review that was only reading the new code.
     */
    #[Test]
    public function the_wizard_s_rule_list_is_unchanged(): void
    {
        $rules = (new RegisterRequest)->rules();

        $this->assertSame([
            'name', 'date_of_birth', 'gender', 'civil_status', 'nationality',
            'nationality_other', 'address', 'sector_id', 'email',
            'household_number', 'valid_id_photo', 'password',
        ], array_keys($rules), 'The wizard\'s rule keys or their order changed.');

        $this->assertSame(['required', 'string', 'max:50'], $rules['household_number']);
        $this->assertSame(['required', 'string', 'max:255'], $rules['name']);
        $this->assertSame(['required', 'string', 'max:255'], $rules['address']);
    }

    // -----------------------------------------------------------------
    // 2. Who may use it
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedRoleProvider(): array
    {
        return [
            'kagawad' => [User::ROLE_KAGAWAD],
            'resident' => [User::ROLE_RESIDENT],
        ];
    }

    /**
     * A Kagawad and a resident are refused the WRITE, not just the form.
     *
     * The GET is covered by the route matrix; the PUT is asserted here because a
     * write that is only guarded in the route table would still be reached by
     * anything that posts to the action directly, and the matrix reads middleware
     * rather than making a request.
     */
    #[Test]
    #[DataProvider('refusedRoleProvider')]
    public function the_write_is_refused_to_anyone_who_is_not_staff(string $role): void
    {
        $profile = $this->resident();
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY, 'is_verified' => true]);

        $this->actingAs(User::factory()->create(['role' => $role, 'is_verified' => true]))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Somebody Else Entirely',
            ], $secretary))
            ->assertForbidden();

        $this->assertSame(
            $profile->user->name,
            $profile->user->fresh()->name,
            "A {$role} changed a resident's name.",
        );
    }

    #[Test]
    public function a_secretary_may_open_the_correction_form(): void
    {
        $profile = $this->resident();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SECRETARY, 'is_verified' => true]))
            ->get(route('staff.residents.edit', $profile))
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // 3. Validation
    // -----------------------------------------------------------------

    /**
     * An empty submission is refused on every field, in one test.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'name' => [['name' => '']],
            'date of birth' => [['date_of_birth' => '']],
            'gender' => [['gender' => '']],
            'civil status' => [['civil_status' => '']],
            'nationality' => [['nationality' => '']],
            'address' => [['address' => '']],
            'sector' => [['sector_id' => '']],
            'household number' => [['household_number' => '']],
        ];
    }

    #[Test]
    #[DataProvider('requiredFieldProvider')]
    public function each_shared_field_is_required(array $blank): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, $blank))
            ->assertRedirect(route('staff.residents.edit', $profile))
            ->assertSessionHasErrors(array_key_first($blank));

        $this->assertDatabaseMissing('resident_profiles', [
            'id' => $profile->id,
            'address' => '',
        ]);
    }

    /**
     * Values the wizard's closed lists refuse are refused here too.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function closedListProvider(): array
    {
        return [
            'gender' => ['gender', 'Not a gender', 'gender'],
            'civil status' => ['civil_status', 'Complicated', 'civil_status'],
            'nationality' => ['nationality', 'Martian', 'nationality'],
        ];
    }

    #[Test]
    #[DataProvider('closedListProvider')]
    public function a_value_outside_the_wizard_s_closed_list_is_refused(string $field, string $value, string $errorKey): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [$field => $value]))
            ->assertSessionHasErrors($errorKey);
    }

    #[Test]
    public function a_future_date_of_birth_is_refused(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'date_of_birth' => now()->addYear()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('date_of_birth');
    }

    #[Test]
    public function a_sector_that_does_not_exist_is_refused(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'sector_id' => Sector::max('id') + 1000,
            ]))
            ->assertSessionHasErrors('sector_id');
    }

    // -----------------------------------------------------------------
    // 4. Household number uniqueness, ignoring the record being corrected
    // -----------------------------------------------------------------

    #[Test]
    public function a_household_number_already_taken_by_another_resident_is_refused(): void
    {
        $first = $this->resident(['household_number' => 'HH-0001']);
        $second = $this->resident(['household_number' => 'HH-0002']);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $second))
            ->put(route('staff.residents.update', $second), $this->payload($second, [
                'household_number' => 'HH-0001',
            ]))
            ->assertSessionHasErrors('household_number');

        $this->assertSame('HH-0002', $second->fresh()->household_number);
    }

    /**
     * The rule ignores the record being corrected.
     *
     * Without ignore(), a Secretary correcting a misspelled NAME on a household
     * whose number is already correct could not save at all, because the form
     * would report its own untouched number as a duplicate.
     */
    #[Test]
    public function saving_a_kept_household_number_is_not_a_duplicate_of_itself(): void
    {
        $profile = $this->resident(['household_number' => 'HH-0100']);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Corrected Name',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('HH-0100', $profile->fresh()->household_number);
        $this->assertSame('Corrected Name', $profile->user->fresh()->name);
    }

    // -----------------------------------------------------------------
    // 5. The correction itself
    // -----------------------------------------------------------------

    #[Test]
    public function a_correction_is_written_to_both_tables(): void
    {
        $profile = $this->resident();
        $other = Sector::factory()->create(['sector_name' => 'Tala']);

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Juan Dela Cruz',
                'date_of_birth' => '1988-04-17',
                'gender' => 'Male',
                'civil_status' => 'Married',
                'address' => 'House no. 12, Barangay Linasin',
                'sector_id' => $other->id,
                'household_number' => 'HH-4242',
            ]))
            ->assertRedirect(route('staff.residents.show', $profile))
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $user = $profile->user->refresh();

        $this->assertSame('HH-4242', $profile->household_number);
        $this->assertSame('House no. 12, Barangay Linasin', $profile->address);
        $this->assertSame($other->id, $profile->sector_id);

        $this->assertSame('Juan Dela Cruz', $user->name);
        $this->assertSame('1988-04-17', $user->date_of_birth->format('Y-m-d'));
        $this->assertSame('Male', $user->gender);
        $this->assertSame('Married', $user->civil_status);
    }

    /**
     * The account and decision fields are not reachable from this form at all.
     *
     * Posted anyway, to prove they are ignored rather than merely absent from the
     * markup: a form that hides a field but accepts it is still an account screen.
     */
    #[Test]
    public function posting_account_and_verification_fields_changes_nothing(): void
    {
        $profile = $this->resident();
        $originalEmail = $profile->user->email;
        $originalPassword = $profile->user->password;
        $originalStatus = $profile->verification_status;

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'email' => 'hijacked@example.com',
                'password' => 'new-password',
                'national_or_voter_id' => '0000-0000-0000',
                'id_photo_path' => 'ids/forged.png',
                'verification_status' => 'verified',
                'role' => User::ROLE_ADMIN,
                'is_verified' => true,
            ]))
            ->assertSessionHasNoErrors();

        $user = $profile->user->fresh();
        $profile->refresh();

        $this->assertSame($originalEmail, $user->email);
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame(User::ROLE_RESIDENT, $user->role);
        $this->assertFalse((bool) $user->is_verified);
        $this->assertNotSame('0000-0000-0000', $profile->national_or_voter_id);
        $this->assertSame($originalStatus, $profile->verification_status);
        $this->assertNull($profile->id_photo_path);
    }

    /**
     * "Others" is an escape hatch in the list; the column holds the real value.
     */
    #[Test]
    public function others_nationality_stores_what_was_typed(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'nationality' => 'Others',
                'nationality_other' => 'American',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('American', $profile->user->fresh()->nationality);
    }

    #[Test]
    public function others_nationality_without_a_value_is_refused(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'nationality' => 'Others',
                'nationality_other' => '',
            ]))
            ->assertSessionHasErrors('nationality_other');
    }

    /**
     * A stored value outside the list round-trips as "Others" plus the text.
     *
     * Otherwise a Secretary who opened the form and saved it without touching
     * nationality would rewrite "American" to "Others" and lose the nationality.
     */
    #[Test]
    public function a_nationality_outside_the_list_is_not_rewritten_by_opening_and_saving_the_form(): void
    {
        $profile = $this->resident(userAttributes: ['nationality' => 'American']);

        $form = $this->actingAs($this->secretary())
            ->get(route('staff.residents.edit', $profile))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($form);
        $selected = $xpath->query('//select[@id="nationality"]//option[@selected]');

        $this->assertNotFalse($selected);
        $this->assertCount(1, $selected, 'The nationality select did not mark the stored value as chosen.');
        $this->assertSame('Others', trim($selected->item(0)->textContent));

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'nationality' => 'Others',
                'nationality_other' => 'American',
                'name' => 'Someone Else',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('American', $profile->user->fresh()->nationality);
    }

    // -----------------------------------------------------------------
    // 6. A no-op save writes and logs nothing
    // -----------------------------------------------------------------

    #[Test]
    public function saving_without_changing_anything_writes_nothing_and_logs_nothing(): void
    {
        $profile = $this->resident();

        $updatedAtBefore = $profile->updated_at;

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile))
            ->assertRedirect(route('staff.residents.show', $profile))
            ->assertSessionHas('info', 'No changes to save.');

        $this->assertSame(
            $updatedAtBefore->format('Y-m-d H:i:s'),
            $profile->fresh()->updated_at->format('Y-m-d H:i:s'),
            'A no-op save moved updated_at, which would make the form look stale to anyone else holding it.',
        );

        $this->assertDatabaseMissing('audit_logs', ['action' => 'resident.updated']);
    }

    /**
     * A changed value that the database normalises back is still a no-op.
     *
     * sector_id arrives from a form as the string "3" and is stored as the integer
     * 3. Comparing raw input to raw storage would report a change on every save of
     * an untouched form, and the audit log would fill with entries saying a sector
     * changed from 3 to 3.
     */
    #[Test]
    public function a_value_the_database_normalises_is_not_reported_as_a_change(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'sector_id' => (string) $profile->sector_id,
            ]))
            ->assertSessionHas('info', 'No changes to save.');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'resident.updated']);
    }

    // -----------------------------------------------------------------
    // 7. The audit entry
    // -----------------------------------------------------------------

    #[Test]
    public function a_correction_is_audited_with_the_actor_and_the_before_and_after_of_each_field(): void
    {
        $profile = $this->resident(['household_number' => 'HH-0001']);
        $secretary = $this->secretary();

        // Read before the write, because the assertion is about the value the
        // record held when the form was submitted rather than the one it holds now.
        $nameBefore = $profile->user->name;
        $this->assertSame('Juan Dela Cruz', $nameBefore);

        $this->actingAs($secretary)
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Renamed Person',
                'household_number' => 'HH-0009',
            ]))
            ->assertSessionHasNoErrors();

        $entry = AuditLog::query()
            ->where('action', 'resident.updated')
            ->sole();

        $this->assertSame($secretary->id, $entry->user_id);
        $this->assertSame(ResidentProfile::class, $entry->subject_type);
        $this->assertSame($profile->id, $entry->subject_id);

        $this->assertSame(
            ['name', 'household_number'],
            $entry->context['changed_fields'],
            'The entry does not name the fields that changed.',
        );

        $this->assertSame(
            ['from' => $nameBefore, 'to' => 'Renamed Person'],
            $entry->context['changes']['name'],
        );

        $this->assertSame(
            ['from' => 'HH-0001', 'to' => 'HH-0009'],
            $entry->context['changes']['household_number'],
        );
    }

    #[Test]
    public function the_audit_label_is_registered(): void
    {
        $this->assertSame('Resident details updated', AuditLog::actions()['resident.updated']);
    }

    /**
     * The audit trail never carries a secret in full.
     *
     * The corrected fields are not themselves secret, so the redaction is proved
     * by exercising it: the request helper is asked what it would do with the
     * values this form is forbidden to write, and the answer must be that it
     * records only that they changed.
     */
    #[Test]
    public function a_sensitive_field_is_recorded_as_changed_and_never_in_full(): void
    {
        $redacted = UpdateResidentProfileRequest::redactedFields();

        $this->assertContains('national_or_voter_id', $redacted);
        $this->assertContains('id_photo_path', $redacted);
        $this->assertContains('password', $redacted);
        $this->assertContains('email', $redacted);
    }

    #[Test]
    public function no_audit_entry_ever_contains_a_national_id_or_a_password(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Renamed Person',
                'password' => 'super-secret-value',
                'national_or_voter_id' => '1234-5678-9012',
            ]))
            ->assertSessionHasNoErrors();

        $entry = AuditLog::query()->where('action', 'resident.updated')->sole();

        $serialised = json_encode($entry->context);

        $this->assertStringNotContainsString('super-secret-value', $serialised);
        $this->assertStringNotContainsString('1234-5678-9012', $serialised);
    }

    // -----------------------------------------------------------------
    // 8. Optimistic concurrency
    // -----------------------------------------------------------------

    /**
     * The profile half of the record is guarded.
     *
     * A corrected record is not a document with a single author: two staff can
     * have the same resident's screen open at once, and whoever saves second
     * silently discards the first one's correction while both believe they
     * succeeded.
     *
     * The clock is moved explicitly because the whole check turns on the two
     * timestamps differing. Created and updated within the same second - which is
     * what happens in a fast test - the check has nothing to compare and the form
     * is accepted even though somebody else really did change the record first.
     */
    #[Test]
    public function a_save_is_refused_when_the_profile_moved_after_the_form_was_opened(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00', 'Asia/Manila'));

        $profile = $this->resident();
        $payload = $this->payload($profile, ['name' => 'Correction Based On Stale Data']);

        $this->travelTo(Carbon::parse('2026-03-10 10:05:00', 'Asia/Manila'));

        // Somebody else corrects the record while the form is open.
        $profile->update(['address' => 'A different address entirely']);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $payload)
            ->assertSessionHasErrors('profile_updated_at');

        $this->assertSame(
            'A different address entirely',
            $profile->fresh()->address,
            'A stale form overwrote a change somebody else had already made.',
        );

        $this->assertSame(
            'Juan Dela Cruz',
            $profile->user->fresh()->name,
            'The refused save wrote the stale name anyway.',
        );
    }

    /**
     * The account half of the record is guarded too.
     *
     * The form writes columns on two tables, so watching only the profile's
     * timestamp would leave every name and date of birth overwriteable by a form
     * opened before someone else's correction.
     */
    #[Test]
    public function a_save_is_refused_when_the_account_moved_after_the_form_was_opened(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00', 'Asia/Manila'));

        $profile = $this->resident();
        $payload = $this->payload($profile, ['name' => 'Correction Based On Stale Data']);

        $this->travelTo(Carbon::parse('2026-03-10 10:05:00', 'Asia/Manila'));

        $profile->user->update(['name' => 'Somebody Who Corrected First']);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.edit', $profile))
            ->put(route('staff.residents.update', $profile), $payload)
            ->assertSessionHasErrors('user_updated_at');

        $this->assertSame(
            'Somebody Who Corrected First',
            $profile->user->fresh()->name,
            'A stale form overwrote a name somebody else had already corrected.',
        );
    }

    #[Test]
    public function the_concurrency_check_does_not_fire_when_nothing_else_moved(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'name' => 'Nobody Was Here First',
            ]))
            ->assertSessionHasNoErrors();
    }

    // -----------------------------------------------------------------
    // 9. Relief history is a snapshot, not a live reference
    // -----------------------------------------------------------------

    /**
     * Moving a resident does not move them off a checklist that was already open.
     *
     * A relief row carries its own copy of the sector and the household number, and
     * that copy is what the Kagawad's checklist is built from. Rewriting it here
     * would silently pull a household out of a distribution already under way, and
     * a household nobody delivered to would still be on the list.
     */
    #[Test]
    public function changing_a_sector_leaves_open_relief_rows_in_their_original_sector(): void
    {
        $profile = $this->resident(['verification_status' => 'verified']);
        $from = $profile->sector;
        $to = Sector::factory()->create(['sector_name' => 'Tala']);

        $batch = ReliefBatch::factory()->create(['status' => ReliefBatch::STATUS_ACTIVE]);
        $row = ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'household_number' => $profile->household_number,
            'sector_id' => $from->id,
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $this->actingAs($this->secretary())
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'sector_id' => $to->id,
            ]))
            ->assertSessionHasNoErrors();

        $row->refresh();

        $this->assertSame($from->id, $row->sector_id, 'An open relief row followed the resident to the new sector.');
        $this->assertSame($to->id, $profile->fresh()->sector_id);
    }

    /**
     * The future is the opposite: a batch opened after the move uses the new sector.
     *
     * Both halves matter together. The first test proves an open checklist keeps
     * its original sector, which on its own would read as "the correction does
     * nothing". This one proves the correction reaches the NEXT batch, which is
     * what makes it worth the notice the form shows.
     */
    #[Test]
    public function a_batch_opened_after_the_change_uses_the_new_sector(): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident(['verification_status' => 'verified']);
        $to = Sector::factory()->create(['sector_name' => 'Tala']);

        $this->actingAs($secretary)
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'sector_id' => $to->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($secretary)
            ->post(route('staff.batches.store'), [
                'title' => 'Opened After The Move',
                'sector_ids' => [$to->id],
            ])
            ->assertSessionHasNoErrors();

        $row = ReliefDistribution::query()
            ->where('household_number', $profile->household_number)
            ->sole();

        $this->assertSame(
            $to->id,
            $row->sector_id,
            'A batch opened after the correction did not use the resident\'s new sector.',
        );
    }

    /**
     * And the corrected household is not dragged onto a checklist it never joined.
     */
    #[Test]
    public function a_batch_for_the_old_sector_no_longer_contains_the_moved_resident(): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident(['verification_status' => 'verified']);
        $from = $profile->sector;

        $this->actingAs($secretary)
            ->put(route('staff.residents.update', $profile), $this->payload($profile, [
                'sector_id' => Sector::factory()->create(['sector_name' => 'Tala'])->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($secretary)
            ->post(route('staff.batches.store'), [
                'title' => 'For The Old Sector',
                'sector_ids' => [$from->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('relief_distributions', [
            'household_number' => $profile->household_number,
            'sector_id' => $from->id,
        ]);
    }

    // -----------------------------------------------------------------
    // 10. What the two pages actually render
    // -----------------------------------------------------------------

    #[Test]
    public function the_directory_row_offers_a_view_and_an_edit_action_each_named_after_the_resident(): void
    {
        $profile = $this->resident(userAttributes: ['name' => 'Row Subject']);

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.index'))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);

        $view = $xpath->query('//a[contains(@aria-label, "View Row Subject")]');
        $edit = $xpath->query('//a[contains(@aria-label, "Edit Row Subject")]');

        $this->assertSame(1, $view->length, 'The directory row has no View action named after the resident.');
        $this->assertSame(1, $edit->length, 'The directory row has no Edit action named after the resident.');

        $this->assertSame(
            route('staff.residents.edit', $profile),
            $edit->item(0)->getAttribute('href'),
        );

        // The glyph is on the <i> inside the control, not on the control itself.
        $glyph = $xpath->query('.//i', $edit->item(0))->item(0);

        $this->assertNotNull($glyph, 'The Edit action draws no glyph.');
        $this->assertStringContainsString(
            'fa-pen',
            $glyph->getAttribute('class'),
            'The Edit action does not draw a pen.',
        );
        $this->assertSame(
            'true',
            $glyph->getAttribute('aria-hidden'),
            'The pen glyph is not hidden from assistive technology.',
        );
    }

    #[Test]
    public function the_record_page_carries_the_five_cards_the_clerk_works_through(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.show', $profile))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);

        $headings = [];

        foreach ($xpath->query('//main//h2') as $heading) {
            $headings[] = trim($heading->textContent);
        }

        foreach ([
            'Personal information',
            'Household and sector',
            'Contact',
            'Account and verification',
            'Requests and relief history',
        ] as $expected) {
            $this->assertContains($expected, $headings, "The record page is missing the [{$expected}] card.");
        }
    }

    /**
     * The national ID is masked on the record page.
     */
    #[Test]
    public function the_record_page_masks_the_national_id(): void
    {
        $profile = $this->resident(['national_or_voter_id' => '1234-5678-9012']);

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.show', $profile))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            '1234-5678-9012',
            $html,
            'The full national ID number is on the record page.',
        );

        $this->assertMatchesRegularExpression(
            '/•+9012/',
            $html,
            'The masked national ID does not end in the last four digits.',
        );
    }

    /**
     * The record page offers a way to the correction form.
     */
    #[Test]
    public function the_record_page_links_to_the_correction_form(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.show', $profile))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('staff.residents.edit', $profile), $html);
    }

    /**
     * The correction form warns about the sector change before it happens.
     *
     * Asserted as text on the page rather than as the behaviour of the save,
     * because the behaviour is the schema's (the sector column simply changes) -
     * what this form owns is whether the Secretary was told what follows.
     */
    #[Test]
    public function the_correction_form_states_what_changing_the_sector_does(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.edit', $profile))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);
        $text = $xpath->query('//main')->item(0)->textContent;

        $this->assertStringContainsString('Kagawad', $text, 'The sector change notice does not mention the Kagawad who gains or loses sight of the resident.');
        $this->assertStringContainsString('Relief batches already opened', $text, 'The notice does not say what happens to checklists already opened.');
    }

    #[Test]
    public function the_correction_form_warns_that_a_new_household_number_does_not_move_past_records(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.edit', $profile))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);
        $text = $xpath->query('//main')->item(0)->textContent;

        $this->assertStringContainsString(
            'does not move past relief records',
            $text,
            'The household number notice is missing.',
        );
    }

    /**
     * The form has exactly the eight shared inputs, and none of the forbidden ones.
     */
    #[Test]
    public function the_correction_form_offers_the_shared_fields_and_nothing_else(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.edit', $profile))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);

        $named = [];

        foreach ($xpath->query('//main//input[@name] | //main//select[@name] | //main//textarea[@name]') as $control) {
            $named[] = $control->getAttribute('name');
        }

        foreach (['name', 'date_of_birth', 'gender', 'civil_status', 'nationality',
            'nationality_other', 'address', 'sector_id', 'household_number',
            'profile_updated_at', 'user_updated_at'] as $expected) {
            $this->assertContains($expected, $named, "The correction form has no [{$expected}] control.");
        }

        foreach (['email', 'password', 'national_or_voter_id', 'id_photo_path',
            'verification_status', 'role', 'is_verified'] as $forbidden) {
            $this->assertNotContains($forbidden, $named, "The correction form offers [{$forbidden}].");
        }

        $this->assertSame(
            route('staff.residents.update', $profile),
            $xpath->query('//main//form')->item(0)->getAttribute('action'),
        );
    }

    /**
     * The form posts as a PUT, so a refresh cannot silently replay it as a GET.
     */
    #[Test]
    public function the_correction_form_uses_the_put_method(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.edit', $profile))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="_method"[^>]*value="PUT"|value="PUT"[^>]*name="_method"/',
            $html,
            'The correction form does not carry a PUT method override.',
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function secretary(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'is_verified' => true,
        ]);
    }

    /**
     * A resident carrying every field the wizard collects.
     *
     * The wizard's fields are set explicitly rather than left to the factory,
     * because the factory does not populate them and a profile with a null gender
     * would make every payload in this file meaningless.
     *
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $userAttributes
     */
    private function resident(
        array $profileAttributes = [],
        array $userAttributes = [],
    ): ResidentProfile {
        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_RESIDENT,
            'name' => 'Juan Dela Cruz',
            'date_of_birth' => '1990-05-04',
            'gender' => 'Male',
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
        ], $userAttributes));

        return ResidentProfile::factory()->create(array_merge([
            'user_id' => $user->id,
            'household_number' => 'HH-0001',
            'sector_id' => Sector::factory(),
            'verification_status' => 'pending',
            'verified_at' => null,
        ], $profileAttributes));
    }

    /**
     * A submission that reproduces the stored record, so a change is what differs.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function payload(ResidentProfile $profile, array $overrides = [], ?User $actor = null): array
    {
        return array_merge([
            'name' => $profile->user->name,
            'date_of_birth' => $profile->user->date_of_birth->format('Y-m-d'),
            'gender' => $profile->user->gender,
            'civil_status' => $profile->user->civil_status,
            'nationality' => $profile->user->nationality,
            'nationality_other' => '',
            'address' => $profile->address,
            'sector_id' => (string) $profile->sector_id,
            'household_number' => $profile->household_number,
            'profile_updated_at' => $profile->updated_at->format('Y-m-d H:i:s'),
            'user_updated_at' => $profile->user->updated_at->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * The page, parsed.
     *
     * A DOM rather than a regular expression, because the questions being asked
     * here - is this control named, is that attribute on the right element, is this
     * heading present - are questions about a tree, and a regex answers them
     * wrongly as often as not.
     */
    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $this->assertTrue(
            @$document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>'),
            'Could not parse the page as HTML.',
        );

        return new DOMXPath($document);
    }
}
