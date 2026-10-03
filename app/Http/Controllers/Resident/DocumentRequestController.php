<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DigitalCopyPassword;
use App\Services\DigitalCopyRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A resident's own document requests.
 *
 * Every read and every write is scoped to the signed-in user. A request id in
 * the URL is resolved through a whereBelongsTo on the user, so another
 * resident's request is not found and the answer is a 404 - identical to what a
 * made-up id returns. Resolving the model first and then checking ownership
 * would leak the difference between "does not exist" and "is not yours".
 */
class DocumentRequestController extends Controller
{
    /**
     * The resident's own requests, newest first.
     *
     * The status filter is a query parameter rather than a POST, so a filtered
     * list is a real URL: it survives a refresh and the back button, which a
     * list somebody is working through needs.
     *
     * "totalCount" is counted separately and unconditionally. Reading it off the
     * rendered collection would make the figure change with the filter, so
     * clicking "Open requests" would quietly report "3 on file" and then
     * "2 on file" for the same household.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $open = DocumentRequest::openCountFor($user);

        $filter = $this->statusFilter($request->query('status'));
        $statuses = $filter['statuses'];

        return view('resident.requests.index', [
            'requests' => DocumentRequest::query()
                ->whereBelongsTo($user)
                ->with('documentType')
                // WhereBelongsTo first, so the filter can only ever narrow this
                // resident's own rows.
                ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
                ->latest()
                ->get(),

            'openCount' => $open,
            'limit' => DocumentRequest::OPEN_REQUEST_LIMIT,
            'slotsRemaining' => max(0, DocumentRequest::OPEN_REQUEST_LIMIT - $open),
            'canCreate' => $user->residentProfile?->isVerified() === true
                && ! $user->isRestricted()
                && ! DocumentRequest::isSuspended($user)
                && $open < DocumentRequest::OPEN_REQUEST_LIMIT,

            // The unclaimed warning, and whether it is a hard block or something
            // to read first. canCreate above is the block; this is the reason,
            // and the page must be able to say why the button is gone.
            'suspensionNotice' => DocumentRequest::suspensionMessageFor($user),
            'isSuspended' => DocumentRequest::isSuspended($user),
            'strikeCount' => DocumentRequest::strikeCountFor($user),
            'strikesToSuspend' => DocumentRequest::strikesToSuspend(),
            'suspensionPolicy' => DocumentRequest::suspensionPolicySentence(),

            'statusFilter' => $filter['key'],
            'isFiltered' => $filter['key'] !== null,
            // Every request this resident has ever filed, whatever the filter is
            // showing.
            'totalCount' => DocumentRequest::query()->whereBelongsTo($user)->count(),
            'statusFilters' => self::statusFilters(),
        ]);
    }

    /**
     * The chip list, and the statuses each one selects.
     *
     * Written as one list so the chips and the query filter cannot disagree: a
     * chip with no matching entry, or an entry with no chip, is one of the two
     * being wrong.
     *
     * @return array<string, array{label: string, statuses: list<string>}>
     */
    public static function statusFilters(): array
    {
        return [
            'open' => [
                'label' => 'Open',
                'statuses' => DocumentRequest::OPEN_STATUSES,
            ],
            'under_review' => [
                'label' => 'Processing',
                'statuses' => [DocumentRequest::STATUS_UNDER_REVIEW],
            ],
            'ready_for_pickup' => [
                'label' => 'Ready for pickup',
                'statuses' => [DocumentRequest::STATUS_READY],
            ],
            'claimed' => [
                'label' => 'Completed',
                'statuses' => [DocumentRequest::STATUS_CLAIMED],
            ],
            'rejected' => [
                'label' => 'Rejected',
                'statuses' => [DocumentRequest::STATUS_REJECTED],
            ],
        ];
    }

    /**
     * Resolve a requested filter to the statuses it selects.
     *
     * An unrecognised value selects nothing, so the list falls back to all
     * requests rather than to an empty page: a hand-edited or stale URL should
     * show the resident their documents, not an error.
     *
     * @return array{key: string|null, statuses: list<string>}
     */
    private function statusFilter(mixed $requested): array
    {
        $key = is_string($requested) ? $requested : null;

        if ($key === null || $key === '' || $key === 'all') {
            return ['key' => null, 'statuses' => []];
        }

        $filters = self::statusFilters();

        if (! array_key_exists($key, $filters)) {
            return ['key' => null, 'statuses' => []];
        }

        return ['key' => $key, 'statuses' => $filters[$key]['statuses']];
    }

    /**
     * The new request form.
     *
     * Reachable by a pending resident, who needs to be told what is holding them
     * rather than finding a link that is simply missing. The view explains; the
     * form request refuses the POST, so nothing is created either way.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $open = DocumentRequest::openCountFor($user);

        return view('resident.requests.create', [
            'types' => DocumentType::query()->active()->get(),
            'openCount' => $open,
            'limit' => DocumentRequest::OPEN_REQUEST_LIMIT,
            'slotsRemaining' => max(0, DocumentRequest::OPEN_REQUEST_LIMIT - $open),
            'isVerified' => $user->residentProfile?->isVerified() === true,
            'isRestricted' => $user->isRestricted(),
            'suspensionNotice' => DocumentRequest::suspensionMessageFor($user),
            'isSuspended' => DocumentRequest::isSuspended($user),
        ]);
    }

    /**
     * Withdraw one of this resident's own requests, before it is ready.
     *
     * Ownership is not re-checked here. The route binder resolves documentRequest
     * through a whereBelongsTo on the signed-in user for every non-staff route,
     * so another resident's request was never found: it answered 404 before this
     * method ran, identically to an id that does not exist. A second ownership
     * check would be a third answer to a question two layers already settled.
     *
     * Cancelling is not a strike and cannot clear one. The two are separate
     * columns, and this one writes only cancelled_at: withdrawing a request you
     * changed your mind about is not the same act as not turning up for a
     * document the office finished.
     *
     * Refused once the request is ready, because from that moment the document
     * exists and is waiting at the hall. Withdrawing it then would leave a made
     * document nobody is expecting and a resident with no record of asking for it;
     * collecting it is the remedy, and the slot hold is what the policy says
     * happens if they do not.
     */
    public function cancel(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        if (! $documentRequest->canBeCancelled()) {
            abort(409, sprintf(
                'Request %s is %s, so it can no longer be cancelled. Collect it at the Barangay Hall instead.',
                $documentRequest->reference_number,
                $documentRequest->statusLabel()
            ));
        }

        $from = $documentRequest->status;

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $documentRequest->recordStatusChange($request->user(), $from, DocumentRequest::STATUS_CANCELLED);

        return redirect()
            ->route('resident.requests.index')
            ->with('success', sprintf(
                'Request %s was cancelled. Your open request slot is free again.',
                $documentRequest->reference_number
            ));
    }

    /**
     * Send this resident their document as an encrypted PDF.
     *
     * Ownership is not re-checked here, for the same reason cancel() does not:
     * the route binder resolves documentRequest through a whereBelongsTo on the
     * signed-in user for every non-staff route, so another resident's request was
     * never found.
     *
     * Refused unless the request is ready. That is canBeDownloaded() rather than
     * `status === ready` spelled out here, so the button on the page and this
     * answer come from one place.
     *
     * A resident with no birthdate on file, or whose surname has no letter that can
     * be put on a keyboard, gets an instruction to ask at the hall instead of a
     * file. The password is derived from those two facts precisely so nobody has
     * to invent or reset one, and a resident those facts cannot produce a password
     * for would otherwise be handed a copy opened by a guess - which is a worse
     * outcome than a short walk to the counter.
     *
     * THE ORDER HERE IS THE POINT
     *
     * Render, then record, then send. The delivery row and the audit entry are
     * written after the bytes exist and before the response goes out, so a failure
     * to render leaves no trace claiming a document was issued, and the count the
     * staff desk reads is a count of copies that were really produced.
     *
     * Nothing is written to storage. The bytes are held in memory for this request
     * and streamed, because a PDF in public/ is a PDF anybody with the reference
     * number can fetch without signing in - which is precisely what the password
     * exists to prevent, undone by leaving the file on a web server.
     *
     * The password appears nowhere but the bytes: not in the response, not in the
     * delivery row, not in the audit context. The resident derives their own from
     * their own details, and the office keeps no copy of it.
     */
    public function downloadDocument(Request $request, DocumentRequest $documentRequest): Response
    {
        if (! $documentRequest->canBeDownloaded()) {
            abort(409, sprintf(
                'Request %s is %s, so there is no digital copy to download yet. A copy is '
                .'available once the Barangay Hall has marked it ready for pickup.',
                $documentRequest->reference_number,
                $documentRequest->statusLabel()
            ));
        }

        $password = DigitalCopyPassword::for($documentRequest->user);

        if ($password === null) {
            abort(409, sprintf(
                'A password-protected copy of %s cannot be made from your account, because your '
                .'birthdate or surname is not on file. Please ask at the Barangay Hall, quoting '
                .'%s.',
                $documentRequest->reference_number,
                $documentRequest->reference_number
            ));
        }

        $bytes = app(DigitalCopyRenderer::class)->render($documentRequest, $password);

        $this->recordDelivery($request->user(), $documentRequest);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $this->filenameFor($documentRequest)),
            // A copy is generated per download and is never cached by anything: the
            // bytes are keyed to a password derived from this resident, and a
            // shared or intermediary cache is not somewhere they should end up.
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    /**
     * Record that a copy was issued, and optionally that it counts as collection.
     *
     * The auto-claim is opt-in through settings and off by default, because a
     * download is not a collection: the tab may have been closed unread, or the
     * copy wanted for somebody else. An office that wants a download to close the
     * request turns that on knowingly, and the audit entry says which channel the
     * collection came through so a later reader is not left guessing whether the
     * resident came to the hall.
     *
     * Only ever marks a request that is not already collected, and only from ready,
     * so a second download cannot overwrite a claimed_at the desk wrote.
     */
    private function recordDelivery(User $user, DocumentRequest $documentRequest): void
    {
        DocumentDelivery::create([
            'document_request_id' => $documentRequest->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $logger = app(AuditLogger::class);

        $logger->log(
            actor: $user,
            action: 'request.pdf_downloaded',
            subject: $documentRequest,
            description: sprintf(
                'Digital copy of %s downloaded by the resident',
                $documentRequest->reference_number
            ),
            // The reference number and the type are already on the subject row, so
            // repeating them here would only add a second place for them to be
            // wrong. What is not derivable is that the copy was issued by download
            // rather than handed over at the desk, and that is the channel.
            context: ['channel' => 'resident_download'],
        );

        if (! Setting::flag('digital_copy_counts_as_claimed')) {
            return;
        }

        if (! $documentRequest->canBeClaimed() || $documentRequest->status !== DocumentRequest::STATUS_READY) {
            return;
        }

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);

        $logger->log(
            actor: $user,
            action: 'request.claimed',
            subject: $documentRequest,
            description: sprintf(
                'Request %s marked collected by download',
                $documentRequest->reference_number
            ),
            context: ['channel' => 'resident_download'],
        );
    }

    /**
     * A filename the resident's browser will name the file.
     *
     * Built from the reference number rather than the document type, because the
     * reference is unique and the type is not - two copies of a clearance must not
     * both be saved as "barangay-clearance.pdf", or the second overwrites the
     * first in the Downloads folder with no warning.
     */
    private function filenameFor(DocumentRequest $documentRequest): string
    {
        return preg_replace(
            '/[^A-Za-z0-9._-]/',
            '-',
            sprintf('digital-copy-%s.pdf', $documentRequest->reference_number)
        ) ?? 'digital-copy.pdf';
    }

    /**
     * Record a new request.
     */
    public function store(StoreDocumentRequestRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Re-read the type rather than trusting the posted id, so the label that
        // is stored comes from the row that exists.
        $type = DocumentType::query()->active()->findOrFail($request->integer('document_type_id'));

        $created = DocumentRequest::create([
            'user_id' => $user->getKey(),
            'document_type_id' => $type->getKey(),
            'reference_number' => DocumentRequest::generateReferenceNumber(),
            'purpose' => $request->input('purpose'),
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        app(AuditLogger::class)->log(
            actor: $user,
            action: 'request.submitted',
            subject: $created,
            description: sprintf(
                'Resident submitted request %s for %s',
                $created->reference_number,
                $type->label
            ),
            context: ['document_type_id' => $type->getKey()],
        );

        return redirect()
            ->route('resident.requests.index')
            ->with('success', sprintf(
                'Your request was submitted. Quote reference %s at the Barangay Hall.',
                $created->reference_number
            ));
    }

    /**
     * The old single-request page, now a redirect to the list.
     *
     * The detail view became the Track dialog on the list, which carries
     * everything the page carried: status, the submitted date and time, the
     * stated purpose, the rejection reason, the ready-for-collection notice and
     * the timeline. A second page showing one resident's own record was a
     * second thing to keep in step with the dialog.
     *
     * The route name stays, because the navbar, a bookmark and a message
     * somebody sent can all point at it.
     */
    public function show(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return redirect()->route('resident.requests.index');
    }
}
