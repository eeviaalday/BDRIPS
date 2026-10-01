{{--
    A resident's own relief record.

    No search box, and there is not going to be one. Relief follows the
    household, and a household number is short and sequential enough to walk in
    order - so an open lookup would let a resident read a neighbour's record by
    trying the next one along. The rows are theirs and only theirs.

    ONE section, not two. "Batches going out now" and "What my household has
    received" were separate lists of the same thing from two directions, and a
    resident had to work out which one to read.

    The three figures are the filter, exactly as on the Document Requests page,
    and they are counted over every batch the household has been on - never over
    the rows the table is currently showing.
--}}
<x-public-layout>
    <x-slot name="title">Relief Transparency</x-slot>

    <x-page-header :crumbs="['Relief Transparency']" title="My Relief Record"
        description="What your household has received from each barangay relief batch." />

    <x-shell>
        @if (! $isVerified)
            <x-alert variant="warning" icon="lock">
                Relief records unlock once the Barangay Secretary has verified your details.
                <a href="{{ route('account.edit', ['tab' => 'verification']) }}" class="font-semibold underline">Check your verification status</a>.
            </x-alert>
        @else
            {{-- The same interactive figures as Document Requests: two filters and
                 one that clears the filter. A figure a resident can act on has to
                 be a control, or they have to translate it into a chip click
                 themselves. --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-resident.filter-card label="Relief batches" :value="$totalCount"
                    tone="navy" icon="gift" as="link" :href="route('resident.relief')"
                    :active="$statusFilter === null" />

                <x-resident.filter-card label="Waiting to receive" :value="$pendingCount"
                    tone="gold" icon="clock" as="link"
                    :href="route('resident.relief', ['status' => 'pending'])"
                    :active="$statusFilter === 'pending'" />

                <x-resident.filter-card label="Received by my household" :value="$receivedCount"
                    tone="sky" icon="check-circle" as="link"
                    :href="route('resident.relief', ['status' => 'received'])"
                    :active="$statusFilter === 'received'" />
            </div>

            <h2 class="mt-8 text-lg font-bold text-navy-900">My household&rsquo;s relief</h2>

            {{-- mt-4 here and the table below: a chip row sitting flush against a
                 bordered table reads as one block, and the chips stop looking
                 switchable. --}}
            <nav aria-label="Filter relief records" class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('resident.relief') }}"
                    @class([
                        'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2',
                        'border-gold bg-gold/15 text-navy-900 ring-2 ring-gold' => $statusFilter === null,
                        'border-navy-200 bg-white text-navy-700 hover:border-navy-300 hover:bg-navy-50' => $statusFilter !== null,
                    ])
                    @if ($statusFilter === null) aria-current="page" @endif>All</a>

                @foreach ($statusFilters as $key => $filter)
                    <a href="{{ route('resident.relief', ['status' => $key]) }}"
                        @class([
                            'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2',
                            'border-gold bg-gold/15 text-navy-900 ring-2 ring-gold' => $statusFilter === $key,
                            'border-navy-200 bg-white text-navy-700 hover:border-navy-300 hover:bg-navy-50' => $statusFilter !== $key,
                        ])
                        @if ($statusFilter === $key) aria-current="page" @endif>{{ $filter['label'] }}</a>
                @endforeach
            </nav>

            <div class="mt-4">
                @if ($distributions->isEmpty())
                    @if ($isFiltered)
                        <x-empty-state icon="search" title="Nothing in this filter"
                            description="Your household has no relief that is {{ strtolower($statusFilters[$statusFilter]['label'] ?? 'in this state') }}.">
                            <x-btn :href="route('resident.relief')" variant="navy" size="sm" class="mt-5">
                                Show all my relief
                            </x-btn>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="archive" title="Nothing recorded yet"
                            description="When the barangay opens a relief batch your household is on, it will be listed here." />
                    @endif
                @else
                    <x-data-table :columns="[
                        ['label' => 'Batch'],
                        ['label' => 'Distribution date'],
                        ['label' => 'My household'],
                        ['label' => 'Collected by'],
                    ]"
                        variant="resident"
                        caption="Relief recorded for your household">
                        @foreach ($distributions as $distribution)
                            @php($batch = $distribution->batch)
                            <tr>
                                {{-- text-left on the row header, deliberately. Tailwind's
                                     preflight does not reset th alignment, so the
                                     browser's own th { text-align: center } wins over
                                     anything inherited - which centres the first
                                     column while every td beside it is left. --}}
                                <th scope="row" class="px-4 py-3 text-left">
                                    <span class="block font-semibold text-navy-900">
                                        {{ $batch?->title ?? 'Removed batch' }}
                                    </span>

                                    @if ($batch?->description)
                                        <span class="mt-0.5 block text-xs text-slate-600">{{ $batch->description }}</span>
                                    @endif

                                    @if ($batch?->items() !== [])
                                        <span class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach ($batch->items() as $item)
                                                <span class="rounded-full bg-navy-50 px-2 py-0.5 text-2xs font-medium text-navy-800">
                                                    {{ $item }}
                                                </span>
                                            @endforeach
                                        </span>
                                    @endif
                                </th>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $batch?->distribution_date?->format('j M Y') ?? '—' }}
                                    @if ($batch)
                                        <span class="mt-1 block">
                                            <x-status-badge :status="$batch->status" size="sm" soft />
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$distribution->status" size="sm" soft />
                                    {{-- The one thing a resident asking about a pending
                                         row cannot work out for themselves: what to
                                         bring. --}}
                                    @if (! $distribution->isReceived() && $batch?->isActive())
                                        <span class="mt-1 block text-xs text-slate-500">Bring a valid ID when claiming</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    @if ($distribution->isReceived())
                                        <span class="block font-medium text-navy-900">{{ $distribution->received_by_name }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ $distribution->claimed_at?->format('j M Y') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                @endif
            </div>
        @endif
    </x-shell>
</x-public-layout>
