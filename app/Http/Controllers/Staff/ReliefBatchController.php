<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Relief batches: one distribution event, opened and closed.
 *
 * Opening a batch also seeds its household rows, because a batch with no
 * households in it is not a distribution - it is a notice. Every verified
 * resident in the barangay gets one row, carrying their sector, so a Kagawad's
 * checklist is a query rather than a guess about who lives where.
 *
 * Seeding is insert-or-ignore on the compound unique, so opening a batch twice
 * cannot produce a second row for the same household.
 */
class ReliefBatchController extends Controller
{
    /**
     * Every batch, newest first, with an optional state filter.
     *
     * DEFAULT IS "Everything", AND IT NOW HAS A CONTROL.
     *
     * The default was already everything, but the page had no select for it, so
     * ?status=active narrowed the list invisibly and there was no way back. The
     * parameter is normalised to '' for "everything" so the empty parameter is the
     * one meaning no filter, the same as every other list in the staff area; an
     * old ?status=all bookmark still resolves to everything.
     *
     * Open batches first, then newest. A closed batch is finished, so it should
     * not sit above a batch people are still handing out.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        if ($status === 'all') {
            $status = '';
        }

        $search = trim((string) $request->query('q', ''));

        $query = ReliefBatch::query()
            ->withCount('distributions')
            ->with('createdBy')
            // Inside a group, so that a search term and a state filter are ANDed
            // rather than the OR leaking out and matching every batch in the other
            // state - the same trap the announcements list documents.
            ->when($search !== '', fn ($batches) => $batches->where(
                fn ($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when(
                in_array($status, ['active', 'closed'], true),
                fn ($batches) => $batches->where('status', $status)
            )
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReliefBatch::STATUS_ACTIVE])
            ->latest('created_at')
            ->latest('id');

        return view('staff.batches.index', [
            'batches' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'activeCount' => ReliefBatch::query()->active()->count(),
        ]);
    }

    public function create(): View
    {
        return view('staff.batches.create', [
            'sectors' => Sector::orderBy('id')->get(),
        ]);
    }

    /**
     * Open a batch and give every verified household a place in it.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items_included' => ['nullable', 'string', 'max:1000'],
            'distribution_date' => ['nullable', 'date'],
            'sector_ids' => ['nullable', 'array'],
            'sector_ids.*' => ['integer', 'exists:sectors,id'],
        ]);

        $batch = DB::transaction(function () use ($request, $validated): ReliefBatch {
            $batch = ReliefBatch::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                // A textarea of one item per line, stored as a list. Free text
                // would have to be re-parsed by every reader of the batch.
                'items_included' => $this->itemsFrom($validated['items_included'] ?? null),
                'distribution_date' => $validated['distribution_date'] ?? null,
                'status' => ReliefBatch::STATUS_ACTIVE,
                'created_by_user_id' => $request->user()->getKey(),
            ]);

            $this->seedHouseholds($batch, $validated['sector_ids'] ?? []);

            return $batch;
        });

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'relief.batch_created',
            subject: $batch,
            description: sprintf('Relief batch "%s" was opened', $batch->title),
            context: ['households' => $batch->distributions()->count()],
        );

        return redirect()
            ->route('staff.batches.show', $batch)
            ->with('success', sprintf('"%s" is open. %d households were added to the checklist.', $batch->title, $batch->distributions()->count()));
    }

    /**
     * One batch, with progress by sector.
     */
    public function show(ReliefBatch $reliefBatch): View
    {
        $reliefBatch->load('createdBy');

        // One grouped read rather than a count per sector: the monitor shows every
        // sector at once, and N+1 counts here would be the slowest thing on the
        // screen.
        $bySector = ReliefDistribution::query()
            ->where('relief_batch_id', $reliefBatch->getKey())
            ->select('sector_id', 'status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('sector_id', 'status')
            ->get()
            ->groupBy('sector_id');

        $sectors = Sector::orderBy('id')->get()->map(function (Sector $sector) use ($bySector) {
            $rows = $bySector->get($sector->id, collect());

            return [
                'sector' => $sector,
                'total' => (int) $rows->sum('aggregate'),
                'received' => (int) ($rows->firstWhere('status', ReliefDistribution::STATUS_RECEIVED)->aggregate ?? 0),
            ];
        });

        return view('staff.batches.show', [
            'batch' => $reliefBatch,
            'sectors' => $sectors,
            'progress' => $reliefBatch->progress(),
        ]);
    }

    /**
     * Close a batch.
     *
     * A status change, never a delete: closing is how the record of what was
     * handed out survives, and how a Kagawad can still read the history of a
     * finished batch.
     */
    public function close(Request $request, ReliefBatch $reliefBatch): RedirectResponse
    {
        abort_if($reliefBatch->isActive() === false, 409, 'That batch is already closed.');

        $reliefBatch->update([
            'status' => ReliefBatch::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $reliefBatch->recordClosed($request->user());

        return back()->with('success', sprintf('"%s" is closed. The checklist stays readable.', $reliefBatch->title));
    }

    /**
     * Reopen a closed batch.
     *
     * Offered because a batch closed by mistake is otherwise unrecoverable
     * without a database edit, and the only cost of allowing it is that a closed
     * batch is not permanently final.
     */
    public function reopen(Request $request, ReliefBatch $reliefBatch): RedirectResponse
    {
        abort_if($reliefBatch->isActive(), 409, 'That batch is already open.');

        $reliefBatch->update([
            'status' => ReliefBatch::STATUS_ACTIVE,
            'closed_at' => null,
        ]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'relief.batch_reopened',
            subject: $reliefBatch,
            description: sprintf('Relief batch "%s" was reopened', $reliefBatch->title),
        );

        return back()->with('success', sprintf('"%s" is open again.', $reliefBatch->title));
    }

    /**
     * Give every verified household in the chosen sectors a row.
     *
     * @param  list<int>  $sectorIds
     */
    private function seedHouseholds(ReliefBatch $batch, array $sectorIds): void
    {
        $query = ResidentProfile::query()
            ->where('verification_status', ResidentProfile::VERIFICATION_VERIFIED);

        // An empty selection means every sector rather than no sectors: the
        // create form offers a multi-select with a "all sectors" default, and a
        // batch nobody chose a sector for should cover the barangay.
        if ($sectorIds !== []) {
            $query->whereIn('sector_id', $sectorIds);
        }

        $rows = [];
        $now = now();

        foreach ($query->get() as $profile) {
            $rows[] = [
                'relief_batch_id' => $batch->getKey(),
                'household_number' => $profile->household_number,
                'sector_id' => $profile->sector_id,
                'status' => ReliefDistribution::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            // insertOrIgnore rather than insert: the compound unique on
            // (relief_batch_id, household_number) would turn a second open of
            // the same household into a duplicate-key error that rolls the whole
            // batch back.
            DB::table('relief_distributions')->insertOrIgnore($chunk);
        }
    }

    /**
     * One item per line, blank lines dropped.
     *
     * @return list<string>
     */
    private function itemsFrom(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $raw) ?: [])));
    }
}
