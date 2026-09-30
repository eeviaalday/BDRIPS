<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        return view('staff.sectors.index', [
            'sectors' => Sector::with('kagawad', 'residents')->orderBy('sector_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sector_name' => ['required', 'string', 'max:255'],
            'purok_name' => ['nullable', 'string', 'max:255'],
        ]);

        Sector::create($validated);

        return back()->with('success', 'Sector created.');
    }
}
