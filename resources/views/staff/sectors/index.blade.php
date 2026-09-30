<x-app-layout>
    <x-slot name="title">Sectors &amp; Kagawads</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Sectors / Puroks</h2>

            @if ($sectors->isEmpty())
                <p class="text-sm text-gray-500">No sectors defined yet. Create the first one.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Sector</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Purok</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kagawad</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Residents</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sectors as $sector)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">{{ $sector->sector_name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $sector->purok_name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($sector->kagawad)
                                            <span class="text-blue-700 font-medium">{{ $sector->kagawad->name }}</span>
                                        @else
                                            <span class="text-gray-400">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $sector->residents->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Add New Sector</h2>
            <form method="POST" action="{{ route('staff.sectors.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Sector Name" />
                    <x-text-input name="sector_name" type="text" required class="mt-1 block w-full"
                        placeholder="Sector 1" value="{{ old('sector_name') }}" />
                    <x-input-error :messages="$errors->get('sector_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Purok Name (optional)" />
                    <x-text-input name="purok_name" type="text" class="mt-1 block w-full"
                        placeholder="Purok San Jose" value="{{ old('purok_name') }}" />
                </div>
                <button type="submit"
                    class="w-full rounded-lg bg-blue-700 hover:bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white transition">
                    Create Sector
                </button>
            </form>
        </div>
    </div>
</x-app-layout>