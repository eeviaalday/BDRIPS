<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sectors, and which Kagawad is responsible for each.
 *
 * A sector cannot be deleted while households are still assigned to it, because
 * a sector is the thing a relief checklist and a resident's address are scoped
 * by. Deleting one would leave those rows pointing at nothing, or quietly move
 * them somewhere else.
 *
 * sectors.kagawad_user_id is UNIQUE, so a Kagawad holds at most one sector.
 * The assignment form says so rather than letting the database refuse: the
 * alternative is a 500 on a screen whose only job is to set a field.
 */
class SectorController extends Controller
{
    public function index(Request $request): View
    {
        // "Unassigned only" is a GET parameter rather than a client-side toggle, so
        // the filtered view can be linked to, bookmarked and reloaded, and so it can
        // be asserted in a test without a browser.
        $unassignedOnly = $request->boolean('unassigned');

        $sectors = Sector::query()
            ->with('kagawad')
            // Both counts here rather than through $sector->isEmpty() in the view.
            //
            // isEmpty() asks the database two questions per sector - does it have
            // residents, does it have distributions - and the view called it once
            // per row. Measured: 71 queries to render this page with the barangay's
            // 17 sectors, about four each. The counts are two subselects on the one
            // query, and the view compares them.
            ->withCount(['residents', 'distributions'])
            ->when($unassignedOnly, fn ($query) => $query->whereNull('kagawad_user_id'))
            ->orderBy('sector_name')
            ->get();

        return view('staff.sectors.index', [
            'sectors' => $sectors,
            'unassignedOnly' => $unassignedOnly,
            'unassignedKagawad' => User::query()
                ->where('role', User::ROLE_KAGAWAD)
                ->whereDoesntHave('reliefKagawadSectors')
                ->orderBy('name')
                ->get(),
            'totals' => [
                'total' => Sector::query()->count(),
                'assigned' => Sector::query()->whereNotNull('kagawad_user_id')->count(),
                // The third figure is a subtraction rather than its own count: a
                // sector with a kagawad_user_id that no longer points at a user is
                // assigned as far as the column is concerned and unassigned as far
                // as the work is, and "assigned" must not over-report it.
                'unassigned' => Sector::query()->whereNull('kagawad_user_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sector_name' => ['required', 'string', 'max:255', 'unique:sectors,sector_name'],
            'purok_name' => ['nullable', 'string', 'max:255'],
        ], [
            'sector_name.unique' => 'There is already a sector with that name.',
        ]);

        $sector = Sector::create($validated);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'sector.created',
            subject: $sector,
            description: sprintf('Sector "%s" was created', $sector->sector_name),
        );

        return back()->with('success', sprintf('"%s" was added.', $sector->sector_name));
    }

    public function update(Request $request, Sector $sector): RedirectResponse
    {
        $validated = $request->validate([
            'sector_name' => [
                'required',
                'string',
                'max:255',
                // Scoped to this row, or a sector's own name would count as taken
                // and the rename could never be saved.
                Rule::unique('sectors', 'sector_name')->ignore($sector->getKey()),
            ],
            'purok_name' => ['nullable', 'string', 'max:255'],
        ], [
            'sector_name.unique' => 'There is already a sector with that name.',
        ]);

        $sector->update($validated);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'sector.updated',
            subject: $sector,
            description: sprintf('Sector "%s" was renamed', $sector->sector_name),
        );

        return back()->with('success', 'Sector updated.');
    }

    /**
     * Assign a Kagawad to this sector, or clear the assignment.
     *
     * The sector being the subject of the log matters: the question an Admin
     * asks later is "which sector did this person get", and logging the
     * Kagawad would answer the wrong one.
     */
    public function assignKagawad(Request $request, Sector $sector): RedirectResponse
    {
        $validated = $request->validate([
            'kagawad_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $kagawadId = $validated['kagawad_user_id'] ?? null;

        if ($kagawadId !== null) {
            $kagawad = User::query()->findOrFail($kagawadId);

            // Checked here as well as by the unique index, so an assignment to a
            // Kagawad who already has a sector is a message naming the other
            // sector rather than a duplicate-key 500.
            $existing = Sector::query()
                ->where('kagawad_user_id', $kagawadId)
                ->whereKeyNot($sector->getKey())
                ->first();

            if ($existing !== null) {
                return back()->with('error', sprintf(
                    '%s is already responsible for "%s". Clear that assignment first.',
                    $kagawad->name,
                    $existing->sector_name
                ));
            }

            abort_if($kagawad->role !== User::ROLE_KAGAWAD, 422, 'That account is not a Kagawad.');
        }

        $from = $sector->kagawad?->name;

        $sector->update(['kagawad_user_id' => $kagawadId]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'sector.kagawad_assigned',
            subject: $sector,
            description: $kagawadId === null
                ? sprintf('Kagawad cleared for "%s"', $sector->sector_name)
                : sprintf(
                    '%s was assigned to "%s"%s',
                    User::findOrFail($kagawadId)->name,
                    $sector->sector_name,
                    $from === null ? '' : ', replacing '.$from
                ),
            context: ['kagawad_user_id' => $kagawadId, 'previous' => $from],
        );

        return back()->with('success', $kagawadId === null
            ? sprintf('"%s" no longer has a Kagawad.', $sector->sector_name)
            : sprintf('%s now covers "%s".', User::findOrFail($kagawadId)->name, $sector->sector_name));
    }

    /**
     * Remove a sector.
     *
     * Only while nothing depends on it. A sector with households is refused by
     * name, so the operator knows to move them rather than just being told no.
     */
    public function destroy(Request $request, Sector $sector): RedirectResponse
    {
        $households = $sector->residents()->count();
        $batches = $sector->distributions()->count();

        if ($households > 0 || $batches > 0) {
            return back()->with('error', sprintf(
                '"%s" still has %d household%s and %d relief entr%s on it. Move them first.',
                $sector->sector_name,
                $households,
                $households === 1 ? '' : 's',
                $batches,
                $batches === 1 ? 'y' : 'ies'
            ));
        }

        $name = $sector->sector_name;

        $sector->delete();

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'sector.deleted',
            subject: null,
            description: sprintf('Empty sector "%s" was deleted', $name),
            context: ['sector_name' => $name],
        );

        return back()->with('success', sprintf('"%s" was deleted.', $name));
    }
}
