{{--
    The verification queue.

    The queue a Secretary works through, so it leads with who is waiting rather than
    with the figures. The waiting count sits beside the heading because it is the one
    number a Secretary opens this page to see, and it is a filter as much as a
    figure: the Status select is on "Pending review" by default, which is what makes
    the count and the list agree.
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
            <x-staff.stat-card label="Waiting" :value="$pendingCount" tone="gold" icon="clock" />
        </header>

        <x-staff.filter-bar :action="route('staff.verifications.index')"
            :has-filters="$search !== '' || $status !== \App\Models\ResidentProfile::VERIFICATION_PENDING"
            :clear-href="route('staff.verifications.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Name, household number, or national ID" />
            <x-staff.filter-select name="status" label="Status" :options="$states" :value="$status" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Resident'],
                ['label' => 'Household'],
                ['label' => 'Sector'],
                ['label' => 'Submitted'],
                ['label' => 'Status'],
            ]"
            caption="Resident verification queue"
            :empty-title="$search !== '' || $status !== \App\Models\ResidentProfile::VERIFICATION_PENDING
                ? 'No profiles match these filters'
                : 'Nothing waiting'"
            :empty-description="$search !== '' || $status !== \App\Models\ResidentProfile::VERIFICATION_PENDING
                ? 'Try a different search, or clear the filters to see everyone.'
                : 'Every registration has been looked at.'"
            empty-icon="id-badge"
            :empty-action-label="$search !== '' || $status !== \App\Models\ResidentProfile::VERIFICATION_PENDING ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $status !== \App\Models\ResidentProfile::VERIFICATION_PENDING ? route('staff.verifications.index') : null">
            @foreach ($profiles as $profile)
                <tr x-on:click="openRow($event, @js(route('staff.verifications.show', $profile)))">
                    <th scope="row">
                        <x-staff.person-cell :name="$profile->user?->name" :secondary="$profile->user?->email"
                            :href="route('staff.verifications.show', $profile)" chevron />
                    </th>
                    <td class="font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->created_at?->format('j M Y') }}</td>
                    <td><x-staff.status-badge :status="$profile->verification_status" size="sm" /></td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$profiles" />
    </div>
</x-app-layout>