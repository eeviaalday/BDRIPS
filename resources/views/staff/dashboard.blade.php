{{--
    The staff landing page.

    Reached by /dashboard after signing in, and by the sidebar's Dashboard link.

    It answers one question - what is waiting on me - in the order a staff member
    asks it: what needs a decision, what is ready to be handed over, what is
    moving right now, and what can I act on without thinking.

    Every figure is a link. A number you cannot act on is a number you have to
    remember the route for, and that is exactly the kind of thing the sidebar is
    for, not the landing page.

    The Admin strip is the only part of this page a Secretary does not get, and
    the only links on it point at the three Admin-only read routes
    (/staff/audit, /staff/accounts, /staff/sectors, /staff/settings). It reports;
    it never acts.
    Everything above it is identical for both roles, because the queues a
    Secretary and an Admin clear are the same queues.
--}}
<x-app-layout :user="auth()->user()" title="Dashboard">
    <div class="mx-auto w-full max-w-6xl space-y-8">
        <x-breadcrumb :items="[['label' => 'Staff', 'route' => 'staff.dashboard'], ['label' => 'Dashboard']]" />

        <header>
            <h1 class="text-xl font-bold text-navy-900">{{ $greeting }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ $today }}</p>
            <p class="mt-2 text-sm text-navy-600">Here is what is waiting on you.</p>
        </header>

        {{-- The four queues. Ordered by what ages worst: a registration that
             nobody reviewed is the one a household notices. --}}
        <x-staff.stat-row>
            <x-staff.stat-card label="Verifications pending" :value="$pendingVerifications" tone="gold" icon="id-badge"
                :href="route('staff.verifications.index', ['status' => 'pending'])"
                :hint="$oldestPendingDays === null ? null : 'oldest waiting '.$oldestPendingDays.' '.\Illuminate\Support\Str::plural('day', $oldestPendingDays)" />
            <x-staff.stat-card label="Requests to review" :value="$awaitingDecision" tone="sky" icon="document"
                :href="route('staff.requests.index', ['status' => 'queue'])" />
            <x-staff.stat-card label="Ready for pickup" :value="$readyForPickup" tone="navy" icon="check-circle"
                :href="route('staff.requests.index', ['status' => 'ready_for_pickup'])" />
            <x-staff.stat-card label="Active relief batches" :value="$activeBatches" tone="green" icon="gift"
                :href="route('staff.batches.index')" />
        </x-staff.stat-row>

        {{-- Two queues that are both waiting on a decision, so they sit side by
             side. The grid collapses to one column on a phone, where a Secretary
             works through one queue at a time. --}}
        <section aria-labelledby="needs-attention" class="space-y-4">
            <div class="flex items-center gap-2">
                <x-icon name="clock" class="h-5 w-5 text-gold-600" />
                <h2 id="needs-attention" class="text-base font-bold text-navy-900">Needs attention</h2>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Registrations waiting on a decision. --}}
                <div class="rounded-xl border border-navy-100 bg-white shadow-card">
                    <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-navy-900">Latest pending verifications</h3>
                        @if ($latestPending->isNotEmpty())
                            <a href="{{ route('staff.verifications.index') }}"
                                class="text-xs font-semibold text-sky-700 hover:text-sky-900">See all {{ $pendingVerifications }}</a>
                        @endif
                    </div>

                    @if ($latestPending->isEmpty())
                        <div class="p-5">
                            <x-staff.empty-state icon="check-circle" title="No registrations waiting."
                                description="Every registration has been reviewed." level="p" />
                        </div>
                    @else
                        {{-- A list, not a table: five rows of one person each. A table
                             would need a header row to say nothing the rows do not
                             already say. The <a> stays in the person cell. --}}
                        <ul class="divide-y divide-navy-50">
                            @foreach ($latestPending as $profile)
                                <li>
                                    <a href="{{ route('staff.verifications.show', $profile) }}"
                                        class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-navy-50/60">
                                        <x-staff.person-cell
                                            :name="$profile->user?->name"
                                            :secondary="$profile->household_number"
                                            class="min-w-0" />
                                        <div class="shrink-0 text-right text-xs text-navy-500">
                                            <p class="font-medium text-navy-700">{{ $profile->sector?->sector_name ?? 'No sector' }}</p>
                                            <p class="mt-0.5">{{ $profile->created_at?->format('j M Y') }}</p>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Document requests waiting on a decision. --}}
                <div class="rounded-xl border border-navy-100 bg-white shadow-card">
                    <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-navy-900">Requests awaiting decision</h3>
                        @if ($latestAwaiting->isNotEmpty())
                            <a href="{{ route('staff.requests.index') }}"
                                class="text-xs font-semibold text-sky-700 hover:text-sky-900">See all {{ $awaitingDecision }}</a>
                        @endif
                    </div>

                    @if ($latestAwaiting->isEmpty())
                        <div class="p-5">
                            <x-staff.empty-state icon="check-circle" title="No requests waiting."
                                description="Every document request has a decision." level="p" />
                        </div>
                    @else
                        <ul class="divide-y divide-navy-50">
                            @foreach ($latestAwaiting as $documentRequest)
                                <li>
                                    <a href="{{ route('staff.requests.show', $documentRequest) }}"
                                        class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-navy-50/60">
                                        <x-staff.person-cell
                                            :name="$documentRequest->reference_number"
                                            :secondary="$documentRequest->user?->name"
                                            mono
                                            class="min-w-0" />
                                        {{--
                                            min-w-0 and truncate, not shrink-0.

                                            The document-type label is the longest
                                            string on this row - "First-Time Jobseeker
                                            Certificate Submission" is longer than
                                            the column at 1024px, where the two-column
                                            grid halves each list. With shrink-0 the
                                            label could not give way, so the row
                                            pushed the whole document past the
                                            viewport and every other panel on the
                                            page scrolled sideways with it.

                                            The badge keeps shrink-0: it is a pill
                                            with a dot and a word, and a truncated
                                            status is worse than a truncated type.
                                        --}}
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="hidden min-w-0 truncate text-xs text-navy-500 sm:inline">
                                                {{ $documentRequest->documentType?->label ?? 'Retired document' }}
                                            </span>
                                            <span class="shrink-0"><x-staff.status-badge
                                                :status="$documentRequest->status" size="sm" /></span>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Finished and waiting on the resident, not on us. --}}
            <section aria-labelledby="ready-pickup" class="rounded-xl border border-navy-100 bg-white shadow-card">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 id="ready-pickup" class="text-sm font-bold text-navy-900">Ready for pickup</h2>
                    @if ($readyForPickupList->isNotEmpty())
                        <a href="{{ route('staff.requests.index', ['status' => 'ready_for_pickup']) }}"
                            class="text-xs font-semibold text-sky-700 hover:text-sky-900">See all {{ $readyForPickup }}</a>
                    @endif
                </div>

                @if ($readyForPickupList->isEmpty())
                    <div class="p-5">
                        <x-staff.empty-state icon="archive" title="Nothing ready for pickup."
                            description="No document is waiting for a resident to collect it." level="p" />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($readyForPickupList as $documentRequest)
                            <li>
                                <a href="{{ route('staff.requests.show', $documentRequest) }}"
                                    class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-navy-50/60">
                                    <x-staff.person-cell
                                        :name="$documentRequest->reference_number"
                                        :secondary="$documentRequest->user?->name ?? 'Unknown resident'"
                                        mono
                                        class="min-w-0" />
                                    <x-icon name="arrow-right" class="h-4 w-4 shrink-0 text-navy-300" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- What is moving today. --}}
            <section aria-labelledby="relief-now" class="rounded-xl border border-navy-100 bg-white shadow-card">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 id="relief-now" class="text-sm font-bold text-navy-900">Relief right now</h2>
                    @if ($activeBatchList->isNotEmpty())
                        <a href="{{ route('staff.batches.index') }}"
                            class="text-xs font-semibold text-sky-700 hover:text-sky-900">See all {{ $activeBatches }}</a>
                    @endif
                </div>

                @if ($activeBatchList->isEmpty())
                    <div class="p-5">
                        <x-staff.empty-state icon="gift" title="No active relief batch."
                            description="Nothing is being distributed at the moment."
                            actionLabel="Open a relief batch" :actionHref="route('staff.batches.create')" level="p" />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($activeBatchList as $batch)
                            @php
                                // Seeded at zero by the controller, so a batch whose
                                // distributions have not been logged yet shows an empty
                                // bar rather than a division by zero.
                                $progress = $batchProgress[$batch->id] ?? ['total' => 0, 'received' => 0, 'pending' => 0];
                                $percent = $progress['total'] > 0
                                    ? (int) round($progress['received'] / $progress['total'] * 100)
                                    : 0;
                            @endphp
                            <li>
                                <a href="{{ route('staff.batches.show', $batch) }}"
                                    class="block px-5 py-4 transition hover:bg-navy-50/60">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <p class="truncate text-sm font-semibold text-navy-900">{{ $batch->title }}</p>
                                        <span class="shrink-0 text-xs text-navy-500">
                                            {{ $batch->distribution_date?->format('j M Y') }}
                                        </span>
                                    </div>
                                    <div class="mt-2.5 flex items-center gap-3">
                                        {{-- role="progressbar" with the numbers in the accessible
                                             name, and a real aria-valuenow.

                                             Written as {{ }} rather than Blade's :aria-valuenow
                                             shorthand on purpose: that shorthand is a *component*
                                             feature and on a plain <div> Blade leaves the text
                                             ":aria-valuenow" in the output verbatim. The bar still
                                             looked correct, and a browser drops an unknown
                                             attribute without a word, so the only symptom was a
                                             screen reader being told the value was 0 of 0. --}}
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-navy-100"
                                            role="progressbar"
                                            aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                                            aria-label="{{ $progress['received'] }} of {{ $progress['total'] }} households collected">
                                            <div class="h-full rounded-full bg-gold-400 transition-all"
                                                style="width: {{ $percent }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold tabular-nums text-navy-700">
                                            {{ $progress['received'] }} / {{ $progress['total'] }}
                                        </span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- The three things a staff member does most often, in one place. --}}
        <section aria-labelledby="quick-actions" class="space-y-3">
            <h2 id="quick-actions" class="text-sm font-bold text-navy-900">Quick actions</h2>
            <div class="flex flex-wrap gap-3">
                <x-btn :href="route('staff.batches.create')" variant="primary" size="md" icon="gift">
                    Open a relief batch
                </x-btn>
                <x-btn :href="route('staff.announcements.create')" variant="secondary" size="md" icon="megaphone">
                    New announcement
                </x-btn>
                <x-btn :href="route('staff.residents.index')" variant="ghost" size="md" icon="users">
                    View residents
                </x-btn>
            </div>
        </section>

        {{--
            ADMIN ONLY.

Every route linked here sits behind role:admin, so this strip shows an
Admin four places they can already reach and it performs nothing. A
Secretary never sees it, which is what keeps the Admin-only pages from
leaking through a dashboard card.

All FOUR, deliberately. This strip started as three cards and a test
named the_admin_strip_links_to_the_five_admin_pages while asserting
three - so Sectors & Kagawad and Settings were reachable only by
typing a URL or via the sidebar, and the test passed while the gap was
there.

The fifth card was System health, linking to the System Status page. That
page has been removed: it read the APP_DEBUG flag out loud on a screen
the Captain does not need, and a page whose main job was to report on
itself was one more thing to keep current. The grid is four across on xl
and two from md, which fits four cards without a fifth wrapping.
--}}
        @if ($isAdmin)
            <section aria-labelledby="admin-strip"
                class="space-y-4 rounded-xl border border-navy-200 bg-navy-50/60 p-5">
                <div class="flex items-center gap-2">
                    <x-icon name="shield-check" class="h-5 w-5 text-navy-600" />
                    <h2 id="admin-strip" class="text-sm font-bold text-navy-900">For admins</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {{-- Audit tail. --}}
                    <a href="{{ route('staff.audit.index') }}"
                        class="rounded-lg border border-navy-100 bg-white p-4 transition hover:border-navy-200 hover:shadow-card">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-navy-400"></span>
                            <p class="text-xs font-semibold uppercase tracking-wide text-navy-500">Recent activity</p>
                        </div>
                        @if ($recentAudit->isEmpty())
                            <p class="mt-2 text-sm text-navy-500">Nothing logged yet.</p>
                        @else
                            <ul class="mt-2 space-y-1">
                                @foreach ($recentAudit as $entry)
                                    <li class="truncate text-xs text-navy-600" title="{{ $entry->description }}">
                                        {{ $entry->actionLabel() }}<span class="text-navy-400"> · {{ $entry->created_at?->diffForHumans() }}</span>
                                    </li>
                                @endforeach                            </ul>
                        @endif
                    </a>

                    {{-- Accounts by role. --}}
                    <a href="{{ route('staff.accounts.index') }}"
                        class="rounded-lg border border-navy-100 bg-white p-4 transition hover:border-navy-200 hover:shadow-card">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-sky-500"></span>
                            <p class="text-xs font-semibold uppercase tracking-wide text-navy-500">Accounts</p>
                        </div>
                        <ul class="mt-2 space-y-1">
                            @foreach ($roleCounts as $role => $total)
                                <li class="flex items-center justify-between text-xs">
                                    <span class="text-navy-600">{{ ucfirst($role) }}</span>
                                    <span class="font-semibold tabular-nums text-navy-800">{{ $total }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </a>

                    {{-- Sectors. A count of what needs acting on, not a figure that
                         changes minute to minute: a Kagawad with no sector sees an
                         empty dashboard and an empty relief list, and this is the
                         one place that says so. --}}
                    <a href="{{ route('staff.sectors.index') }}"
                        class="rounded-lg border border-navy-100 bg-white p-4 transition hover:border-navy-200 hover:shadow-card">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                            <p class="text-xs font-semibold uppercase tracking-wide text-navy-500">Sectors</p>
                        </div>
                        <p class="mt-2 text-sm font-bold text-navy-900">
                            {{ $sectorSummary['unassigned'] }} unassigned
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-navy-500">
                            of {{ $sectorSummary['total'] }} sectors,
                            {{ $sectorSummary['assigned'] }} with a Kagawad
                        </p>
                    </a>

                    {{-- Settings. Deliberately says nothing about the settings
                         themselves - a summary of them here would either leak a
                         value or go stale before anyone read it. --}}
                    <a href="{{ route('staff.settings.index') }}"
                        class="rounded-lg border border-navy-100 bg-white p-4 transition hover:border-navy-200 hover:shadow-card">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-slate-400"></span>
                            <p class="text-xs font-semibold uppercase tracking-wide text-navy-500">Settings</p>
                        </div>
                        <p class="mt-2 text-sm font-bold text-navy-900">Barangay details</p>
                        <p class="mt-1 text-xs leading-relaxed text-navy-500">
                            Name, address and contacts, as the public pages read them.
                        </p>
                    </a>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
