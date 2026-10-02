<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\KagawadSectorResidents;
use App\Support\NavigationBadges;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Staff archiving of a resident, and every consequence of it.
 *
 * An archive is four nullable columns and one predicate, and it is easy to write
 * that much and still ship a feature that quietly breaks the barangay. The ways
 * this feature can be wrong, and what each section below defends against:
 *
 *   1. IT DELETES. The whole point is that history survives: the profile row, the
 *      household number, the requests, the relief rows, the audit entries.
 *   2. IT STRANDS WORK. A document request that can never be approved, or a
 *      packet that can never be released, is a household the office has lost.
 *   3. IT DOES NOT ACTUALLY CLOSE THE ACCOUNT. Signing in must fail, a session
 *      already open must die, and a Kagawad must stop being able to find the
 *      household - including in the HTML, not merely by an unlinked page.
 *   4. IT STRANDS THE HOUSEHOLD NUMBER. Reissuing a number to a second household
 *      and restoring the first onto it corrupts the next relief batch silently.
 *
 * Every assertion reads a parsed document or the database. None assert on a Blade
 * source string, because a source string passes just as happily when the markup
 * around it is malformed.
 */
class ResidentArchiveTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // 1. Who may archive, and who may not
    // -----------------------------------------------------------------

    /**
     * The two roles the owner named, and only those two.
     *
     * Asserted as the whole set rather than as two passing cases, so a third role
     * being added to the archive routes later fails here instead of shipping.
     */
    #[Test]
    public function a_secretary_and_an_admin_may_archive_and_restore(): void
    {
        foreach ([User::ROLE_SECRETARY, User::ROLE_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_verified' => true]);
            $profile = $this->resident();

            $this->actingAs($actor)
                ->from(route('staff.residents.show', $profile))
                ->post(route('staff.residents.archive', $profile), [
                    'archive_reason' => 'Moved away',
                ])->assertRedirect(route('staff.residents.show', $profile));

            $this->assertTrue($profile->user->fresh()->isArchived(), "A $role could not archive.");

            $this->actingAs($actor)
                ->from(route('staff.residents.show', $profile))
                ->post(route('staff.residents.restore', $profile))
                ->assertRedirect(route('staff.residents.show', $profile));

            $this->assertFalse($profile->user->fresh()->isArchived(), "A $role could not restore.");
        }
    }

    /**
     * The three roles that may not, each refused the way the middleware refuses.
     *
     * A Kagawad and a resident are signed in, so they get the 403 the role
     * middleware gives a wrong role. A guest is not signed in at all, so it gets
     * the redirect to login that the middleware gives a guest - the two are
     * different answers and asserting the wrong one would mean the test passes
     * against a 403 that should have been a login redirect, or the reverse.
     */
    #[Test]
    #[DataProvider('unauthorisedRoles')]
    public function a_kagawad_a_resident_and_a_guest_may_not_archive(string $role, int $expectedStatus): void
    {
        $actor = $role === 'guest'
            ? null
            : User::factory()->create(['role' => $role, 'is_verified' => true]);

        $profile = $this->resident();

        $response = $actor === null
            ? $this->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away'])
            : $this->actingAs($actor)->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away']);

        $response->assertStatus($expectedStatus);

        $this->assertFalse($profile->user->fresh()->isArchived());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function unauthorisedRoles(): array
    {
        return [
            'kagawad' => [User::ROLE_KAGAWAD, 403],
            'resident' => [User::ROLE_RESIDENT, 403],
            'guest' => ['guest', 302],
        ];
    }

    /**
     * Refusal has to be symmetric. A restore is a write on the same record, so a
     * role that cannot archive cannot undo an archive either - otherwise the pair
     * of routes is a way to reopen an account that only one role is trusted with.
     */
    #[Test]
    public function a_resident_may_not_restore(): void
    {
        $profile = $this->resident(archived: true);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_RESIDENT, 'is_verified' => true]))
            ->post(route('staff.residents.restore', $profile))
            ->assertStatus(403);

        $this->assertTrue($profile->user->fresh()->isArchived());
    }

    // -----------------------------------------------------------------
    // 2. What the action requires and writes
    // -----------------------------------------------------------------

    /**
     * A reason is required, and only the four the model offers are accepted.
     *
     * Both halves matter. Required, because an archive with no stated reason is a
     * household removed from every list with nothing on the record to explain it.
     * Closed, because the reasons have to stay countable - "Moved away: 34" is a
     * report and free text is not.
     */
    #[Test]
    #[DataProvider('invalidReasons')]
    public function the_archive_requires_a_reason_from_the_closed_list(?string $reason): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident();

        $this->actingAs($secretary)
            ->from(route('staff.residents.index'))
            ->post(route('staff.residents.archive', $profile), array_filter([
                'archive_reason' => $reason,
            ], fn ($value): bool => $value !== null))
            ->assertRedirect(route('staff.residents.index'))
            ->assertSessionHasErrors('archive_reason');

        $this->assertFalse($profile->user->fresh()->isArchived());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function invalidReasons(): array
    {
        return [
            'omitted' => [null],
            'blank' => [''],
            'not in the list' => ['Went to Cebu'],
            'a slug rather than the stored string' => ['moved_away'],
        ];
    }

    /**
     * Every reason in the list is accepted and stored exactly as given.
     */
    #[Test]
    public function every_offered_reason_is_accepted(): void
    {
        foreach (User::archiveReasons() as $reason) {
            $profile = $this->resident();

            $this->actingAs($this->secretary())
                ->post(route('staff.residents.archive', $profile), ['archive_reason' => $reason])
                ->assertRedirect(route('staff.residents.show', $profile));

            $this->assertSame($reason, $profile->user->fresh()->archive_reason);
        }
    }

    /**
     * The note is optional.
     */
    #[Test]
    public function the_note_is_optional(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Deceased'])
            ->assertSessionHasNoErrors();

        $this->assertNull($profile->user->fresh()->archive_note);
    }

    /**
     * All four columns are written together, with the actor and the time.
     *
     * Asserted as a set because a partial write is the failure this feature has
     * rather than has not: an account with a reason but no timestamp reads as live
     * to isArchived(), and one with a timestamp but no archived_by cannot say who
     * closed it - which is the first question anybody asks of this table.
     */
    #[Test]
    public function archiving_writes_the_actor_the_time_the_reason_and_the_note(): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident();

        $this->actingAs($secretary)->post(route('staff.residents.archive', $profile), [
            'archive_reason' => 'Moved away',
            'archive_note' => 'Relocated to Taguig in March.',
        ]);

        $user = $profile->user->fresh();

        $this->assertTrue($user->isArchived());
        $this->assertNotNull($user->archived_at);
        $this->assertSame($secretary->id, $user->archived_by);
        $this->assertSame('Moved away', $user->archive_reason);
        $this->assertSame('Relocated to Taguig in March.', $user->archive_note);
    }

    /**
     * Nothing about the resident is destroyed or rewritten.
     *
     * The account, the profile, the household number and the verification status
     * are all exactly as they were. An archive that cleared verification_status
     * would make a restore unable to bring the household back to Verified, and one
     * that touched the household number would release it to a second family.
     */
    #[Test]
    public function archiving_keeps_the_profile_the_household_number_and_the_verification_status(): void
    {
        $profile = $this->resident(
            profileAttributes: ['verification_status' => 'verified', 'verified_at' => now()],
        );

        $before = [
            'household' => $profile->household_number,
            'verification' => $profile->verification_status,
            'verified_at' => $profile->verified_at?->toDateTimeString(),
            'sector' => $profile->sector_id,
            'address' => $profile->address,
        ];

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Duplicate record']);

        $after = $profile->fresh();

        $this->assertSame($before['household'], $after->household_number);
        $this->assertSame($before['verification'], $after->verification_status);
        $this->assertSame($before['verified_at'], $after->verified_at?->toDateTimeString());
        $this->assertSame($before['sector'], $after->sector_id);
        $this->assertSame($before['address'], $after->address);
        $this->assertDatabaseHas('resident_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('users', ['id' => $profile->user_id]);
    }

    /**
     * Archiving twice, or restoring something that was never archived, is refused
     * rather than silently repeated.
     *
     * Without this a replayed form would overwrite archived_at with a new time and
     * archived_by with whoever replayed it - rewriting who closed an account from
     * a double-click.
     */
    #[Test]
    public function archiving_twice_is_refused(): void
    {
        $profile = $this->resident(archived: true);

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away'])
            ->assertStatus(409);

        $this->assertSame('Moved away', $profile->user->fresh()->archive_reason);
    }

    #[Test]
    public function restoring_something_that_is_not_archived_is_refused(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.restore', $profile))
            ->assertStatus(409);
    }

    // -----------------------------------------------------------------
    // 3. Open document requests block the archive
    // -----------------------------------------------------------------

    /**
     * A request awaiting a decision, and a packet ready for pickup, each refuse
     * the whole archive and name themselves.
     *
     * The refusal has to carry the references, not just a count: "you cannot
     * archive this yet" with no way forward is a dead end, and the Secretary needs
     * to be sent to the exact rows in the way.
     */
    #[Test]
    #[DataProvider('blockingRequestStatuses')]
    public function an_open_document_request_refuses_the_archive_and_is_linked(string $status): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident();
        $request = DocumentRequest::factory()->forResident($profile->user)->status($status)->create();

        $response = $this->actingAs($secretary)
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away']);

        $response->assertRedirect(route('staff.residents.show', $profile));
        $response->assertSessionHas('error');
        $response->assertSessionHas('archive_blocked');

        $this->assertFalse($profile->user->fresh()->isArchived(), 'The archive went ahead with work still open.');

        // The linked list is the same set the message describes, so the reader is
        // sent to the rows that are actually in the way.
        $blocked = session('archive_blocked');
        $this->assertCount(1, $blocked);
        $this->assertSame($request->reference_number, $blocked[0]['reference_number']);
        $this->assertSame(route('staff.requests.show', $request), $blocked[0]['url']);
        $this->assertSame($status, $blocked[0]['status']);

        // And the rendered page carries a link to it, not only the session payload.
        $this->get(route('staff.residents.show', $profile))
            ->assertSee($request->reference_number)
            ->assertSee(route('staff.requests.show', $request));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockingRequestStatuses(): array
    {
        return [
            'awaiting a decision, submitted' => [DocumentRequest::STATUS_SUBMITTED],
            'awaiting a decision, under review' => [DocumentRequest::STATUS_UNDER_REVIEW],
            'ready for pickup' => [DocumentRequest::STATUS_READY],
        ];
    }

    /**
     * Two open requests, so the message counts them and links both.
     */
    #[Test]
    public function every_open_request_is_named_not_just_the_first(): void
    {
        $profile = $this->resident();
        $first = DocumentRequest::factory()->forResident($profile->user)->submitted()->create();
        $second = DocumentRequest::factory()->forResident($profile->user)->ready()->create();

        $response = $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away']);

        $blocked = session('archive_blocked');

        $this->assertCount(2, $blocked);
        $this->assertEqualsCanonicalizing(
            [$first->reference_number, $second->reference_number],
            array_column($blocked, 'reference_number'),
        );

        // Oldest first, which is the order the office has been waiting in.
        $this->assertSame($first->reference_number, $blocked[0]['reference_number']);

        $this->assertStringContainsString('2', session('error'));
    }

    /**
     * Finished requests do not block. Claimed and Rejected are history, and a
     * household with only those can be archived - otherwise the guard would make
     * an archive impossible for any household that had ever used the office.
     */
    #[Test]
    #[DataProvider('nonBlockingRequestStatuses')]
    public function a_finished_request_does_not_block_the_archive(string $status): void
    {
        $profile = $this->resident();
        DocumentRequest::factory()->forResident($profile->user)->status($status)->create();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($profile->user->fresh()->isArchived());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonBlockingRequestStatuses(): array
    {
        return [
            'collected' => [DocumentRequest::STATUS_CLAIMED],
            'rejected' => [DocumentRequest::STATUS_REJECTED],
        ];
    }

    /**
     * Someone else's open request is not this household's problem.
     *
     * The guard is scoped to the resident being archived. An unscoped version would
     * refuse every archive in the barangay whenever any one household had a request
     * in flight, which is a bug that looks like working software.
     */
    #[Test]
    public function another_households_open_request_does_not_block(): void
    {
        $profile = $this->resident();
        $other = $this->resident(household: 'HH-0002');

        DocumentRequest::factory()->forResident($other->user)->submitted()->create();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($profile->user->fresh()->isArchived());
    }

    /**
     * A PENDING VERIFICATION does not block, and that is the point of allowing it.
     *
     * Archiving an unapproved registration is how a staff member says no to one -
     * there is no work in flight on it, and the household never became real as far
     * as the office is concerned. So this must succeed, which is the opposite of
     * the open-request case above and worth asserting as its own test.
     */
    #[Test]
    public function a_pending_verification_does_not_block_the_archive(): void
    {
        $profile = $this->resident(profileAttributes: [
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->verification_status);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Duplicate record'])
            ->assertSessionHasNoErrors();

        $user = $profile->user->fresh();

        $this->assertTrue($user->isArchived());
        $this->assertSame('Duplicate record', $user->archive_reason);

        // And it is still pending underneath: the archive decided the registration,
        // it did not rewrite it into a rejection, so a restore brings it back to
        // the queue it came from.
        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->fresh()->verification_status);
    }

    // -----------------------------------------------------------------
    // 4. The account is actually closed
    // -----------------------------------------------------------------

    /**
     * An archived resident cannot sign in, and is told why.
     *
     * The message is asserted against the model's constant rather than a string
     * written here, so the sign-in refusal and the mid-session refusal cannot
     * drift apart - they are the same sentence to the same person.
     */
    #[Test]
    public function an_archived_resident_cannot_sign_in_and_is_told_why(): void
    {
        $profile = $this->resident(archived: true);
        $email = $profile->user->email;
        $password = 'correct-horse-battery-staple';

        $profile->user->update(['password' => bcrypt($password)]);

        $this->post(route('login'), ['email' => $email, 'password' => $password])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            [User::ARCHIVED_LOGIN_MESSAGE],
            session('errors')->get('email'),
            'The archived resident was not told why the account is closed.',
        );

        $this->assertGuest();
    }

    /**
     * A wrong password on an archived account still fails as a wrong password.
     *
     * Checking the archive before checking the credential would confirm which
     * addresses are archived to anybody who asked, and would tell a genuinely
     * archived resident their password is "wrong" rather than that their account
     * is closed. The generic auth failure is the correct answer here.
     */
    #[Test]
    public function a_wrong_password_on_an_archived_account_still_fails_as_a_wrong_password(): void
    {
        $profile = $this->resident(archived: true);

        $this->post(route('login'), ['email' => $profile->user->email, 'password' => 'not-the-password'])
            ->assertSessionHasErrors('email');

        $this->assertSame([trans('auth.failed')], session('errors')->get('email'));
    }

    /**
     * The reason reaches the screen, not just the session.
     *
     * The tests above only ever looked at session('errors'), which is enough to
     * prove the controller did its job and nothing more. The login page then
     * rendered no $errors at all, so every rejected sign-in - a wrong password,
     * an unknown address, and above all the archived-account refusal - sent the
     * resident back to an unchanged form with no explanation. Following the
     * redirect and reading the page that actually comes back is what makes that
     * failure visible.
     */
    #[Test]
    public function the_login_page_shows_why_a_sign_in_was_refused(): void
    {
        $archived = $this->resident(archived: true);
        $password = 'correct-horse-battery-staple';
        $archived->user->update(['password' => bcrypt($password)]);

        $this->from(route('login'))
            ->post(route('login'), ['email' => $archived->user->email, 'password' => $password])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), ['email' => $archived->user->email, 'password' => $password])
            ->assertOk()
            ->assertSee(User::ARCHIVED_LOGIN_MESSAGE);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), ['email' => $archived->user->email, 'password' => 'not-the-password'])
            ->assertOk()
            ->assertSee(trans('auth.failed'))
            ->assertDontSee(User::ARCHIVED_LOGIN_MESSAGE);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), ['email' => 'nobody@example.test', 'password' => $password])
            ->assertOk()
            ->assertSee(trans('auth.failed'))
            ->assertDontSee(User::ARCHIVED_LOGIN_MESSAGE);
    }

    /**
     * A session that was already open when the archive happened dies on the very
     * next request, and carries the same message.
     *
     * The archive is not only a rule about the next sign-in. A resident who was
     * signed in when a Secretary closed their account is, at that moment, still
     * holding a working session - and without this they would keep browsing until
     * they happened to reach a page behind the role middleware.
     */
    #[Test]
    public function an_already_signed_in_archived_resident_is_ended_on_the_next_request(): void
    {
        $profile = $this->resident();

        $this->actingAs($profile->user)->get(route('resident.requests.index'))->assertOk();

        $profile->user->update(['archived_at' => now()]);

        $this->get(route('resident.requests.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * The same refusal is shown to the resident and the archived account is signed
     * out rather than merely refused, so the session row is not left behind.
     */
    #[Test]
    public function ending_a_live_session_signs_the_account_out(): void
    {
        $profile = $this->resident(archived: true);

        $this->actingAs($profile->user)
            ->get(route('resident.requests.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // 5. The household number stays reserved
    // -----------------------------------------------------------------

    /**
     * A restore refuses when a second household has taken the number meanwhile.
     *
     * relief_distributions is unique on (relief_batch_id, household_number), so two
     * profiles on one number means the next batch keeps one row and drops the
     * other: a family receives nothing while the record says they were served.
     * This is the check that stops the archive from setting that up.
     */
    #[Test]
    public function a_restore_refuses_when_another_household_holds_the_number(): void
    {
        $profile = $this->resident(archived: true, household: 'HH-0001');
        $claimant = $this->resident(household: 'HH-0001');

        $response = $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.restore', $profile));

        $response->assertSessionHas('error');
        $response->assertSessionHas('restore_blocked');

        $blocked = session('restore_blocked');
        $this->assertSame(route('staff.residents.show', $claimant), $blocked['url']);

        $this->assertTrue($profile->user->fresh()->isArchived(), 'The restore went ahead onto a taken number.');
    }

    /**
     * The blocked restore says who holds the number and links to that record, so
     * the Secretary knows which household to fix.
     */
    #[Test]
    public function a_blocked_restore_names_the_other_household_and_links_to_it(): void
    {
        $profile = $this->resident(archived: true, household: 'HH-0001');
        $claimant = $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Clara Santiago']);

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.restore', $profile));

        $this->assertStringContainsString('Clara Santiago', session('error'));

        $this->get(route('staff.residents.show', $profile))
            ->assertSee('Clara Santiago')
            ->assertSee(route('staff.residents.show', $claimant));
    }

    /**
     * With the number unclaimed, the restore succeeds. The refusal above has to be
     * about a collision and not about restoring in general.
     */
    #[Test]
    public function a_restore_succeeds_when_the_number_is_unclaimed(): void
    {
        $profile = $this->resident(archived: true, household: 'HH-0001');

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.restore', $profile))
            ->assertSessionHasNoErrors();

        $this->assertFalse($profile->user->fresh()->isArchived());
    }

    // -----------------------------------------------------------------
    // 6. Restore keeps what the archive must not have changed
    // -----------------------------------------------------------------

    /**
     * All four archive columns are cleared together, and the household number,
     * sector and verification status are untouched.
     *
     * Asserted as the full row so a partial clear is caught: a live account still
     * carrying archived_at is a false answer to isArchived() for every query in the
     * application, and one still carrying a reason reads as a record that is
     * somehow still closed.
     */
    #[Test]
    public function a_restore_clears_every_archive_column(): void
    {
        $profile = $this->resident(
            profileAttributes: ['verification_status' => 'verified', 'verified_at' => now()],
            archived: true,
            archiveReason: 'Moved away',
            archiveNote: 'Relocated to Taguig in March.',
        );

        $before = [
            'household' => $profile->household_number,
            'sector' => $profile->sector_id,
            'verification' => $profile->verification_status,
            'verified_at' => $profile->verified_at?->toDateTimeString(),
        ];

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.restore', $profile))
            ->assertRedirect(route('staff.residents.show', $profile));

        $user = $profile->user->fresh();
        $after = $profile->fresh();

        foreach (['archived_at', 'archived_by', 'archive_reason', 'archive_note'] as $column) {
            $this->assertNull($user->{$column}, "restore() left {$column} set.");
        }

        $this->assertSame($before['household'], $after->household_number);
        $this->assertSame($before['sector'], $after->sector_id);
        $this->assertSame($before['verification'], $after->verification_status);
        $this->assertSame($before['verified_at'], $after->verified_at?->toDateTimeString());
    }

    /**
     * A restore brings the household back exactly where it was, not merely back
     * somewhere valid.
     */
    #[Test]
    public function a_restored_resident_returns_to_the_verification_queue_it_came_from(): void
    {
        $profile = $this->resident(
            profileAttributes: ['verification_status' => ResidentProfile::VERIFICATION_PENDING],
            archived: true,
        );

        $this->actingAs($this->secretary())->post(route('staff.residents.restore', $profile));

        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->fresh()->verification_status);

        $this->actingAs($this->secretary())
            ->get(route('staff.verifications.index'))
            ->assertSee($profile->user->name);
    }

    // -----------------------------------------------------------------
    // 7. The audit trail
    // -----------------------------------------------------------------

    /**
     * Both actions are written, with the reason and note in the context rather
     * than only in the description.
     *
     * The description is what a reader skims; the context is what answers "what
     * exactly did they type". After a restore the four columns are null, so the
     * archive entry is the ONLY place the reason still exists - which is the
     * reason a restore does not erase history when it clears the columns.
     */
    #[Test]
    public function archiving_and_restoring_are_both_audited_with_the_reason(): void
    {
        $secretary = $this->secretary();
        $profile = $this->resident();

        $this->actingAs($secretary)->post(route('staff.residents.archive', $profile), [
            'archive_reason' => 'Moved away',
            'archive_note' => 'Relocated to Taguig in March.',
        ]);

        $archiveEntry = AuditLog::query()
            ->where('action', 'resident.archived')
            ->where('subject_type', ResidentProfile::class)
            ->where('subject_id', $profile->id)
            ->sole();

        $this->assertSame($secretary->id, $archiveEntry->user_id);
        $this->assertSame('Moved away', $archiveEntry->context['reason']);
        $this->assertSame('Relocated to Taguig in March.', $archiveEntry->context['note']);

        $this->actingAs($secretary)->post(route('staff.residents.restore', $profile));

        $restoreEntry = AuditLog::query()
            ->where('action', 'resident.restored')
            ->where('subject_id', $profile->id)
            ->sole();

        // The restore records what it undid, read before the columns were cleared.
        $this->assertSame('Moved away', $restoreEntry->context['previous_reason']);
        $this->assertSame('Relocated to Taguig in March.', $restoreEntry->context['previous_note']);
    }

    /**
     * A refused archive writes nothing. An audit entry for an action that did not
     * happen is worse than none: the log is read as a record of what staff did to a
     * household.
     */
    #[Test]
    public function a_refused_archive_writes_no_audit_entry(): void
    {
        $profile = $this->resident();
        DocumentRequest::factory()->forResident($profile->user)->submitted()->create();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.show', $profile))
            ->post(route('staff.residents.archive', $profile), ['archive_reason' => 'Moved away']);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'resident.archived']);
    }

    /**
     * A submission with no reason writes nothing either, for the same reason.
     */
    #[Test]
    public function an_archive_with_no_reason_writes_no_audit_entry(): void
    {
        $profile = $this->resident();

        $this->actingAs($this->secretary())
            ->from(route('staff.residents.index'))
            ->post(route('staff.residents.archive', $profile));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'resident.archived']);
    }

    // -----------------------------------------------------------------
    // 8. Lists, filters and what the reader can see
    // -----------------------------------------------------------------

    /**
     * The directory hides archived households by default, and says so.
     *
     * The default is the deliberate exception to "filters default to All": this is
     * the working directory and a list of people who have left is not work. What
     * is asserted is the pair - hidden AND stated - because a list that hides
     * silently is indistinguishable from one that has lost them.
     */
    #[Test]
    public function the_directory_hides_archived_households_by_default_and_says_so(): void
    {
        $this->secretary();

        $live = $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live']);
        $gone = $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ben Gone'], archived: true);

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_SECRETARY, 'is_verified' => true]))
            ->get(route('staff.residents.index'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('Ana Live', $html);
        $this->assertStringNotContainsString('Ben Gone', $html, 'An archived household is on the default list.');

        // The hint, with the link that shows them.
        $this->assertStringContainsString('Archived records are hidden. Show them', $html);
        $this->assertStringContainsString(route('staff.residents.index', ['record' => 'all']), $html);
    }

    /**
     * The Archived filter shows them, and All shows both.
     */
    #[Test]
    public function the_record_filter_shows_archived_and_all_records(): void
    {
        $secretary = $this->secretary();

        $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live']);
        $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ben Gone'], archived: true);

        $archived = $this->actingAs($secretary)->get(route('staff.residents.index', ['record' => 'archived']));
        $archived->assertOk()->assertSee('Ben Gone')->assertDontSee('Ana Live');

        $all = $this->actingAs($secretary)->get(route('staff.residents.index', ['record' => 'all']));
        $all->assertOk()->assertSee('Ben Gone')->assertSee('Ana Live');
    }

    /**
     * An unrecognised filter value falls back to Active rather than widening the
     * list.
     *
     * The parameter arrives in a URL, so a typo and a hand-edited link both land
     * here. Defaulting to All would let ?record=nonsense show archived households
     * while the filter bar still read "Active".
     */
    #[Test]
    public function an_unknown_record_filter_falls_back_to_active(): void
    {
        $secretary = $this->secretary();

        $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live']);
        $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ben Gone'], archived: true);

        $this->actingAs($secretary)
            ->get(route('staff.residents.index', ['record' => 'nonsense']))
            ->assertOk()
            ->assertSee('Ana Live')
            ->assertDontSee('Ben Gone');
    }

    /**
     * The filter survives the search and sector filters, so a Secretary looking
     * for an archived household can still find them.
     */
    #[Test]
    public function the_record_filter_combines_with_the_search_filter(): void
    {
        $secretary = $this->secretary();

        $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live']);
        $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ben Gone'], archived: true);

        $this->actingAs($secretary)
            ->get(route('staff.residents.index', ['record' => 'archived', 'q' => 'Ben']))
            ->assertOk()
            ->assertSee('Ben Gone')
            ->assertDontSee('Ana Live');
    }

    /**
     * An archived row is greyed, badged, and offers Restore instead of Archive and
     * Edit.
     *
     * Read from the parsed document rather than from the HTML string, because the
     * question is which controls are on this row and in what state - a question
     * about a tree.
     */
    #[Test]
    public function an_archived_row_offers_restore_instead_of_archive_and_edit(): void
    {
        $secretary = $this->secretary();

        $live = $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live']);
        $gone = $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ben Gone'], archived: true);

        $xpath = $this->xpath(
            $this->actingAs($secretary)
                ->get(route('staff.residents.index', ['record' => 'all']))
                ->getContent(),
        );

        $liveRow = $this->rowFor($xpath, 'Ana Live');
        $goneRow = $this->rowFor($xpath, 'Ben Gone');

        // The archived row is greyed and badged.
        $this->assertStringContainsString('opacity-', $goneRow['class']);
        $this->assertStringContainsString('Archived', $goneRow['text']);

        // Restore replaces Archive and Edit.
        $this->assertStringContainsString('Restore Ben Gone', $goneRow['text']);
        $this->assertStringNotContainsString('Archive Ben Gone', $goneRow['text']);
        $this->assertStringNotContainsString('Edit Ben Gone', $goneRow['text']);

        // The live row is untouched by any of this.
        $this->assertStringContainsString('Edit Ana Live', $liveRow['text']);
        $this->assertStringContainsString('Archive Ana Live', $liveRow['text']);
        $this->assertStringNotContainsString('Archived', $liveRow['text']);

        // And the detail page of an archived household offers Restore, not Edit.
        $this->actingAs($secretary)
            ->get(route('staff.residents.show', $gone))
            ->assertSee('Restore')
            ->assertDontSee(route('staff.residents.edit', $gone));

        $this->actingAs($secretary)
            ->get(route('staff.residents.show', $live))
            ->assertSee(route('staff.residents.edit', $live));
    }

    /**
     * Hiding the Edit control is not the whole of the rule: the URL still works.
     *
     * A bookmark, a stale tab, or a Secretary working from muscle memory all reach
     * the edit form directly. Asserted on both halves - the form and the write -
     * because a guard on the page alone still lets a POST through, and a guard on
     * the write alone still shows a form that cannot be saved.
     */
    #[Test]
    public function an_archived_household_cannot_be_edited_by_url_either(): void
    {
        $secretary = $this->secretary();
        $gone = $this->resident(userAttributes: ['name' => 'Ben Gone'], archived: true);

        $this->actingAs($secretary)
            ->get(route('staff.residents.edit', $gone))
            ->assertStatus(409);

        $this->actingAs($secretary)
            ->put(route('staff.residents.update', $gone), $this->validEditPayload($gone, [
                'household_number' => 'HH-9999',
            ]))
            ->assertStatus(409);

        $this->assertSame($gone->household_number, $gone->fresh()->household_number);
    }

    /**
     * And an active household is still editable, so the guard above is not simply
     * refusing everything.
     */
    #[Test]
    public function an_active_household_is_still_editable(): void
    {
        $secretary = $this->secretary();
        $live = $this->resident();

        $this->actingAs($secretary)
            ->get(route('staff.residents.edit', $live))
            ->assertOk();
    }

    /**
     * The archive control on a live row is a button that opens a dialog carrying a
     * required reason select and an optional note.
     *
     * The reason being inside the dialog is the point: a dialog with no field to
     * fill in gets dismissed unread forty times, and a reason collected somewhere
     * else is a second form to forget.
     */
    #[Test]
    public function the_archive_dialog_carries_a_required_reason_and_an_optional_note(): void
    {
        $profile = $this->resident();

        $html = $this->actingAs($this->secretary())
            ->get(route('staff.residents.index'))
            ->getContent();

        $xpath = $this->xpath($html);

        $dialog = $xpath->query('//form[contains(@action, "'.$profile->id.'/archive")]')->item(0);

        $this->assertNotNull($dialog, 'No archive form on the page.');

        $reason = $xpath->query('.//select[@name="archive_reason"]', $dialog)->item(0);
        $note = $xpath->query('.//textarea[@name="archive_note"]', $dialog)->item(0);

        $this->assertNotNull($reason, 'The archive dialog has no reason select.');
        $this->assertInstanceOf(DOMElement::class, $reason);

        $this->assertNotNull($note, 'The archive dialog has no note field.');
        $this->assertInstanceOf(DOMElement::class, $note);

        // hasAttribute rather than getAttribute: a valueless HTML boolean attribute
        // parses to an empty string, so comparing the value would fail against
        // markup that is exactly right.
        $this->assertTrue($reason->hasAttribute('required'), 'The archive reason is not required in the markup.');
        $this->assertFalse($note->hasAttribute('required'), 'The archive note must stay optional in the markup.');

        // Exactly the four offered reasons, and nothing else.
        $offered = [];

        foreach ($xpath->query('.//select[@name="archive_reason"]/option', $dialog) as $option) {
            $this->assertInstanceOf(DOMElement::class, $option);

            $value = $option->getAttribute('value');

            if ($value !== '') {
                $offered[] = $value;
            }
        }

        $this->assertSame(User::archiveReasons(), $offered);

        // And the consequence line, word for word.
        $this->assertStringContainsString(
            'They will no longer be able to sign in or request documents, and will be left out of new relief batches. Their history is kept and you can restore them.',
            $html,
        );
    }

    /**
     * The detail page carries the dialog for whichever state the record is in.
     *
     * Both halves asserted against the page that shows them: an archived household
     * must not be one keystroke from being archived again, and an archived one must
     * not be hiding its way back.
     */
    #[Test]
    public function the_detail_page_carries_the_dialog_for_the_state_it_is_in(): void
    {
        $active = $this->resident();
        $archived = $this->resident(household: 'HH-0002', archived: true);

        $activePage = $this->xpath(
            $this->actingAs($this->secretary())
                ->get(route('staff.residents.show', $active))
                ->getContent(),
        );

        $this->assertGreaterThan(
            0,
            $activePage->query('//form[contains(@action, "'.$active->id.'/archive")]')->length,
            'No archive form on an active resident detail page.',
        );

        $archivedPage = $this->xpath(
            $this->actingAs($this->secretary())
                ->get(route('staff.residents.show', $archived))
                ->getContent(),
        );

        $this->assertGreaterThan(
            0,
            $archivedPage->query('//form[contains(@action, "'.$archived->id.'/restore")]')->length,
            'No restore form on an archived resident detail page.',
        );

        // And an archived household is not offered the archive a second time.
        $this->assertSame(
            0,
            $archivedPage->query('//form[contains(@action, "'.$archived->id.'/archive")]')->length,
            'An archived household still offers the archive action.',
        );
    }

    /**
     * User Accounts badges an archived account and can filter to them.
     */
    #[Test]
    public function user_accounts_badges_and_filters_archived_accounts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_verified' => true]);

        $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana Live', 'email' => 'ana@example.test']);
        $gone = $this->resident(
            household: 'HH-0002',
            userAttributes: ['name' => 'Ben Gone', 'email' => 'ben@example.test'],
            archived: true,
        );

        $this->actingAs($admin)
            ->get(route('staff.accounts.index'))
            ->assertOk()
            ->assertSee('Ana Live')
            ->assertDontSee('Ben Gone')
            ->assertSee('Archived records are hidden. Show them');

        $this->actingAs($admin)
            ->get(route('staff.accounts.index', ['record' => 'archived']))
            ->assertOk()
            ->assertSee('Ben Gone')
            ->assertDontSee('Ana Live');

        // The role filter still reaches them, so the two combine rather than fight.
        $this->actingAs($admin)
            ->get(route('staff.accounts.index', ['record' => 'archived', 'role' => User::ROLE_RESIDENT]))
            ->assertOk()
            ->assertSee($gone->user->email);
    }

    /**
     * The verification queue leaves archived households out entirely - not under
     * one status, not under another.
     *
     * Archiving a pending registration IS the decision, so an archived profile has
     * nothing left to decide and must not sit in the queue claiming otherwise. And
     * the pending count has to agree with the list, or the badge says there is work
     * that the page cannot show.
     */
    #[Test]
    public function the_verification_queue_excludes_archived_households_and_counts_to_match(): void
    {
        $secretary = $this->secretary();

        $this->resident(
            household: 'HH-0001',
            userAttributes: ['name' => 'Ana Pending'],
            profileAttributes: ['verification_status' => ResidentProfile::VERIFICATION_PENDING],
        );

        $this->resident(
            household: 'HH-0002',
            userAttributes: ['name' => 'Ben Archived'],
            profileAttributes: ['verification_status' => ResidentProfile::VERIFICATION_PENDING],
            archived: true,
        );

        $response = $this->actingAs($secretary)->get(route('staff.verifications.index'));

        $response->assertOk();
        $response->assertSee('Ana Pending');
        $response->assertDontSee('Ben Archived');

        // The badge is the same figure, so the sidebar and the page cannot disagree.
        $this->assertSame(1, NavigationBadges::forUser($secretary)['staff.verifications.index']['count'] ?? 0);
    }

    // -----------------------------------------------------------------
    // 9. Relief: new batches skip archived households, open ones do not
    // -----------------------------------------------------------------

    /**
     * A household archived BEFORE a new batch is opened is not on it.
     *
     * A checklist row is an instruction to go to an address. Sending a Kagawad to
     * a household the office has closed wastes the trip and leaves the batch
     * permanently short of collected, because nobody can ever tick it off.
     */
    #[Test]
    public function a_new_batch_does_not_seed_an_archived_household(): void
    {
        $sector = Sector::factory()->create();

        $live = $this->resident(household: 'HH-0001', sector: $sector);
        $gone = $this->resident(household: 'HH-0002', sector: $sector, archived: true);

        $this->makeLive($live);

        $batch = $this->openBatchThroughTheOffice([$sector->id]);

        $households = ReliefDistribution::query()
            ->where('relief_batch_id', $batch->id)
            ->pluck('household_number')
            ->all();

        $this->assertContains('HH-0001', $households);
        $this->assertNotContains('HH-0002', $households, 'An archived household was seeded onto a new batch.');

        // The batch's own household count agrees with its rows.
        $this->assertSame(1, $batch->distributions()->count());
    }

    /**
     * A household archived AFTER the batch was opened keeps its row.
     *
     * This is the half that is easy to get wrong. Deleting the row would erase the
     * fact that the batch was offered to that household and would move the count a
     * Kagawad is working to, mid-distribution. The row stays, and is labelled.
     */
    #[Test]
    public function an_open_batch_keeps_its_row_for_a_household_archived_afterwards(): void
    {
        $sector = Sector::factory()->create();
        $household = $this->resident(household: 'HH-0001', sector: $sector);
        $this->makeLive($household);

        $batch = $this->openBatchThroughTheOffice([$sector->id]);

        $this->assertSame(1, $batch->distributions()->count());

        $household->user->update(['archived_at' => now()]);

        $this->assertSame(
            1,
            ReliefDistribution::query()->where('relief_batch_id', $batch->id)->count(),
            'The open batch lost a row because the household was archived.',
        );
    }

    /**
     * The Kagawad's checklist labels the archived row and offers no way to mark it
     * collected.
     *
     * "No Mark control", not a disabled one: the action cannot be completed, and a
     * disabled control on a phone in the street is a puzzle rather than an
     * instruction. The row is still counted in the sector totals, because that is
     * the number the office reconciles against.
     *
     * The row is written the way the office writes it - by opening the batch while
     * the household was still active - so the archive lands on it afterwards, which
     * is the only order in which this situation can arise.
     */
    #[Test]
    public function the_kagawad_checklist_labels_an_archived_row_and_offers_no_mark_control(): void
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $live = $this->resident(household: 'HH-0001', sector: $sector);
        $gone = $this->resident(household: 'HH-0002', sector: $sector);

        $this->makeLive($live);
        $this->makeLive($gone);

        $batch = $this->openBatchThroughTheOffice([$sector->id]);

        $this->actingAs($this->secretary())
            ->post(route('staff.residents.archive', $gone), ['archive_reason' => 'Moved away'])
            ->assertRedirect(route('staff.residents.show', $gone));

        $xpath = $this->xpath(
            $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent(),
        );

        $archivedRow = $this->listItemFor($xpath, 'HH-0002');

        $this->assertStringContainsString('Archived', $archivedRow['text']);
        $this->assertStringNotContainsString('Mark HH-0002 collected', $archivedRow['text']);

        // The live household is unaffected.
        $liveRow = $this->listItemFor($xpath, 'HH-0001');
        $this->assertStringContainsString('Mark HH-0001 collected', $liveRow['text']);
        $this->assertStringNotContainsString('Archived', $liveRow['text']);
    }

    /**
     * An archived household is gone from the Kagawad's resident list in the HTML.
     *
     * Asserted on the page source, not on a link being absent. Filtering in the
     * view would leave the household number sitting in the HTML for anybody reading
     * the source, which is the leak the sector scope exists to prevent; and an
     * archived household is not a household a Kagawad should be able to find at all.
     */
    #[Test]
    public function an_archived_household_is_absent_from_the_kagawad_resident_html(): void
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $this->resident(household: 'HH-0001', sector: $sector, userAttributes: ['name' => 'Ana Live']);
        $gone = $this->resident(
            household: 'HH-0002',
            sector: $sector,
            userAttributes: ['name' => 'Ben Gone', 'email' => 'ben@example.test'],
            archived: true,
        );

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->getContent();

        $this->assertStringContainsString('Ana Live', $html);
        $this->assertStringNotContainsString('Ben Gone', $html);
        $this->assertStringNotContainsString($gone->household_number, $html);
        $this->assertStringNotContainsString('ben@example.test', $html);

        // And the Kagawad's own counts agree with the list they link to.
        $counts = KagawadSectorResidents::counts([$sector->id], null);
        $this->assertSame(1, $counts['residents']);
    }

    /**
     * A Kagawad's dashboard badge and the list it links to cannot disagree about an
     * archived household.
     */
    #[Test]
    public function the_kagawad_resident_count_excludes_archived_households(): void
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $this->resident(household: 'HH-0001', sector: $sector);
        $this->resident(household: 'HH-0002', sector: $sector, archived: true);

        $this->assertSame(1, KagawadSectorResidents::counts([$sector->id], null)['residents']);
    }

    /**
     * The staff dashboard's pending figures exclude archived households too.
     */
    #[Test]
    public function the_staff_dashboard_pending_figures_exclude_archived_households(): void
    {
        $secretary = $this->secretary();

        $this->resident(
            household: 'HH-0001',
            userAttributes: ['name' => 'Ana Pending'],
            profileAttributes: ['verification_status' => ResidentProfile::VERIFICATION_PENDING],
        );

        $this->resident(
            household: 'HH-0002',
            userAttributes: ['name' => 'Ben Archived'],
            profileAttributes: ['verification_status' => ResidentProfile::VERIFICATION_PENDING],
            archived: true,
        );

        $html = $this->actingAs($secretary)->get(route('staff.dashboard'))->getContent();

        $this->assertStringContainsString('Ana Pending', $html);
        $this->assertStringNotContainsString('Ben Archived', $html);

        $this->assertSame(1, NavigationBadges::forUser($secretary)['staff.verifications.index']['count'] ?? 0);
    }

    // -----------------------------------------------------------------
    // 10. Non-archived behaviour is untouched
    // -----------------------------------------------------------------

    /**
     * With no archived account anywhere, every list and count is what it was.
     *
     * This is the test that would catch an archive predicate added with the wrong
     * polarity - an `archived()` scope that returned live accounts would hide every
     * resident in the barangay, and nothing else in this file would notice.
     */
    #[Test]
    public function nothing_changes_for_households_that_are_not_archived(): void
    {
        $secretary = $this->secretary();

        $this->resident(household: 'HH-0001', userAttributes: ['name' => 'Ana One']);
        $this->resident(household: 'HH-0002', userAttributes: ['name' => 'Ana Two']);
        $this->resident(household: 'HH-0003', userAttributes: ['name' => 'Ana Three']);

        $this->actingAs($secretary)
            ->get(route('staff.residents.index'))
            ->assertOk()
            ->assertSee('Ana One')
            ->assertSee('Ana Two')
            ->assertSee('Ana Three');

        // No hint, because nothing is being hidden.
        $this->actingAs($secretary)
            ->get(route('staff.residents.index'))
            ->assertDontSee('Archived records are hidden. Show them');

        // User Accounts unchanged. Admin, because that is the only role that page
        // is for, so a Secretary reading it proves nothing about the archive.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'is_verified' => true]))
            ->get(route('staff.accounts.index'))
            ->assertOk()
            ->assertSee('Ana One')
            ->assertSee('Ana Two')
            ->assertSee('Ana Three');

        $this->assertSame(0, User::query()->archived()->count());

        // Scoped to residents: the Secretary and the Admin this test signed in as
        // are active accounts too, and asserting on all of them would make the
        // count depend on the fixtures rather than on the archive.
        $this->assertSame(3, User::query()->active()->where('role', User::ROLE_RESIDENT)->count());
        $this->assertSame(0, User::query()->archived()->where('role', User::ROLE_RESIDENT)->count());
    }

    /**
     * A live resident can still sign in and still sign their own requests.
     *
     * The archive predicate sits in the middleware and the login request, both of
     * which every resident passes through on every request. This is the test that
     * says the added checks are false for an account that was never archived.
     */
    #[Test]
    public function a_live_resident_can_still_sign_in(): void
    {
        $profile = $this->resident();
        $password = 'correct-horse-battery-staple';

        $profile->user->update(['password' => bcrypt($password)]);

        $this->post(route('login'), ['email' => $profile->user->email, 'password' => $password])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($profile->user);
    }

    /**
     * And a live resident is still refused a request when they are RESTRICTED, with
     * the restricted wording rather than the archived one.
     *
     * The two states share a middleware block now. This asserts the messages did not
     * get swapped on the way in.
     */
    #[Test]
    public function a_restricted_resident_still_gets_the_restricted_message(): void
    {
        $profile = $this->resident();

        $profile->user->update(['restricted_until' => now()->addYear()]);

        $this->actingAs($profile->user)
            ->get(route('resident.requests.index'))
            ->assertStatus(403)
            ->assertSee('This account is temporarily restricted. Please contact the barangay office.');

        $this->assertStringNotContainsString(
            User::ARCHIVED_LOGIN_MESSAGE,
            'This account is temporarily restricted. Please contact the barangay office.',
        );

        // A restricted account that was never archived is not treated as archived.
        $this->assertFalse($profile->user->fresh()->isArchived());
    }

    // -----------------------------------------------------------------
    // 11. Query budget
    // -----------------------------------------------------------------

    /**
     * The archive predicate costs the Kagawad list nothing.
     *
     * One query for one resident and one for twenty, the same as before the
     * predicate existed: the archived check is a whereHas subselect inside the one
     * query, not a second lookup per row. A change that made this scale with the
     * page size would be a query per household on the page a Kagawad opens on a
     * phone.
     */
    #[Test]
    public function the_kagawad_resident_list_costs_the_same_at_one_and_twenty_households(): void
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector = Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $counts = [];

        // Measured at one household and at twenty, in the same test and against the
        // same code, because the second number is meaningless on its own.
        foreach ([1, 20] as $size) {
            while (ResidentProfile::query()->count() < $size) {
                $this->resident(
                    household: sprintf('HH-%04d', ResidentProfile::query()->count() + 1),
                    sector: $sector,
                    userAttributes: ['name' => 'Juan Dela Cruz'],
                );
            }

            $counts[$size] = $this->countKagawadResidentQueries($kagawad);
        }

        $this->assertSame(
            $counts[1],
            $counts[20],
            "The Kagawad resident list cost {$counts[1]} queries for one household and {$counts[20]} for twenty.",
        );

        $this->assertLessThanOrEqual(
            9,
            $counts[20],
            'The Kagawad resident list cost more queries than the fixed budget it was on before archiving existed.',
        );
    }

    // -----------------------------------------------------------------
    // 12. The audit labels
    // -----------------------------------------------------------------

    /**
     * Every action the application can write has an English label.
     *
     * Asserted over the action list rather than over the two new names, so an
     * action added later without a label fails here instead of rendering as a raw
     * slug in the audit log - which is what the fallback does.
     */
    #[Test]
    public function every_audit_action_has_an_english_label(): void
    {
        foreach (array_keys(AuditLog::actions()) as $action) {
            $label = AuditLog::actions()[$action];

            $this->assertIsString($label);
            $this->assertNotSame('', trim($label), "Audit action {$action} has no label.");

            // Capitalised words rather than the raw slug: "resident.archived"
            // failing this is the whole reason the label map exists.
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-z]+(?: [A-Za-z]+)+$/',
                $label,
                "Audit action {$action} is labelled '{$label}', which is not English prose.",
            );
        }

        // The two this feature writes are labelled, not left as slugs.
        $this->assertSame('Resident archived', AuditLog::actions()['resident.archived'] ?? null);
        $this->assertSame('Resident restored', AuditLog::actions()['resident.restored'] ?? null);
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
     * A verified resident, so it can be seeded onto a relief batch.
     */
    private function makeLive(ResidentProfile $profile): void
    {
        $profile->update([
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);
    }

    /**
     * The households handed out when a test does not care which number it gets.
     *
     * Sequential rather than a fixed default, because two households on one number
     * is a condition this feature reacts to: a test that builds two residents and
     * restores one of them would fail on the collision guard, which is the guard
     * working, not the test being wrong. A test that wants the collision asks for
     * the same number twice on purpose.
     */
    private static int $households = 0;

    /**
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $userAttributes
     */
    private function resident(
        array $profileAttributes = [],
        array $userAttributes = [],
        ?Sector $sector = null,
        ?string $household = null,
        bool $archived = false,
        ?string $archiveReason = null,
        ?string $archiveNote = null,
    ): ResidentProfile {
        $household ??= sprintf('HH-%04d', ++self::$households);

        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_RESIDENT,
            'name' => 'Juan Dela Cruz',
        ], $userAttributes));

        if ($archived) {
            $user->update(array_filter([
                'archived_at' => now(),
                'archived_by' => $this->secretary()->id,
                'archive_reason' => $archiveReason ?? 'Moved away',
                'archive_note' => $archiveNote,
            ], fn ($value): bool => $value !== null));
        }

        return ResidentProfile::factory()->create(array_merge([
            'user_id' => $user->id,
            'household_number' => $household,
            'sector_id' => ($sector ?? Sector::factory()->create())->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
            'verified_at' => null,
        ], $profileAttributes));
    }

    /**
     * A verification that reproduces the stored record, so the guard is what
     * refuses the submission rather than the rules.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validEditPayload(ResidentProfile $profile, array $overrides = []): array
    {
        $profile->loadMissing('user');
        $user = $profile->user;

        return array_merge([
            'name' => $user->name,
            // The factory leaves the identity columns empty for a resident, so the
            // payload supplies values the rules will accept. The point of this
            // payload is that it is VALID: a submission the rules reject never
            // reaches the guard under test.
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d') ?? '1990-01-01',
            'gender' => $user->gender ?? array_key_first(config('bdrpis.registration.genders')),
            'civil_status' => $user->civil_status ?? array_key_first(config('bdrpis.registration.civil_statuses')),
            'nationality' => $user->nationality ?? config('bdrpis.registration.default_nationality'),
            'nationality_other' => '',
            'address' => $profile->address ?? 'A complete address',
            'sector_id' => (string) $profile->sector_id,
            'household_number' => $profile->household_number,
            'profile_updated_at' => $profile->updated_at->format('Y-m-d H:i:s'),
            'user_updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * Open a batch the way the office does, so the checklist rows are the ones the
     * controller writes.
     *
     * A factory batch has no rows at all, which makes it useless for anything
     * about seeding: a test written against one ends up testing the test's own
     * fixture rather than the rule it is here to defend.
     *
     * @param  list<int>  $sectorIds
     */
    private function openBatchThroughTheOffice(array $sectorIds = []): ReliefBatch
    {
        $this->actingAs($this->secretary())
            ->post(route('staff.batches.store'), [
                'title' => 'Archive relief batch',
                'sector_ids' => $sectorIds,
            ])
            ->assertSessionHasNoErrors();

        return ReliefBatch::query()->latest('id')->firstOrFail();
    }

    /**
     * Every statement the Kagawad resident list runs.
     *
     * The query log rather than a listener: listeners accumulate for the life of
     * the process, so a second measurement in the same test would be counted
     * twice and a budget would fail for a reason that has nothing to do with the
     * page.
     *
     * Warmed first. The opening request in a test also resolves the Kagawad's
     * sector a second way, and counting that would put one query of one-time setup
     * into the first measurement and not the second - which reads as "twenty
     * households cost one query less than one", a conclusion about the page that is
     * not true.
     */
    private function countKagawadResidentQueries(User $kagawad): int
    {
        $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    /**
     * The page, parsed.
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

    /**
     * The table row for a named resident, as its class attribute and its text.
     *
     * @return array{class: string, text: string}
     */
    private function rowFor(DOMXPath $xpath, string $name): array
    {
        foreach ($xpath->query('//tr') as $row) {
            $this->assertInstanceOf(DOMElement::class, $row);

            if (str_contains($row->textContent, $name)) {
                return [
                    'class' => $row->getAttribute('class'),
                    'text' => $row->textContent,
                ];
            }
        }

        $this->fail("No row for {$name} on the page.");
    }

    /**
     * The checklist list item for a household number, as its text.
     *
     * @return array{text: string}
     */
    private function listItemFor(DOMXPath $xpath, string $household): array
    {
        foreach ($xpath->query('//li') as $item) {
            if (str_contains($item->textContent, $household)) {
                return ['text' => $item->textContent];
            }
        }

        $this->fail("No checklist row for {$household} on the page.");
    }
}
