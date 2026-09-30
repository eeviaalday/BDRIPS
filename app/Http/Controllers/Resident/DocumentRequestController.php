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
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $open = DocumentRequest::openCountFor($user);

        return view('resident.requests.index', [
            'requests' => DocumentRequest::query()
                ->whereBelongsTo($user)
                ->with('documentType')
                ->latest()
                ->get(),

            'openCount' => $open,
            'limit' => DocumentRequest::OPEN_REQUEST_LIMIT,
            'slotsRemaining' => max(0, DocumentRequest::OPEN_REQUEST_LIMIT - $open),
            'canCreate' => $user->residentProfile?->isVerified() === true
                && ! $user->isRestricted()
                && $open < DocumentRequest::OPEN_REQUEST_LIMIT,
        ]);
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

        // Re-read the type rather than trusting the posted id, so the label and
        // the fee that are stored come from the row that exists.
        $type = DocumentType::query()->active()->findOrFail($request->integer('document_type_id'));

        $created = DocumentRequest::create([
            'user_id' => $user->getKey(),
            'document_type_id' => $type->getKey(),
            'reference_number' => DocumentRequest::generateReferenceNumber(),
            'purpose' => $request->input('purpose'),
            'status' => DocumentRequest::STATUS_SUBMITTED,
            // Snapshotted, so a later fee change does not restate what the
            // resident was quoted at the moment they asked.
            'fee_amount' => $type->fee_amount,
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
            context: ['document_type_id' => $type->getKey(), 'fee_amount' => $type->fee_amount],
        );

        return redirect()
            ->route('resident.requests.show', $created)
            ->with('success', sprintf(
                'Your request was submitted. Quote reference %s at the Barangay Hall.',
                $created->reference_number
            ));
    }

    /**
     * One request, in full.
     *
     * Scoped through the user rather than resolved by id alone, so another
     * resident's reference number returns exactly what a wrong one does.
     */
    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        return view('resident.requests.show', [
            'documentRequest' => $documentRequest->load('documentType'),
        ]);
    }
}
