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
            {{-- Two more figures, and they are the ones that carry consequence:
                 "ready for pickup" is a state, "waiting too long to be collected"
                 is a problem, and the number of them is a measure of the desk
                 rather than of the residents. Excused is shown beside Unclaimed
                 because the honest reading of an unclaimed list is how much of it
                 was excusable - without it, the two counts sit next to each other
                 and the first one alone decides how the office sees its residents. --}}
            <x-staff.stat-card label="Overdue for collection" :value="$overdueCount" tone="gold" icon="help-circle" />
            <x-staff.stat-card label="Unclaimed" :value="$unclaimedCount" tone="navy" icon="archive" />
            {{-- Digital copies, and the requests they were issued against. Both,
                 because one number alone misleads in a specific way: 12 copies
                 could be one resident whose password kept failing. "12" beside a
                 list of a handful of requests is a signal something is wrong; "12
                 copies across 9 requests" is a normal fortnight. --}}
            <x-staff.stat-card label="Digital copies issued" :value="$downloadCount" tone="navy" icon="download"
                :hint="'across '.$downloadedRequestCount.($downloadedRequestCount === 1 ? ' request' : ' requests')" />
        </x-staff.stat-row>

        <x-staff.filter-bar :action="route('staff.requests.index')"
            :has-filters="$search !== '' || $status !== ''"
            :clear-href="route('staff.requests.index')">
            <x-staff.filter-field name="q" label="Search" :value="$search"
                placeholder="Reference number, name, or household" />
            {{-- "Everything" is the empty parameter, not an `all` value. It used to be
                 an option of its own AND the absence of a default, so "everything"
                 had two spellings and a bookmark of one did not mean the other. --}}
            <x-staff.filter-select name="status" label="Show" all-label="Everything"
                :value="$status" :options="[
                    'queue' => 'Awaiting a decision',
                    'ready_for_pickup' => 'Ready for pickup',
                    'overdue' => 'Overdue for collection',
                    {{-- The one option that describes what a resident did rather
                         than what a Secretary did. It sits next to Ready for
                         pickup because that is who it selects: a document that was
                         downloaded and never turned up at the hall is the case
                         worth seeing, and a claim has not been made either way. --}}
                    'downloaded' => 'Downloaded, not yet collected',
                    'unclaimed' => 'Unclaimed',
                    'claimed' => 'Collected',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ]" />
        </x-staff.filter-bar>

        <x-staff.table
            :columns="[
                ['label' => 'Reference'],
                ['label' => 'Resident'],
                ['label' => 'Document'],
                ['label' => 'Submitted'],
                ['label' => 'Status'],
                ['label' => 'Actions'],
            ]"
            caption="Document requests"
            :empty-title="$search !== '' || $status !== '' ? 'No requests match these filters' : 'Nothing here'"
            :empty-description="$search !== '' || $status !== ''
                ? 'Try a different search, or clear the filters to see everything.'
                : 'No request has been submitted yet.'"
            empty-icon="document"
            :empty-action-label="$search !== '' || $status !== '' ? 'Clear filters' : null"
            :empty-action-href="$search !== '' || $status !== '' ? route('staff.requests.index') : null">
            @foreach ($requests as $documentRequest)
                {{-- The reference number is plain text, not a link. A row of
                     reference numbers that are all links reads as a wall of links and
                     gives a screen reader nothing to distinguish them by; the Actions
                     control is the way in and it says what it does. --}}
                <tr>
                    <th scope="row">
                        <x-staff.person-cell :name="$documentRequest->reference_number" mono />
                    </th>
                    <td class="text-sm text-navy-800">
                        {{ $documentRequest->user?->name }}
                        <span class="block font-mono text-xs text-slate-500">{{ $documentRequest->user?->residentProfile?->household_number }}</span>
                    </td>
                    <td class="text-sm text-slate-600">{{ $documentRequest->documentType?->label ?? 'Retired document' }}</td>
                    <td class="text-sm text-slate-600">{{ $documentRequest->created_at?->format('j M Y') }}</td>
                    <td>
                        <x-staff.status-badge :status="$documentRequest->status" size="sm" />
                        {{-- Whether a digital copy has gone out, and how many
                             times. Read from the eager-loaded count, so drawing
                             this badge costs the page nothing. It sits under the
                             status rather than in a column of its own because it
                             is a fact about the row, not a queue to be worked
                             through - except where it is, which is what the filter
                             above is for. --}}
                        @php $copies = (int) ($documentRequest->deliveries_count ?? 0); @endphp
                        @if ($copies > 0)
                            <span class="mt-1 block text-xs font-semibold text-navy-700">
                                {{ $copies === 1 ? '1 digital copy issued' : $copies.' digital copies issued' }}
                            </span>
                        @endif
                        {{-- How long it has been sitting ready, because "Ready for
                             pickup" on its own hides the only fact that decides
                             whether this row needs work today. Days are counted by
                             the model against the same 14-day config the server
                             marks it with, so the queue can never disagree with the
                             record page about what is overdue. --}}
                        @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_READY)
                            @php $waitingDays = $documentRequest->daysReady(); @endphp
                            <span @class([
                                'mt-1 block text-xs',
                                'font-semibold text-amber-700' => $documentRequest->isOverdue(),
                                'text-slate-500' => ! $documentRequest->isOverdue(),
                            ])>
                                Ready {{ $waitingDays === null ? '—' : $waitingDays.'d' }}
                                @if ($documentRequest->isOverdue())
                                    · can be marked unclaimed
                                @else
                                    · {{ \App\Models\DocumentRequest::readyDaysBeforeUnclaimed() - $waitingDays }}d left
                                @endif
                            </span>
                        @elseif ($documentRequest->status === \App\Models\DocumentRequest::STATUS_UNCLAIMED)
                            <span @class([
                                'mt-1 block text-xs',
                                'font-semibold text-amber-700' => $documentRequest->unclaimed_excused_at === null,
                                'text-slate-500' => $documentRequest->unclaimed_excused_at !== null,
                            ])>
                                @if ($documentRequest->unclaimed_excused_at)
                                    Excused {{ $documentRequest->unclaimed_excused_at->format('j M Y') }}
                                    @if ($excusedCount)
                                        ({{ $excusedCount }} of {{ $unclaimedCount }} unclaimed)
                                    @endif
                                @else
                                    Not excused · counts as a strike
                                @endif
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                        <x-staff.icon-action icon="eye" label="Review request {{ $documentRequest->reference_number }}"
                            :href="route('staff.requests.show', $documentRequest)" />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-staff.table>

        <x-staff.pagination :paginator="$requests" />
    </div>
</x-app-layout>