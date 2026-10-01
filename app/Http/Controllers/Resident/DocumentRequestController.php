<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                && $open < DocumentRequest::OPEN_REQUEST_LIMIT,

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
        ]);
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
