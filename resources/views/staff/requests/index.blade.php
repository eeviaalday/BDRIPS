{{--
    The document requests queue.

    A queue a Secretary works through, so it leads with the reference number rather
    than with a name: this is a list of documents, and the reference is what gets
    quoted at the hall. The resident is the second column, with their household
    number under it, because that is what identifies the household when the name is
    ambiguous.
--}}
<x-app-layout :user="auth()->user()" title="Document Requests">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Document Requests']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Document Requests</h1>
                <p class="mt-1 text-sm text-gray-600">Review, approve, and mark documents for collection.</p>
            </div>
        </header>

        {{-- The two queue figures sit above the filters rather than beside the
             heading: they are the state of the whole queue, while the filter is the
             state of the list below. --}}
        <x-staff.stat-row>
            <x-staff.stat-card label="Awaiting decision" :value="$awaitingCount" tone="gold" icon="clock" />
            <x-staff.stat-card label="Ready for pickup" :value="$readyCount" tone="navy" icon="check-circle" />
        </x-staff.stat-row>

        <x-staff.filter-bar :action="route('staff.requests.index')"
            :has-filters="$search !== '' || $status !== 'queue'"
            :clear-href="route('staff.requests.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Reference number, name, or household" />
            <x-staff.filter-select name="status" label="Show" :value="$status" :options="[
                'queue' => 'Awaiting a decision',
                'ready_for_pickup' => 'Ready for pickup',
                'claimed' => 'Collected',
                'rejected' => 'Rejected',
                'all' => 'Everything',
            ]" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Reference'],
                ['label' => 'Resident'],
                ['label' => 'Document'],
                ['label' => 'Submitted'],
                ['label' => 'Status'],
            ]"
            caption="Document requests"
            :empty-title="$search !== '' || $status !== 'queue' ? 'No requests match these filters' : 'Nothing here'"
            :empty-description="$search !== '' || $status !== 'queue'
                ? 'Try a different search, or clear the filters to see everything.'
                : 'No request has been submitted yet.'"
            empty-icon="document"
            :empty-action-label="$search !== '' || $status !== 'queue' ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $status !== 'queue' ? route('staff.requests.index') : null">
            @foreach ($requests as $documentRequest)
                <tr x-on:click="openRow($event, @js(route('staff.requests.show', $documentRequest)))">
                    <th scope="row">
                        <x-staff.person-cell :name="$documentRequest->reference_number" mono
                            :href="route('staff.requests.show', $documentRequest)" chevron />
                    </th>
                    <td class="text-sm text-navy-800">
                        {{ $documentRequest->user?->name }}
                        <span class="block font-mono text-xs text-slate-500">{{ $documentRequest->user?->residentProfile?->household_number }}</span>
                    </td>
                    <td class="text-sm text-slate-600">{{ $documentRequest->documentType?->label ?? 'Retired document' }}</td>
                    <td class="text-sm text-slate-600">{{ $documentRequest->created_at?->format('j M Y') }}</td>
                    <td><x-staff.status-badge :status="$documentRequest->status" size="sm" /></td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$requests" />
    </div>
</x-app-layout>