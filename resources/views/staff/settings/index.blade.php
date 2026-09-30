<x-app-layout>
    <x-slot name="title">System Settings</x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'System Settings'],
        ]" />

        {{--
            Deliberately not a user-management screen. There is no account
            listing, role editor or deletion route in this application - an
            admin cannot create a secretary or disable a kagawad from here, and
            the page does not pretend otherwise. Accounts are made by
            registering or by the seeder.
        --}}
        <div class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
            <h1 class="text-lg font-bold text-navy-900">System Settings</h1>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-navy-600">
                These are the values the application reads at runtime. The letterhead details are
                printed on every certificate the office generates.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {{-- Letterhead --}}
            <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                <h2 class="text-base font-bold text-navy-900">Official header &amp; officers</h2>
                <p class="mt-1 text-sm text-navy-600">Printed on generated certificates.</p>

                <form method="POST" action="{{ route('staff.settings.update') }}" class="mt-5 space-y-4">
                    @csrf

                    @foreach ([
                        'barangay_name' => 'Barangay name',
                        'barangay_address' => 'Barangay address',
                        'punong_barangay' => 'Punong Barangay',
                        'secretary_name' => 'Barangay Secretary',
                        'emergency_hotline' => 'Emergency hotline',
                    ] as $key => $label)
                        <div>
                            <label for="{{ $key }}" class="block text-sm font-semibold text-navy-900">
                                {{ $label }}
                                @if (in_array($key, ['barangay_name', 'barangay_address'], true))
                                    <span class="text-red-600" aria-hidden="true">*</span>
                                @endif
                            </label>
                            <input id="{{ $key }}" name="{{ $key }}" type="text" maxlength="255"
                                value="{{ old($key, \App\Models\Setting::get($key, '')) }}"
                                @if (in_array($key, ['barangay_name', 'barangay_address'], true)) required @endif
                                class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                            @error($key)
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                        Save header settings
                    </button>
                </form>
            </section>

            {{-- Fees --}}
            <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                <h2 class="text-base font-bold text-navy-900">Document fee rates</h2>
                <p class="mt-1 text-sm leading-relaxed text-navy-600">
                    Recorded against each request so the office has a figure to collect.
                </p>
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900">
                    These rates are not yet applied automatically. Nothing copies them onto a request
                    when you approve it, so the amount a resident pays still has to be entered on the
                    request itself.
                </p>

                <form method="POST" action="{{ route('staff.settings.update') }}" class="mt-5 space-y-4">
                    @csrf

                    @foreach ([
                        'clearance_fee' => 'Barangay Clearance',
                        'indigency_fee' => 'Certificate of Indigency',
                        'jobseeker_fee' => 'First-Time Jobseeker',
                        'residency_fee' => 'Residency Certificate',
                    ] as $key => $label)
                        <div>
                            <label for="{{ $key }}" class="block text-sm font-semibold text-navy-900">{{ $label }}</label>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="text-sm font-semibold text-navy-500" aria-hidden="true">&#8369;</span>
                                <input id="{{ $key }}" name="{{ $key }}" type="number" step="0.01" min="0"
                                    value="{{ old($key, \App\Models\Setting::get($key, 0)) }}"
                                    class="block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                            </div>
                            @error($key)
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                        Save fee rates
                    </button>
                </form>
            </section>
        </div>

        {{-- Kagawad assignment --}}
        <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
            <h2 class="text-base font-bold text-navy-900">Assign a kagawad to a sector</h2>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-navy-600">
                A kagawad can only see the household directory and relief checklist of the sector
                recorded here, so this is the authorisation anchor for the whole kagawad area.
            </p>

            @if ($officials->isEmpty())
                <x-empty-state class="mt-5"
                    title="No accounts to assign"
                    description="There are no staff or kagawad accounts yet." />
            @else
                <form method="POST" action="{{ route('staff.settings.assign-kagawad') }}" class="mt-5 space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="kagawad_user_id" class="block text-sm font-semibold text-navy-900">
                                Account <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <select id="kagawad_user_id" name="kagawad_user_id" required
                                class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                                <option value="">Select an account&hellip;</option>
                                @foreach ($officials as $official)
                                    <option value="{{ $official->id }}">
                                        {{ $official->name }} &mdash; {{ ucfirst($official->role) }} ({{ $official->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('kagawad_user_id')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sector_id" class="block text-sm font-semibold text-navy-900">
                                Sector <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <select id="sector_id" name="sector_id" required
                                class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                                <option value="">Select a sector&hellip;</option>
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}">
                                        {{ $sector->sector_name }}{{ $sector->kagawad_user_id ? ' — already assigned' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sector_id')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900">
                        Assigning here sets the account's role to kagawad. If you pick an admin or a
                        secretary, they lose access to the staff area, and assigning to a second
                        sector takes that sector's access away from the kagawad who had it.
                    </div>

                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                        Assign kagawad
                    </button>
                </form>
            @endif
        </section>

        <p class="text-xs leading-relaxed text-navy-500">
            Every change on this page is immediate and is not recorded in a separate activity log -
            this application has no audit trail, so there is no history to review or roll back here.
        </p>
    </div>
</x-app-layout>
