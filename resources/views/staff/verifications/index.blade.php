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
            :has-filters="$search !== '' || $status !== ''"
            :clear-href="route('staff.verifications.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Name, household number, or national ID" />
            {{-- The empty parameter is "All". Without an all-label the select would
                 open on its first option, "Pending review", while the list showed
                 every status - the one disagreement this page must not have. --}}
            <x-staff.filter-select name="status" label="Status" all-label="All"
                :options="$states" :value="$status" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Resident'],
                ['label' => 'Household'],
                ['label' => 'Sector'],
                ['label' => 'Submitted'],
                ['label' => 'Status'],
                ['label' => 'Actions'],
            ]"
            caption="Resident verification queue"
            :empty-title="$search !== '' || $status !== ''
                ? 'No profiles match these filters'
                : 'Nothing waiting'"
            :empty-description="$search !== '' || $status !== ''
                ? 'Try a different search, or clear the filters to see everyone.'
                : 'Every registration has been looked at.'"
            empty-icon="id-badge"
            :empty-action-label="$search !== '' || $status !== '' ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $status !== '' ? route('staff.verifications.index') : null">
            @foreach ($profiles as $profile)
                {{--
                    The row is not clickable and the name is not a link. The way into
                    a profile is the Actions control, which is a real link with a real
                    accessible name, so a keyboard user tabbing the table lands on it
                    and a screen reader announces what it does. A clickable <tr> is
                    neither: it cannot be tabbed to, it is not a link, and middle-click
                    and "open in new tab" do nothing on it.
                --}}
                <tr>
                    <th scope="row">
                        <x-staff.person-cell :name="$profile->user?->name" :secondary="$profile->user?->email" />
                    </th>
                    <td class="font-mono text-xs text-navy-800">{{ $profile->household_number }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->sector?->sector_name ?? '—' }}</td>
                    <td class="text-sm text-slate-600">{{ $profile->created_at?->format('j M Y') }}</td>
                    <td><x-staff.status-badge :status="$profile->verification_status" size="sm" /></td>
                    <td>
                        <div class="flex items-center gap-1">
                        <x-staff.icon-action icon="eye" label="Review verification for {{ $profile->user?->name }}"
                            :href="route('staff.verifications.show', $profile)" />
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$profiles" />
    </div>
</x-app-layout>