<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = request()->user();

        /*
         * Scoped to the signed-in resident in the query itself, BEFORE any filter
         * is applied. A filter built on top of an unscoped query and then
         * constrained would be one refactor away from leaking another resident's
         * rows; constraining first means the filter can only ever narrow what
         * this resident already owns.
         *
         * Only these two query parameters are read. Anything else in the query
         * string is ignored rather than passed to the query builder, so a
         * crafted ?order_by= or ?user_id= cannot reach the database at all.
         */
        $query = DocumentRequest::where('user_id', $user->id);

        $status = $request->query('status');

        /*
         * "processing" is a filter, not a status: the database has no such
         * value, it means submitted OR under review. Handling it here rather
         * than in the view matters - filtering a paginator after the fact
         * narrows one page of ten and leaves the page count lying about how many
         * rows there are.
         */
        if ($status === 'processing') {
            $query->whereIn('status', [
                DocumentRequest::STATUS_SUBMITTED,
                DocumentRequest::STATUS_UNDER_REVIEW,
            ]);
        } elseif (is_string($status) && $status !== '' && in_array($status, DocumentRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            // The search runs against this resident's own rows only, because
            // $query is already constrained. The LIKE is parameterised.
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('reference_number', 'like', '%'.$escaped.'%')
                    ->orWhere('purpose', 'like', '%'.$escaped.'%');
            });
        }

        return view('resident.requests.index', [
            'requests' => $query->latest()->paginate(10)->withQueryString(),
            'status' => $status,
            'search' => $search,
            // The gate and the cap are both read by the view, and both are read
            // from the same place the guards read them: isVerified() on the
            // profile, and the three open statuses store() counts.
            'user' => $user,
            'profile' => $user->residentProfile,
            'openCount' => DocumentRequest::where('user_id', $user->id)
                ->whereIn('status', DocumentRequest::OPEN_STATUSES)
                ->count(),
        ]);
    }

    public function create(): View
    {
        $user = request()->user();

        return view('resident.requests.create', [
            'profile' => $user->residentProfile,
            'isRestricted' => $user->isRestricted(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->isRestricted()) {
            return back()->withErrors([
                'account' => 'Your online application privileges are temporarily restricted because of repeated abandoned document requests. Please visit the barangay hall to follow up.',
            ]);
        }

        $profile = $user->residentProfile;

        if ($profile === null || ! $profile->isVerified()) {
            return redirect()->route('resident.verification.status')
                ->with('error', 'You must complete resident verification before submitting document requests.');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', DocumentRequest::TYPES)],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        // Anti-spam: rate limit a resident to a handful of open requests at a time.
        $openCount = DocumentRequest::where('user_id', $user->id)
            ->whereIn('status', DocumentRequest::OPEN_STATUSES)
            ->count();

        if ($openCount >= 3) {
            return back()->withErrors([
                'document_type' => 'You already have up to 3 pending document requests. Please wait for those to be completed first.',
            ]);
        }

        DocumentRequest::create([
            'user_id' => $user->id,
            'reference_number' => DocumentRequest::generateReferenceNumber(),
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        return redirect()->route('resident.requests.index')
            ->with('success', 'Document request submitted. Track it using the reference number in the list below.');
    }

    /**
     * Look up one of the signed-in resident's own requests by reference number.
     *
     * The lookup is scoped to the user rather than to the reference alone.
     * Reference numbers are short, sequential and handed out in year order, so
     * an unscoped lookup let any signed-in resident read another resident's
     * document type, stated purpose and rejection reason by trying the next
     * number along. A reference that belongs to somebody else is treated the
     * same as one that does not exist: both resolve to no result, so the page
     * cannot be used to confirm that a guess was a real request.
     */
    public function track(Request $request): View
    {
        $reference = $request->query('reference');

        $documentRequest = $reference
            ? DocumentRequest::where('user_id', $request->user()->id)
                ->where('reference_number', $reference)
                ->first()
            : null;

        return view('resident.requests.track', [
            'reference' => $reference,
            'documentRequest' => $documentRequest,
        ]);
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        abort_if($documentRequest->user_id !== $request->user()->id, 403);

        return view('resident.requests.show', ['documentRequest' => $documentRequest]);
    }
}
