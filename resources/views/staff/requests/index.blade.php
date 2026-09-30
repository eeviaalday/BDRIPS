{{--
    The document review queue.

    The default filter is the work still waiting on a decision, newest first at
    the top of the page but sorted by submission so the one that has waited
    longest can be found.
--}}
<x-app-layout :user="auth()->user()" title="Document Requests">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Document Requests']]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">Document Requests</h1>
                <p class="mt-1 text-sm text-gray-600">Review, approve, and mark documents for collection.</p>
            </div>
            <div class="flex gap-3">
                <x-stat-card label="Awaiting decision" :value="$awaitingCount" tone="gold" icon="clock" variant="badged" />
                <x-stat-card label="Ready for pickup" :value="$readyCount" tone="navy" icon="check-circle" variant="badged" />
            </div>
        </header>

        <form method="GET" action="{{ route('staff.requests.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label for="q" class="block text-sm font-semibold text-navy-800">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Reference number, name, or household"
                    class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
            </div>
            <div>
                <label for="status" class="block text-sm font-semibold text-navy-800">Show</label>
                <select id="status" name="status" class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    @foreach ([
                        'queue' => 'Awaiting a decision',
                        'ready_for_pickup' => 'Ready for pickup',
                        'claimed' => 'Collected',
                        'rejected' => 'Rejected',
                        'all' => 'Everything',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-btn type="submit" variant="navy" size="md" icon="search">Filter</x-btn>
        </form>

        <x-data-table :columns="[['label' => 'Reference'], ['label' => 'Resident'], ['label' => 'Document'], ['label' => 'Submitted'], ['label' => 'Status']]"
            caption="Document requests" empty-title="Nothing here"
            empty-description="No request matches the current filter.">
            @foreach ($requests as $documentRequest)
                <tr>
                    <th scope="row" class="px-4 py-3 font-mono text-xs font-semibold text-navy-900">
                        <a href="{{ route('staff.requests.show', $documentRequest) }}" class="hover:underline">{{ $documentRequest->reference_number }}</a>
                    </th>
                    <td class="px-4 py-3 text-sm text-navy-800">
                        {{ $documentRequest->user?->name }}
                        <span class="block font-mono text-xs text-slate-500">{{ $documentRequest->user?->residentProfile?->household_number }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $documentRequest->documentType?->label ?? 'Retired document' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $documentRequest->created_at?->format('j M Y') }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$documentRequest->status" size="sm" /></td>
                </tr>
            @endforeach
        </x-data-table>

        <div>{{ $requests->links() }}</div>
    </div>
</x-app-layout>
