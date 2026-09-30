<?php

namespace App\Http\Controllers;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReliefBatchController extends Controller
{
    public function index(): View
    {
        return view('staff.batches.index', [
            'batches' => ReliefBatch::withCount(['distributions', 'distributions as received_count' => function ($query) {
                $query->where('status', ReliefDistribution::STATUS_RECEIVED);
            }])->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('staff.batches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'distribution_date' => ['required', 'date'],
            'items' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,completed'],
        ]);

        $batch = ReliefBatch::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'distribution_date' => $validated['distribution_date'],
            'items_included' => array_values(array_filter(array_map('trim', explode(',', (string) $validated['items'])))),
            'status' => $validated['status'],
            'created_by_user_id' => $request->user()->id,
        ]);

        $generated = $this->generateDistributions($batch);

        return redirect()->route('staff.batches.show', $batch)
            ->with('success', "Relief batch created and mapped to {$generated} households across all sectors.");
    }

    public function show(ReliefBatch $batch): View
    {
        $batch->loadCount('distributions');

        $sectorStats = ReliefDistribution::with('sector')
            ->where('relief_batch_id', $batch->id)
            ->get()
            ->groupBy('sector_id')
            ->map(function ($group) {
                return [
                    'sector' => $group->first()->sector,
                    'total' => $group->count(),
                    'received' => $group->where('status', ReliefDistribution::STATUS_RECEIVED)->count(),
                    'pending' => $group->where('status', ReliefDistribution::STATUS_PENDING)->count(),
                ];
            })
            ->values();

        return view('staff.batches.show', [
            'batch' => $batch,
            'sectorStats' => $sectorStats,
        ]);
    }

    public function toggleStatus(Request $request, ReliefBatch $batch)
    {
        $request->validate(['status' => ['required', 'in:active,completed']]);

        $batch->update(['status' => $request->input('status')]);

        return back()->with('success', 'Relief batch status updated.');
    }

    private function generateDistributions(ReliefBatch $batch): int
    {
        $households = ResidentProfile::query()
            ->where('verification_status', ResidentProfile::VERIFICATION_VERIFIED)
            ->orderBy('sector_id')
            ->get();

        foreach ($households as $household) {
            ReliefDistribution::firstOrCreate(
                ['relief_batch_id' => $batch->id, 'household_number' => $household->household_number],
                ['sector_id' => $household->sector_id],
            );
        }

        return $households->count();
    }
}
