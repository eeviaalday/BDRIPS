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

    {{--
        One page container for everything below: the notice, the three figures
        and the My Documents section. They were three separate blocks, and the
        lock dialog used to print its own panel into the flow between them, which
        is what made the section below look like it had left the column.

        The pending case is the one that has to read without any clicking, so the
        lock is said twice on purpose and in two different registers: once as a
        quiet amber line on the page, so they know the state without hunting for
        it, and once inside the dialog, for when they press the button and the
        page they are on does not explain itself.
    --}}
    <x-shell>
        @if (! $canCreate && ! auth()->user()->residentProfile?->isVerified())
            <x-alert variant="warning" icon="lock" class="mb-6">
                New requests are locked. You can request documents once the Barangay Secretary
                verifies your ID.
                <a href="{{ route('account.edit', ['tab' => 'verification']) }}"
                    class="ml-1 inline-flex items-center gap-1.5 font-semibold underline underline-offset-2">
                    Check verification status
                </a>
            </x-alert>
        @endif

        {{-- Three figures that are also the filter. Two are links, one is a
             button that opens a dialog, so the interactive card is a resident
             component of its own: the shared stat-card renders a plain div when
             it has no href, and a div cannot be focused or activated. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            {{-- 1. Open requests -> ?status=open. --}}
            <x-resident.filter-card label="Open requests" :value="$openCount" :hint="'of '.$limit.' allowed'"
                tone="navy" icon="document" as="link" :href="route('resident.requests.index', ['status' => 'open'])"
                :active="$statusFilter === 'open'" />

            {{-- 2. Slots remaining. Opens the New Request dialog, or the lock
                 dialog for a resident who cannot use it yet.

                 A pending resident is shown a dash and the words "Locked until
                 verified" rather than the number 3. Three is the allowance, not
                 something they can spend, and printing it on a card they cannot
                 act on reads as a refusal rather than as a state. The card is
                 still a button: a control that silently does nothing is worse
                 than one that explains itself. --}}
            @if ($slotsRemaining === 0)
                <x-resident.filter-card label="Slots remaining" :value="$slotsRemaining" hint="None left"
                    tone="gold" icon="lock" as="disabled" />
            @elseif ($canCreate)
                <x-resident.filter-card label="Slots remaining" :value="$slotsRemaining"
                    hint="Start a new request" tone="gold" icon="check-circle" as="button"
                    x-on:click="$dispatch('open-modal', 'new-request')" />
            @else
                <x-resident.filter-card label="Slots remaining" value="&mdash;"
                    hint="Locked until verified" tone="gold" icon="lock" as="button"
                    x-on:click="$dispatch('open-modal', 'requests-locked')" />
            @endif

            {{-- 3. Total on file -> clears the filter. Counted on the server over
                 every request this resident has, so it does not change when the
                 list is filtered. --}}
            <x-resident.filter-card label="Total on file" :value="$totalCount"
                tone="sky" icon="archive" as="link" :href="route('resident.requests.index')"
                :active="$statusFilter === null" />
        </div>

        {{-- The New Request dialog, for a resident who can use it. It explains
             the remaining allowance rather than pretending the button is
             missing. --}}
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
        @endif

        {{-- The lock dialog. Resident-built rather than the shared modal, because
             it has to move focus into itself and give it back: the shared one
             never focuses the panel, so a keyboard user opening it is left focused
             on a button behind the backdrop. --}}
        @if (! auth()->user()->residentProfile?->isVerified())
            <x-resident.modal name="requests-locked" title="New requests are locked" maxWidth="md">
                <div class="px-6 pb-2 pt-6">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <x-icon name="lock" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 id="requests-locked-title" class="text-lg font-bold text-navy-900">
                                New requests are locked
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                You can request documents once the Barangay Secretary verifies your
                                ID. Until then the request form stays closed, and anything you
                                filed earlier is still listed below.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                    <x-btn type="button" variant="outline" size="md" x-on:click="$dispatch('close-modal', 'requests-locked')">
                        Close
                    </x-btn>
                    <x-btn :href="route('account.edit', ['tab' => 'verification'])" variant="navy" size="md"
                        icon="shield-check">
                        Check verification status
                    </x-btn>
                </div>
            </x-resident.modal>
        @endif

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-navy-900">My Documents</h2>

            {{-- Pending gets a lock on the button and the same dialog, so pressing
                 it explains rather than refusing. --}}
            @if ($canCreate)
                <x-btn :href="route('resident.requests.create')" variant="primary" size="md">+ New Request</x-btn>
            @else
                <x-btn type="button" variant="primary" size="md" icon="lock"
                    x-on:click="$dispatch('open-modal', 'requests-locked')">
                    + New Request
                </x-btn>
            @endif
        </div>

        {{-- Chips, and only when there is something to filter. A row of chips over
             an empty list offers six ways to see nothing, and reads as though the
             page has records it is not showing.

             "All" is a link with no parameter rather than ?status=all, so the
             unfiltered page has one canonical URL. --}}
        @if ($totalCount > 0)
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
        @endif

        {{-- mt-4 separates the chips from the table below. Flush against a
             bordered table they read as part of the same block and stop looking
             switchable. --}}
        <div class="mt-4">
        @if ($requests->isEmpty())
            @if ($isFiltered)
                {{-- Inside a card, with its own padding. Full-bleed it ran to both
                     screen edges and stopped reading as part of the section. --}}
                <x-card tone="white" padding="p-6">
                    <x-empty-state icon="search" title="Nothing in this filter"
                        description="You have no requests that are {{ strtolower($statusFilters[$statusFilter]['label'] ?? 'in this state') }}.">
                        <x-btn :href="route('resident.requests.index')" variant="navy" size="sm" class="mt-5">
                            Show all my requests
                        </x-btn>
                    </x-empty-state>
                </x-card>
            @else
                {{-- No button for a resident who cannot use one: a "Request a
                     document" button on a locked page contradicts the banner above
                     it. --}}
                <x-card tone="white" padding="p-6">
                    <x-empty-state icon="document" title="No requests yet"
                        description="Once your account is verified, the documents you request will appear here with their status." />
                </x-card>
            @endif
        @else
            {{--
                One wrapper owns which row is being tracked, so the Track dialog is
                written once rather than once per row.

                The per-row panels inside the dialog are read-only, and x-show
                takes the ones that are not selected out of the accessibility
                tree rather than merely hiding them from sight.
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
                                <span class="block font-semibold text-navy-900">
                                    {{ $documentRequest->documentType?->label ?? 'Retired document' }}
                                </span>
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
                                        nothing is worse than no button.
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
                                            <x-status-badge :status="$documentRequest->status" soft />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                                        <dd class="mt-1 text-sm font-medium text-navy-900">
                                            {{ $documentRequest->created_at?->format('j M Y, g:i a') ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                                        <dd class="mt-1 text-sm font-medium text-navy-900">
                                            {{ $documentRequest->fee_amount == 0
                                                ? 'Free'
                                                : 'PHP '.number_format((float) $documentRequest->fee_amount, 2) }}
                                        </dd>
                                    </div>
                                </dl>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stated purpose</p>
                                    <p @class([
                                        'mt-1 text-sm leading-relaxed text-navy-900',
                                        'whitespace-pre-line' => $documentRequest->purpose,
                                    ])>{{ $documentRequest->purpose ?: 'No purpose was stated on this request.' }}</p>
                                </div>

                                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_REJECTED)
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm">
                                        <p class="font-semibold text-red-800">This request was not approved.</p>
                                        <p class="mt-1 text-red-700">
                                            {{ $documentRequest->rejection_reason
                                                ?? 'The Secretariat did not record a reason. Please ask at the Barangay Hall.' }}
                                        </p>
                                    </div>
                                @endif

                                {{-- The one thing a ready document needs: where to go
                                     and what to quote. --}}
                                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_READY)
                                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm">
                                        <p class="font-semibold text-sky-900">Your document is ready.</p>
                                        <p class="mt-1 text-sky-800">
                                            Collect it at the Barangay Hall, quoting
                                            <strong class="font-mono font-semibold">{{ $documentRequest->reference_number }}</strong>.
                                        </p>
                                    </div>
                                @endif

                                <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />
                            </div>

                            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                                <x-btn type="button" variant="navy" size="md"
                                    x-on:click="$dispatch('close-modal', 'track-request')">
                                    Close
                                </x-btn>
                            </div>
                        </div>
                    @endforeach
                </x-modal>
            </div>
        @endif
        </div>
    </x-shell>
</x-public-layout>
