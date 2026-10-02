<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
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
            ->with(['user', 'documentType'])
            ->when($search !== '', function ($requests) use ($search): void {
                $requests->where(function ($inner) use ($search): void {
                    $inner->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user.residentProfile', fn ($profile) => $profile
                            ->where('household_number', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'queue', fn ($requests) => $requests->whereIn('status', $awaiting))
            ->when($status !== '' && $status !== 'queue', fn ($requests) => $requests->where('status', $status))
            // Awaiting a decision first, then newest first. `awaitingDecision()` is
            // deliberately NOT used for the ordering: it carries its own
            // oldest('created_at'), which is what produced two contradictory sorts.
            ->orderByRaw(
                'case when status in (?, ?) then 0 else 1 end',
                $awaiting
            )
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
        ]);
    }

    public function show(DocumentRequest $documentRequest): View
    {
        return view('staff.requests.show', [
            'documentRequest' => $documentRequest->load(['user', 'user.residentProfile', 'documentType']),
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
     * Mark a document as collected. This is the terminal state: the resident has
     * their document, and the slot it held is free.
     */
    public function claim(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $this->guard($request, $documentRequest, [DocumentRequest::STATUS_READY]);

        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);

        $documentRequest->recordStatusChange(
            $request->user(),
            $from,
            DocumentRequest::STATUS_CLAIMED
        );

        return back()->with('success', $documentRequest->reference_number.' was collected.');
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
