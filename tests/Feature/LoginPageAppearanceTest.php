<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The login page.
 *
 * Unlike every other view in the project this one is a standalone document: it
 * opens with its own <!DOCTYPE html> and carries its design as an inline
 * <style> block, rather than extending a layout and loading a stylesheet. The
 * tests are written accordingly - the design is read back out of the rendered
 * markup, which is what the browser actually receives, and the form contract is
 * asserted on its own terms rather than through the shared components.
 */
class LoginPageAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_a_standalone_document(): void
    {
        $content = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($content));
        $this->assertStringContainsString('<html lang="en"', $content);
        $this->assertStringContainsString('<style>', $content);

        // It opts out of the shared layout entirely, so neither the layout's
        // frame nor the global stylesheet should appear.
        $this->assertStringNotContainsString('@extends', $content);
        $this->assertStringNotContainsString('@section', $content);
        $this->assertStringNotContainsString('@vite', $content);
        $this->assertStringNotContainsString('/build/assets/', $content);
    }

    public function test_the_design_is_carried_inline_and_keeps_its_class_names(): void
    {
        // The classes the design is built from. They are the page's contract
        // with its own CSS: renaming one silently unstyles the element.
        $classes = [
            'page', 'left', 'right', 'form', 'sub', 'field', 'wrap', 'f',
            'eye', 'row', 'btn', 'or', 'reg', 'priv', 'perks', 'seal',
            'rule', 'zone', 'home',
        ];

        $css = $this->tight();
        $markup = $this->content();

        foreach ($classes as $class) {
            $this->assertStringContainsString(
                '.'.$class.'{',
                $css,
                "The design no longer styles [.$class].",
            );
            $this->assertStringContainsString(
                'class="'.$class,
                $markup,
                "No element carries [$class] any more.",
            );
        }
    }

    public function test_the_wave_is_built_from_masked_svg_data_uris(): void
    {
        $raw = $this->style();

        // The gold ribbon, the sheet edge, and the composite mask that gives
        // the glass its wavy left edge. All three are inline SVG data URIs; none
        // of them is a bitmap or a separate request.
        $this->assertStringContainsString('--ribbon:', $raw);
        $this->assertStringContainsString('--wave:', $raw);
        $this->assertStringContainsString('--mask:', $raw);
        $this->assertSame(2, substr_count($raw, 'data:image/svg'), 'Both mask images should be inline SVG.');

        $this->assertDeclares('-webkit-mask:var(--ribbon)');
        $this->assertDeclares('mask:var(--ribbon)');
        $this->assertDeclares('-webkit-mask:var(--mask)');
        $this->assertDeclares('mask:var(--mask)');

        // The navy panel and the frosted sheet, each blurred.
        $this->assertDeclares('backdrop-filter:blur(3px)');
        $this->assertDeclares('backdrop-filter:blur(18px)saturate(1.2)');
    }

    public function test_the_photograph_is_the_one_actually_painted_behind_the_page(): void
    {
        $content = $this->get(route('login'))->assertOk()->getContent();

        // The photograph has to be set as an inline custom property on the html
        // element. The :root block declares --photo as a gradient stand-in, and
        // body paints background:var(--photo) - so with no override on the
        // element, the page renders that flat gradient and the photograph never
        // appears, while every other rule on the page still looks correct. That
        // is exactly how this page drifted away from the registration form,
        // which has always carried the override.
        $this->assertStringContainsString(
            "--photo: url('".asset('images/login.jpg')."')",
            $content,
            'The login page is not overriding --photo, so the gradient placeholder is what gets painted.',
        );

        // ...and body must actually paint it, fixed and cover, or the override
        // is set but never seen.
        $this->assertDeclares('background:var(--photo)center/coverfixed');

        // The gradient stays as the fallback for a missing photograph, and it
        // must stay in :root rather than replacing the override - asset() is a
        // Blade call and cannot live in a static stylesheet.
        $this->assertDeclares('--photo:linear-gradient');
    }

    public function test_the_login_and_registration_pages_share_one_photograph(): void
    {
        $photo = "--photo: url('".asset('images/login.jpg')."')";

        // A resident moving between the two pages sees one continuous background.
        // If either side changes its asset the other has to change with it, and
        // this is what catches the pair drifting apart again.
        $this->assertStringContainsString($photo, $this->get(route('login'))->assertOk()->getContent());
        $this->assertStringContainsString($photo, $this->get('/register')->assertOk()->getContent());
    }

    public function test_the_design_custom_properties_are_intact(): void
    {
        foreach ([
            '--photo', '--navy', '--gold', '--goldink', '--glass', '--ink',
            '--muted', '--line', '--field', '--blue',
        ] as $property) {
            $this->assertDeclares($property.':');
        }

        $this->assertDeclares('--navy:#0c2240');
        $this->assertDeclares('--gold:#f5b82e');
        $this->assertDeclares('--goldink:#3a2900');
        $this->assertDeclares('--glass:rgba(255,255,255,.72)');
        $this->assertDeclares('--ink:#15294a');
        $this->assertDeclares('--muted:#43536e');
        $this->assertDeclares('--blue:#2b5aa8');
    }

    public function test_the_page_grid_and_the_overlapping_sheet_are_as_designed(): void
    {
        $this->assertDeclares('grid-template-columns:minmax(0,1fr)minmax(0,1.1fr)');

        // The sheet is pulled left over the navy panel and given matching
        // padding, so the form sits in clear space rather than under it.
        $this->assertDeclares('margin-left:-120px');
        $this->assertDeclares('padding:48px40px48px170px');
    }

    public function test_the_narrow_screen_layout_stacks_and_hides_the_perks(): void
    {
        $this->assertStringContainsString('@media(max-width:860px)', $this->style());

        $this->assertDeclares('grid-template-columns:1fr');
        $this->assertDeclares('.perks{display:none}');

        // The wave becomes a rounded sheet edge across the top instead of down
        // the side, and the gold becomes a bar along it.
        $this->assertDeclares('mask:none');
        $this->assertDeclares('border-radius:28px28px00');
        $this->assertDeclares('background:rgba(245,184,46,.85)');
    }

    public function test_the_design_keeps_light_and_dark_theme_support(): void
    {
        $raw = $this->style();

        $this->assertStringContainsString('@media (prefers-color-scheme:dark)', $raw);
        $this->assertStringContainsString(':root:not([data-theme="light"])', $raw);
        $this->assertStringContainsString(':root[data-theme="dark"]', $raw);
        $this->assertStringContainsString('env(safe-area-inset-top', $raw);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $raw);
    }

    public function test_the_branding_section_carries_the_wordmark_and_welcome(): void
    {
        $left = $this->section('left');

        $this->assertStringContainsString('BARANGAY LINASIN', $left);
        $this->assertStringContainsString('Zone 1, Bangued, Abra', $left);
        $this->assertStringContainsString('class="rule"', $left);
        $this->assertStringContainsString('Welcome back!', $left);
        $this->assertStringContainsString('Sign in to access your dashboard and barangay services.', $left);
    }

    public function test_the_seal_is_the_barangay_logo_not_a_placeholder(): void
    {
        $left = $this->section('left');
        $css = $this->tight();

        // The real seal, loaded through the URL helper.
        $this->assertStringContainsString('src="'.asset('images/Zone 1_logo.png').'"', $left);
        $this->assertStringNotContainsString('<div class="seal"', $left);

        // Decorative: the wordmark is the label, immediately below it.
        $this->assertMatchesRegularExpression('/<img class="seal"[^>]*alt=""/', $left);

        // The gold circle that stood in for it is gone, but the frame it was
        // drawn with is kept - same 92px round box, same ring, same shadow.
        $this->assertStringNotContainsString('radial-gradient', $css);
        $this->assertDeclares('.seal{width:92px;height:92px;border-radius:50%');
        $this->assertDeclares('border:4pxsolidrgba(255,255,255,.45)');
        $this->assertDeclares('box-shadow:010px30pxrgba(0,0,0,.35)');
    }

    public function test_the_branding_section_lists_the_three_perks(): void
    {
        $left = $this->section('left');

        $this->assertSame(3, substr_count($left, '<span><svg'), 'The perk list should hold three items.');

        foreach ([
            'Request certificates and clearances',
            'Track the status of your requests',
            'Get community announcements',
        ] as $perk) {
            $this->assertStringContainsString($perk, $left);
        }

        // The announcement perk is a bell.
        $this->assertStringContainsString('M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21', $left);
    }

    public function test_the_icons_are_the_designs_own_glyphs_at_its_own_weight(): void
    {
        $content = $this->content();

        // 24x24 at stroke-width 2, throughout.
        $this->assertStringContainsString('stroke-width="2"', $content);
        $this->assertSame(0, substr_count($content, 'stroke-width="1.7"'), 'A different icon family leaked in.');

        foreach ([
            '<rect x="3" y="5" width="18" height="14" rx="2"/>',   // envelope
            '<rect x="4" y="11" width="16" height="10" rx="2"/>',  // padlock
            'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z',        // shield
        ] as $path) {
            $this->assertStringContainsString($path, $content);
        }
    }

    public function test_the_form_keeps_the_laravel_bindings(): void
    {
        $content = $this->content();

        $this->assertStringContainsString('method="POST"', $content);
        $this->assertStringContainsString('action="'.route('login').'"', $content);
        $this->assertStringContainsString('name="_token"', $content, 'The CSRF token is missing.');
        $this->assertStringContainsString('class="form" method="POST"', $content);
    }

    public function test_the_field_names_and_labels_are_unchanged(): void
    {
        $content = $this->content();

        // These names are the whole contract with LoginRequest. Changing one
        // silently breaks authentication.
        foreach (['email', 'password', 'remember'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $content);
        }

        // old('email') is checked in the template, not the output: Blade has
        // already rendered it away by the time there is a response to read.
        $this->assertStringContainsString(
            "old('email')",
            file_get_contents(resource_path('views/auth/login.blade.php')),
            'The email field no longer repopulates from old input.',
        );

        // Each label resolves to its own control.
        $this->assertStringContainsString('<label for="email">', $content);
        $this->assertStringContainsString('id="email"', $content);
        $this->assertStringContainsString('<label for="pw">', $content);
        $this->assertStringContainsString('id="pw"', $content);

        $this->assertStringContainsString('Remember me', $content);
        $this->assertStringContainsString('Forgot password?', $content);
    }

    public function test_the_submit_button_submits_the_form(): void
    {
        // type="submit" on the Log in button, not type="button": the design's
        // static mockup has it as a button because nothing was wired up.
        $this->assertMatchesRegularExpression(
            '/<button class="btn" type="submit">Log in<\/button>/',
            $this->content(),
        );
    }

    public function test_the_password_toggle_is_wired(): void
    {
        $content = $this->content();

        // The eye sits inside the form, so it must not submit it.
        $this->assertStringContainsString('<button class="eye" type="button"', $content);
        $this->assertStringContainsString('aria-label="Show password"', $content);

        // And the script that flips it, targeting the same two ids.
        $this->assertStringContainsString("document.getElementById('pw')", $content);
        $this->assertStringContainsString("document.getElementById('eye')", $content);
        $this->assertStringContainsString("pw.type=s?'text':'password'", $content);
        $this->assertStringContainsString("s?'Hide password':'Show password'", $content);
    }

    public function test_the_secondary_links_point_at_the_real_routes(): void
    {
        $content = $this->content();

        $this->assertStringContainsString('href="'.route('password.request').'"', $content);
        $this->assertStringContainsString('href="'.route('register').'"', $content);
        $this->assertStringContainsString('Create an account', $content);
        $this->assertStringContainsString('New to the barangay portal?', $content);
        $this->assertStringContainsString('R.A. 10173', $content);
    }

    public function test_the_back_to_home_link_is_present(): void
    {
        $this->assertStringContainsString('class="home" href="/"', $this->content());
    }

    public function test_signing_in_with_valid_credentials_still_works(): void
    {
        User::factory()->secretary()->create(['email' => 'staff@bdrpis.test']);

        $response = $this->post(route('login'), [
            'email' => 'staff@bdrpis.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_a_failed_sign_in_is_recorded_against_the_email_field(): void
    {
        User::factory()->secretary()->create(['email' => 'staff@bdrpis.test']);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'staff@bdrpis.test',
            'password' => 'not-the-password',
        ]);

        $response->assertRedirect(route('login'));

        // The backend is untouched: the failure is still recorded, and still
        // keyed to the email field rather than to a general bag.
        //
        // Deliberately the only assertion here. Reading the error bag out of the
        // session empties it, so a test that did this and then reloaded the
        // page would find the flash already consumed and would be testing the
        // order of its own assertions rather than the page. The reloaded form is
        // covered separately, below.
        $response->assertSessionHasErrors('email');
    }

    public function test_a_reloaded_form_keeps_the_address_the_resident_typed(): void
    {
        User::factory()->secretary()->create(['email' => 'staff@bdrpis.test']);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'staff@bdrpis.test',
            'password' => 'not-the-password',
        ]);

        $this->assertStringContainsString('value="staff@bdrpis.test"', $this->content());
    }

    public function test_a_failed_sign_in_is_not_shown_to_the_resident(): void
    {
        // KNOWN GAP, asserted so it cannot go unnoticed.
        //
        // The template as specified carries no error block, so a resident who
        // signs in with the wrong password is returned to a form that looks
        // exactly as they left it: no message, no highlighted field. The failure
        // is still recorded (the test above proves it) and the typed address is
        // still kept, but nothing on the page says the sign-in did not work.
        //
        // If an error block is added, this test is the one to invert.
        User::factory()->secretary()->create(['email' => 'staff@bdrpis.test']);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'staff@bdrpis.test',
            'password' => 'not-the-password',
        ]);

        $content = $this->content();

        $this->assertStringNotContainsString('These credentials do not match our records', $content);
        $this->assertStringNotContainsString('role="alert"', $content);
        $this->assertStringNotContainsString('aria-invalid', $content);
    }

    public function test_the_page_title_names_the_portal(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title>Log in · Barangay Linasin</title>', false);
    }

    /** The rendered markup, once. */
    private function content(): string
    {
        return $this->get(route('login'))->assertOk()->getContent();
    }

    /** The page's inline stylesheet, as the browser receives it. */
    private function style(): string
    {
        $this->assertMatchesRegularExpression('/<style>(.*)<\/style>/s', $this->content());

        preg_match('/<style>(.*)<\/style>/s', $this->content(), $matches);

        return $matches[1];
    }

    /**
     * The stylesheet with all whitespace removed.
     *
     * The design is written in a compact style - "prop:value" with no space -
     * so comparing declarations literally would make these tests fail on a
     * reformat rather than on a change to the design. Whitespace is dropped
     * from both sides, which leaves a change to a value, a selector or a
     * property name as the only thing that can break an assertion.
     */
    private function tight(): string
    {
        return (string) preg_replace('/\s+/', '', $this->style());
    }

    /** Assert a CSS declaration is present, ignoring how it is spaced. */
    private function assertDeclares(string $declaration): void
    {
        $this->assertStringContainsString(
            (string) preg_replace('/\s+/', '', $declaration),
            $this->tight(),
            "The design no longer declares [{$declaration}].",
        );
    }

    /**
     * One of the page's two sections, so an assertion about the branding cannot
     * be satisfied by the form and the other way about.
     */
    private function section(string $name): string
    {
        $content = $this->content();

        $this->assertMatchesRegularExpression(
            '/<section class="'.preg_quote($name, '/').'">(.*?)<\/section>/s',
            $content,
            "The [{$name}] section is missing.",
        );

        preg_match('/<section class="'.preg_quote($name, '/').'">(.*?)<\/section>/s', $content, $matches);

        return $matches[1];
    }
}
