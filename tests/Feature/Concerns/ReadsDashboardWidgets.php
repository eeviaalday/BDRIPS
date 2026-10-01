<?php

namespace Tests\Feature\Concerns;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ResidentProfile;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reading a figure off the rendered dashboard.
 *
 * The reason this is a DOM parser rather than a regex: x-staff.stat-card renders
 * the value *before* the label - icon, figure, then caption. So "/label.*?N/"
 * does not read the card's own number, it runs on into the next card and finds
 * whatever figure happens to follow. Every regex version of that assertion was
 * passing for the wrong reason. Splitting the HTML on tags was no better: the
 * icon badge is a <span>, not a <div>, so no inner element carries rounded-xl
 * and the split never fired - which made every card report the first card's
 * number.
 *
 * So: parse it, find the figure by the class that only the figure has, walk up to
 * the card, and read the caption inside that card. If stat-card ever reorders or
 * drops a class, the lookup misses and the test fails loudly instead of quietly
 * reading a neighbour.
 */
trait ReadsDashboardWidgets
{
    /**
     * Every stat card on the page, as label => figure.
     *
     * @return array<string, string>
     */
    protected function statCards(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $cards = [];

        // tabular-nums is on the figure only; the label and hint are not
        // tabular, which is what keeps a digit out of a caption being read here.
        $figures = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tabular-nums ")]');

        if ($figures === false) {
            return [];
        }

        foreach ($figures as $figure) {
            if (! $figure instanceof DOMElement) {
                continue;
            }

            $card = $this->closestCard($figure);

            if ($card === null) {
                continue;
            }

            $label = $this->captionOf($card, $figure);

            if ($label !== null) {
                $cards[$label] = trim($figure->textContent);
            }
        }

        return $cards;
    }

    /**
     * The figure a named stat card shows, or null if there is no such card.
     */
    protected function statCardValue(string $html, string $label): ?string
    {
        return $this->statCards($html)[$label] ?? null;
    }

    /**
     * The caption: the <p> inside the card that is not the figure and not the
     * hint, which is the label stat-card renders after the figure.
     */
    private function captionOf(DOMElement $card, DOMElement $figure): ?string
    {
        foreach ($card->getElementsByTagName('p') as $paragraph) {
            if (! $paragraph instanceof DOMElement || $paragraph === $figure) {
                continue;
            }

            $text = trim($paragraph->textContent);

            // The hint is the second line, smaller and paler; the caption is the
            // one that names the card.
            if ($text !== '' && str_contains((string) $paragraph->getAttribute('class'), 'font-semibold')) {
                return $text;
            }
        }

        return null;
    }

    /**
     * The nearest ancestor that is the whole card: the <a> when the card is a
     * link, otherwise the styled root element.
     */
    private function closestCard(DOMElement $element): ?DOMElement
    {
        $node = $element;

        while ($node instanceof DOMElement) {
            $classes = ' '.$node->getAttribute('class').' ';

            if (str_contains($classes, ' rounded-xl ') || $node->tagName === 'a') {
                return $node;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * The queues the dashboard counts, as the controller reads them.
     *
     * Built with the same predicates as the controller so a test cannot pass
     * because it and the controller disagree about what "pending" means.
     *
     * @return array<string, int>
     */
    protected function expectedQueueCounts(): array
    {
        return [
            'Verifications pending' => ResidentProfile::query()
                ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
                ->count(),
            'Requests to review' => DocumentRequest::query()
                ->whereIn('status', [DocumentRequest::STATUS_SUBMITTED, DocumentRequest::STATUS_UNDER_REVIEW])
                ->count(),
            'Ready for pickup' => DocumentRequest::query()
                ->where('status', DocumentRequest::STATUS_READY)
                ->count(),
            'Active relief batches' => ReliefBatch::query()
                ->where('status', ReliefBatch::STATUS_ACTIVE)
                ->count(),
        ];
    }
}
