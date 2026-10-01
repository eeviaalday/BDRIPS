<?php

namespace Tests\Feature\Auth;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The uploaded ID is written to the private disk, so redirect it at a fake
     * for every test in this class rather than writing real files into
     * storage/app/private during a test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * A complete wizard submission, as the five steps post it.
     *
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'date_of_birth' => '1990-05-14',
            'gender' => 'Male',
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
            'address' => 'Purok 3, Barangay Linasin',
            'sector_id' => Sector::factory()->create()->id,
            'email' => 'juan@example.com',
            'household_number' => 'HH-0001',
            'valid_id_photo' => UploadedFile::fake()->image('valid-id.jpg'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', $this->submission());

        $this->assertAuthenticated();

        // Through /dashboard, which answers per role. A newly registered
        // resident is sent there, and from there to the public home page.
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_stores_the_identity_details_given_in_the_wizard(): void
    {
        $this->post('/register', $this->submission());

        $user = User::where('email', 'juan@example.com')->firstOrFail();

        $this->assertSame('Juan Dela Cruz', $user->name);
        $this->assertSame('1990-05-14', $user->date_of_birth->format('Y-m-d'));
        $this->assertSame('Male', $user->gender);
        $this->assertSame('Single', $user->civil_status);
        $this->assertSame('Filipino', $user->nationality);
    }

    public function test_registration_creates_the_resident_profile_so_nothing_is_typed_twice(): void
    {
        $this->post('/register', $this->submission());

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $profile = $user->residentProfile;

        // The address, household number and valid ID the resident supplied in
        // steps 2, 4 and 5 are already on file, so the verification screen only
        // has to offer a replacement rather than re-ask for all of it.
        $this->assertNotNull($profile, 'Signing up no longer creates a resident profile.');
        $this->assertSame('HH-0001', $profile->household_number);
        $this->assertSame('Purok 3, Barangay Linasin', $profile->address);
        $this->assertSame(ResidentProfile::VERIFICATION_PENDING, $profile->verification_status);
    }

    public function test_the_uploaded_valid_id_is_stored_on_the_private_disk(): void
    {
        $this->post('/register', $this->submission());

        $profile = User::where('email', 'juan@example.com')->firstOrFail()->residentProfile;

        $this->assertNotNull($profile->id_photo_path, 'The uploaded valid ID was not attached to the profile.');
        Storage::disk('local')->assertExists($profile->id_photo_path);

        // The private disk, not the public one: a resident's ID must not be
        // reachable from a guessable URL, only through the staff route that
        // authorises the read.
        $this->assertStringStartsWith('id-photos/', $profile->id_photo_path);
    }

    public function test_a_pdf_is_accepted_as_a_valid_id(): void
    {
        // The rule is deliberately mimes without the "image" rule. "image"
        // rejects PDFs outright, so keeping it alongside pdf in the list would
        // make every PDF unuploadable.
        $this->post('/register', $this->submission([
            'valid_id_photo' => UploadedFile::fake()->create('scan.pdf', 120, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_a_file_that_is_neither_a_photo_nor_a_pdf_is_refused(): void
    {
        $this->post('/register', $this->submission([
            'valid_id_photo' => UploadedFile::fake()->create('id.docx', 120),
        ]))->assertSessionHasErrors('valid_id_photo');

        $this->assertGuest();
        $this->assertSame(5, session('registration_step'));
    }

    public function test_a_valid_id_over_five_megabytes_is_refused(): void
    {
        $this->post('/register', $this->submission([
            'valid_id_photo' => UploadedFile::fake()->image('huge.jpg')->size(5200),
        ]))->assertSessionHasErrors('valid_id_photo');

        $this->assertGuest();
    }

    public function test_a_registration_with_no_valid_id_reopens_the_security_step(): void
    {
        $data = $this->submission();
        unset($data['valid_id_photo']);

        $this->post('/register', $data)->assertSessionHasErrors('valid_id_photo');

        $this->assertSame(5, session('registration_step'));
    }

    public function test_the_form_asks_for_a_document_rather_than_a_typed_id_number(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('Valid ID Photo / Document', $content);
        $this->assertStringNotContainsString('national_or_voter_id', $content);

        // The file has to be posted as a file, not as a string. Without the
        // multipart encoding the browser sends only the filename and the
        // validation above can never be satisfied by a real submission.
        $this->assertStringContainsString('enctype="multipart/form-data"', $content);
        $this->assertStringContainsString('type="file"', $content);
    }

    public function test_the_file_input_is_not_given_the_help_text_the_other_fields_carry(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The formats are carried by the accept attribute and by the validation
        // messages, so the paragraph under the field was the only thing making
        // step 5 taller than the rest.
        $this->assertStringNotContainsString('A photograph or scan of any valid government ID', $content);
        $this->assertStringNotContainsString('valid_id_photo-hint', $content);
    }

    public function test_the_file_control_is_a_filename_box_with_the_browse_button_beside_it(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The real control. It has to stay in the form under its own name or the
        // upload never reaches the server, and it has to be a genuine file input
        // rather than a lookalike, so this is the part that must not be lost.
        preg_match('#<input[^>]*\bname="valid_id_photo"[^>]*>#', $content, $real);
        $this->assertNotEmpty($real, 'The form no longer submits a file for the valid ID.');
        $this->assertStringContainsString('type="file"', $real[0]);
        $this->assertStringContainsString('class="file-input"', $real[0], 'The real input is no longer clipped out of the way.');

        // The filename box, defaulting to the prompt, and the button beside it.
        $this->assertStringContainsString('placeholder="No file chosen"', $content);
        $this->assertStringContainsString('class="file-name"', $content);
        $this->assertStringContainsString('>Browse...</button>', $content);

        // Order matters: the button has to come after the box in the markup, or
        // it renders on the left.
        $box = strpos($content, 'class="file-name"');
        $button = strpos($content, 'class="file-browse"');
        $this->assertNotFalse($box);
        $this->assertNotFalse($button);
        $this->assertGreaterThan($box, $button, 'The browse button is rendered before the filename box.');

        // They are on one row, not stacked.
        $this->assertStringContainsString('.file-row{display:flex', str_replace(' ', '', $content));
    }

    public function test_the_filename_box_is_not_submitted_to_the_server(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The box is a display for the browser's own filename. If it were given
        // a name it would post a second value for a field the server never
        // reads, and its contents would be taken from whatever the resident
        // could type - it is readonly, but readonly is not the same as absent.
        preg_match('#<input[^>]*\bclass="file-name"[^>]*>#', $content, $box);

        $this->assertNotEmpty($box);
        $this->assertStringNotContainsString('name=', $box[0]);
    }

    public function test_the_filename_box_clears_the_leading_icon(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The icon is painted over the row at left:0, so the box has to reserve
        // the same 44px the text inputs do or the filename runs underneath it.
        // Checked against the stylesheet because that is where the padding lives
        // now - the box carries no utility class of its own.
        $this->assertMatchesRegularExpression(
            '/\.file-name\{[^}]*padding-left:44px/',
            str_replace(' ', '', $content),
            'The filename box no longer leaves room for the leading icon.',
        );
    }

    public function test_a_nationality_outside_the_list_is_stored_as_what_was_typed(): void
    {
        $this->post('/register', $this->submission([
            'nationality' => 'Others',
            'nationality_other' => 'American',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            'American',
            User::where('email', 'juan@example.com')->firstOrFail()->nationality,
            'The escape-hatch value was stored instead of the nationality the resident gave.',
        );
    }

    public function test_choosing_others_without_naming_a_nationality_is_rejected(): void
    {
        $this->post('/register', $this->submission([
            'nationality' => 'Others',
            'nationality_other' => '',
        ]))->assertSessionHasErrors('nationality_other');

        $this->assertGuest();
    }

    public function test_a_date_of_birth_in_the_future_is_rejected(): void
    {
        $this->post('/register', $this->submission(['date_of_birth' => now()->addWeek()->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertGuest();
    }

    public function test_a_gender_outside_the_configured_list_is_rejected(): void
    {
        $this->post('/register', $this->submission(['gender' => 'unspecified']))
            ->assertSessionHasErrors('gender');

        $this->assertGuest();
    }

    public function test_an_email_already_registered_is_refused_and_points_at_signing_in(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', $this->submission(['email' => 'taken@example.com']));

        // The wording has to name the way out, not just the problem: a resident
        // who already has an account should be told to log in rather than left
        // to wonder whether their address was mistyped.
        $response->assertSessionHasErrors([
            'email' => 'This email address is already registered. Please use another email or log in.',
        ]);

        // Back to step 3, where the address is typed, rather than to step one.
        $this->assertSame(3, session('registration_step'));

        $this->assertGuest();
        $this->assertSame(1, User::count(), 'A second account was created for an email already in use.');
    }

    public function test_email_uniqueness_does_not_depend_on_the_case_typed(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        // Without the "lowercase" rule normalising the input before the lookup,
        // this would sail past the unique check and create a second account for
        // the same person.
        $this->post('/register', $this->submission(['email' => 'TAKEN@Example.com']));

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_the_sector_dropdown_offers_the_barangays_sectors_in_their_own_order(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        $expected = [
            'Actividad Economia', 'Inner 1', 'Inner 2', 'Inner 3A', 'Inner 3B', 'Inner 4A',
            'Inner 4B', 'Inner 4C', 'Inner 4D', 'Kasabaan', 'Petron', 'INC', 'Benesa',
            'Capitulacion', 'Kabildatan', 'Palawan', 'Yamaha',
        ];

        $previous = -1;

        foreach ($expected as $name) {
            $position = strpos($content, '>'.$name.'</option>');

            $this->assertNotFalse($position, "\"{$name}\" is missing from the sector dropdown.");
            $this->assertGreaterThan($previous, $position, "\"{$name}\" is out of order in the dropdown.");
            $previous = $position;
        }

        // The design-phase placeholders are gone, not merely reordered.
        $this->assertStringNotContainsString('Sector 1', $content);
    }

    public function test_a_rejected_submission_reopens_the_step_that_failed(): void
    {
        // Every step is valid except the household number in step 4, so the
        // resident is put back on step 4 rather than at the top of the form.
        $response = $this->post('/register', $this->submission(['household_number' => '']));

        $response->assertSessionHasErrors('household_number');
        $this->assertSame(4, session('registration_step'));
    }

    public function test_a_password_mismatch_reopens_the_security_step(): void
    {
        $this->post('/register', $this->submission(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors('password');

        $this->assertSame(5, session('registration_step'));
    }

    public function test_a_first_step_failure_reopens_on_step_one(): void
    {
        $this->post('/register', $this->submission(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        // The email field lives in step 3, so a bad address is a step 3 failure.
        $this->assertSame(3, session('registration_step'));
    }

    public function test_no_account_is_created_when_the_wizard_is_rejected(): void
    {
        $this->post('/register', $this->submission(['email' => 'not-an-email']));

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('resident_profiles', 0);
    }

    public function test_the_reloaded_form_opens_on_the_step_that_failed(): void
    {
        // The flashed step is what the page actually opens on, so this asserts
        // the rendered wizard rather than the session key alone: a resident sent
        // back to step one after failing step four has lost the form.
        $this->post('/register', $this->submission(['household_number' => '']));

        $content = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('step: 4', $content);

        // The rejected answer and the reason are both back on screen, so the
        // resident can fix it without retyping the three steps above it.
        $this->assertStringContainsString('name="household_number"', $content);
        $this->assertStringContainsString(
            'Your household reference number is required.',
            $content,
        );
    }

    public function test_a_fresh_visit_always_starts_on_step_one(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('step: 1', $content);
    }

    public function test_the_wizard_ships_the_javascript_that_drives_it(): void
    {
        // The register page is a standalone document, so it no longer picks
        // Alpine up from a shared layout the way it used to. If that script tag
        // is ever dropped, nothing errors: all five steps render stacked and
        // Next Step silently does nothing, which is the kind of failure no
        // server-side test would otherwise notice.
        $content = $this->get('/register')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#/build/assets/app-[^"]*\.js#',
            $content,
            'The register page no longer loads the JavaScript entrypoint its step navigation depends on.',
        );
    }

    public function test_all_five_steps_post_together_as_one_submission(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The steps are a way of filling one form in, not five separate saves,
        // so there is exactly one form and every step's fields are inside it.
        $this->assertSame(1, substr_count($content, '<form'), 'The wizard was split into more than one form.');

        foreach ([
            'name', 'date_of_birth', 'gender', 'civil_status', 'nationality',
            'address', 'sector_id', 'email', 'household_number',
            'valid_id_photo', 'password', 'password_confirmation',
        ] as $field) {
            $this->assertStringContainsString(
                "name=\"{$field}\"",
                $content,
                "The \"{$field}\" field is missing from the registration form.",
            );
        }
    }

    public function test_exactly_one_step_is_readable_without_javascript(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The steps other than the opening one are hidden with x-cloak. If the
        // opening one were cloaked as well, a browser with no JavaScript would
        // show a blank form with no way to recover - the resident would have no
        // form at all rather than a form missing its later steps.
        preg_match_all('/<fieldset(?![^>]*x-cloak)[^>]*>/', $content, $matches);

        $this->assertCount(
            1,
            $matches[0],
            'Without JavaScript the form should reveal exactly one step, not zero and not all five.',
        );
    }

    public function test_the_hero_panel_uses_the_barangay_photograph(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The design ships a CSS gradient as a stand-in for the photograph. A
        // page that keeps it looks close enough in a test to be mistaken for the
        // real thing, and the missing image goes unnoticed until someone opens
        // the site.
        $this->assertStringContainsString(
            "--photo: url('".asset('images/login.jpg')."')",
            $content,
            'The register page is not using the barangay photograph behind the hero panel.',
        );
    }

    public function test_fields_with_a_leading_icon_leave_room_for_it(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The soft variant used to drop the icon's left padding, which was safe
        // only because the sign-in card is the one page that pairs it with no
        // icon. Every icon field on this form is a soft field, so all of them
        // had their placeholder starting underneath the glyph.
        //
        // valid_id_photo is not in this list: it is clipped out of sight and
        // reserves the room on a separate filename box instead, which
        // test_the_filename_box_clears_the_leading_icon covers.
        foreach (['name', 'email', 'household_number', 'address'] as $field) {
            preg_match('/<input\b[^>]*\bname="'.$field.'"[^>]*>/', $content, $match);

            $this->assertNotEmpty($match, "The \"{$field}\" input is missing from the form.");
            $this->assertStringContainsString(
                'pl-11',
                $match[0],
                "The \"{$field}\" input has a leading icon but no left padding to clear it.",
            );
        }
    }

    public function test_civil_status_and_nationality_share_one_row_on_step_one(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // Step 1 is the tallest of the five, and it is the one that has to clear
        // a laptop viewport without scrolling. Two fields were each claiming a
        // full row of their own, which is what pushed it over. A grid cell that
        // opts out of the two columns carries .span2, so their absence of it is
        // what puts them side by side.
        foreach (['civil_status', 'nationality'] as $field) {
            preg_match('/<div class="([^"]*)">\s*<label for="'.$field.'"/', $content, $match);

            $this->assertNotEmpty($match, "The \"{$field}\" control is missing from step 1.");
            $this->assertStringNotContainsString(
                'span2',
                $match[1],
                "\"{$field}\" claims a full row, so step 1 no longer fits on one screen.",
            );
        }
    }

    public function test_the_login_link_sits_on_the_first_step_and_nowhere_else(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // Split on the fieldsets so each step's own markup can be inspected. A
        // prompt rendered outside all of them would sit on screen during every
        // step, taking the room the later steps need.
        $steps = array_slice(explode('<fieldset', $content), 1);

        $this->assertCount(5, $steps);
        $this->assertStringContainsString('<p class="signin-prompt">', $steps[0], 'Step 1 has no way back to sign in.');
        $this->assertStringContainsString('href="'.route('login').'"', $steps[0]);
        $this->assertStringContainsString('class="italic"', $steps[0], 'The login link is not styled as a link.');

        for ($i = 1; $i < 5; $i++) {
            $this->assertStringNotContainsString(
                'signin-prompt',
                $steps[$i],
                "The sign-in prompt is showing on step {$i} + 1 as well as step 1.",
            );
        }
    }

    public function test_the_account_card_at_the_foot_of_the_form_is_gone(): void
    {
        $content = $this->get('/register')->assertOk()->getContent();

        // The card duplicated the sign-in link, and it was the main reason the
        // form was taller than the screen. The link that replaced it lives on
        // step 1; the card should not still be here.
        $this->assertStringNotContainsString('class="signin"', $content);
        $this->assertStringNotContainsString('Login here', $content);
    }
}
