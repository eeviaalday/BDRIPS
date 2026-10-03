<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The office's switches for digital copies, on the settings screen.
 *
 * ONE FLAG, ONE ROW, TWO VALUES
 *
 * digital_copy_counts_as_claimed is stored as the string '0' or '1' in a
 * varchar column, next to every other setting on the screen. That is what makes it
 * possible for it to be edited by the same form as the text fields - but it is
 * also the trap this file exists for. The string '0' is FALSY in PHP and the
 * string 'false' is TRUTHY, so a flag read with a plain if() is one careless
 * refactor away from meaning the opposite of what it says, and nothing about the
 * screen would look wrong.
 *
 * So the tests here pin the behaviour the office sees: the checkbox round-trips,
 * the stored value stays one of the two strings, and the string is read as a flag
 * by one rule rather than by each caller remembering one.
 */
class StaffDocumentCopySettingsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The flag
    // ---------------------------------------------------------------------

    #[Test]
    public function the_flag_is_off_until_an_admin_turns_it_on(): void
    {
        // A download is not a collection: the tab may have been closed unread, or
        // the copy wanted for somebody else. So the office opts in.
        $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'));
    }

    #[Test]
    public function an_admin_turning_the_flag_on_and_off_through_the_form_is_recorded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('staff.settings.update'), [
            'values' => ['digital_copy_counts_as_claimed' => '1'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Setting::flag('digital_copy_counts_as_claimed'));

        $this->actingAs($admin)->put(route('staff.settings.update'), [
            'values' => ['digital_copy_counts_as_claimed' => '0'],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'));
    }

    /**
     * The stored value is the string, not a boolean cast into it.
     *
     * If this ever stores 1 rather than '1', the value stops matching every other
     * row on the screen and the "unchanged" check in the controller starts
     * reporting edits that were not made.
     */
    #[Test]
    public function the_saved_value_is_one_of_the_two_strings(): void
    {
        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => ['digital_copy_counts_as_claimed' => '1'],
        ]);

        $this->assertSame('1', Setting::get('digital_copy_counts_as_claimed'));
    }

    #[Test]
    public function the_checkbox_comes_back_checked_after_it_was_turned_on(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'digital_copy_counts_as_claimed'],
            ['value' => '1']
        );

        $content = $this->actingAs($this->admin())
            ->get(route('staff.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="setting-digital_copy_counts_as_claimed"', $content);
        $this->assertStringContainsString('checked', $content);
    }

    /**
     * The strings a text box could produce are read as the officer meant.
     *
     * Someone who types "yes" into a value that used to be a text field has said
     * yes, and a screen that silently stored "yes" as off would be a switch that
     * lies. Anything unrecognised falls back to the default rather than to one of
     * the two, so a corrupt value is explicable instead of merely wrong.
     */
    #[Test]
    public function a_value_written_as_a_word_still_reads_as_the_office_meant(): void
    {
        foreach (['1', 'true', 'yes', 'on', 'YES', ' on '] as $on) {
            Setting::query()->updateOrCreate(['key' => 'digital_copy_counts_as_claimed'], ['value' => $on]);

            $this->assertTrue(Setting::flag('digital_copy_counts_as_claimed'), "'{$on}' should read as on.");
        }

        foreach (['0', 'false', 'no', 'off', '', ' off '] as $off) {
            Setting::query()->updateOrCreate(['key' => 'digital_copy_counts_as_claimed'], ['value' => $off]);

            $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'), "'{$off}' should read as off.");
        }
    }

    /**
     * A value that is neither leaves the door open.
     *
     * Rather than guessing, the reader reports whatever it was told to default to
     * - which is what lets a test above pin each case and what stops a typo from
     * quietly switching a policy off.
     */
    #[Test]
    public function an_unrecognised_value_falls_back_to_the_default_it_was_given(): void
    {
        Setting::query()->updateOrCreate(['key' => 'digital_copy_counts_as_claimed'], ['value' => 'perhaps']);

        $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'));
        $this->assertTrue(Setting::flag('digital_copy_counts_as_claimed', default: true));
    }

    // ---------------------------------------------------------------------
    // The rest of the document settings
    // ---------------------------------------------------------------------

    #[Test]
    public function the_document_settings_are_on_the_screen_and_the_footer_is_editable(): void
    {
        $content = $this->actingAs($this->admin())
            ->get(route('staff.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('digital_copy_footer', $content);
        $this->assertStringContainsString('barangay_logo_path', $content);
        $this->assertStringContainsString('Documents', $content);
    }

    #[Test]
    public function an_admin_can_edit_the_footer_the_pdf_prints(): void
    {
        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => [
                'digital_copy_footer' => '  Ask at the Barangay Hall for a stamped copy.  ',
            ],
        ])->assertSessionHasNoErrors();

        // Trimmed, because the controller trims every value: a footer with a stray
        // leading space would be a different string from the same sentence and the
        // "unchanged" check would call every save an edit.
        $this->assertSame(
            'Ask at the Barangay Hall for a stamped copy.',
            Setting::text('digital_copy_footer')
        );
    }

    /**
     * An empty footer is a real choice: the office can decide a copy needs no
     * footer, and clearing the field has to mean that rather than failing.
     */
    #[Test]
    public function an_admin_can_clear_the_footer(): void
    {
        $this->actingAs($this->admin())->put(route('staff.settings.update'), [
            'values' => ['digital_copy_footer' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('', Setting::text('digital_copy_footer'));
    }

    /**
     * A key the form does not carry is not created by posting it.
     *
     * The screen edits rows; it does not invent them. A key created from a POST
     * would be a value on the public pages that no migration ever wrote down.
     */
    #[Test]
    public function a_key_that_does_not_exist_is_refused_rather_than_created(): void
    {
        $this->actingAs($this->admin())
            ->put(route('staff.settings.update'), [
                'values' => ['digital_copy_encryption' => 'none'],
            ])
            ->assertSessionHas('error');

        $this->assertNull(Setting::get('digital_copy_encryption'));
    }

    // ---------------------------------------------------------------------
    // Who may change it
    // ---------------------------------------------------------------------

    #[Test]
    public function a_secretary_may_not_reach_the_settings_screen(): void
    {
        $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]))
            ->get(route('staff.settings.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_secretary_may_not_change_the_flag(): void
    {
        $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]))
            ->put(route('staff.settings.update'), [
                'values' => ['digital_copy_counts_as_claimed' => '1'],
            ])
            ->assertForbidden();

        $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_verified' => true]);
    }
}
