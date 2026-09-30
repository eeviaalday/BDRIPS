<?php

namespace App\Http\Controllers;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReliefDistributionController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        /*
         * withCount('residents') is required, not an optimisation. The dashboard
         * renders {{ $sector->residents_count }} as the household headcount, and
         * without this the attribute does not exist on the model, so the view's
         * ?? '—' fallback fired and the figure read "—" on every sector, always.
         */
        $sectors = Sector::where('kagawad_user_id', $user->id)
            ->withCount('residents')
            ->get();

        $batches = collect();
        if ($sectors->isNotEmpty()) {
            $batches = ReliefBatch::active()
                ->withCount(['distributions as pending_count' => function ($query) use ($sectors) {
                    $query->whereIn('sector_id', $sectors->pluck('id'))
                        ->where('status', ReliefDistribution::STATUS_PENDING);
                }, 'distributions as received_count' => function ($query) use ($sectors) {
                    $query->whereIn('sector_id', $sectors->pluck('id'))
                        ->where('status', ReliefDistribution::STATUS_RECEIVED);
                }])
                ->latest()
                ->get();
        }

        return view('kagawad.dashboard', [
            'sectors' => $sectors,
            'batches' => $batches,
        ]);
    }

    public function checklist(Request $request, ReliefBatch $batch, Sector $sector): View
    {
        $user = $request->user();

        // The authorisation anchor. sectors.kagawad_user_id is the single source
        // of truth for who may see a sector, and users.assigned_sector_id is
        // never read for this - it is a denormalised copy that can disagree.
        abort_if($sector->kagawad_user_id !== $user->id, 403);

        /*
         * Scoped to this batch AND this sector before any filter is applied.
         * A checklist that could be widened by a query parameter would let a
         * kagawad read another sector's households by editing a URL, which is
         * the one thing the guard above exists to prevent.
         */
        $query = ReliefDistribution::with('sector')
            ->where('relief_batch_id', $batch->id)
            ->where('sector_id', $sector->id);

        $status = $request->query('status');

        if (in_array($status, [ReliefDistribution::STATUS_PENDING, ReliefDistribution::STATUS_RECEIVED], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            // Household number and the logged receiver's name, both already
            // inside the sector scope set above. LIKE is parameterised, and the
            // wildcards in the user's own input are escaped so a '%' searches for
            // a literal one.
            $escaped = addcslashes($search, '%_\\');

            $query->where(function ($builder) use ($escaped): void {
                $builder->where('household_number', 'like', '%'.$escaped.'%')
                    ->orWhere('received_by_name', 'like', '%'.$escaped.'%');
            });
        }

        return view('kagawad.checklist', [
            'batch' => $batch,
            'sector' => $sector,
            'distribution' => $query->latest('id')->get(),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function markReceived(Request $request, ReliefDistribution $distribution)
    {
        $user = $request->user();
        $sector = $distribution->sector;

        abort_if($sector->kagawad_user_id !== $user->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,received'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        $isReceived = $validated['status'] === ReliefDistribution::STATUS_RECEIVED;

        $defaultReceiverName = optional(
            ResidentProfile::where('household_number', $distribution->household_number)->first()?->user
        )->name;

        $distribution->update([
            'status' => $validated['status'],

            /*
             * claimed_at and logged_by_user_id are AUDIT facts: they record
             * that a receipt was claimed, when, and by whom. They are written
             * only on a move to received and are deliberately NOT cleared on a
             * correction back to pending - the previous version nulled all
             * three, which meant a mis-click left no trace that anybody had ever
             * logged the claim, and the relief record could quietly be rewritten
             * with nobody able to see it had happened.
             *
             * Only received_by_name is cleared, because that is the mutable
             * claim itself: a household recorded as pending has not named
             * anyone to have received goods.
             */
            'received_by_name' => $isReceived
                ? trim((string) $validated['received_by_name']) ?: $defaultReceiverName
                : null,
            'claimed_at' => $isReceived ? ($distribution->claimed_at ?? now()) : $distribution->claimed_at,
            'logged_by_user_id' => $isReceived ? $user->id : $distribution->logged_by_user_id,
        ]);

        return back()->with('success', 'Distribution status updated.');
    }
}
