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

        <x-staff.filter-bar :action="route('staff.residents.index')"
            :has-filters="$search !== '' || $sectorId !== null"
            :clear-href="route('staff.residents.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Name, email, address, household number" />
            <x-staff.filter-select name="sector_id" label="Sector"
                :options="$sectors->pluck('sector_name', 'id')->all()" :value="$sectorId" all-label="All sectors" />
        </x-staff.filter-bar>

        {{-- The empty state distinguishes "nothing is registered" from "your filter
             matched nothing", because only one of those is the reader's problem to
             fix, and the way out is offered either way it can be offered. --}}
        <x-staff.table
            :columns="[
                ['label' => 'Resident'],
                ['label' => 'Household'],
                ['label' => 'Sector'],
                ['label' => 'Verification'],
                ['label' => 'Registered'],
                ['label' => 'Actions'],
            ]"
            caption="Resident directory"
            :empty-title="$search !== '' || $sectorId !== null ? 'No residents match these filters' : 'No residents yet'"
            :empty-description="$search !== '' || $sectorId !== null
                ? 'Try a different search, or clear the filters to see everyone.'
                : 'Residents appear here as soon as they finish registering.'"
            empty-icon="users"
            :empty-action-label="$search !== '' || $sectorId !== null ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $sectorId !== null ? route('staff.residents.index') : null">
            @foreach ($profiles as $profile)
                <tr>
                    <th scope="row">
                        <x-staff.person-cell :name="$profile->user?->name" :secondary="$profile->user?->email" />
                    </th>
                    <td class="font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td><x-staff.status-badge :status="$profile->verification_status" size="sm" /></td>
                    <td class="text-sm text-slate-600">{{ $profile->user?->created_at?->format('j M Y') ?? '—' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                        <x-staff.icon-action icon="eye" label="View {{ $profile->user?->name }}"
                            :href="route('staff.residents.show', $profile)" />
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$profiles" />
    </div>
</x-app-layout>