<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The wizard will not let a resident walk past a field they have not filled in.
 *
 * A unit test cannot press a button, so most of what follows asserts the contract
 * that makes the button behave: that Next Step is guarded, that every field a step
 * declares has a rule, that the rule is wired to that control, and that there is no
 * second way round the guard. The clicking itself is verified in a browser; what
 * these tests protect is the wiring, which is the part that can rot silently.
 *
 * A page that renders the same markup with the guard removed would pass a test
 * that only checked for a "required" attribute, which is why the guard is asserted
 * as code and not as intent.
 */
class RegistrationStepValidationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The guard
    // ---------------------------------------------------------------------

    /**
     * The actual defect this file exists for.
     *
     * Next Step called goTo() and nothing else, so it advanced whatever the step
     * held. The required attributes never fired because a button of type "button"
     * does not submit a form, and the only validator was RegisterRequest, which
     * does not run until the last step is posted.
     */
    #[Test]
    public function the_next_button_does_not_advance_without_validating_first(): void
    {
        $source = $this->wizardScript();

        $this->assertMatchesRegularExpression(
            '/next\(\)\s*\{\s*if\s*\(\s*!\s*this\.validateStep\(\)\s*\)\s*return;/',
            $source,
            'Next Step is not guarded by validateStep(). It will walk past a blank step.'
        );
    }

    /**
     * The guard has to be on the way forward only. A resident who has gone past a
     * field is allowed to come back to it, and blocking that would be a worse dead
     * end than the one this fixes.
     */
    #[Test]
    public function going_back_is_deliberately_not_guarded(): void
    {
        $source = $this->wizardScript();

        $this->assertMatchesRegularExpression(
            '/back\(\)\s*\{\s*this\.goTo\(this\.step\s*-\s*1\)\s*[,;]?\s*\}/',
            $source,
            'Previous Step should move back without consulting the validation.'
        );
    }

    #[Test]
    public function there_is_a_validate_step_and_it_reports_whether_progression_is_allowed(): void
    {
        $source = $this->wizardScript();

        foreach ([
            'validateStep' => 'the step check itself',
            'isSatisfied' => 'the per-field check',
            'isRequired' => 'the conditional-field check',
            'clearError' => 'clearing a message once a field is corrected',
        ] as $method => $what) {
            $this->assertStringContainsString($method, $source, "The wizard has lost {$what}.");
        }
    }

    // ---------------------------------------------------------------------
    // Every step's fields are covered
    // ---------------------------------------------------------------------

    /**
     * A step that declares a field the wizard has no rule for is a field the
     * wizard will happily walk past - the exact bug, one level down.
     *
     * Read at runtime rather than through a data provider: a provider is built
     * before the application boots, so `config()` is not available there and a
     * provider reading it raises an error instead of failing a plain assertion.
     * That is a slower failure to read and a much slower one to trust, because
     * the run still reports the body as having passed.
     */
    #[Test]
    public function every_field_a_step_declares_has_a_rule(): void
    {
        $rules = config('bdrpis.registration.field_rules');

        foreach (config('bdrpis.registration.steps') as $i => $step) {
            foreach ($step['fields'] as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $rules,
                    'Step '.($i + 1)." declares [{$field}] but the wizard has no rule for it, "
                        .'so the wizard will step over it empty.'
                );
                $this->assertNotEmpty(
                    $rules[$field]['message'] ?? null,
                    "[{$field}] has no message, so a resident would be told a field is required without being told which."
                );
            }
        }
    }

    /**
     * The rules have to stay in step with the fields they describe, in both
     * directions: a rule for a field no step collects is a rule nothing reads.
     */
    /**
     * The sector is the only residential area a resident is asked for.
     *
     * "Sector / Purok" implied the form wanted two things and settled for one, and
     * a resident who knew their purok had no idea whether to type it here or put
     * it in the address. One dropdown, one label, and the sectors table is where
     * the options come from.
     */
    #[Test]
    public function step_two_asks_for_one_area_called_the_sector(): void
    {
        $html = $this->registerPage();

        $this->assertSame(
            1,
            preg_match_all('/name="sector_id"/', $html),
            'The sector selection is not a single control.'
        );

        // A purok field beside the sector, or a combined label, both read as two.
        $this->assertSame(
            0,
            preg_match('/name="purok/', $html),
            'The form asks for a purok separately from the sector.'
        );
        $this->assertStringNotContainsString('Sector / Purok', $html, 'The sector is still labelled as two things.');
        $this->assertStringNotContainsString('sector or purok', $html, 'Resident-facing wording still says "sector or purok".');

        $this->assertStringContainsString('Select your sector', $html);
    }

    /**
     * The options have to be the sectors that actually exist, not a list written
     * into the view. A sector the server would refuse must not be offered, and a
     * sector in the table must not be missing.
     */
    #[Test]
    public function the_sector_options_are_the_rows_in_the_sectors_table(): void
    {
        $html = $this->registerPage();

        foreach (Sector::orderBy('id')->pluck('sector_name') as $name) {
            $this->assertStringContainsString(
                '>'.$name.'</option>',
                $html,
                "[{$name}] is in the sectors table but not offered in the dropdown."
            );
        }
    }

    /**
     * An id the table does not have is refused, so a hand-edited or stale
     * submission cannot attach a resident to a sector that does not exist.
     */
    #[Test]
    public function a_sector_that_does_not_exist_is_refused(): void
    {
        $this->post('/register', $this->validSubmission(['sector_id' => 999999]))
            ->assertSessionHasErrors('sector_id');

        $this->assertSame(0, User::count(), 'A nonexistent sector still created an account.');
    }

    /**
     * The sector a resident picked is the sector they are stored against, and the
     * relationship resolves back to it.
     */
    #[Test]
    public function the_selected_sector_is_what_the_profile_is_stored_against(): void
    {
        Storage::fake('local');
        $sector = Sector::factory()->create(['sector_name' => 'Inner 3A']);

        $this->post('/register', $this->validSubmission(['sector_id' => $sector->id]))
            ->assertSessionHasNoErrors();

        $user = User::firstWhere('email', 'juan@example.com');

        $this->assertSame(
            $sector->id,
            $user->residentProfile->sector_id,
            'The profile was not stored against the sector the resident chose.'
        );
        $this->assertTrue(
            $user->residentProfile->sector->is($sector),
            'The profile\'s sector relationship does not resolve to the chosen sector.'
        );
    }

    /**
     * Picking a sector and being sent back leaves it chosen. A resident correcting
     * their address should not have to pick their area all over again.
     */
    #[Test]
    public function a_refused_registration_keeps_the_sector_that_was_chosen(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Kasabaan']);

        $this->from('/register')
            ->post('/register', $this->validSubmission(['sector_id' => $sector->id, 'name' => '']))
            ->assertSessionHasErrors('name');

        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.$sector->id.'" selected/',
            $html,
            'The chosen sector was not still selected on the reloaded form.'
        );
    }

    #[Test]
    public function the_rules_describe_exactly_the_fields_the_steps_collect(): void
    {
        $collected = collect(config('bdrpis.registration.steps'))
            ->flatMap(fn (array $step): array => $step['fields'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $described = collect(array_keys(config('bdrpis.registration.field_rules')))->sort()->values()->all();

        $this->assertSame(
            $collected,
            $described,
            'The browser rules and the wizard steps have drifted apart. A field in one and not the other is either unguarded or dead.'
        );
    }

    #[Test]
    public function every_control_on_every_step_is_wired_to_its_own_message(): void
    {
        $html = $this->registerPage();

        foreach (config('bdrpis.registration.steps') as $step) {
            foreach ($step['fields'] as $field) {
                // Bound to the field's own error rather than a blanket flag, so a
                // screen reader is told which control is wrong, not just that
                // something on the step is. null rather than 'false' because
                // aria-invalid="false" still announces the control as checked.
                $this->assertStringContainsString(
                    "x-bind:aria-invalid=\"errors.{$field} ? 'true' : null\"",
                    $html,
                    "[{$field}] is not marked invalid when it is wrong."
                );
                $this->assertStringContainsString(
                    'id="'.$field.'-client-error"',
                    $html,
                    "[{$field}] has no message element to show, so nothing tells the resident what to do."
                );
            }
        }
    }

    /**
     * Each control has to point at the message it can raise. A message that is
     * rendered but never referenced is not announced, so the screen reader
     * reports the field as simply empty - which is the same as not having
     * validated at all.
     */
    #[Test]
    public function every_control_points_at_its_own_message(): void
    {
        $html = $this->registerPage();

        foreach (config('bdrpis.registration.steps') as $step) {
            foreach ($step['fields'] as $field) {
                preg_match('/<(?:input|select|textarea)[^>]*name="'.$field.'"[^>]*>/', $html, $control);
                $this->assertNotEmpty($control, "[{$field}] has no control on the page.");

                preg_match('/aria-describedby="([^"]*)"/', $control[0], $describedBy);

                $this->assertContains(
                    $field.'-client-error',
                    preg_split('/\s+/', $describedBy[1] ?? '') ?: [],
                    "[{$field}] does not reference its message, so it is never announced."
                );
            }
        }
    }

    /**
     * The message is hidden until the field is actually wrong. Rendering it open
     * would greet every resident with a page of red.
     */
    #[Test]
    public function every_client_message_starts_hidden(): void
    {
        preg_match_all(
            '/<p id="[a-z_]+-client-error"([^>]*)>/',
            $this->registerPage(),
            $matches,
            PREG_SET_ORDER
        );

        $this->assertNotEmpty($matches, 'No client-side error messages were rendered at all.');

        foreach ($matches as $match) {
            $this->assertStringContainsString('x-show=', $match[1], 'A client message is not bound to its condition.');
            $this->assertStringContainsString('x-cloak', $match[1], 'A client message is not cloaked, so it flashes before Alpine boots.');
        }
    }

    // ---------------------------------------------------------------------
    // Placeholders cannot pass for an answer
    // ---------------------------------------------------------------------

    /**
     * A select renders its placeholder as a real option with an empty value, which
     * is what makes "still showing Select Gender" detectable at all. A placeholder
     * that carried a value would be indistinguishable from a choice.
     */
    #[Test]
    public function every_placeholder_is_an_empty_valued_option(): void
    {
        $html = $this->registerPage();

        foreach (['Select Gender', 'Select Civil Status', 'Select Nationality', 'Select your sector'] as $placeholder) {
            $this->assertStringContainsString(
                'value=""',
                $html,
                "The [{$placeholder}] placeholder does not carry an empty value."
            );
            $this->assertMatchesRegularExpression(
                '/<option value=""[^>]*>'.preg_quote($placeholder, '/').'<\/option>/',
                $html,
                "The [{$placeholder}] placeholder is not a disabled empty option."
            );
        }
    }

    /**
     * nationality is the one control that is legitimately pre-filled, because
     * "Filipino" is the answer almost everybody gives and forcing a pointless click
     * is a worse thing than a sensible default. This pins that the default is a
     * real value rather than a placeholder that happens to look like one.
     */
    #[Test]
    public function the_nationality_default_is_a_real_answer_not_a_placeholder(): void
    {
        $default = config('bdrpis.registration.default_nationality');

        $this->assertStringContainsString(
            ':value="old(\'nationality\', $defaultNationality)"',
            $this->wizardSource(),
            'The nationality default is no longer rendered.'
        );
        $this->assertNotSame('', $default, 'The default nationality is empty, which would make it a placeholder.');
    }

    // ---------------------------------------------------------------------
    // There is no way round the guard
    // ---------------------------------------------------------------------

    /**
     * The step badges are a progress indicator, not navigation. The moment they
     * become clickable they are a way past the guard, and a resident who uses one
     * lands on a step whose fields nobody checked.
     */
    #[Test]
    public function the_step_indicators_cannot_be_used_to_jump_ahead(): void
    {
        $html = $this->registerPage();

        $this->assertSame(
            0,
            preg_match('/class="steps".*?<\/ol>/s', $html, $stepper) ? substr_count($stepper[0], '<button') : 0,
            'The step indicator contains a button, which would be a way past the guard.'
        );
        $this->assertSame(
            0,
            preg_match('/class="steps".*?<\/ol>/s', $html, $stepper) ? substr_count($stepper[0], '<a ') : 0,
            'The step indicator contains a link, which would be a way past the guard.'
        );
    }

    /**
     * Only two handlers move the wizard, and only one of them moves forward. Any
     * third would be an unguarded route to another step.
     */
    #[Test]
    public function the_only_ways_to_move_the_wizard_are_next_and_back(): void
    {
        $source = $this->wizardSource();

        preg_match_all('/(?:@click|x-on:click)="([a-zA-Z]+)"/', $source, $matches);

        $handlers = array_values(array_unique(array_filter(
            $matches[1],
            fn (string $h): bool => ! in_array($h, ['clearError'], true)
        )));

        sort($handlers);

        $this->assertSame(['back', 'next'], $handlers, 'The wizard has another way to change step.');
    }

    /**
     * Whatever the browser does, the server decides. These are the same rules that
     * were always there; what is being pinned is that they still refuse a direct
     * POST, so bypassing the wizard buys a resident nothing.
     */
    #[Test]
    public function a_direct_post_missing_everything_is_refused_by_the_server(): void
    {
        $this->post('/register', [])->assertSessionHasErrors([
            'name', 'date_of_birth', 'gender', 'civil_status', 'nationality',
            'address', 'sector_id', 'email', 'household_number', 'valid_id_photo', 'password',
        ]);

        $this->assertSame(0, User::count(), 'An empty registration created an account.');
    }

    #[Test]
    public function a_direct_post_with_one_field_blank_is_refused_for_that_field_alone(): void
    {
        $this->post('/register', $this->validSubmission(['name' => '']))
            ->assertSessionHasErrors('name');

        // Only the blank field should have failed. A rule that quietly broke
        // would show up here as an extra key, which is the point of naming it.
        $this->assertSame(
            ['name'],
            array_keys(session('errors')->getBag('default')->getMessages()),
            'Blanking one field also broke something else.'
        );

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function a_direct_post_with_a_dropdown_left_at_its_placeholder_is_refused(): void
    {
        // An empty value is what a placeholder submits as, so this is the
        // dropdown equivalent of a blank text field.
        $this->post('/register', $this->validSubmission(['gender' => '', 'civil_status' => '']))
            ->assertSessionHasErrors(['gender', 'civil_status']);

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function a_direct_post_with_an_impossible_date_is_refused(): void
    {
        foreach (['2099-01-01', '1800-01-01', 'not-a-date', ''] as $date) {
            $this->post('/register', $this->validSubmission(['date_of_birth' => $date]))
                ->assertSessionHasErrors('date_of_birth');
        }

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function a_direct_post_with_a_password_mismatch_is_refused(): void
    {
        $this->post('/register', $this->validSubmission(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------------------
    // The happy paths still work
    // ---------------------------------------------------------------------

    #[Test]
    public function a_complete_registration_still_works_end_to_end(): void
    {
        Storage::fake('local');

        $response = $this->post('/register', $this->validSubmission())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::firstWhere('email', 'juan@example.com');

        $this->assertNotNull($user, 'A complete registration did not create the account.');
        $this->assertSame('resident', $user->role);
        $this->assertNull($user->email_verified_at, 'The account should still await email confirmation.');

        // The profile is written in the same request, from the same submission.
        $this->assertSame('HH-0001', $user->residentProfile->household_number);

        // And the ID went to the private disk rather than being posted as a field.
        Storage::disk('local')->assertExists($user->residentProfile->id_photo_path);
    }

    #[Test]
    public function a_pdf_is_still_accepted_as_a_valid_id(): void
    {
        Storage::fake('local');

        $this->post('/register', $this->validSubmission([
            'valid_id_photo' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, User::count());
    }

    // ---------------------------------------------------------------------
    // What the resident keeps
    // ---------------------------------------------------------------------

    /**
     * A refused registration has to come back with what they typed, or a resident
     * correcting one field loses the other ten.
     */
    #[Test]
    public function a_refused_registration_keeps_what_was_typed(): void
    {
        $this->from('/register')
            ->post('/register', $this->validSubmission(['name' => '']))
            ->assertSessionHasErrors('name');

        // Two requests, not one: the POST comes back as a redirect, and the page
        // that carries the old values is the one it points at.
        $html = $this->get('/register')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('juan@example.com', $html, 'The email was not carried back.');
        $this->assertStringContainsString('House no. 12, Barangay Linasin', $html, 'The address was not carried back.');
        $this->assertStringContainsString('HH-0001', $html, 'The household number was not carried back.');

        // And it reopens on the step that failed, which is where they were.
        $this->assertStringContainsString('data-step="1"', $html);
        $this->assertStringContainsString('step: 1,', $html, 'The wizard did not reopen on the step that failed.');
    }

    /**
     * The fields the browser checks are also the fields the server checks.
     *
     * "Required" on the server is not always a bare `required`: nationality_other
     * only becomes mandatory once nationality is "Others". So this accepts any of
     * the conditional forms rather than pretending the literal string is the rule.
     */
    #[Test]
    public function every_field_the_wizard_guards_is_also_required_by_the_server(): void
    {
        $rules = (new RegisterRequest)->rules();

        foreach (array_keys(config('bdrpis.registration.field_rules')) as $field) {
            // The confirmation box has no rule of its own, and should not have
            // one: the server compares the two boxes by asking whether the
            // password was "confirmed". Holding this field to a rule key of its
            // own would be asserting a rule that does not need to exist.
            if ($field === 'password_confirmation') {
                $this->assertContains(
                    'confirmed',
                    array_filter((array) ($rules['password'] ?? []), 'is_string'),
                    'The confirmation box is guarded in the browser, but nothing on the server compares the two boxes.'
                );

                continue;
            }

            $this->assertArrayHasKey($field, $rules, "[{$field}] is guarded in the browser but not on the server.");

            $required = collect((array) $rules[$field])
                ->filter(fn ($rule) => is_string($rule))
                ->contains(fn (string $rule): bool => $rule === 'required' || str_starts_with($rule, 'required_'));

            $this->assertTrue(
                $required,
                "[{$field}] is guarded in the browser but the server never requires it, not even conditionally."
            );
        }
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    private function registerPage(): string
    {
        return $this->get('/register')->assertOk()->getContent();
    }

    /**
     * The wizard's Alpine scope, as the browser reads it.
     *
     * Parsed rather than sliced on a marker: the x-data attribute is a single
     * double-quoted value, and a reader that assumed where it ended would be
     * reading a different string from the one that runs.
     */
    private function wizardScript(): string
    {
        $html = $this->registerPage();
        $start = strpos($html, 'class="wizard"');
        $this->assertIsInt($start, 'The wizard element is gone from the page.');

        $from = strpos($html, 'x-data="', $start);
        $this->assertIsInt($from, 'The wizard no longer carries an x-data scope.');

        $valueStart = $from + strlen('x-data="');
        $valueEnd = strpos($html, '"', $valueStart);
        $this->assertIsInt($valueEnd, 'The x-data attribute is not closed, so the browser cannot read it.');

        return html_entity_decode(substr($html, $valueStart, $valueEnd - $valueStart));
    }

    private function wizardSource(): string
    {
        return (string) file_get_contents(resource_path('views/auth/register.blade.php'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSubmission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'date_of_birth' => '1990-05-14',
            'gender' => 'Male',
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
            'address' => 'House no. 12, Barangay Linasin',
            'sector_id' => Sector::factory()->create()->id,
            'email' => 'juan@example.com',
            'household_number' => 'HH-0001',
            'valid_id_photo' => UploadedFile::fake()->image('valid-id.jpg'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }
}
