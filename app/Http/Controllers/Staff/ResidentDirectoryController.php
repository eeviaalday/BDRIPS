<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The residents directory: every registered resident, searchable.
 *
 * A staff member, unlike a resident, may look across households here. That is
 * the job. The scoping that protects residents from each other does not apply,
 * and pretending otherwise would make the directory useless.
 */
class ResidentDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $sectorId = $request->integer('sector_id') ?: null;

        $query = ResidentProfile::query()
            ->with(['user', 'sector'])
            ->when($search !== '', function ($profiles) use ($search): void {
                $profiles->where(function ($inner) use ($search): void {
                    $inner->where('household_number', 'like', "%{$search}%")
                        ->orWhere('national_or_voter_id', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($sectorId !== null, fn ($profiles) => $profiles->where('sector_id', $sectorId))
            ->orderBy('household_number');

        return view('staff.residents.index', [
            'profiles' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'sectorId' => $sectorId,
            'sectors' => Sector::orderBy('id')->get(),
        ]);
    }

    /**
     * One resident: their record, their requests, and their relief history.
     */
    public function show(ResidentProfile $profile): View
    {
        return view('staff.residents.show', [
            'profile' => $profile->load(['user', 'sector']),
            'requests' => DocumentRequest::query()
                ->whereBelongsTo($profile->user)
                ->with('documentType')
                ->latest()
                ->get(),
        ]);
    }
}
