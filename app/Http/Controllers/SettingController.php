<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('staff.settings.index', [
            'officials' => User::whereIn('role', [
                User::ROLE_ADMIN,
                User::ROLE_SECRETARY,
                User::ROLE_KAGAWAD,
            ])->orderBy('role')->get(),
            'sectors' => Sector::orderBy('sector_name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $keys = [
            'barangay_name',
            'barangay_address',
            'punong_barangay',
            'secretary_name',
            'emergency_hotline',
            'clearance_fee',
            'indigency_fee',
            'jobseeker_fee',
            'residency_fee',
        ];

        /*
         * 'sometimes' on every rule, because the page posts these from separate
         * forms: one for the letterhead, one for the fee rates. Neither form
         * carries the other's fields, so a plain 'required' on barangay_name
         * meant the fee form could never validate - every attempt to save a
         * fee was rejected for a field it does not contain. Each rule now
         * applies only to the keys the request actually sent, and the save loop
         * below writes only those keys, so saving fees cannot blank the header.
         */
        $request->validate([
            'barangay_name' => ['sometimes', 'required', 'string', 'max:255'],
            'barangay_address' => ['sometimes', 'required', 'string', 'max:255'],
            'punong_barangay' => ['sometimes', 'nullable', 'string', 'max:255'],
            'secretary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_hotline' => ['sometimes', 'nullable', 'string', 'max:50'],
            'clearance_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'indigency_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'jobseeker_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'residency_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $saved = 0;

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
                $saved++;
            }
        }

        if ($saved === 0) {
            return back()->withErrors(['settings' => 'Nothing was submitted to save.']);
        }

        return back()->with('success', 'System parameters updated.');
    }

    public function assignKagawad(Request $request)
    {
        $validated = $request->validate([
            'kagawad_user_id' => ['required', 'exists:users,id'],
            'sector_id' => ['required', 'exists:sectors,id'],
        ]);

        $user = User::findOrFail($validated['kagawad_user_id']);
        $user->update(['role' => User::ROLE_KAGAWAD, 'assigned_sector_id' => $validated['sector_id']]);

        Sector::findOrFail($validated['sector_id'])
            ->update(['kagawad_user_id' => $validated['kagawad_user_id']]);

        return back()->with('success', 'Kagawad assigned to sector.');
    }
}
