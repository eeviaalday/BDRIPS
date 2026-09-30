{{--
    Open a relief batch.

    The sector multi-select defaults to every sector: a batch that covered
    nobody because nobody was ticked would look like a working distribution and
    hand out nothing.
--}}
<x-app-layout :user="auth()->user()" title="Open a Relief Batch">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Relief Distribution', 'route' => 'staff.batches.index'],
            ['label' => 'Open a batch'],
        ]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Open a relief batch</h1>
            <p class="mt-1 text-sm text-gray-600">
                Every verified household in the sectors you choose is added to the checklist, for the
                Kagawad of each sector to work through.
            </p>
        </header>

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
            <form method="POST" action="{{ route('staff.batches.store') }}" class="space-y-5">
                @csrf

                <x-field id="title" name="title" label="Title" variant="soft" required
                    :value="old('title')" placeholder="Typhoon Relief Drive" />

                <div>
                    <label for="description" class="block text-sm font-semibold text-navy-800">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                        placeholder="Who this is for, and what is being given.">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label for="items_included" class="block text-sm font-semibold text-navy-800">Goods included</label>
                    <textarea id="items_included" name="items_included" rows="4" maxlength="1000"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                        placeholder="One item per line">{{ old('items_included') }}</textarea>
                    <p class="mt-1.5 text-xs text-slate-500">One per line. Blank lines are ignored.</p>
                </div>

                <x-field id="distribution_date" name="distribution_date" label="Distribution date" type="date"
                    variant="soft" :value="old('distribution_date')" />

                <fieldset>
                    <legend class="block text-sm font-semibold text-navy-800">Sectors covered</legend>
                    <p class="mt-1 text-xs text-slate-500">Tick none to cover the whole barangay.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($sectors as $sector)
                            <label class="flex items-center gap-2.5 rounded-lg border border-navy-100 bg-navy-50/40 px-3.5 py-2.5 text-sm text-navy-900">
                                <input type="checkbox" name="sector_ids[]" value="{{ $sector->id }}"
                                    @checked(in_array($sector->id, old('sector_ids', [])))
                                    class="rounded border-navy-300 text-navy-600 focus:ring-navy-400">
                                {{ $sector->sector_name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex justify-end gap-3 border-t border-navy-100 pt-5">
                    <x-btn :href="route('staff.batches.index')" variant="outline" size="md">Cancel</x-btn>
                    <x-btn type="submit" variant="navy" size="md" icon="gift">Open batch</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
