<x-public-layout>
    <x-slot name="title">Relief Transparency</x-slot>

    <x-page-header :crumbs="['Relief Transparency']" title="Relief Transparency"
        description="Enter your household reference number to check your relief distribution status, or review the overall distribution statistics below." />
    <x-shell>
        @php
            $percent = $overallTotal > 0 ? round($overallReceived / $overallTotal * 100) : 0;
        @endphp

        {{-- ============================================================
             Statistics
             ============================================================ --}}
        <section aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">Relief distribution statistics</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card label="Households Served" :value="number_format($overallReceived)"
                    hint="Marked as received" icon="check-circle" tone="navy" />
                <x-stat-card label="Total Records" :value="number_format($overallTotal)"
                    hint="Across all relief batches" icon="users" tone="white" />
                <x-stat-card label="Completion" :value="$percent.'%'"
                    hint="Share of records received" icon="chart" tone="sky" />
                <x-stat-card label="Active Batches" :value="$activeBatches->count()"
                    hint="Currently open" icon="inbox" tone="gold" />
            </div>

            {{-- Progress bar (aria-described for screen readers) --}}
            <div class="mt-5 rounded-xl border border-navy-100 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="font-semibold text-navy-900">Overall distribution progress</span>
                    <span class="font-extrabold text-navy-900">{{ $percent }}%</span>
                </div>
                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-navy-100"
                    role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                    aria-label="Overall relief distribution progress">
                    <div class="h-full rounded-full bg-gradient-to-r from-sky-500 to-navy-700 transition-all duration-700"
                        style="width: {{ $percent }}%"></div>
                </div>
            </div>
        </section>

        {{-- ============================================================
             Household search
             ============================================================ --}}
        <section class="mt-12" aria-labelledby="search-heading">
            <x-section-heading eyebrow="Household Lookup" title="Check your household"
                description="Search returns only records that match the exact household reference number you enter." class="max-w-2xl" />

            <div class="mt-6 rounded-xl border border-navy-100 bg-white p-6 shadow-card sm:p-7">
                <form method="GET" action="{{ route('transparency.search') }}" class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <x-field id="household_number" name="household_number" label="Household reference number"
                            :value="$searchedNumber ?? ''" placeholder="e.g. HH-0001" mono icon="search" />
                    </div>
                    <div class="flex items-end">
                        <x-btn type="submit" variant="navy" size="lg" icon="search" class="w-full sm:w-auto">
                            Check Status
                        </x-btn>
                    </div>
                </form>

                <x-alert variant="privacy" icon="lock" class="mt-5">
                    This lookup is available to signed-in residents. Results show only the relief
                    entries tied to the exact reference you enter; no other household&rsquo;s
                    information is displayed.
                </x-alert>
            </div>

            {{-- Search results --}}
            @isset($searchedNumber)
                <div class="mt-6" aria-live="polite">
                    @if ($result->isEmpty())
                        <x-empty-state icon="search" title="No records found" class="sm:max-w-2xl">
                            No relief distribution records were found for
                            <span class="font-mono font-bold text-navy-900">{{ $searchedNumber }}</span>.
                            Please check the reference number and try again.
                        </x-empty-state>
                    @else
                        <div class="overflow-hidden rounded-xl border border-navy-100 bg-white shadow-card">
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-navy px-6 py-4">
                                <p class="text-sm font-bold text-white">
                                    Results for
                                    <span class="ml-1 font-mono text-gold">{{ $searchedNumber }}</span>
                                </p>
                                <p class="text-xs text-navy-200">{{ $result->count() }} {{ \Illuminate\Support\Str::plural('record', $result->count()) }}</p>
                            </div>

                            <ul class="divide-y divide-navy-100">
                                @foreach ($result as $row)
                                    <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-navy-900">{{ $row->batch->title }}</p>
                                            <p class="mt-1 text-sm text-navy-600">
                                                {{ $row->sector?->sector_name ?? 'Sector not set' }}
                                                @if ($row->batch->distribution_date)
                                                    &middot; {{ $row->batch->distribution_date->format('M d, Y') }}
                                                @endif
                                            </p>
                                            @if ($row->received_by_name)
                                                <p class="mt-1 text-sm text-navy-500">Received by: {{ $row->received_by_name }}</p>
                                            @endif
                                            @if ($row->claimed_at)
                                                <p class="mt-1 text-sm text-navy-500">Claimed: {{ $row->claimed_at->format('M d, Y g:i A') }}</p>
                                            @endif
                                        </div>

                                        <x-status-badge :status="$row->status" class="shrink-0 self-start" />
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endisset
        </section>

        {{-- ============================================================
             Active batches
             ============================================================ --}}
        @if ($activeBatches->isNotEmpty())
            <section class="mt-16" aria-labelledby="batches-heading">
                <h2 id="batches-heading" class="sr-only">Active Relief Batches</h2>

                <x-section-heading eyebrow="In progress" title="Active Relief Batches"
                    description="Relief batches that are currently open for distribution." />

                <div class="mt-8 grid gap-5 md:grid-cols-2">
                    @foreach ($activeBatches as $batch)
                        <x-card tone="white" padding="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-navy-900">{{ $batch->title }}</p>
                                <x-status-badge :status="$batch->status" size="sm" class="shrink-0" />
                            </div>

                            @if ($batch->distribution_date)
                                <p class="mt-1 text-sm text-navy-600">
                                    <x-icon name="calendar" class="mr-1 inline h-4 w-4" />
                                    {{ $batch->distribution_date->format('M d, Y') }}
                                </p>
                            @endif

                            @if ($batch->getItemsAttribute())
                                <ul class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($batch->getItemsAttribute() as $item)
                                        <li class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-800">{{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <p class="mt-4 border-t border-navy-100 pt-3 text-sm text-navy-600">
                                {{ $batch->distributions_count }} {{ \Illuminate\Support\Str::plural('household', $batch->distributions_count) }} mapped
                            </p>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    </x-shell>
</x-public-layout>
