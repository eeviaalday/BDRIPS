<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Public tracking of a document request by reference number.
 *
 * The reference alone is not enough, and that is the whole design decision here.
 * A reference is BRGY-YYYY-NNNN - four random digits, so a few thousand
 * possibilities a year. Guessing them one at a time would walk a stranger's
 * request: their document type, their timestamps, and their rejection reason,
 * which is free text a Secretary wrote about them.
 *
 * So the lookup takes a second factor, the household number. Both are printed on
 * the request the resident filed and shown on their own request page, so the
 * person who filed it can always answer both. A guesser needs both, and the
 * route is throttled so they cannot try many.
 *
 * What comes back is deliberately short of the record: no name, no address, no
 * contact details. The person asking has proved they filed it and has no need of
 * the rest.
 */
class RequestTrackingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $reference = trim((string) $request->query('reference', ''));
        $household = trim((string) $request->query('household', ''));

        $tracked = null;

        // Only look once both fields are present. A half-filled form is a
        // half-filled form, not a failed search.
        if ($reference !== '' && $household !== '') {
            $tracked = DocumentRequest::query()
                ->where('reference_number', $reference)
                // The second factor, in the same query rather than compared
                // afterwards. Fetching first and checking in PHP would still be
                // correct, but it would read a stranger's record before deciding
                // they were not allowed to see it.
                ->whereHas('user.residentProfile', fn ($profiles) => $profiles
                    ->where('household_number', $household))
                ->with('documentType')
                ->first();
        }

        return view('tracking.index', [
            'reference' => $reference,
            'household' => $household,
            'tracked' => $tracked,
            'searched' => $reference !== '' && $household !== '',
            // One message for "no such reference" and "wrong household number".
            // Distinguishing them would turn this into an oracle for confirming a
            // guessed reference number.
            'notFound' => $reference !== '' && $household !== '' && $tracked === null,
            'timeline' => $tracked === null ? [] : self::timeline($tracked),
        ]);
    }

    /**
     * What the tracked request has been through, so the page does not re-derive
     * a timeline the model already owns.
     *
     * @return list<array{label: string, at: Carbon|null}>
     */
    private static function timeline(DocumentRequest $request): array
    {
        return array_values(array_filter([
            ['label' => 'Submitted', 'at' => $request->created_at],
            ['label' => 'Taken for review', 'at' => $request->reviewed_at],
            ['label' => 'Ready for pickup', 'at' => $request->ready_at],
            ['label' => 'Collected', 'at' => $request->claimed_at],
        ], fn (array $step): bool => $step['at'] !== null));
    }
}
