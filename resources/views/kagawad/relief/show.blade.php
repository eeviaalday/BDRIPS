{{--
    The checklist, grouped by sector.

    Built for one thumb and a bright sun. Everything a Kagawad does here happens
    while holding a clipboard, standing in a street, one-handed.

    FOUR THINGS WORTH KNOWING BEFORE CHANGING THIS FILE

    1. There is no Undo, deliberately. The brief asked for one "only if ROLES.md
       permits" - ROLES.md does not exist in this repository, and ReliefController
       has no undo endpoint at all, so a button would have to invent both a
       permission and a route. An Undo that quietly reverses a signed handover is
       worse than no Undo: the audit log would record a collection and then
       un-record it, and nothing in the data model distinguishes the two. Collected
       rows instead show who marked it and when, which is what makes a mistake
       correctable *by a person*.

    2. Marking collected is irreversible, so it is confirmed - but inline, not with
       a modal. A modal per household is forty dialogs during one distribution, and
       a dialog that appears forty times is a dialog that gets dismissed forty
       times unread. Tapping Mark swaps that one row's control for Confirm /
       Cancel, so the second tap is deliberate, local, and cancellable.

    3. Filtering is client-side. The search box and the Remaining / Collected / All
       tabs are Alpine over rows already in the DOM and already scoped to this
       Kagawad's sectors. Doing it on the server would mean a round trip per
       keystroke, on the connection this page is opened on, to re-derive a list the
       browser already holds. Nothing here adds a query.

       Each row registers its own filter facts with the page scope once
       (register), and then only ever asks isVisible(id). The alternative - passing
       the status, household number and collector to every visible() call as well -
       would send the same three facts twice, and the two copies could drift.

    4. There is a "nothing matches" state, because a filtered list that empties
       silently is the one failure the empty-state component's own comment calls
       the worst of the three: the reader cannot tell a search that missed from a
       page that broke.
--}}
@php
    /*
     * Counts for the tabs, read off the data already in memory rather than with
     * three more queries. $total and $received are the controller's own tallies.
     */
    $remainingCount = max(0, $total - $received);
@endphp

<x-app-layout :user="auth()->user()" :title="$batch->title">
    <div class="mx-auto w-full max-w-5xl space-y-6"
        {{--
            NOTE ON THE COMMENTS IN HERE.

            They are /* block */ comments and must stay that way. Alpine evaluates
            x-data as a JavaScript *expression* by building a function around it, so
            a // line comment inside the object literal runs to the end of the line,
            swallows the closing brace on that same line, and the expression fails to
            parse with "Unexpected token 'const'" - which is the first token of the
            x-init that follows. The page still renders, the HTML is still correct,
            and every server test still passes; the tabs and the search box are
            simply dead. Found in a browser, not in PHPUnit.

            Same shape as the two Blade-comment traps already recorded in this
            project: written where the tool does not strip it, and silent.
        --}}
        x-data="{
            tab: 'remaining',
            search: '',
            /* id => the three facts the filter reads. Populated by register(). */
            registry: {},
            register(id, status, household, collectedBy) {
                this.registry[id] = { status, household, collectedBy }
            },
            wanted(status) {
                if (this.tab === 'all') return true
                return this.tab === 'collected' ? status === 'received' : status !== 'received'
            },
            matches(household, collectedBy) {
                const needle = this.search.trim().toLowerCase()
                if (needle === '') return true
                return (household + ' ' + collectedBy).toLowerCase().includes(needle)
            },
            isVisible(id) {
                const row = this.registry[id]
                if (! row) return true
                return this.wanted(row.status) && this.matches(row.household, row.collectedBy)
            },
            /*
             * Recomputed by Alpine whenever the registry changes, which is why
             * register() assigns a key rather than pushing: a plain push is not
             * reactive and the count would sit at zero forever.
             */
            get visibleCount() {
                return Object.values(this.registry).filter(
                    (row) => this.wanted(row.status) && this.matches(row.household, row.collectedBy)
                ).length
            },
        }"
        {{--
            Scroll restore.

            Written as an IIFE because this Alpine build evaluates x-init as a
            single *expression* - `with (scope) { __self.result = <here> }` - and
            does not fall back to treating it as a statement body. So a handler
            written as

                const saved = sessionStorage.getItem(...)
                if (saved !== null) { ... }

            throws "Unexpected token 'const'" and never runs, with the page still
            rendering, the HTML still correct, and every server test still green.
            Verified in the browser by evaluating the extracted attribute both
            ways: expression form THROWS, arrow-body form is fine - and it is the
            expression form this build uses.

            An IIFE is a single expression, so it parses in the form Alpine
            actually evaluates. Same rule as the comments in x-data above.
        --}}
        x-init="(() => { const saved = sessionStorage.getItem('kagawad-checklist-scroll'); if (saved !== null) { window.scrollTo(0, parseInt(saved, 10)); sessionStorage.removeItem('kagawad-checklist-scroll') } })()">

        <x-breadcrumb :items="[
            ['label' => 'Kagawad', 'route' => 'kagawad.dashboard'],
            ['label' => 'Relief Distribution', 'route' => 'kagawad.relief'],
            ['label' => $batch->title],
        ]" />

        <header class="space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-navy-900">{{ $batch->title }}</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $batch->distribution_date?->format('l, j F Y') ?? 'No date set' }}
                    </p>
                </div>
                <x-staff.status-badge :status="$batch->isActive() ? 'active' : 'closed'" />
            </div>

            {{-- The goods, as chips. A Kagawad confirming a handover counts what is
                 in the box; a comma-separated line is harder to check against than
                 four things they can tick off. --}}
            @if ($batch->items() !== [])
                <ul class="flex flex-wrap items-center gap-2" aria-label="Goods in this batch">
                    <li class="text-xs font-semibold uppercase tracking-wide text-navy-500">Goods</li>
                    @foreach ($batch->items() as $item)
                        <li>
                            <x-staff.status-badge status="neutral" :label="$item" :dot="false" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </header>

        @unless ($batch->isActive())
            <x-alert variant="warning" icon="lock">
                This batch is closed, so the checklist is a record and cannot be changed.
            </x-alert>
        @endunless

        @if ($bySector->isEmpty())
            <x-staff.empty-state icon="folder" title="None of this batch's households are in your sectors"
                description="There is nothing here for you to work through on this batch." />
        @else
            @php
                $percent = $total > 0 ? (int) round($received / $total * 100) : 0;
            @endphp

            {{-- Overall progress for THIS Kagawad's sectors. Labelled "your"
                 because the batch total belongs to the barangay, and a Kagawad must
                 never be shown a figure that implies it is theirs. --}}
            <section aria-labelledby="your-progress"
                class="rounded-xl border border-navy-100 bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="your-progress" class="text-sm font-bold text-navy-900">Your progress on this batch</h2>
                    <p class="text-sm font-semibold tabular-nums text-navy-800">
                        {{ $received }} / {{ $total }} collected
                    </p>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-navy-100" role="progressbar"
                    aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                    aria-label="{{ $received }} of {{ $total }} households collected">
                    <div class="h-full rounded-full bg-gold-400 transition-all" style="width: {{ $percent }}%"></div>
                </div>
            </section>

            <div class="space-y-3">
                <div>
                    <label for="checklist-search" class="block text-sm font-semibold text-navy-800">
                        Search a household
                    </label>
                    <input id="checklist-search" type="search" x-model="search" autocomplete="off"
                        placeholder="Household number, or who collected"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-base focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>

                {{-- Real tab semantics: these switch which rows are shown, and arrow-key
                     movement is what a screen reader expects from controls shaped like
                     tabs. aria-controls is omitted rather than faked: the rows are a list
                     that this filters, not a single tabpanel. --}}
                <div role="tablist" aria-label="Filter the checklist" class="flex flex-wrap gap-2">
                    @php
                        $tabs = [
                            'remaining' => ['Remaining', $remainingCount],
                            'collected' => ['Collected', $received],
                            'all' => ['All', $total],
                        ];
                    @endphp
                    @foreach ($tabs as $key => [$label, $count])
                        <button type="button" role="tab"
                            @click="tab = '{{ $key }}'"
                            x-bind:aria-selected="tab === '{{ $key }}'"
                            @class([
                                'min-h-[44px] rounded-lg border px-4 py-2 text-sm font-semibold transition',
                                'border-navy-800 bg-navy text-white shadow-sm' => "tab === '{$key}'",
                                'border-navy-200 bg-white text-navy-700 hover:bg-navy-50' => "tab !== '{$key}'",
                            ])>
                            {{ $label }}
                            <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- "Nothing matches" for a search that missed, or a tab whose count is
                 zero. Not per sector: a message beside a sector the Kagawad never
                 filtered out would be confusing. --}}
            <div x-show="visibleCount === 0" x-cloak>
                <x-staff.empty-state icon="search" title="No households match"
                    description="Try a different household number, or switch to the All tab.">
                </x-staff.empty-state>
            </div>

            @foreach ($bySector as $group)
                <section aria-labelledby="sector-{{ $group['sector']?->id ?? $loop->index }}"
                    class="rounded-xl border border-navy-100 bg-white shadow-card">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-navy-100 px-5 py-4">
                        <h2 id="sector-{{ $group['sector']?->id ?? $loop->index }}"
                            class="text-base font-bold text-navy-900">
                            {{ $group['sector']?->sector_name ?? 'Sector '.$group['rows']->first()->sector_id }}
                        </h2>
                        <p class="text-sm text-navy-600">
                            <span class="font-semibold tabular-nums">{{ $group['received'] }}</span>
                            of
                            <span class="tabular-nums">{{ $group['total'] }}</span>
                            collected
                        </p>
                    </div>

                    <ul class="divide-y divide-navy-50">
                        @foreach ($group['rows'] as $row)
                            @php
                                $collectedBy = (string) ($row->received_by_name ?? '');
                                $collectedLabel = $row->isReceived()
                                    ? 'Collected by '.($collectedBy !== '' ? $collectedBy : 'the Kagawad')
                                        .($row->claimed_at ? ' · '.$row->claimed_at->format('j M, g:i a') : '')
                                    : 'Not collected yet';
                            @endphp
                            <li class="px-5 py-4" x-cloak
                                x-init="register(@js((string) $row->id), @js($row->status), @js($row->household_number), @js($collectedBy))"
                                x-show="isVisible(@js((string) $row->id))">

                                {{--
                                    Stacked until lg, not sm.

                                    Side by side from sm (640px) the row overflows:
                                    the control block is 348px and cannot shrink, and
                                    a long "Collected by ... · 2 Oct, 2:20 am" second
                                    line pushes the rest of the row past the edge, so
                                    the page itself scrolled sideways at 768px. Measured,
                                    not guessed - the form ran from x=455 to x=802 in a
                                    768px viewport.

                                    Full-width stacked controls are also the better
                                    target for a thumb, so this is a phone fix that
                                    happens to be a tablet fix too.
                                --}}
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <x-staff.person-cell :name="$row->household_number" :secondary="$collectedLabel"
                                        mono class="min-w-0 flex-1" />

                                    @if ($row->isReceived())
                                        <div class="flex shrink-0 items-center gap-2">
                                            {{-- "Collected", not "Ready for pickup": a household
                                                 that has already signed for its goods is not
                                                 waiting to collect them. --}}
                                            <x-staff.status-badge status="collected" size="sm" />
                                        </div>
                                    @elseif ($batch->isActive())
                                        {{-- x-data on the row, not the page: two rows can be
                                             mid-confirm at once and a page-level flag would
                                             cancel both. --}}
                                        <div class="shrink-0" x-data="{ confirming: false }">
                                            <form method="POST" class="flex flex-col gap-2 lg:flex-row lg:items-center"
                                                {{-- Concatenated with + and not with a dot: a dot in
                                                     JavaScript is member access, so 'H-4'.' marked'
                                                     is a syntax error ("Unexpected string") and this
                                                     handler threw on every submit. PHP's . looks
                                                     identical here and is not the same language. --}}
                                                x-on:submit="(() => { sessionStorage.setItem('kagawad-checklist-scroll', String(window.scrollY)); sessionStorage.setItem('kagawad-checklist-last', @js($row->household_number) + ' marked as collected') })()"
                                                action="{{ route('kagawad.relief.received', [$batch, $row->id]) }}">
                                                @csrf
                                                <label class="sr-only" for="received-{{ $row->id }}">
                                                    Who collected for {{ $row->household_number }}
                                                </label>
                                                <input id="received-{{ $row->id }}" name="received_by_name" type="text"
                                                    maxlength="255" placeholder="Who collected (optional)"
                                                    class="w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-base focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200 lg:w-40 lg:text-sm">

                                                {{-- type="button", not submit: this first tap only
                                                     reveals the confirm. As a submit button it posted
                                                     the form on the first tap, which made the confirm
                                                     unreachable and turned an irreversible record
                                                     into a single unconfirmed click. --}}
                                                <x-btn type="button" variant="navy" size="lg" icon="check"
                                                    x-on:click="confirming = true"
                                                    x-show="! confirming" class="min-h-[44px] w-full lg:w-auto">
                                                    Mark collected
                                                </x-btn>

                                                <span x-show="confirming" x-cloak class="flex items-center gap-2">
                                                    <x-btn type="submit" variant="primary" size="lg" class="min-h-[44px]">
                                                        Confirm
                                                    </x-btn>
                                                    <x-btn type="button" variant="ghost" size="lg"
                                                        x-on:click="confirming = false" class="min-h-[44px]">
                                                        Cancel
                                                    </x-btn>
                                                </span>
                                            </form>
                                        </div>
                                    @else
                                        <x-btn variant="ghost" size="md" disabled class="min-h-[44px]">
                                            Closed
                                        </x-btn>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>

    {{--
        Toast.

        A compact confirmation that the tap registered, shown after the redirect and
        then dismissed. Deliberately NOT the session flash: layouts/app already
        renders that, and a second copy of the same sentence on the same page is
        noise. This says the tap worked and names the household, and it is written
        to sessionStorage on submit so it survives the reload that a form POST
        forces.
    --}}
    <div x-data="{}" x-init="(() => { const last = sessionStorage.getItem('kagawad-checklist-last'); if (last !== null) { const toast = $refs.toast; toast.textContent = last; toast.classList.remove('hidden'); sessionStorage.removeItem('kagawad-checklist-last'); setTimeout(() => toast.classList.add('hidden'), 4000) } })()">
        {{-- The ref is on the <p>, not on this wrapper. With it on the wrapper,
             `$refs.toast.textContent = ...` replaced the styled pill element with
             bare text, so the toast appeared as unstyled words on the page: the
             padding, background and shadow all went with it. The message showed
             and nothing looked wrong, which is why this took a browser. --}}
        <div class="pointer-events-none fixed inset-x-0 bottom-24 z-50 flex justify-center px-4 md:bottom-6"
            role="status" aria-live="polite">
            <p x-ref="toast" class="hidden rounded-full bg-navy px-4 py-2.5 text-sm font-semibold text-white shadow-lg"></p>
        </div>
    </div>
</x-app-layout>
