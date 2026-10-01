<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the BROWSER sees in the resident dialogs, rather than what the response
 * body contains.
 *
 * Every other test on this page asserts against the HTML as a string, and that
 * is exactly why a dialog could be completely inert while every one of them
 * passed. The markup for the lock dialog was present and correct as text; what
 * was wrong was that the browser could not read it.
 *
 * The cause was a double quote inside a double-quoted x-data attribute. HTML has
 * no escape for that - Blade passes a backslash through untouched - so the first
 * quote closed the attribute early. The browser kept the text up to it as the
 * x-data value, threw away every remaining attribute on the element as junk, and
 * evaluated what was left as JavaScript. The result was a SyntaxError at boot, a
 * panel with no positioning and no visibility rules sitting in the page flow,
 * and a Close button whose handler referred to a function that did not exist.
 *
 * So these tests parse the response and check the attributes the parser actually
 * produced. A string assertion cannot fail on this class of bug; this one does.
 */
class ResidentModalAttributeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The attributes the resident lock dialog needs to be hidden, positioned and
     * operable.
     *
     * @return list<string>
     */
    private const RESIDENT_REQUIRED = [
        'x-data',
        'x-on:open-modal.window',
        'x-on:close-modal.window',
        'x-on:keydown.escape.window',
        'x-show',
        'x-cloak',
        'style',
        'class',
    ];

    /**
     * The shared modal's own set. It is a different component and is not being
     * changed here, so it is held to its own contract: it hides itself with the
     * server-rendered inline style instead of x-cloak, and it traps Tab with a
     * pair of keydown handlers rather than a method.
     *
     * @return list<string>
     */
    private const SHARED_REQUIRED = [
        'x-data',
        'x-init',
        'x-on:open-modal.window',
        'x-on:close-modal.window',
        'x-on:close.stop',
        'x-on:keydown.escape.window',
        'x-on:keydown.tab.prevent',
        'x-on:keydown.shift.tab.prevent',
        'x-show',
        'style',
        'class',
    ];

    // ---------------------------------------------------------------------
    // The pending resident's lock dialog
    // ---------------------------------------------------------------------

    /**
     * The regression itself: the browser must see all of the dialog's attributes.
     */
    #[Test]
    public function the_lock_dialogs_attributes_all_survive_html_parsing(): void
    {
        $root = $this->lockDialogRoot();

        foreach (self::RESIDENT_REQUIRED as $attribute) {
            $this->assertTrue(
                $root->hasAttribute($attribute),
                "The lock dialog root has no [{$attribute}]. An earlier attribute is being cut short, "
                    .'which is what makes the whole dialog inert.'
            );
        }
    }

    /**
     * The dialog must be an overlay, not a block in the page flow. Losing the
     * class is how a dialog ends up visible in the middle of the page with
     * nothing on top of it.
     */
    #[Test]
    public function the_lock_dialog_is_still_a_fixed_full_screen_overlay(): void
    {
        $class = $this->lockDialogRoot()->getAttribute('class');

        $this->assertStringContainsString('fixed', $class);
        $this->assertStringContainsString('inset-0', $class);
        $this->assertStringContainsString('z-50', $class);
    }

    /**
     * Hidden on load by two independent means: the inline style for a browser
     * that has not run Alpine yet, and x-cloak for the CSS rule.
     */
    #[Test]
    public function the_lock_dialog_is_hidden_by_both_the_inline_style_and_x_cloak(): void
    {
        $root = $this->lockDialogRoot();

        $this->assertStringContainsString('display: none', $root->getAttribute('style'));
        $this->assertSame('open', $root->getAttribute('x-show'));
    }

    /**
     * The state and the handlers have to name each other, or a click does nothing.
     */
    #[Test]
    public function the_lock_dialogs_state_and_handlers_agree_on_their_names(): void
    {
        $root = $this->lockDialogRoot();
        $data = $root->getAttribute('x-data');

        foreach (['open', 'show()', 'hide()', 'focusables()', 'onTab('] as $member) {
            $this->assertStringContainsString($member, $data, "The scope no longer defines {$member}.");
        }

        $this->assertStringContainsString('open: false', $data, 'The dialog must start closed.');

        foreach ([
            'x-on:open-modal.window',
            'x-on:close-modal.window',
            'x-on:keydown.escape.window',
        ] as $handler) {
            $this->assertMatchesRegularExpression(
                '/\b(show|hide)\(\)/',
                $root->getAttribute($handler),
                "[{$handler}] no longer calls a method that exists."
            );
        }
    }

    /**
     * The rule that caused it. A double quote anywhere inside a double-quoted
     * x-data ends the attribute, so this asserts the quote is simply not there.
     */
    #[Test]
    public function no_x_data_attribute_on_the_page_contains_a_double_quote(): void
    {
        $page = $this->pendingPage();

        $this->assertGreaterThan(0, $this->xDataAttributes($page), 'The page has no x-data to check.');

        foreach ($this->xDataAttributes($page) as $index => $value) {
            $this->assertStringNotContainsString(
                '"',
                $value,
                "x-data #{$index} contains a double quote, which closes the attribute early: "
                    .substr($value, 0, 80)
            );
        }
    }

    /**
     * The selector has to stay inside the attribute, so its quoting is part of
     * the contract rather than a style preference.
     */
    #[Test]
    public function the_focusable_selector_quotes_its_attribute_values_with_single_quotes(): void
    {
        $data = $this->lockDialogRoot()->getAttribute('x-data');

        $this->assertStringContainsString("input:not([type='hidden'])", $data);
        $this->assertStringContainsString("[tabindex]:not([tabindex='-1'])", $data);
    }

    // ---------------------------------------------------------------------
    // The Close button
    // ---------------------------------------------------------------------

    /**
     * Requirement 9: the button is inside the dialog and has a handler, rather
     * than being a decorative X that cannot be pressed.
     */
    #[Test]
    public function the_corner_close_button_is_inside_the_dialog_and_closes_it(): void
    {
        $root = $this->lockDialogRoot();
        $x = $this->buttonWithHandler($root, 'hide()');

        $this->assertNotNull($x, 'The dialog has no button that calls hide().');
        $this->assertSame('button', $x->getAttribute('type'), 'The corner button must not submit anything.');
        $this->assertStringContainsString('New requests are locked', $root->textContent);

        // Inside the dialog, not merely somewhere on the page.
        $ancestor = $x->parentNode;
        $inside = false;
        while ($ancestor instanceof DOMElement) {
            if ($ancestor === $root) {
                $inside = true;
                break;
            }
            $ancestor = $ancestor->parentNode;
        }

        $this->assertTrue($inside, 'The corner close button is not inside the dialog.');
    }

    /**
     * The named Close button in the footer, which dispatches the close event.
     */
    #[Test]
    public function the_named_close_button_sends_the_close_event_for_this_dialog(): void
    {
        $found = null;

        foreach ($this->buttons($this->lockDialogRoot()) as $button) {
            if (trim($button->textContent) === 'Close'
                && str_contains($button->getAttribute('x-on:click'), 'close-modal')) {
                $found = $button;
                break;
            }
        }

        $this->assertNotNull($found, 'The dialog has no working Close button.');

        $handler = $found->getAttribute('x-on:click');
        $this->assertStringContainsString('close-modal', $handler);
        $this->assertStringContainsString('requests-locked', $handler, 'It would close the wrong dialog.');
    }

    /**
     * The dialog keeps its explanation and the way through to verification.
     */
    #[Test]
    public function the_dialog_keeps_its_explanation_and_the_verification_link(): void
    {
        $text = $this->lockDialogRoot()->textContent;

        $this->assertStringContainsString('New requests are locked', $text);
        $this->assertStringContainsString('Barangay Secretary verifies your', $text);
        $this->assertStringContainsString('Check verification status', $text);
    }

    // ---------------------------------------------------------------------
    // The shared modal, which the verified resident uses
    // ---------------------------------------------------------------------

    /**
     * The shared modal is the one the fix must NOT have broken, so its attributes
     * get the same treatment.
     */
    #[Test]
    public function the_shared_modals_attributes_all_survive_html_parsing(): void
    {
        $root = $this->sharedModalRoot();

        foreach (self::SHARED_REQUIRED as $attribute) {
            $this->assertTrue(
                $root->hasAttribute($attribute),
                "The shared modal root has no [{$attribute}]. An earlier attribute is being cut short."
            );
        }

        $this->assertStringContainsString('display: none', $root->getAttribute('style'));
        $this->assertSame('show', $root->getAttribute('x-show'));
        $this->assertStringContainsString('show: false', $root->getAttribute('x-data'));
    }

    #[Test]
    public function no_x_data_attribute_on_the_verified_page_contains_a_double_quote(): void
    {
        foreach ($this->xDataAttributes($this->verifiedPage()) as $index => $value) {
            $this->assertStringNotContainsString('"', $value, "x-data #{$index} on the verified page is cut short.");
        }
    }

    // ---------------------------------------------------------------------
    // Fixtures and helpers
    // ---------------------------------------------------------------------

    private function lockDialogRoot(): DOMElement
    {
        return $this->rootWhoseXDataContains($this->pendingPage(), 'trigger: null');
    }

    private function sharedModalRoot(): DOMElement
    {
        return $this->rootWhoseXDataContains($this->verifiedPage(), 'firstFocusable');
    }

    private function rootWhoseXDataContains(string $html, string $needle): DOMElement
    {
        foreach ($this->elements($html) as $element) {
            $value = $element->getAttribute('x-data');

            if ($value !== '' && str_contains($value, $needle)) {
                return $element;
            }
        }

        $this->fail("No element's x-data contains [{$needle}]. The dialog is not being rendered.");
    }

    /**
     * Every x-data value the browser would parse out of the page.
     *
     * @return list<string>
     */
    private function xDataAttributes(string $html): array
    {
        $found = [];

        foreach ($this->elements($html) as $element) {
            if ($element->hasAttribute('x-data')) {
                $found[] = $element->getAttribute('x-data');
            }
        }

        return $found;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $elements = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @return list<DOMElement>
     */
    private function buttons(DOMElement $root): array
    {
        $buttons = [];

        foreach ($root->getElementsByTagName('button') as $button) {
            $buttons[] = $button;
        }

        return $buttons;
    }

    private function buttonWithHandler(DOMElement $root, string $handler): ?DOMElement
    {
        foreach ($this->buttons($root) as $button) {
            if (str_contains($button->getAttribute('x-on:click'), $handler)) {
                return $button;
            }
        }

        return null;
    }

    private function pendingPage(): string
    {
        return $this->actingAs($this->pendingResident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    private function verifiedPage(): string
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        return $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    private function pendingResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->pending()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
        ]);

        return $user;
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }
}
