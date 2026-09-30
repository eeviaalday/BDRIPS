{{--
    A resident's own document requests.

    The hero is the same x-page-header every public inner page uses, and it sits
    as a SIBLING of x-shell rather than inside it. x-shell is the padded,
    max-width container, so a hero nested inside it renders as an inset rounded
    box instead of the full-bleed band the public pages have. The page action
    therefore lives in the My Documents section header, not in the hero.

    The three stat cards are the filter, as buttons. A figure a resident can read
    but not act on is a figure they have to translate into a chip click
    themselves, and "Open requests: 2" is the same question as "show me my two
    open requests". They are real <button> or <a> elements, so they are keyboard
    reachable and announce themselves, rather than divs with click handlers.
--}}
<x-public-layout>
    <x-slot name="title">Document Requests</x-slot>

    <x-page-header :crumbs="['Document Requests']" title="My Document Requests"
        description="Requests you have submitted, and where each one stands." />

    <x-shell>
        {{-- Three figures that are also the filter. Two are links, one is a
             button that opens a modal, so the interactive card is a resident
             component of its own: the shared stat-card renders a plain div when
             it has no href, and a div cannot be focused or activated. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            {{-- 1. Open requests -> ?status=open. --}}
            <x-resident.filter-card label="Open requests" :value="$openCount" :hint="'of '.$limit.' allowed'"
                tone="navy" icon="document" as="link" :href="route('resident.requests.index', ['status' => 'open'])"
                :active="$statusFilter === 'open'" />

            {{-- 2. Slots remaining -> opens the New Request modal. --}}
            @if ($slotsRemaining === 0)
                <x-resident.filter-card label="Slots remaining" :value="$slotsRemaining" hint="None left"
                    tone="gold" icon="lock" as="disabled" />
            @elseif ($canCreate)
                <x-resident.filter-card label="Slots remaining" :value="$slotsRemaining"
                    hint="Start a new request" tone="gold" icon="check-circle" as="button"
                    x-on:click="$dispatch('open-modal', 'new-request')" />
            @else
                {{-- Pending or restricted: the card still opens something, because a
                     control that silently does nothing is worse than one that explains. --}}
                <x-resident.filter-card label="Slots remaining" :value="$slotsRemaining"
                    hint="Locked for now" tone="gold" icon="lock" as="button"
                    x-on:click="$dispatch('open-modal', 'requests-locked')" />
            @endif

            {{-- 3. Total on file -> clears the filter. Counted on the server over
                 every request this resident has, so it does not change when the
                 list is filtered. --}}
            <x-resident.filter-card label="Total on file" :value="$totalCount"
                tone="sky" icon="archive" as="link" :href="route('resident.requests.index')"
                :active="$statusFilter === null" />
        </div>

        {{-- The New Request modal. Shown to a resident who can act, and it
             explains the limit rather than pretending the button is missing. --}}
        @if ($canCreate)
            <x-modal name="new-request" maxWidth="lg" dismissible>
                <div class="bg-white px-6 pb-2 pt-6">
                    <h2 id="new-request-heading" class="text-lg font-bold text-navy-900">Start a new request</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-700">
                        You have {{ $slotsRemaining }} of {{ $limit }} request
                        {{ $slotsRemaining === 1 ? 'slot' : 'slots' }} left.
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                    <x-btn type="button" variant="outline" size="md" x-on:click="$dispatch('close-modal', 'new-request')">
                        Not now
                    </x-btn>
                    <x-btn :href="route('resident.requests.create')" variant="primary" size="md" icon="document">
                        Choose a document
                    </x-btn>
                </div>
            </x-modal>
        @elseif ($slotsRemaining > 0)
            {{-- Locked, with the way out in the dialog rather than left to be
                 discovered on another page. --}}
            <x-modal name="requests-locked" maxWidth="lg" dismissible>
                <div class="bg-white px-6 pb-2 pt-6">
                    <h2 id="requests-locked-heading" class="text-lg font-bold text-navy-900">New requests are locked</h2>

                    @if (! auth()->user()->residentProfile?->isVerified())
                        <p class="mt-2 text-sm leading-relaxed text-navy-700">
                            You can follow the requests below, but new requests stay locked until the
                            Barangay Secretary has verified your details.
                        </p>
                    @else
                        <p class="mt-2 text-sm leading-relaxed text-navy-700">
                            This account is temporarily restricted, so new requests cannot be submitted.
                        </p>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                    <x-btn type="button" variant="outline" size="md" x-on:click="$dispatch('close-modal', 'requests-locked')">
                        Close
                    </x-btn>

                    @if (! auth()->user()->residentProfile?->isVerified())
                        <x-btn :href="route('resident.verification.status')" variant="navy" size="md" icon="shield-check">
                            Check your verification status
                        </x-btn>
                    @endif
                </div>
            </x-modal>
        @endif

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-navy-900">My Documents</h2>

            <x-btn :href="route('resident.requests.create')" variant="primary" size="md">+ New Request</x-btn>
        </div>

        {{-- Chips. Plain links, so each one is a real URL: a filtered list can be
             shared, bookmarked, and reached with the back button. "All" is a link
             with no parameter rather than ?status=all, so the unfiltered page has
             one canonical URL. --}}
        <nav aria-label="Filter requests" class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('resident.requests.index') }}"
                @class([
                    'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2',
                    'border-gold bg-gold/15 text-navy-900 ring-2 ring-gold' => $statusFilter === null,
                    'border-navy-200 bg-white text-navy-700 hover:border-navy-300 hover:bg-navy-50' => $statusFilter !== null,
                ])
                @if ($statusFilter === null) aria-current="page" @endif>All</a>

            @foreach ($statusFilters as $key => $filter)
                <a href="{{ route('resident.requests.index', ['status' => $key]) }}"
                    @class([
                        'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2',
                        'border-gold bg-gold/15 text-navy-900 ring-2 ring-gold' => $statusFilter === $key,
                        'border-navy-200 bg-white text-navy-700 hover:border-navy-300 hover:bg-navy-50' => $statusFilter !== $key,
                    ])
                    @if ($statusFilter === $key) aria-current="page" @endif>{{ $filter['label'] }}</a>
            @endforeach
        </nav>

        @if ($requests->isEmpty())
            @if ($isFiltered)
                <x-empty-state icon="search" title="Nothing in this filter"
                    description="You have no requests that are {{ strtolower($statusFilters[$statusFilter]['label'] ?? 'in this state') }}.">
                    <x-btn :href="route('resident.requests.index')" variant="navy" size="sm" class="mt-5">
                        Show all my requests
                    </x-btn>
                </x-empty-state>
            @else
                <x-empty-state icon="document" title="No requests yet"
                    description="When you apply for a barangay document it will appear here with its reference number.">
                    <x-btn :href="route('resident.requests.create')" variant="navy" size="sm" class="mt-5">
                        Request a document
                    </x-btn>
                </x-empty-state>
            @endif
        @else
            {{--
                One wrapper owns which row is being tracked, so the Track dialog is
                written once rather than once per row.

                The per-row panels inside the dialog are read-only, and x-show
                takes the ones that are not selected out of the accessibility tree
                rather than merely hiding them from sight. That is the reason this
                is acceptable where a hidden form would not be: there is nothing in
                here to be submitted by a keyboard that is not meant to be.
            --}}
            <div x-data="{ tracked: null }">
                <x-data-table
                    :columns="[
                        ['label' => 'Document'],
                        ['label' => 'Status'],
                        ['label' => 'Submitted'],
                        ['label' => 'Actions'],
                    ]"
                    variant="resident"
                    caption="Your document requests"
                    empty-title="No requests yet">

                    @foreach ($requests as $i => $documentRequest)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left">
                                <a href="{{ route('resident.requests.show', $documentRequest) }}"
                                    class="font-semibold text-navy-900 hover:underline">
                                    {{ $documentRequest->documentType?->label ?? 'Retired document' }}
                                </a>
                                <span class="mt-0.5 block font-mono text-xs text-slate-500">
                                    {{ $documentRequest->reference_number }}
                                </span>
                            </th>
                            <td class="px-4 py-3">
                                <x-status-badge :status="$documentRequest->status" size="sm" soft />
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ $documentRequest->created_at?->format('j M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                        x-on:click="tracked = {{ $i }}; $dispatch('open-modal', 'track-request')"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-navy-200 px-2.5 py-1.5 text-xs font-semibold text-navy-800 transition hover:border-navy-300 hover:bg-navy-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-1">
                                        <x-icon name="search" class="h-3.5 w-3.5" />
                                        Track
                                        <span class="sr-only">{{ $documentRequest->reference_number }}</span>
                                    </button>

                                    {{--
                                        View PDF is deliberately absent. Nothing in this
                                        system puts a document behind a request: the only
                                        PDF anywhere is a resident's uploaded ID, and
                                        generating one would need a package this project
                                        does not have. A button that always leads to
                                        nothing is worse than no button, so the slot is
                                        left empty until there is a file to point at.
                                    --}}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>

                <x-modal name="track-request" maxWidth="xl" dismissible>
                    @foreach ($requests as $i => $documentRequest)
                        <div x-show="tracked === {{ $i }}" x-cloak class="bg-white">
                            <div class="px-6 pb-2 pt-6">
                                <h2 id="track-request-title" class="text-lg font-bold text-navy-900">
                                    {{ $documentRequest->documentType?->label ?? 'Document request' }}
                                </h2>
                                <p class="mt-1 font-mono text-xs text-slate-500">
                                    {{ $documentRequest->reference_number }}
                                </p>
                            </div>

                            <div class="space-y-5 px-6 py-4">
                                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                                        <dd class="mt-1">
                                            <x-status-badge :status="$documentRequest->status" size="sm" soft />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                                        <dd class="mt-1 text-sm text-navy-900">
                                            {{ $documentRequest->created_at?->format('j M Y') ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                                        <dd class="mt-1 text-sm text-navy-900">
                                            {{ $documentRequest->fee_amount ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>

                                <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />

                                @if ($documentRequest->needsRejectionReason())
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                                        <p class="text-sm font-semibold text-red-800">The office could not process this request</p>
                                        <p class="mt-1 text-sm text-red-700">
                                            {{ $documentRequest->rejection_reason ?? 'Ask at the barangay office what to change.' }}
                                        </p>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                                <x-btn type="button" variant="outline" size="md"
                                    x-on:click="$dispatch('close-modal', 'track-request')">
                                    Close
                                </x-btn>
                                <x-btn :href="route('resident.requests.show', $documentRequest)" variant="navy" size="md">
                                    View full request
                                </x-btn>
                            </div>
                        </div>
                    @endforeach
                </x-modal>
            </div>
        @endif
    </x-shell>
</x-public-layout>
