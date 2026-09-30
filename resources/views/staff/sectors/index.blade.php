{{--
    Sectors and their Kagawad.

    The assignment select only offers a Kagawad who has no sector. That is the
    unique index on sectors.kagawad_user_id expressed in the UI, so the screen
    cannot produce a save the database will refuse.
--}}
<x-app-layout :user="auth()->user()" title="Sectors & Kagawad">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Sectors & Kagawad']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Sectors &amp; Kagawad</h1>
            <p class="mt-1 text-sm text-gray-600">
                A sector is what a relief checklist and a resident's address are scoped by.
            </p>
        </header>

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Add a sector</h2>

            <form method="POST" action="{{ route('staff.sectors.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="sector_name" class="block text-sm font-semibold text-navy-800">Sector name</label>
                    <input id="sector_name" name="sector_name" type="text" required maxlength="255" value="{{ old('sector_name') }}"
                        class="mt-1.5 w-64 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>
                <div>
                    <label for="purok_name" class="block text-sm font-semibold text-navy-800">Purok (optional)</label>
                    <input id="purok_name" name="purok_name" type="text" maxlength="255" value="{{ old('purok_name') }}"
                        class="mt-1.5 w-64 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>
                <x-btn type="submit" variant="navy" size="md" icon="check">Add sector</x-btn>
            </form>
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Sectors</h2>

            @if ($sectors->isEmpty())
                <p class="text-sm text-slate-500">No sectors yet. Add one above.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($sectors as $sector)
                        <li class="rounded-xl border border-navy-100 p-4">
                            <form method="POST" action="{{ route('staff.sectors.update', $sector) }}"
                                class="mb-3 flex flex-wrap items-end gap-3 border-b border-navy-50 pb-3">
                                @csrf
                                @method('PUT')
                                <div class="min-w-[12rem] flex-1">
                                    <label for="name-{{ $sector->id }}" class="sr-only">Sector name</label>
                                    <input id="name-{{ $sector->id }}" name="sector_name" type="text" required
                                        maxlength="255" value="{{ $sector->sector_name }}"
                                        class="w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                </div>
                                <div class="min-w-[12rem] flex-1">
                                    <label for="purok-{{ $sector->id }}" class="sr-only">Purok</label>
                                    <input id="purok-{{ $sector->id }}" name="purok_name" type="text"
                                        maxlength="255" value="{{ $sector->purok_name }}"
                                        class="w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200"
                                        placeholder="Purok">
                                </div>
                                <x-btn type="submit" variant="outline" size="sm">Rename</x-btn>
                            </form>

                            <form method="POST" action="{{ route('staff.sectors.kagawad', $sector) }}"
                                class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PUT')
                                <div class="min-w-[16rem] flex-1">
                                    <label for="kagawad-{{ $sector->id }}" class="block text-sm font-semibold text-navy-800">
                                        Kagawad
                                    </label>
                                    <select id="kagawad-{{ $sector->id }}" name="kagawad_user_id"
                                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                        <option value="">Nobody assigned</option>
                                        @if ($sector->kagawad)
                                            <option value="{{ $sector->kagawad->id }}" selected>
                                                {{ $sector->kagawad->name }} (current)
                                            </option>
                                        @endif
                                        @foreach ($unassignedKagawad as $candidate)
                                            <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-btn type="submit" variant="navy" size="sm" icon="check">Assign</x-btn>

                                <div class="ml-auto flex items-center gap-3 text-xs text-slate-500">
                                    <span>{{ $sector->residents_count }} household{{ $sector->residents_count === 1 ? '' : 's' }}</span>
                                    @if ($sector->isEmpty())
                                        <x-confirm-dialog :name="'delete-sector-'.$sector->id"
                                            title="Delete this sector?"
                                            message="Nothing is filed under it, so nothing will be left pointing at a sector that is not there."
                                            confirm-label="Delete" tone="danger" method="DELETE"
                                            :form="route('staff.sectors.destroy', $sector)" />
                                        <button type="button" x-on:click="$dispatch('open-modal', 'delete-sector-{{ $sector->id }}')"
                                            class="rounded-lg border border-red-200 px-2.5 py-1.5 font-semibold text-red-700 transition hover:bg-red-50">
                                            Delete
                                        </button>
                                    @else
                                        <span class="italic">Cannot delete while it has households or relief entries</span>
                                    @endif
                                </div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
