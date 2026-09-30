<?php

namespace App\Http\Controllers;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransparencyController extends Controller
{
    public function index(): View
    {
        return view('transparency.index', [
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->get(),
            'overallReceived' => ReliefDistribution::where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'overallTotal' => ReliefDistribution::count(),
            'result' => null,
        ]);
    }

    public function search(Request $request): View
    {
        $householdNumber = $request->input('household_number');

        $result = collect();
        if ($householdNumber !== null && $householdNumber !== '') {
            $result = ReliefDistribution::with('batch', 'sector')
                ->where('household_number', $householdNumber)
                ->latest()
                ->get();
        }

        return view('transparency.index', [
            'activeBatches' => ReliefBatch::active()->withCount('distributions')->get(),
            'overallReceived' => ReliefDistribution::where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
            'overallTotal' => ReliefDistribution::count(),
            'result' => $result,
            'searchedNumber' => $householdNumber,
        ]);
    }
}
