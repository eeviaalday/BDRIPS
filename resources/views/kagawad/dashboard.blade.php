{{--
    The Kagawad's landing page.

    Everything on it is scoped to the sectors this Kagawad is responsible for.
    An unassigned Kagawad gets the empty state rather than every sector, which is
    what a missing scope would produce.

    Built for a phone, in a field, one-handed. It is opened outdoors, mid-
    distribution, on a connection that may be one bar - so the figures are few,
    the tap targets are large, and there is nothing on the page that has to be
    read to know what to do next.
--}}
<x-app-layout :user="auth()->user()" title="Dashboard">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Kagawad', 'route' => 'kagawad.dashboard'], ['label' => 'Dashboard']]" />

        @if ($sectorIds === [])
            {{--
                No sector: the header still greets, because this is a person
                signing in and not an error page. Everything below it is the one
                empty state, with no links out - every destination in the staff
                area is behind a role check this account does not pass.
            --}}
            <header>
                <h1 class="text-xl font-bold text-navy-900">
                    Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}
                </h1>
                <p class="mt-1 text-sm text-gray-600">Your relief checklist will appear here.</p>
            </header>

            <x-staff.unassigned-sector />
        @else
            <header class="space-y-3">
                <h1 class="text-xl font-bold text-navy-900">
                    Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}
                </h1>
                <p class="mt-1 text-sm text-gray-600">Here is what is waiting on you.</p>

                {{-- The sectors, as chips. Naming them on the landing page is the
                     difference between a Kagawad knowing why they see a list and
                     suspecting the list is wrong. --}}
                <ul class="flex flex-wrap gap-2" aria-label="Your sectors">
                    @foreach ($sectors as $sector)
                        <li>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-navy-200 bg-navy-50 px-3 py-1 text-xs font-semibold text-navy-800">
                                <x-icon name="pin" class="h-3.5 w-3.5 text-navy-500" />
                                {{ $sector->sector_name }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </header>

            <x-staff.stat-row>
                <x-staff.stat-card label="Active batches" :value="$activeBatches" tone="navy" icon="gift"
                    :href="route('kagawad.relief')" />
                <x-staff.stat-card label="Still to collect" :value="$pendingCount" tone="gold" icon="clock"
                    :href="route('kagawad.relief')" />
                <x-staff.stat-card label="Collected" :value="$receivedCount" tone="green" icon="check-circle"
                    :href="route('kagawad.relief')" />
            </x-staff.stat-row>

            {{--
                The one way to the resident list. A whole card rather than a link in
                the prose above, because "N residents in your sector, M still to
                receive" is a figure a Kagawad acts on and the sentence is what tells
                them where to act on it.

                Both figures come from the same scoped query the list page runs, so
                this card can never promise a number the list then fails to show.
                Both are also gated on the card being reachable at all: an
                unassigned Kagawad takes the branch above, and $sectorResidentCounts
                would be two zeroes.
            --}}
            <a href="{{ route('kagawad.residents.index') }}"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-navy-100 bg-navy-50/60 p-5 shadow-card transition duration-200 hover:border-navy-200 hover:shadow-md">
                <div class="flex min-w-0 items-center gap-4">
                    <span aria-hidden="true"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-navy-700 shadow-card">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-navy-900">My Sector Residents</p>
                        <p class="mt-0.5 text-sm text-navy-600">
                            {{ $sectorResidentCounts['residents'] }} {{ \Illuminate\Support\Str::plural('resident', $sectorResidentCounts['residents']) }}
                            in your sector,
                            {{ $sectorResidentCounts['outstanding'] }} still to receive.
                        </p>
                    </div>
                </div>
                <span class="shrink-0 text-navy-400">
                    <x-icon name="arrow-right" class="h-5 w-5" />
                </span>
            </a>

            <section aria-labelledby="active-distributions" class="space-y-4">
                <h2 id="active-distributions" class="text-base font-bold text-navy-900">
                    Active distributions
                </h2>

                @if ($activeDistributions->isEmpty())
                    {{-- Assigned, but nothing to hand out. Different from the
                         unassigned state above and worded differently on purpose. --}}
                    <x-staff.empty-state icon="gift" title="No active distribution"
                        description="When the Secretary opens a batch that covers your sector, it appears here with a checklist to work through." />
                @else
                    <ul class="space-y-4">
                        @foreach ($activeDistributions as $batch)
                            @php
                                // Both counts are subselects on one query, already
                                // scoped to this Kagawad's sectors. Nothing here is
                                // barangay-wide: a total would tell a Kagawad the size
                                // of a neighbouring purok's list.
                                $total = (int) $batch->kagawad_total;
                                $received = (int) $batch->kagawad_received;
                                $remaining = max(0, $total - $received);
                                $percent = $total > 0 ? (int) round($received / $total * 100) : 0;
                            @endphp
                            <li
                                class="rounded-xl border border-navy-100 bg-white p-5 shadow-card transition duration-200">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <h3 class="text-base font-bold text-navy-900">{{ $batch->title }}</h3>
                                    <p class="text-xs text-navy-500">
                                        {{ $batch->distribution_date?->format('j M Y') ?? 'No date set' }}
                                    </p>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-navy-100"
                                        role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="{{ $received }} of {{ $total }} households collected">
                                        <div class="h-full rounded-full bg-gold-400 transition-all"
                                            style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums text-navy-700">
                                        {{ $received }} / {{ $total }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs font-medium {{ $remaining > 0 ? 'text-gold-800' : 'text-emerald-700' }}">
                                        @if ($remaining > 0)
                                            {{ $remaining }} still to collect
                                        @else
                                            All collected
                                        @endif
                                    </p>

                                    {{-- lg, not md: on a phone this is the primary
                                         control on the card and has to clear 44px. --}}
                                    <x-btn :href="route('kagawad.relief.show', $batch)" variant="navy" size="lg"
                                        icon="clipboard" class="w-full sm:w-auto">
                                        Open checklist
                                    </x-btn>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
