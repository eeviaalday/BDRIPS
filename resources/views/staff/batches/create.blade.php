<x-app-layout>
    <x-slot name="title">Create Relief Batch</x-slot>

    <div class="max-w-2xl mx-auto">
        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900">New Relief Goods Batch</h2>
            <p class="text-sm text-gray-500 mt-1">
                The batch will automatically map to all verified households in the barangay master list, grouped by sector.
            </p>

            <form method="POST" action="{{ route('staff.batches.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="title" value="Batch Title" />
                    <x-text-input id="title" name="title" type="text" required
                        class="mt-1 block w-full" placeholder="Typhoon Relief Drive - Sept 2026"
                        value="{{ old('title') }}" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea id="description" name="description" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">{{ old('description') }}</textarea>
                </div>

                <div>
                    <x-input-label for="items" value="Included Items (comma-separated)" />
                    <x-text-input id="items" name="items" type="text"
                        class="mt-1 block w-full" placeholder="5kg Rice, Canned Goods, Noodles"
                        value="{{ old('items') }}" />
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="distribution_date" value="Distribution Date" />
                    <x-text-input id="distribution_date" name="distribution_date" type="date" required
                        class="mt-1 block w-full" value="{{ old('distribution_date') }}" />
                    <x-input-error :messages="$errors->get('distribution_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="completed" @selected(old('status') === 'completed')>Completed</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('staff.batches.index') }}" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancel</a>
                    <button type="submit"
                        class="rounded-lg bg-blue-700 hover:bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white transition">
                        Create Batch &amp; Map Households
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>