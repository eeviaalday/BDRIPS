<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The staff confirmation dialog, and the audit action label it was blocking.
 *
 * Both halves are small and both were real gaps. The dialog did not move focus
 * into itself, so a keyboard user opened a confirmation and was left on the page
 * behind it; and `request.submitted` had no entry in the label map, so the audit
 * log showed the raw slug in a column where every other row reads as English.
 */
class StaffConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every staff page that opens a confirmation dialog, with the dialog's name.
     *
     * @return array<string, array{0: string}>
     */
    public static function staffDialogPageProvider(): array
    {
        return [
            'account restrict' => ['staff.accounts.show'],
            'sectors add' => ['staff.sectors.index'],
        ];
    }

    // ------------------------------------------------- the dialog takes focus

    #[Test]
    #[DataProvider('staffDialogPageProvider')]
    public function every_staff_dialog_renders_a_focusable_target(string $route): void
    {
        $xpath = $this->dom($this->renderPage($route));

        $dialogs = $this->dialogs($xpath);

        $this->assertGreaterThan(0, $dialogs, "[{$route}] renders no dialog at all.");

        foreach ($dialogs as $dialog) {
            $this->assertNotNull(
                $this->firstFocusable($dialog),
                "[{$route}] a dialog has nothing focusable, so it cannot take focus when it opens."
            );
        }
    }

    #[Test]
    public function a_dialog_with_an_input_puts_focus_on_the_first_field(): void
    {
        ['html' => $html, 'xpath' => $xpath] = $this->page('staff.accounts.show');

        // The restrict dialog, whose slot holds a date field, a reason box and the
        // consequence line. firstFocusable() walks the modal in document order, so
        // this asserts the thing the user actually experiences: the dialog opens
        // with the cursor in the first field, not on Cancel.
        $modal = $this->modalFor($xpath, 'restrict');

        $this->assertNotNull($modal, 'The restrict dialog is missing.');
        $this->assertSame('INPUT', $this->firstFocusable($modal));
    }

    #[Test]
    public function a_dialog_with_no_input_puts_focus_on_cancel(): void
    {
        ['xpath' => $xpath] = $this->page('staff.sectors.index');

        // The sector delete dialog has no slot at all, so the first focusable is
        // the Cancel button - which is the correct landing place for a dialog that
        // only needs reading and dismissing.
        $modal = $this->modalFor($xpath, 'delete-sector-');

        $this->assertNotNull($modal, 'A sector delete dialog is missing.');
        $this->assertSame('BUTTON', $this->firstFocusable($modal));

        $buttons = $xpath->query('.//button', $modal);
        $this->assertGreaterThan(0, $buttons->length);
        $this->assertStringContainsString('Cancel', (string) $buttons->item(0)?->textContent);
    }

    #[Test]
    public function the_staff_dialog_gives_focus_back_to_the_trigger(): void
    {
        $html = $this->renderPage('staff.sectors.index');

        // Escape: x-modal hides itself but never restores focus, so the wrapper
        // listens for it too.
        $this->assertStringContainsString('x-on:keydown.escape.window="restore()"', $html);
        // Cancel and a programmatic close both route through the same restore.
        $this->assertStringContainsString('$dispatch(\'staff-dialog-closed\')', $html);
        $this->assertStringContainsString('x-on:staff-dialog-closed="restore()"', $html);
        $this->assertStringContainsString('remember()', $html);
    }

    #[Test]
    public function the_staff_dialog_wrapper_creates_no_box(): void
    {
        $xpath = $this->dom($this->renderPage('staff.sectors.index'));

        // A plain <div> around a position:fixed modal would still be a block in the
        // page flow and could add a gap where a dialog used to be.
        $wrappers = $xpath->query('//div[@class="contents"]');

        $this->assertGreaterThan(0, $wrappers->length, 'The dialog has no display:contents wrapper.');
    }

    #[Test]
    public function the_staff_dialog_keeps_the_shared_components_contract(): void
    {
        $html = $this->renderPage('staff.accounts.show');

        // A drop-in swap, so the swap could not have quietly changed behaviour:
        // the dialog still owns the only form for the action, and the slot still
        // renders above the buttons - which is what makes firstFocusable() land on
        // the first field rather than on Cancel.
        $xpath = $this->dom($html);

        // @csrf compiles to a hidden input, so the token is asserted as the
        // element it becomes rather than as the directive text.
        $this->assertGreaterThan(0, $xpath->query('//input[@name="_token"]')->length, 'The dialog form carries no CSRF token.');
        $this->assertGreaterThan(0, $xpath->query('//form[@method="POST"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//textarea[@name="reason"]')->length, 'The restrict dialog lost its reason field.');
    }

    #[Test]
    public function the_shared_confirm_dialog_and_modal_are_untouched(): void
    {
        // Quoted from the working tree rather than asserted from memory: the rule
        // is byte-for-byte, so a stray edit has to fail here.
        $shared = file_get_contents(base_path('resources/views/components/confirm-dialog.blade.php'));
        $modal = file_get_contents(base_path('resources/views/components/modal.blade.php'));

        // The shared one must still NOT pass focusable through - that is the whole
        // reason the staff variant exists.
        $this->assertStringContainsString('<x-modal :name="$name" maxWidth="md">', $shared);
        $this->assertStringNotContainsString('focusable', $shared);

        // And the focus hook it guards is still there for the resident modals.
        $this->assertStringContainsString("has('focusable')", $modal);
        $this->assertStringContainsString('firstFocusable().focus()', $modal);
    }

    #[Test]
    public function no_staff_page_still_uses_the_shared_dialog(): void
    {
        // The shared dialog is a compiled-away component name, so what is asserted
        // is its signature behaviour: a staff page must expose the focusable
        // target, which the shared one cannot do.
        foreach (['staff.accounts.show', 'staff.sectors.index', 'staff.batches.show'] as $route) {
            $xpath = $this->page($route)['xpath'];

            foreach ($this->dialogs($xpath) as $dialog) {
                $this->assertNotNull(
                    $this->firstFocusable($dialog),
                    "[{$route}] has a dialog with no focusable target, so it was not switched to the staff variant."
                );
            }
        }
    }

    #[Test]
    public function the_shared_dialog_is_replaced_on_every_staff_page_that_opens_one(): void
    {
        foreach (['staff.accounts.show', 'staff.sectors.index', 'staff.announcements.index',
            'staff.batches.show', 'staff.requests.show', 'staff.verifications.show'] as $route) {
            $html = $this->renderPage($route);

            $this->assertStringContainsString(
                'restore()',
                $html,
                "[{$route}] opens a dialog with no focus restoration."
            );
        }
    }

    #[Test]
    public function the_staff_dialog_passes_focusable_to_x_modal(): void
    {
        /*
         * Asserted from the component SOURCE, because `focusable` is only a flag:
         * x-modal tests it with $attributes->has('focusable') inside its own
         * x-init and never renders it, so there is nothing in the output to assert
         * on. The test above proves a focusable target exists; this proves the
         * attribute that makes the browser move focus to it is passed.
         *
         * A markup check is not the evidence - the browser run is - but without
         * this, deleting the attribute changes nothing a server test can see, and
         * that is exactly how the shared component came to lack it.
         */
        $source = file_get_contents(base_path('resources/views/components/staff/confirm-dialog.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '<x-modal :name="$name" maxWidth="md" focusable>',
            $source,
            'The staff dialog no longer passes focusable, so it cannot take focus.'
        );
    }

    #[Test]
    public function every_open_modal_trigger_on_a_staff_page_is_inside_an_alpine_scope(): void
    {
        /*
         * The inert-trigger bug, pinned.
         *
         * An x-on:click with no x-data ancestor is silently inert: the click
         * focuses the button and nothing happens, with no console error. Three
         * dialogs - close a batch, reject a request, reject a registration - have
         * been unreachable for exactly this reason, and two more on this branch.
         *
         * An x-on:click outside any x-data is a documented trap in this project,
         * and it is the one defect a DOM assertion can catch completely.
         */
        foreach (['staff.accounts.show', 'staff.announcements.index', 'staff.batches.show',
            'staff.requests.show', 'staff.sectors.index', 'staff.verifications.show'] as $route) {
            $xpath = $this->page($route)['xpath'];
            $checked = 0;

            foreach ($xpath->query('//*') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $handler = (string) $node->getAttribute('x-on:click');

                if (! str_contains($handler, '$dispatch')) {
                    continue;
                }

                $checked++;
                $this->assertTrue(
                    $node->hasAttribute('x-data') || $node->parentNode instanceof DOMElement
                        && $this->hasScopeAncestor($node),
                    "[{$route}] a trigger that dispatches open-modal has no Alpine scope, so it is inert: ".trim($node->textContent).''
                );
            }

            $this->assertGreaterThan(0, $checked, "[{$route}] has no open-modal trigger to check.");
        }
    }

    private function hasScopeAncestor(DOMNode $node): bool
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->hasAttribute('x-data')) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------- the audit action label

    #[Test]
    public function request_submitted_has_an_english_label(): void
    {
        $label = (new AuditLog(['action' => 'request.submitted']))->actionLabel();

        $this->assertSame('Request submitted', $label);
        $this->assertStringNotContainsString('.', $label, 'The label is still a raw action slug.');
        $this->assertNotSame('request.submitted', $label);
    }

    #[Test]
    public function the_audit_log_page_never_shows_a_raw_slug_for_request_submitted(): void
    {
        AuditLog::factory()->create([
            'action' => 'request.submitted',
            'description' => 'Resident submitted request BR-2026-0001 for a Barangay Clearance',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('staff.audit.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Request submitted', $html);

        // Scoped to the table on purpose. The action FILTER legitimately carries the
        // slug as each option's value, because that is the query parameter the form
        // submits - so asserting it is absent from the whole page would be
        // asserting that the filter does not work. What must not happen is the slug
        // being shown as the reader-facing label.
        $xpath = $this->dom($html);

        foreach ($xpath->query('//table//tbody//tr') as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = $row->getElementsByTagName('td');
            $action = trim($cells->item(2)?->textContent ?? '');

            $this->assertSame('Request submitted', $action, 'The audit table is still showing a raw action slug.');
        }
    }

    #[Test]
    public function every_action_the_application_logs_has_a_label(): void
    {
        // The gap was one missing key, and nothing noticed for as long as the
        // feature existed. This is the check that would have caught it: any action
        // string the code can produce must resolve to English, not to itself.
        $emitted = [
            'request.submitted', 'request.approved', 'request.under_review', 'request.ready',
            'request.claimed', 'request.rejected', 'user.role_changed', 'user.restricted',
            'user.unrestricted', 'account.updated', 'resident.verified', 'resident.rejected',
            'relief.received', 'relief.batch_created', 'relief.batch_closed', 'announcement.deleted',
            'settings.updated', 'sector.kagawad_assigned', 'login', 'logout',
        ];

        foreach ($emitted as $action) {
            $label = (new AuditLog(['action' => $action]))->actionLabel();

            $this->assertNotSame(
                $action,
                $label,
                "Action [{$action}] has no label and falls back to its own slug."
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_verified' => true]);
    }

    /**
     * A rendered staff page that contains at least one dialog.
     */
    private function renderPage(string $route): string
    {
        return $this->page($route)['html'];
    }

    /**
     * @return array{html: string, xpath: DOMXPath}
     */
    private function page(string $route): array
    {
        // The announcements list renders a dialog per row, so a page with no
        // announcements renders no dialog at all - and an assertion over "every
        // dialog on this page" then passes without looking at anything.
        if ($route === 'staff.announcements.index') {
            Announcement::factory()->count(2)->create();
        }

        $html = match ($route) {
            'staff.accounts.show' => $this->renderRoute($route, [$this->kagawad()]),
            'staff.batches.show' => $this->renderRoute($route, [$this->batch()]),
            'staff.requests.show' => $this->renderRoute($route, [$this->documentRequest()]),
            'staff.verifications.show' => $this->renderRoute($route, [$this->pendingProfile()]),
            default => $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent(),
        };

        return ['html' => $html, 'xpath' => $this->dom($html)];
    }

    private function renderRoute(string $route, array $parameters): string
    {
        return $this->actingAs($this->admin())
            ->get(route($route, $parameters))
            ->assertOk()
            ->getContent();
    }

    private function kagawad(): User
    {
        return User::factory()->create(['role' => User::ROLE_KAGAWAD, 'is_verified' => true]);
    }

    private function documentRequest(): DocumentRequest
    {
        return DocumentRequest::factory()->submitted()
            ->forResident(User::factory()->create(['is_verified' => true]))
            ->for(DocumentType::factory()->create())
            ->create();
    }

    private function pendingProfile(): ResidentProfile
    {
        return ResidentProfile::factory()->pending()
            ->for(User::factory()->create(['is_verified' => true]))
            ->for(Sector::factory()->create())
            ->create();
    }

    private function batch(): ReliefBatch
    {
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create(['status' => ReliefBatch::STATUS_ACTIVE]);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $sector->id,
            'household_number' => 'H-1',
        ]);

        return $batch;
    }

    /**
     * The dialog panel whose accessible name belongs to the given dialog.
     *
     * The name is on the inner panel, not on the fixed overlay, so the prefix is
     * matched there. The panel is also the right root for the focusable walk: the
     * overlay contains nothing else.
     */
    private function modalFor(DOMXPath $xpath, string $namePrefix): ?DOMElement
    {
        foreach ($xpath->query('//*[@aria-labelledby]') as $panel) {
            if (! $panel instanceof DOMElement) {
                continue;
            }

            if (str_starts_with((string) $panel->getAttribute('aria-labelledby'), $namePrefix)) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * What x-modal's firstFocusable() would return for this dialog.
     *
     * The same tags, the same exclusions, the same document order - walked in PHP
     * rather than with an XPath expression, because XPath 1.0 has no :not() and the
     * shared component's own selector uses it.
     */
    private function firstFocusable(DOMNode $root): ?string
    {
        $tags = ['a', 'button', 'input', 'textarea', 'select', 'details'];

        foreach ($root->getElementsByTagName('*') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (! in_array($tag, $tags, true) || $node->hasAttribute('disabled')) {
                continue;
            }

            if ($tag === 'input' && $node->getAttribute('type') === 'hidden') {
                continue;
            }

            if ($node->hasAttribute('tabindex') && $node->getAttribute('tabindex') === '-1') {
                continue;
            }

            return strtoupper($node->tagName);
        }

        return null;
    }

    /**
     * Every dialog on the page.
     *
     * @return array<int, DOMElement>
     */
    private function dialogs(DOMXPath $xpath): array
    {
        $found = [];

        foreach ($xpath->query('//*[@aria-modal="true"]') as $panel) {
            if ($panel instanceof DOMElement) {
                $found[] = $panel;
            }
        }

        return $found;
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
