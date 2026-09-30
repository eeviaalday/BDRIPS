{{--
    The resident verification queue.

    A status predicate and a search predicate, combined rather than either/or. An
    ungrouped OR between them would let the search term match every profile
    regardless of status, so "pending" plus a name would quietly become
    "everything" - which is how a Secretary ends up approving somebody they never
    meant to look at.
--}}
<x-app-layout :user="auth()->user()" title="Resident Verification">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Resident Verification']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Resident Verification</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Check each registration against the master list, then approve or reject it.
                </p>
            </div>
            <x-stat-card label="Waiting" :value="$pendingCount" tone="gold" icon="clock" variant="badged" />
        </header>

        <form method="GET" action="{{ route('staff.verifications.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}"
                    placeholder="Name, household number, or national ID"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="status" class="block text-sm font-semibold text-navy-800">Status</label>
                <select id="status" name="status"
                    class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    @foreach ($states as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
        </form>

        <x-data-table :columns="[['label' => 'Resident'], ['label' => 'Household'], ['label' => 'Sector'], ['label' => 'Submitted'], ['label' => 'Status']]"
            caption="Resident verification queue" empty-title="Nothing in this queue"
            empty-description="No profile matches the current filter.">
            @foreach ($profiles as $profile)
                <tr>
                    <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                        <a href="{{ route('staff.verifications.show', $profile) }}" class="hover:underline">
                            {{ $profile->user?->name }}
                        </a>
                        <span class="block text-xs font-normal text-slate-500">{{ $profile->user?->email }}</span>
                    </th>
                    <td class="px-4 py-3 font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $profile->created_at?->format('j M Y') }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$profile->verification_status" size="sm" /></td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $profiles->links() }}</div>
    </div>
</x-app-layout>
