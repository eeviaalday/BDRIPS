<?php

namespace App\Http\Controllers;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TransparencyController extends Controller
{
    /**
     * The overall figures both the board and the lookup carry in their header.
     *
     * @return array{activeBatches: Collection, overallReceived: int, overallTotal: int}
     */
    private function statistics(): array
    {
        return [
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->get(),
            'overallReceived' => ReliefDistribution::where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'overallTotal' => ReliefDistribution::count(),
        ];
    }

    /**
     * The validation a household lookup accepts.
     *
     * The bound matters more than the shape: household numbers are short and
     * sequential, so this endpoint is the obvious place to walk the range and
     * read one household at a time. Capping the length stops an unbounded string
     * being pushed through the query, and allowing only the characters the
     * numbers actually use keeps a malformed guess from being silently treated
     * as a different household. The character class is deliberately permissive
     * about the prefix so a barangay that numbers its households differently is
     * not locked out of its own lookup.
     *
     * Static so the resident portal's copy of the form validates identically
     * instead of keeping a second list that can drift from this one.
     *
     * @return array<string, array<int, string>>
     */
    public static function householdLookupRules(): array
    {
        return [
            'household_number' => [
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/',
            ],
        ];
    }

    public function index(): View
    {
        return view('transparency.index', [
            ...$this->statistics(),
            'result' => null,
        ]);
    }

    /**
     * The lookup itself.
     *
     * Nullable rather than required: /transparency/search with nothing typed is
     * just the board, and answering that with a validation error turns a plain
     * visit to the URL into a failure. When a number IS supplied it is held to
     * the same rules the form states.
     */
    public function search(Request $request): View
    {
        $validated = $request->validate([
            'household_number' => ['nullable', ...self::householdLookupRules()['household_number']],
        ]);

        $searchedNumber = $validated['household_number'] ?? null;

        return view('transparency.index', [
            ...$this->statistics(),
            'result' => $searchedNumber === null
                ? null
                : ReliefDistribution::forHousehold($searchedNumber)->get(),
            'searchedNumber' => $searchedNumber,
        ]);
    }
}
