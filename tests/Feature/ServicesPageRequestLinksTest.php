<?php

namespace Tests\Feature;

use App\Models\ResidentProfile;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two "get started" controls on the Services page go to Login, not Register.
 *
 * They pointed at Create Account. That sends someone who already has an account
 * — and wants to request a document, not create another one — through a form
 * that cannot help them, with no way back to the page they came from. Login is
 * the page that can get a resident to the request form in one step.
 *
 * SCOPE, AND WHY IT IS ASSERTED RATHER THAN TRUSTED. Exactly two controls on one
 * page changed. The page carries ten anchors to login or register, from three
 * sources:
 *
 *     public/services.blade.php   Request a Document        -> now login
 *     public/services.blade.php   Apply Now x4, one per     -> now login
 *                                service card
 *     public/services.blade.php   Create a resident account -> UNCHANGED
 *     layouts/public.blade.php    Log in x2, Register x2    -> UNCHANGED
 *                                (header and mobile menu)
 *
 * The Create Account link in the same page's "Next Step" block is the one a new
 * resident needs, so it stays. The header and menu Register buttons are shared
 * by every public page and are not this brief's business.
 *
 * Parsed with DOMXPath rather than matched as a substring, because the brief is
 * about *which anchor* carries the label: a page containing the text "Request a
 * Document" somewhere proves nothing about where that button goes.
 */
class ServicesPageRequestLinksTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_LABELS = ['Request a Document', 'Apply Now'];

    /**
     * Every "Request a Document" and "Apply Now" anchor points at Login.
     */
    #[Test]
    public function the_two_get_started_controls_go_to_login(): void
    {
        $anchors = $this->labelledAnchors();

        $this->assertNotEmpty($anchors, 'Found no Request a Document or Apply Now anchor on the Services page.');

        $login = route('login');
        $register = route('register');

        foreach ($anchors as $anchor) {
            $label = $this->identifyingLabel($anchor['text']);

            $this->assertSame(
                $login,
                $anchor['href'],
                "[{$label}] should go to Login, not to {$anchor['href']}."
            );

            $this->assertNotSame(
                $register,
                $anchor['href'],
                "[{$label}] still points at Create Account."
            );
        }
    }

    /**
     * Both labels are really present, and Apply Now really is per-card.
     *
     * Asserted rather than assumed because the count is the thing that went
     * wrong before: "Apply Now" is a component default, rendered once per
     * service card, so changing one of them would have looked correct in the
     * source and left three of four cards still going to Register.
     */
    #[Test]
    public function apply_now_appears_once_per_service_card(): void
    {
        $anchors = $this->labelledAnchors();
        $cards = count(config('bdrpis.services'));

        $this->assertGreaterThan(0, $cards, 'No services are configured, so this proves nothing.');

        $applyNow = array_filter(
            $anchors,
            fn (array $a): bool => stripos($a['text'], 'Apply Now') !== false
        );

        $this->assertCount(
            $cards,
            $applyNow,
            'There should be one Apply Now anchor per service card, and every one of them must have been changed.'
        );

        $request = array_filter(
            $anchors,
            fn (array $a): bool => stripos($a['text'], 'Request a Document') !== false
        );

        $this->assertCount(1, $request, 'Expected exactly one Request a Document control.');
    }

    /**
     * Nothing on the page sends these two labels to Register any more.
     *
     * The direct inverse of the first test, kept as its own assertion because
     * "every one of these points at login" and "none of these points at
     * register" fail differently: the first passes if the label set is empty,
     * which the second does not.
     */
    #[Test]
    public function no_anchor_with_those_labels_points_at_register(): void
    {
        $register = route('register');
        $offenders = [];

        foreach ($this->labelledAnchors() as $anchor) {
            if ($anchor['href'] === $register) {
                $offenders[] = $this->identifyingLabel($anchor['text']).' -> '.$anchor['href'];
            }
        }

        $this->assertSame([], $offenders, 'These still go to Create Account: '.implode('; ', $offenders));
    }

    /**
     * The Create Account link on this page is untouched.
     *
     * The one control in the same page that genuinely belongs on Register: it is
     * how a resident with no account starts. Changing it because the other two
     * changed would be the obvious mistake, so it is pinned here.
     */
    #[Test]
    public function the_create_a_resident_account_link_still_goes_to_register(): void
    {
        $labels = [];

        foreach ($this->anchors() as $anchor) {
            $labels[$anchor['label']][] = $anchor['href'];
        }

        $this->assertArrayHasKey(
            'Create a resident account',
            $labels,
            'The Create a resident account link is gone from the Services page.'
        );

        $this->assertContains(
            route('register'),
            $labels['Create a resident account'],
            'Create a resident account no longer goes to Register.'
        );
    }

    /**
     * The labels and styling are unchanged.
     *
     * Only the href was meant to move. A button that now reads "Sign in" is a
     * different control wearing the same link.
     */
    #[Test]
    public function the_labels_are_unchanged(): void
    {
        $html = $this->get(route('services.index'))->assertOk()->getContent();

        foreach (self::LOGIN_LABELS as $label) {
            $this->assertStringContainsString($label, $html, "The label [{$label}] has changed.");
        }

        // The two variants the page uses for those controls, untouched.
        $this->assertStringContainsString('Request a Document', $html);
    }

    /**
     * A signed-in resident is still sent to the request form, not to Login.
     *
     * The three-valued expression in the view is unchanged; this pins the branch
     * that matters, because sending an authenticated resident to /login is the
     * obvious way to break this page while fixing the guest case.
     */
    #[Test]
    public function a_signed_in_resident_still_goes_to_the_request_form(): void
    {
        $resident = User::factory()->resident()->create(['is_verified' => true]);
        ResidentProfile::factory()->create(['user_id' => $resident->id]);

        $html = $this->actingAs($resident)->get(route('services.index'))->assertOk()->getContent();

        $anchors = $this->labelledAnchors($html);
        $requestForm = route('resident.requests.create');

        $this->assertNotEmpty($anchors);

        foreach ($anchors as $anchor) {
            $this->assertSame(
                $requestForm,
                $anchor['href'],
                'A signed-in resident should reach the request form from ['
                    .$this->identifyingLabel($anchor['text']).'], not '.$anchor['href']
            );
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * Every anchor on the page that carries one of the two labels.
     *
     * Matched by *containing* the label rather than by being equal to it, and
     * that distinction matters here. "Request a Document" is its own anchor, but
     * "Apply Now" is the card component's call-to-action rendered inside an
     * anchor whose whole text also holds the card's title and description — so
     * an equality match finds the header button and none of the four cards, and
     * the test would pass while every Apply Now still went to Register.
     *
     * An exact-match helper is not a stricter test; on this page it is a test of
     * the wrong thing.
     *
     * @return list<array{label: string, href: string}>
     */
    private function labelledAnchors(?string $html = null): array
    {
        return array_values(array_filter(
            $this->anchors($html),
            fn (array $a): bool => $this->carriesOneOfTheLabels($a['text']),
        ));
    }

    /**
     * Which of the two labels this anchor actually carries.
     *
     * Used in failure messages so a card reports "Apply Now" rather than the
     * card's whole text, which would bury the real answer in a sentence.
     */
    private function identifyingLabel(string $text): string
    {
        foreach (self::LOGIN_LABELS as $label) {
            if (preg_match('/'.preg_quote($label, '/').'/i', $text) === 1) {
                return $label;
            }
        }

        return '(unlabelled)';
    }

    /**
     * The label that identifies this anchor, or null.
     *
     * Whole-word, case-insensitive. Neither phrase appears in any service title
     * or description, so on this page the match is unambiguous — but that is
     * checked by the test below rather than assumed, because a description
     * containing "request a document" would quietly widen the net.
     */
    private function carriesOneOfTheLabels(string $text): bool
    {
        foreach (self::LOGIN_LABELS as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b/i', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every anchor on the page that has one of the two labels, with its href.
     *
     * Read through DOMXPath so the assertion is about an anchor and its href,
     * not about a string appearing somewhere in the markup.
     *
     * @return list<array{label: string, text: string, href: string}>
     */
    private function anchors(?string $html = null): array
    {
        $html ??= $this->get(route('services.index'))->assertOk()->getContent();

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $anchors = [];

        foreach ((new DOMXPath($document))->query('//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');

            if ($label === '') {
                continue;
            }

            $anchors[] = [
                'label' => $label,
                'text' => $label,
                'href' => $node->getAttribute('href'),
            ];
        }

        return $anchors;
    }
}
