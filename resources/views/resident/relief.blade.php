{{--
    A resident's own relief record.

    No search box, and there is not going to be one. Relief follows the
    household, and a household number is short and sequential enough to walk in
    order - so an open lookup would let a resident read a neighbour's record by
    trying the next one along. The rows are theirs and only theirs.

    The two cards at the top answer the two questions a resident actually opens
    this page with: am I expected somewhere, and is the barangay getting
    through the list. They replaced three count cards, which answered neither -
    they restated what the table below already said and could be acted on as
    filters, which meant a resident had to work out that a number was a button.

    The progress card is counts and nothing else. It is the only figure here that
    is not about this household, so it is the one that has to be built from an
    aggregate rather than from rows: ReliefBatch::progress() runs a single
    grouped query and returns three integers. No household number, no name and
    no per-household status reaches the view, and there is no way to ask for
    them from this page.
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
            {{-- ===================== WHAT IS COMING ===================== --}}
            @if ($nextBatch)
                {{-- Navy gradient, because this is the one card on the page that
                     asks the reader to do something. The goods are tags rather
                     than a sentence, so a resident can see at a glance whether
                     the list contains something they need. --}}
                <section aria-labelledby="next-distribution-heading"
                    class="overflow-hidden rounded-2xl bg-gradient-to-br from-navy via-navy-800 to-navy-900 p-6 text-white shadow-card sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-gold-300">
                                Next distribution for your household
                            </p>
                            <h2 id="next-distribution-heading" class="mt-1.5 text-xl font-bold text-white">
                                {{ $nextBatch->title }}
                            </h2>

                            @if ($nextBatch->description)
                                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-200">
                                    {{ $nextBatch->description }}
                                </p>
                            @endif
                        </div>

                        @if ($sectorKagawad)
                            {{-- Who to ask for. The Kagawad covering this household's
                                 sector, which is the one person who can answer a
                                 question about this handout on the day. --}}
                            <div class="shrink-0 rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">
                                    Your sector Kagawad
                                </p>
                                <p class="mt-0.5 text-sm font-bold text-white">{{ $sectorKagawad }}</p>
                            </div>
                        @endif
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-300">Date</dt>
                            <dd class="mt-1 flex items-center gap-2 text-sm font-semibold text-white">
                                <x-icon name="calendar" class="h-4 w-4 text-gold-300" />
                                {{ $nextBatch->distribution_date?->format('j F Y') ?? 'To be announced' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-300">Bring</dt>
                            <dd class="mt-1 flex items-center gap-2 text-sm font-semibold text-white">
                                <x-icon name="id-badge" class="h-4 w-4 text-gold-300" />
                                A valid ID
                            </dd>
                        </div>
                    </dl>

                    @if ($nextBatch->items() !== [])
                        <div class="mt-5 border-t border-white/15 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Included</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($nextBatch->items() as $item)
                                    <li class="rounded-full bg-gold/20 px-3 py-1 text-xs font-semibold text-gold-100 ring-1 ring-gold/30">
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ===================== BARANGAY-WIDE PROGRESS ===================== --}}
            @if ($batchProgress['total'] > 0)
                <x-card tone="white" padding="p-6" class="mt-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-bold text-navy-900">Barangay-wide progress</h2>
                        <p class="text-sm text-slate-600">
                            <span class="font-bold text-navy-900">{{ $batchProgress['received'] }}</span>
                            of <span class="font-bold text-navy-900">{{ $batchProgress['total'] }}</span>
                            households have received their relief
                        </p>
                    </div>

                    @php
                        // Clamped, because a row that exists for a household that has
                        // since been removed would otherwise render a bar past its
                        // own end and a figure above the total.
                        $percent = $batchProgress['total'] > 0
                            ? min(100, (int) round($batchProgress['received'] / $batchProgress['total'] * 100))
                            : 0;
                    @endphp

                    <div class="mt-3.5 h-2.5 w-full overflow-hidden rounded-full bg-navy-100"
                        role="img"
                        aria-label="{{ $batchProgress['received'] }} of {{ $batchProgress['total'] }} households have received their relief">
                        <div class="h-full rounded-full bg-gold transition-all" style="width: {{ $percent }}%"></div>
                    </div>

                    <p class="mt-2.5 text-xs text-slate-500">
                        A count for the whole barangay. No household is named, and there is
                        no way to look one up from here.
                    </p>
                </x-card>
            @endif

            {{-- ===================== THE HOUSEHOLD'S OWN HISTORY ===================== --}}
            <h2 class="mt-8 text-lg font-bold text-navy-900">My household&rsquo;s relief history</h2>

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
                    {{-- One dialog for the whole table rather than one per row. A
                         dialog per row in the markup would mean every row carries
                         the batch's goods and its status box in the page, which is
                         the same content twice and a lot of DOM for a household
                         with a long history. Each row is a button that opens the
                         dialog named after it.

                         The wrapper exists for $dispatch. A Details button outside
                         any Alpine root is not processed at all, so the dialogs
                         below would be unreachable - which is the failure mode
                         this page had once already, in the other direction. --}}
                    <div x-data>
                    <x-data-table :columns="[
                        ['label' => 'Batch'],
                        ['label' => 'Distribution date'],
                        ['label' => 'My household'],
                        ['label' => 'Details'],
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
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <x-btn type="button" variant="outline" size="sm"
                                        x-on:click="tracked = {{ $loop->index }}; $dispatch('open-modal', 'relief-{{ $loop->index }}')"
                                        :aria-label="'Details of '.($batch?->title ?? 'removed batch')">
                                        Details
                                    </x-btn>
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>

            {{-- The details dialog, one per row, inside the same Alpine scope the
                 row buttons live in and inside this branch, so an empty list emits
                 neither the wrapper nor a dialog. --}}
            @foreach ($distributions as $distribution)
                @php($batch = $distribution->batch)
                <x-modal :name="'relief-'.$loop->index" maxWidth="lg" dismissible>
                    <div class="bg-white px-6 pb-4 pt-6">
                        <h2 class="text-lg font-bold text-navy-900">
                            {{ $batch?->title ?? 'Removed batch' }}
                        </h2>

                        @if ($batch?->description)
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $batch->description }}</p>
                        @endif

                        @if ($batch?->items() !== [])
                            <ul class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($batch->items() as $item)
                                    <li class="rounded-full bg-navy-50 px-2.5 py-0.5 text-xs font-medium text-navy-800">
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- The one thing a resident cannot work out from the table: for
                         a pending row, when and what to bring; for a received one,
                         who took it and who logged it. --}}
                    <div class="px-6 pb-2">
                        @if ($distribution->isReceived())
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-sm leading-relaxed text-emerald-900">
                                    Collected by <span class="font-bold">{{ $distribution->received_by_name ?: 'a household member' }}</span>
                                    on <span class="font-bold">{{ $distribution->claimed_at?->format('j M Y') ?? 'a date not recorded' }}</span>.
                                    @if ($distribution->loggedBy)
                                        Logged by {{ $distribution->loggedBy->name }}.
                                    @endif
                                </p>
                            </div>
                        @else
                            <div class="rounded-lg border border-gold-200 bg-gold-50 p-4">
                                <p class="text-sm leading-relaxed text-navy-900">
                                    @if ($batch?->distribution_date)
                                        Distribution on
                                        <span class="font-bold">{{ $batch->distribution_date->format('j F Y') }}</span>.
                                    @else
                                        A date has not been set yet.
                                    @endif
                                    Bring a valid ID.
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                        <x-btn type="button" variant="outline" size="md"
                            x-on:click="$dispatch('close-modal', 'relief-{{ $loop->index }}')">
                            Close
                        </x-btn>
                    </div>
                </x-modal>
            @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-shell>
</x-public-layout>
