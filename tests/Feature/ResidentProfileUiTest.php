<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The resident navbar's profile control, the My Profile tabs, and the resident
 * table treatment.
 *
 * The navbar assertions are split across the two resident states on purpose.
 * The button is identical for both; the pending dot is not, and a dot that shows
 * for a verified resident would be a resident being nagged about something they
 * have already done.
 */
class ResidentProfileUiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The profile button
    // ---------------------------------------------------------------------

    /**
     * The word is gone, the name is not. A button whose visible content is a bare
     * icon has to be named, or it is an unlabelled control to a screen reader and
     * to anyone whose browser does not load the icon.
     */
    #[Test]
    public function the_profile_control_is_a_named_round_icon_button(): void
    {
        $html = $this->page(route('home'), $this->verifiedResident())->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="My Profile menu"', $html);
        $this->assertStringContainsString('title="My Profile"', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString(':aria-expanded=', $html);

        $this->assertStringContainsString('h-[46px] w-[46px]', $html);
        $this->assertStringContainsString('rounded-full bg-gold', $html);

        // The label, not a visible word in the bar.
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*My Profile\s*<\/button>/',
            $html,
            'The button still carries the word My Profile.'
        );
    }

    #[Test]
    public function a_pending_resident_gets_the_dot_and_a_verified_one_does_not(): void
    {
        $pending = $this->page(route('home'), $this->pendingResident())->assertOk()->getContent();
        $this->assertStringContainsString('bg-orange-500', $pending);
        // The dot itself is decoration; the menu states the state in words.
        $this->assertStringContainsString('Pending verification', $pending);

        $verified = $this->page(route('home'), $this->verifiedResident())->assertOk()->getContent();
        $this->assertStringNotContainsString('bg-orange-500', $verified);
        $this->assertStringContainsString('Verified', $verified);
    }

    /**
     * The guest bar must be untouched. This is the same component file, so a
     * careless edit to the shared header would take the guest buttons with it.
     */
    #[Test]
    public function the_guest_bar_is_unchanged(): void
    {
        $html = $this->page(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('>Log in<', $html);
        $this->assertStringContainsString('>Register<', $html);
        $this->assertStringNotContainsString('aria-haspopup', $html);
        $this->assertStringNotContainsString('h-[46px]', $html);
    }

    // ---------------------------------------------------------------------
    // The dropdown
    // ---------------------------------------------------------------------

    #[Test]
    public function the_dropdown_has_a_header_and_exactly_two_items(): void
    {
        $user = $this->verifiedResident();
        $html = $this->page(route('home'), $user)->assertOk()->getContent();

        $this->assertStringContainsString('w-[290px]', $html);
        $this->assertStringContainsString('rounded-2xl', $html);
        $this->assertStringContainsString('shadow-xl', $html);

        // Header: the initial, the name, the address, the state.
        $this->assertStringContainsString(mb_strtoupper(mb_substr($user->name, 0, 1)), $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString('from-navy-700 to-navy-900', $html);

        // Two menu items only.
        $this->assertSame(2, substr_count($html, 'role="menuitem"'));

        // Whitespace tolerant, or these would pass just because Blade breaks the
        // line after the anchor - which is a test that cannot fail.
        foreach (['Edit Profile', 'Change Password', 'Verification Status'] as $removed) {
            $this->assertDoesNotMatchRegularExpression(
                '/>\s*'.preg_quote($removed, '/').'\s*</',
                $html,
                "{$removed} is still a dropdown item."
            );
        }
    }

    #[Test]
    public function log_out_is_a_post_with_a_csrf_token(): void
    {
        $html = $this->page(route('home'), $this->verifiedResident())->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="[^"]*\/logout"[^>]*>.{0,200}name="_token"/s',
            $html,
            'Log out is not a CSRF-protected POST.'
        );
    }

    #[Test]
    public function the_dropdown_closes_on_outside_click_and_on_escape(): void
    {
        $html = $this->page(route('home'), $this->verifiedResident())->assertOk()->getContent();

        $this->assertStringContainsString('x-on:click.outside', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
    }

    // ---------------------------------------------------------------------
    // The My Profile tabs
    // ---------------------------------------------------------------------

    #[Test]
    public function the_three_tabs_are_real_tabs_with_the_right_one_selected(): void
    {
        foreach (['profile' => 'Edit Profile', 'password' => 'Change Password', 'verification' => 'Verification Status'] as $tab => $label) {
            $html = $this->page(route('account.edit', ['tab' => $tab]), $this->verifiedResident())
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('role="tablist"', $html);
            $this->assertSame(3, substr_count($html, 'role="tab"'), "The {$tab} tab list is incomplete.");
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*</',
                $html,
                "The {$label} tab is missing."
            );

            $this->assertMatchesRegularExpression(
                '/id="tab-'.$tab.'"[^>]*aria-selected="true"/',
                $html,
                "The {$tab} tab is not marked selected on its own page."
            );

            $this->assertStringContainsString('id="panel-'.$tab.'"', $html);
            $this->assertStringContainsString('aria-labelledby="tab-'.$tab.'"', $html);
        }
    }

    /**
     * Only the selected panel renders. Rendering all three and hiding two with
     * CSS would leave a password form in the DOM while somebody reads their name.
     */
    #[Test]
    public function only_the_selected_tab_renders_its_content(): void
    {
        $html = $this->page(route('account.edit', ['tab' => 'profile']), $this->verifiedResident())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringNotContainsString('name="new_password"', $html);
        $this->assertStringNotContainsString('Your verification', $html);
    }

    #[Test]
    public function an_unknown_tab_falls_back_to_the_first_one(): void
    {
        $html = $this->page(route('account.edit', ['tab' => 'nonsense']), $this->verifiedResident())
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="tab-profile"[^>]*aria-selected="true"/', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    /**
     * The old addresses. Every one of them is a URL something already points at -
     * the navbar, the profile menu, a bookmark, a message somebody sent.
     */
    #[Test]
    public function the_old_urls_redirect_to_the_matching_tab(): void
    {
        $user = $this->verifiedResident();

        $this->page(route('account.password'), $user)
            ->assertRedirect(route('account.edit', ['tab' => 'password']));

        $this->page(route('resident.verification.status'), $user)
            ->assertRedirect(route('account.edit', ['tab' => 'verification']));
    }

    /**
     * A redirect to a tab that does not exist would be worse than a 404, because
     * the link looks like it worked.
     */
    #[Test]
    public function every_redirect_lands_on_a_page_that_actually_renders(): void
    {
        $user = $this->verifiedResident();

        foreach (['account.password', 'resident.verification.status'] as $name) {
            $target = $this->page(route($name), $user)->headers->get('Location');

            $this->page($target, $user)->assertOk();
        }
    }

    #[Test]
    public function the_profile_page_has_the_hero_and_one_h1(): void
    {
        $html = $this->page(route('account.edit'), $this->verifiedResident())->assertOk()->getContent();

        $this->assertStringContainsString('My Profile', $html);
        $this->assertStringContainsString('min-h-[300px]', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    // ---------------------------------------------------------------------
    // Resident tables
    // ---------------------------------------------------------------------

    #[Test]
    public function the_resident_tables_carry_the_navy_and_gold_treatment(): void
    {
        $user = $this->verifiedResident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $user->id]);

        $html = $this->page(route('resident.requests.index'), $user)->assertOk()->getContent();

        $this->assertStringContainsString('border-b-[3px] border-gold bg-navy', $html);
        $this->assertStringContainsString('text-white', $html);
        // Escaped in the rendered output: they are arbitrary variants inside a
        // class attribute, so both the ampersand and the ">" arrive as entities.
        $this->assertStringContainsString('[&amp;&gt;tr:nth-child(even)]:bg-sky-50/40', $html);
        $this->assertStringContainsString('[&amp;&gt;tr:hover]:bg-gold/10', $html);
    }

    /**
     * The staff queues and the Kagawad checklist share this table. A variant
     * added for the resident area must not have moved their header row.
     */
    #[Test]
    public function a_staff_table_keeps_the_original_look(): void
    {
        DocumentRequest::factory()->submitted()->create();

        $html = $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]))
            ->get(route('staff.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bg-navy-50/60', $html);
        $this->assertStringNotContainsString('border-b-[3px] border-gold', $html);
        $this->assertStringNotContainsString('[&amp;&gt;tr:nth-child(even)]', $html);
    }

    #[Test]
    public function resident_status_badges_are_soft_pills_with_no_border(): void
    {
        $user = $this->verifiedResident();
        DocumentRequest::factory()->claimed()->create(['user_id' => $user->id]);

        $html = $this->page(route('resident.requests.index'), $user)->assertOk()->getContent();

        $this->assertStringContainsString('border-transparent', $html);
        $this->assertStringNotContainsString('border-green-200', $html);
    }

    /**
     * A staff badge keeps its ring. The soft treatment is a resident design
     * decision, and reaching for it here would restyle every queue in the system.
     */
    #[Test]
    public function a_staff_badge_keeps_its_ring(): void
    {
        $html = $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]))
            ->get(route('staff.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('border-transparent', $html);
    }

    // ---------------------------------------------------------------------
    // The requests table
    // ---------------------------------------------------------------------

    #[Test]
    public function the_requests_table_shows_document_status_date_and_actions(): void
    {
        $user = $this->verifiedResident();
        $type = DocumentType::factory()->create(['label' => 'Barangay Clearance']);
        $request = DocumentRequest::factory()->submitted()->create([
            'user_id' => $user->id,
            'document_type_id' => $type->id,
        ]);

        $html = $this->page(route('resident.requests.index'), $user)->assertOk()->getContent();

        $this->assertStringContainsString('Barangay Clearance', $html);
        $this->assertStringContainsString($request->reference_number, $html);
        $this->assertStringContainsString('Submitted', $html);
        $this->assertStringContainsString('Track', $html);
    }

    /**
     * The Track dialog is opened from the row, so it needs no reference input -
     * the resident has already said which one by pressing Track on it.
     */
    #[Test]
    public function the_track_dialog_needs_no_reference_input(): void
    {
        $user = $this->verifiedResident();
        $request = DocumentRequest::factory()->submitted()->create(['user_id' => $user->id]);

        $html = $this->page(route('resident.requests.index'), $user)->assertOk()->getContent();

        $this->assertStringContainsString("open-modal', 'track-request'", $html);
        $this->assertStringContainsString('tracked = ', $html);
        $this->assertStringContainsString($request->reference_number, $html);

        // No lookup form: the dialog is for the row that was pressed.
        $this->assertStringNotContainsString('name="reference"', $html);
        $this->assertStringNotContainsString('name="household"', $html);
    }

    /**
     * Each row opens its own request, so the dialog has to hold the request that
     * was pressed and not always the first one.
     */
    #[Test]
    public function each_row_opens_its_own_request(): void
    {
        $user = $this->verifiedResident();
        $first = DocumentRequest::factory()->submitted()->create(['user_id' => $user->id]);
        $second = DocumentRequest::factory()->ready()->create(['user_id' => $user->id]);

        $html = $this->page(route('resident.requests.index'), $user)->assertOk()->getContent();

        $this->assertStringContainsString('tracked = 0', $html);
        $this->assertStringContainsString('tracked = 1', $html);
        $this->assertStringContainsString($first->reference_number, $html);
        $this->assertStringContainsString($second->reference_number, $html);
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * Fetch a page as a given user, or as a guest when none is given.
     *
     * Named page() rather than get(), because TestCase::get() is how this test
     * class makes HTTP requests and shadowing it turns every call in the file
     * into a call on the helper.
     */
    private function page(string $path, ?User $user = null): TestResponse
    {
        return $user === null
            ? $this->get($path)
            : $this->actingAs($user)->get($path);
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function pendingResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
        ]);

        return $user;
    }
}
