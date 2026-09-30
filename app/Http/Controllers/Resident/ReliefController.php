<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A resident's own relief record.
 *
 * Scoped by the household number on their own profile, never by a number typed
 * into a form. A household number is short and sequential, so an open lookup
 * would let somebody read a neighbour's record by trying the next one along.
 * There is no search box here and there is not going to be one: a resident reads
 * their own row, and the staff side is where a household number is looked up by
 * someone with a reason to.
 *
 * A pending resident is told relief is locked rather than shown an empty page.
 * An empty page and a page saying "not for you" are different answers, and only
 * one of them is true.
 */
class ReliefController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->residentProfile;

        $household = $profile?->household_number;

        return view('resident.relief', [
            'isVerified' => $profile?->isVerified() === true,
            'householdNumber' => $household,

            // Null while locked, so the view never runs a query it should not.
            'distributions' => $profile?->isVerified() === true
                ? ReliefDistribution::query()
                    ->forHousehold((string) $household)
                    ->get()
                : null,

            // What is currently going out, which a resident can read whether or
            // not they are verified: the schedule is public information.
            'activeBatches' => ReliefBatch::query()
                ->active()
                ->orderByRaw('distribution_date is null, distribution_date asc')
                ->get(),
        ]);
    }
}
