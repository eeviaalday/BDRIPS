<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The document review desk.
 *
 * Four actions on one record, and each is offered only when the request is in a
 * state the action means anything for. Marking something ready that was never
 * approved is a claim the record does not support, so the server refuses it as
 * firmly as the button is hidden.
 *
 * A rejection must carry a reason. It is the one state a resident cannot act on,
 * and the resident's own page shows this text back to them verbatim.
 */
class DocumentReviewController extends Controller
{
    /**
     * Every request, with the ones waiting on a decision first.
     *
     * DEFAULT IS "Everything", NOT "Awaiting a decision".
     *
     * The default used to be 'queue', which meant the page opened on a worklist
     * rather than a list: anything already approved, collected or rejected was
     * simply absent, and nothing on the page said that. A filter that hides
     * records by default cannot be told apart from a page that has lost them, so
     * the empty parameter now means every status and the select says "Everything".
     *
     * Two other things were wrong here and are fixed with it.
     *
     * The old 'all' SENTINEL is gone. There was both an `all => Everything` option
     * and a default of 'queue', so "show everything" was reachable two different
     * ways and a bookmark of one did not mean the other. The empty parameter is
     * now the only way to say everything.
     *
     * And the sort was self-contradicting: `awaitingDecision()` appends its own
     * oldest('created_at'), which then sat ahead of the outer latest() in the
     * ORDER BY, so the default view sorted oldest-first and every other filter
     * sorted newest-first. Ordering is now stated once, in one place.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        // The two statuses that make up the queue, for the ordering and the count.
        $awaiting = [DocumentRequest::STATUS_SUBMITTED, DocumentRequest::STATUS_UNDER_REVIEW];

        $query = DocumentRequest::query()
            // user.residentProfile is here for the same reason deliveries are, and
            // was missing: the row prints the household number, and without this the
            // profile was fetched once per row, so the queue cost one extra query
            // for every request on the page.
            ->with(['user', 'user.residentProfile', 'documentType'])
            // One subquery for the whole page rather than a count per row. This is
            // what keeps the queue's query count identical whether it is showing
            // one request or twenty: a badge that counted deliveries by touching
            // each row would make the desk's slowest page the busiest one.
            ->withCount('deliveries')
            ->when($search !== '', function ($requests) use ($search): void {
                $requests->where(function ($inner) use ($search): void {
                    $inner->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user.residentProfile', fn ($profile) => $profile
                            ->where('household_number', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'queue', fn ($requests) => $requests->whereIn('status', $awaiting))
            // Not a status: a date predicate over ready ones. Goes through the
            // model's scope so "overdue" means the same thing here as it does on
            // the record page and in the refusal the server gives.
            ->when($status === 'overdue', fn ($requests) => $requests->overdue())
            // The one filter that is about what a resident did rather than what a
            // Secretary did. whereHas rather than a join, because the question is
            // about the deliveries and not about a column on the request - and the
            // same subquery is what the withCount above uses, so a row that shows
            // "Downloaded" is a row this filter can find.
            ->when($status === 'downloaded', fn ($requests) => $requests
                ->whereHas('deliveries')
                ->whereIn('status', [DocumentRequest::STATUS_READY, DocumentRequest::STATUS_UNCLAIMED]))
            ->when(! in_array($status, ['', 'queue', 'overdue', 'downloaded'], true), fn ($requests) => $requests->where('status', $status))
            // Awaiting a decision first, then oldest-ready-longest, then newest.
            // The overdue ordering matters: a queue whose oldest item is buried is
            // a queue that generates unclaimed documents, which is the whole
            // problem the strike policy exists to answer.
            ->orderByRaw(
                'case when status in (?, ?) then 0 else 1 end',
                $awaiting
            )
            ->orderByRaw('case when status = ? then 0 else 1 end', [DocumentRequest::STATUS_READY])
            ->orderByRaw('ready_at asc')
            ->latest('created_at')
            ->latest('id');

        return view('staff.requests.index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'awaitingCount' => DocumentRequest::query()->whereIn('status', $awaiting)->count(),
            'readyCount' => DocumentRequest::query()
                ->where('status', DocumentRequest::STATUS_READY)
                ->count(),
            // The two figures the strike policy makes somebody accountable for.
            // Overdue is the actionable one: those are the requests about to
            // become unclaimed, and the number of them is a measure of the desk.
            'overdueCount' => DocumentRequest::overdue()->count(),
            'unclaimedCount' => DocumentRequest::query()
                ->where('status', DocumentRequest::STATUS_UNCLAIMED)
                ->count(),
            'excusedCount' => DocumentRequest::query()
                ->where('status', DocumentRequest::STATUS_UNCLAIMED)
                ->whereNotNull('unclaimed_excused_at')
                ->count(),
            // Copies issued, and how many requests they went out against. Two
            // numbers because they answer different questions and either alone
            // misleads: a handful of requests with five downloads each is a
            // resident whose password kept failing, not a busy month.
            'downloadCount' => DocumentDelivery::query()->count(),
            'downloadedRequestCount' => DocumentRequest::query()->whereHas('deliveries')->count(),
        ]);
    }

    public function show(DocumentRequest $documentRequest): View
    {
        return view('staff.requests.show', [
            'documentRequest' => $documentRequest->load([
                'user',
                'user.residentProfile',
                'documentType',
                'latestExcuse.user',
                // Newest first, because the question the desk asks is "when was the
                // last time somebody took a copy" and the answer is the top row.
                'deliveries' => fn ($query) => $query->latest('id'),
            ]),
        ]);
    }

    /**
     * Take a request for review. The only action that is always available on an
     * undecided request, and the one that says somebody has looked at it.
     */
    public function review(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [DocumentRequest::STATUS_SUBMITTED]);

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
            'reviewed_at' => now(),
        ]);

        $documentRequest->recordStatusChange(
            $request->user(),
            DocumentRequest::STATUS_SUBMITTED,
            DocumentRequest::STATUS_UNDER_REVIEW
        );

        return back()->with('success', 'Request '.$documentRequest->reference_number.' is now under review.');
    }

    /**
     * Mark a document ready for collection.
     */
    public function ready(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [
            DocumentRequest::STATUS_SUBMITTED,
            DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        // Read before the write. Reading it afterwards would log the transition
        // as "ready to ready", which is exactly the kind of wrong record an
        // audit trail exists to prevent.
        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_READY,
            'reviewed_at' => $documentRequest->reviewed_at ?? now(),
            'ready_at' => now(),
        ]);

        $documentRequest->recordStatusChange(
            $request->user(),
            $from,
            DocumentRequest::STATUS_READY
        );

        return back()->with('success', $documentRequest->reference_number.' is ready for collection.');
    }

    /**
     * Mark a document as collected. The terminal state: the resident has
     * their document, and the slot it held is free.
     *
     * Also offered on an UNCLAIMED request. A resident who comes back on day 40
     * has not forfeited the document, and refusing to mark it collected would
     * leave a document handed over with no record that it was. The strike stands
     * either way - collecting late does not undo it - because the office did hold
     * that document for weeks past the window.
     */
    public function claim(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [
            DocumentRequest::STATUS_READY,
            DocumentRequest::STATUS_UNCLAIMED,
        ]);

        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);

        $documentRequest->recordStatusChange(
            $request->user(),
            $from,
            DocumentRequest::STATUS_CLAIMED,
            null,
            $from === DocumentRequest::STATUS_UNCLAIMED ? ['collected_late' => true] : []
        );

        return back()->with('success', $documentRequest->reference_number.' was collected.');
    }

    /**
     * Mark a document Unclaimed: it was made ready and nobody collected it.
     *
     * Refused before the window has passed, not merely hidden, because a strike is
     * something the resident did not earn and the whole policy is built on
     * strikes counting only when the office genuinely held the document for the
     * full period.
     *
     * The status alone is not the cost. The strike is derived from unclaimed_at,
     * so this one write is what feeds the count, the suspension and the slot hold
     * - there is no counter to increment and nothing that can fall out of step.
     */
    public function unclaim(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [DocumentRequest::STATUS_READY]);

        if (! $documentRequest->canBeMarkedUnclaimed()) {
            abort(409, sprintf(
                'Request %s has been ready for %d days, so it cannot be marked unclaimed yet. '
                .'It becomes eligible after %d days.',
                $documentRequest->reference_number,
                (int) $documentRequest->daysReady(),
                DocumentRequest::readyDaysBeforeUnclaimed()
            ));
        }

        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_UNCLAIMED,
            'unclaimed_at' => now(),
        ]);

        // Who marked it is in the entry itself; how long it had been waiting is
        // in the context, so an audit reader can see this was not a borderline
        // call made a day early.
        $documentRequest->recordStatusChange(
            $request->user(),
            $from,
            DocumentRequest::STATUS_UNCLAIMED,
            null,
            [
                'days_ready' => (int) $documentRequest->daysReady(),
                'slot_hold_until' => $documentRequest->slotHoldExpiresAt()?->toDateTimeString(),
            ]
        );

        return back()->with('success', sprintf(
            '%s was marked unclaimed. It holds one of the resident\'s request slots for another %d days.',
            $documentRequest->reference_number,
            DocumentRequest::unclaimedSlotHoldDays()
        ));
    }

    /**
     * Excuse an Unclaimed request, so it stops counting against the resident.
     *
     * The note is REQUIRED. Excusing is the one action in this area that takes a
     * clerk's judgement about somebody else's reliability and removes a
     * consequence from them, and a record that reads only "excused" would leave
     * the next person unable to tell a considered decision from a favour.
     *
     * Excusing twice is refused. Once the column is set the strike has already
     * stopped counting, so a second excuse would write an audit entry describing
     * a change that had no effect.
     */
    public function excuse(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        // No status guard: canBeExcused() is keyed on unclaimed_at, and a request
        // collected late can still be excused. Guarding on the current status here
        // would quietly disagree with the button on the record page.
        if (! $documentRequest->canBeExcused()) {
            abort(409, sprintf(
                'Request %s was already excused.',
                $documentRequest->reference_number
            ));
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'note.required' => 'Say why this one was not the resident\'s fault.',
            'note.min' => 'Give a reason somebody can act on later, not two words.',
        ]);

        $documentRequest->update(['unclaimed_excused_at' => now()]);

        $documentRequest->recordExcuse($request->user(), $validated['note']);

        // No status change, so no transition to record: the request is still
        // unclaimed and the document is still uncollected. Only the resident's
        // accountability changed, and it changed because a row stopped being one
        // of the rows the strike count reads.
        return back()->with('success', sprintf(
            'The strike on %s was excused. It no longer counts against the resident.',
            $documentRequest->reference_number
        ));
    }

    /**
     * Reject a request, with a reason.
     */
    public function reject(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [
            DocumentRequest::STATUS_SUBMITTED,
            DocumentRequest::STATUS_UNDER_REVIEW,
        ]);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Give a reason, so the resident knows what to do instead.',
            'rejection_reason.min' => 'Give the resident something they can act on, not two words.',
        ]);

        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_at' => $documentRequest->reviewed_at ?? now(),
        ]);

        $documentRequest->recordStatusChange(
            $request->user(),
            $from,
            DocumentRequest::STATUS_REJECTED,
            $validated['rejection_reason']
        );

        return back()->with('success', $documentRequest->reference_number.' was rejected.');
    }

    /**
     * Refuse an action that the request's current state does not support.
     *
     * 409 rather than a silent no-op: the screen is stale, and pretending the
     * action succeeded would leave a Secretary believing a request was approved
     * when it was not.
     *
     * @param  list<string>  $allowedFrom
     */
    private function guard(Request $request, DocumentRequest $documentRequest, array $allowedFrom): void
    {
        if (! in_array($documentRequest->status, $allowedFrom, true)) {
            abort(409, sprintf(
                'Request %s is %s, so that action does not apply to it.',
                $documentRequest->reference_number,
                $documentRequest->statusLabel()
            ));
        }
    }
}
