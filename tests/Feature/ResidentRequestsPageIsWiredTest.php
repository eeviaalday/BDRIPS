<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * That the resident requests page's controls are actually wired to anything.
 *
 * The defect these cover is invisible in a feature test that only checks markup,
 * and invisible in the console, which is why it survived. Alpine only
 * initialises a directive on an element that sits inside an x-data scope. The
 * "New Request" button and the Slots card carried x-on:click, and the page had no
 * x-data above them - <main> in the public layout has none - so Alpine never bound
 * either handler. Pressing them ran nothing, dispatched nothing, and threw
 * nothing: the attribute was simply never read.
 *
 * A test that asserts "the button exists and has a click handler" passes against
 * that bug. The only assertion that catches it is a structural one about where
 * the handler sits in the document, which is what this file is.
 */
class ResidentRequestsPageIsWiredTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function residentStateProvider(): array
    {
        return [
            // Pending, and pending at the limit: the pending resident is the one
            // whose controls had no scope, and filling the allowance changes which
            // control is rendered without changing the wiring.
            'pending' => ['pending', 0],
            'pending at the limit' => ['pending', DocumentRequest::OPEN_REQUEST_LIMIT],
            'verified below the limit' => ['verified', 1],
            'verified at the limit' => ['verified', DocumentRequest::OPEN_REQUEST_LIMIT],
        ];
    }

    #[Test]
    #[DataProvider('residentStateProvider')]
    public function every_dispatching_control_sits_inside_an_alpine_scope(string $state, int $requests): void
    {
        $resident = $this->resident($state);

        for ($i = 0; $i < $requests; $i++) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $orphan = $this->dispatchersOutsideAnyScope($this->page($resident));

        $this->assertSame(
            [],
            $orphan,
            'These controls dispatch an Alpine event from outside every x-data scope, '
            .'so Alpine never binds them and pressing them does nothing: '
            .implode(' | ', $orphan)
        );
    }

    /**
     * The specific control, pinned by name, because "the page has a scope" and
     * "this button is inside it" are different claims and only the second one is
     * the bug.
     */
    #[Test]
    #[DataProvider('residentStateProvider')]
    public function the_new_request_control_is_inside_an_alpine_scope(string $state, int $requests): void
    {
        $resident = $this->resident($state);

        for ($i = 0; $i < $requests; $i++) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $html = $this->page($resident);

        $control = $this->findByText($html, '+ New Request');

        $this->assertNotNull(
            $control,
            'The New Request control was not found on the page.'
        );

        $this->assertTrue(
            $this->hasScopeAncestor($control),
            'The New Request control dispatches from outside every x-data scope, '
            .'so Alpine never binds its click handler and the button is inert.'
        );
    }

    /**
     * The Slots card is the second control that was inert, and it is inert in the
     * same way for the same reason.
     */
    #[Test]
    #[DataProvider('residentStateProvider')]
    public function the_slots_card_is_inside_an_alpine_scope(string $state, int $requests): void
    {
        $resident = $this->resident($state);

        for ($i = 0; $i < $requests; $i++) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $card = $this->findByText($this->page($resident), 'Slots remaining');

        $this->assertNotNull($card, 'The Slots remaining card was not found.');

        $this->assertTrue(
            $this->hasScopeAncestor($card),
            'The Slots card dispatches from outside every x-data scope, so pressing it does nothing.'
        );
    }

    /**
     * The page carries a scope of its own, rather than relying on the layout to
     * provide one. <main> in the public layout has no x-data, and every public
     * page is in the same position.
     */
    #[Test]
    public function the_page_provides_an_alpine_scope_of_its_own(): void
    {
        $xpath = $this->xpath($this->page($this->resident('verified')));

        $this->assertGreaterThan(
            0,
            $xpath->query('//*[@x-data]')->length,
            'The page has no x-data of its own, so nothing in the page body can dispatch.'
        );
    }

    /**
     * Guards the assumption this whole file rests on: that the public layout does
     * not wrap the slot in a scope. If somebody adds x-data to <main> later, this
     * file stops being the thing that catches a missing local scope, and the
     * failure would go back to being silent.
     */
    #[Test]
    public function the_public_layout_provides_no_scope_around_the_page_slot(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/public.blade.php'));

        // The <main> that wraps {{ $slot }}.
        preg_match('~<main\b[^>]*>~', $layout, $matches);

        $this->assertNotEmpty($matches, 'Could not find the <main> element in the public layout.');

        $this->assertStringNotContainsString(
            'x-data',
            $matches[0],
            'The public layout now provides an x-data scope. The resident page has its '
            .'own, which is still correct, but this file is no longer what catches a '
            .'missing one.'
        );
    }

    // ---------------------------------------------------------------------
    // DOM helpers
    // ---------------------------------------------------------------------

    /**
     * Every element whose click handler dispatches an Alpine event, that has no
     * x-data ancestor. Each is described well enough to find it by hand.
     *
     * @return list<string>
     */
    private function dispatchersOutsideAnyScope(string $html): array
    {
        $xpath = $this->xpath($html);

        $found = [];

        /*
         * The attribute name cannot be written as @x-on:click: XPath reads the
         * colon as a namespace separator and rejects the predicate. Matching on
         * name() sidesteps that, which is the only way to select an attribute
         * whose name contains a colon.
         */
        foreach ($xpath->query('//*[@*[name()="x-on:click"]]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $handler = $node->getAttribute('x-on:click');

            if (! str_contains($handler, '$dispatch')) {
                continue;
            }

            if ($this->hasScopeAncestor($node)) {
                continue;
            }

            $found[] = sprintf(
                '<%s> "%s" [%s]',
                $node->nodeName,
                trim(preg_replace('/\s+/', ' ', $node->textContent) ?? ''),
                trim(preg_replace('/\s+/', ' ', $handler) ?? '')
            );
        }

        return $found;
    }

    /**
     * True when some ancestor carries x-data. The element itself does not count:
     * a scope has to wrap the control, not sit on it.
     */
    private function hasScopeAncestor(DOMElement $element): bool
    {
        for ($node = $element->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node instanceof DOMElement && $node->hasAttribute('x-data')) {
                return true;
            }
        }

        return false;
    }

    private function findByText(string $html, string $text): ?DOMElement
    {
        foreach ($this->xpath($html)->query('//*') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '') === $text) {
                return $node;
            }
        }

        return null;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        // The page is a full UTF-8 document with no XML declaration, so it has to
        // be loaded with encoding noise suppressed or the text comparisons in here
        // start failing on multibyte characters.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML('<?xml encoding="UTF-8">'.$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function page(User $resident): string
    {
        return $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    private function resident(string $state): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => $state === 'pending'
                ? ResidentProfile::VERIFICATION_PENDING
                : ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }
}
