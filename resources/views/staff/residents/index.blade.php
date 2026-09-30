{{--
    The residents directory.

    A staff member may look across households here, which is the job. The scoping
    that stops one resident reading a neighbour does not apply, and pretending
    otherwise would make the directory useless.
--}}
<x-app-layout :user="auth()->user()" title="Residents">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Residents']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">Residents</h1>
            <p class="mt-1 text-sm text-gray-600">Every registered resident, by household number.</p>
        </header>

        <form method="GET" action="{{ route('staff.residents.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}"
                    placeholder="Name, email, address, household number"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="sector_id" class="block text-sm font-semibold text-navy-800">Sector</label>
                <select id="sector_id" name="sector_id"
                    class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    <option value="">All sectors</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected($sectorId === $sector->id)>{{ $sector->sector_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
        </form>

        <x-data-table :columns="[['label' => 'Resident'], ['label' => 'Household'], ['label' => 'Sector'], ['label' => 'Verification'], ['label' => 'Registered']]"
            caption="Resident directory" empty-title="No residents match"
            empty-description="Try a different search or sector.">
            @foreach ($profiles as $profile)
                <tr>
                    <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                        <a href="{{ route('staff.residents.show', $profile) }}" class="hover:underline">{{ $profile->user?->name }}</a>
                        <span class="block text-xs font-normal text-slate-500">{{ $profile->user?->email }}</span>
                    </th>
                    <td class="px-4 py-3 font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$profile->verification_status" size="sm" /></td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $profile->user?->created_at?->format('j M Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $profiles->links() }}</div>
    </div>
</x-app-layout>
