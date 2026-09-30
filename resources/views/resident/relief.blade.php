<x-public-layout>
    <x-slot name="title">Relief Transparency</x-slot>

    <x-page-header :crumbs="['Relief Transparency']" title="Relief Transparency"
        description="What your household has received from the barangay's relief programmes, and when." />

    <x-shell>
        @php
            /*
             * Scoped to the household, not the account. Relief is issued per
             * household, so every adult in one household sees the same row - and
             * that is why the household number rather than the user id is the
             * join key.
             */
            $isVerified = ($profile ?? null)?->isVerified() ?? false;
            $receivedCount = $distributions->where('status', \App\Models\ReliefDistribution::STATUS_RECEIVED)->count();
        @endphp

        @if ($isVerified)
            {{-- ============================================================
                 Verified: the household's own record.
                 ============================================================ --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card variant="badged" tone="navy" icon="users" label="Household Number"
                    :value="$profile->household_number" hint="Relief is recorded per household." />
                <x-stat-card variant="badged" tone="green" icon="check-circle" label="Assistance Received"
                    :value="$receivedCount" hint="Programmes your household has collected." />
                <x-stat-card variant="badged" tone="blue" icon="gift" label="Active Programmes"
                    :value="$activeBatches->count()" hint="Running barangay-wide right now." />
            </div>

            <x-card tone="white" class="mt-6">
                <h2 class="text-base font-bold text-navy-900">Your household's assistance</h2>
                <p class="mt-1 text-sm leading-relaxed text-navy-600">
                    Every programme your household appears in, with whether it was received and on what date.
                </p>

                @if ($distributions->isEmpty())
                    <x-empty-state icon="gift" class="mt-4" title="No assistance recorded yet"
                        description="When a relief programme includes your household, it will be listed here with its status and date." />
                @else
                    <ul class="mt-5 space-y-3">
                        @foreach ($distributions as $distribution)
                            @php($wasReceived = $distribution->status === \App\Models\ReliefDistribution::STATUS_RECEIVED)
                            <li class="flex flex-col gap-3 rounded-xl border border-navy-100 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-semibold text-navy-900">
                                        {{ $distribution->batch?->name ?? 'Relief assistance' }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-navy-600">
                                        @if ($distribution->sector?->sector_name)
                                            {{ $distribution->sector->sector_name }} &middot;
                                        @endif
                                        @if ($wasReceived && $distribution->distribution_date)
                                            Received {{ $distribution->distribution_date->format('M d, Y') }}
                                        @elseif ($distribution->distribution_date)
                                            Scheduled {{ $distribution->distribution_date->format('M d, Y') }}
                                        @else
                                            Date to be announced
                                        @endif
                                    </p>
                                </div>

                                <span @class([
                                    'inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-wide',
                                    'border-green-200 bg-green-50 text-green-800' => $wasReceived,
                                    'border-navy-200 bg-navy-50 text-navy-700' => ! $wasReceived,
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 shrink-0 rounded-full',
                                        'bg-green-600' => $wasReceived,
                                        'bg-navy-400' => ! $wasReceived,
                                    ]) aria-hidden="true"></span>
                                    {{ $wasReceived ? 'Received' : 'Not yet received' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="mt-5 text-sm leading-relaxed text-navy-600">
                    Relief items are collected at the Barangay Hall. This page records what the barangay has
                    issued; it does not deliver anything.
                </p>
            </x-card>
        @else
            {{-- ============================================================
                 Pending: locked. The panel says what is locked and why, and
                 points at the one page that unlocks it, rather than leaving a
                 resident to wonder why their record is missing.
                 ============================================================ --}}
            <x-card tone="white">
                <x-empty-state icon="lock" title="Relief records open after verification"
                    description="Relief is recorded per verified household, so your record appears here once the Barangay Secretary has matched your details to the master list. Until then you can still read the barangay-wide totals below.">
                    <a href="{{ route('resident.verification.status') }}"
                        class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                        <x-icon name="shield" class="h-4 w-4" />
                        Verification Status
                    </a>
                </x-empty-state>
            </x-card>
        @endif

        {{-- ============================================================
             The barangay-wide view, which is the same data the transparency
             board publishes and is not gated on the resident's own status.
             ============================================================ --}}
        @if ($activeBatches->isEmpty())
            <x-card tone="white" class="mt-6">
                <h2 class="text-base font-bold text-navy-900">Programmes running now</h2>
                <p class="mt-1 text-sm text-navy-600">No programme is active at the moment.</p>
            </x-card>
        @else
            <x-card tone="white" class="mt-6">
                <h2 class="text-base font-bold text-navy-900">Programmes running now</h2>
                <p class="mt-1 text-sm text-navy-600">What the barangay is distributing at the moment, barangay-wide.</p>

                <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($activeBatches as $batch)
                        <li class="rounded-xl border border-navy-100 bg-navy-50/40 px-4 py-3">
                            <p class="font-semibold text-navy-900">{{ $batch->name }}</p>
                            <p class="mt-0.5 text-sm text-navy-600">
                                {{ $batch->distributions_count }}
                                {{ \Illuminate\Support\Str::plural('household', $batch->distributions_count) }} served
                            </p>
                            @if ($batch->distribution_date)
                                <p class="mt-0.5 text-xs text-navy-500">
                                    Distribution {{ $batch->distribution_date->format('M d, Y') }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        {{-- The household lookup that already existed. Left exactly as it was:
             auth-only, and it answers with the rows for the number typed in. It
             is not widened here to "your household" and not narrowed either. --}}
        <x-card tone="white" class="mt-6">
            <h2 class="text-base font-bold text-navy-900">Look up a household number</h2>
            <p class="mt-1 text-sm text-navy-600">
                Check the status recorded against any household number.
            </p>

            <form method="GET" action="{{ route('transparency.search') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="household_number" class="block text-sm font-semibold text-navy-900">Household number</label>
                    <input id="household_number" name="household_number" type="text" maxlength="50"
                        value="{{ $searchedNumber ?? '' }}"
                        class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                    Search
                </button>
            </form>

            @if (($searchedNumber ?? null) !== null)
                @if ($result->isEmpty())
                    <x-alert variant="warning" icon="search" class="mt-4">
                        No relief record is filed against <span class="font-mono font-semibold">{{ $searchedNumber }}</span>.
                    </x-alert>
                @else
                    <ul class="mt-4 space-y-2">
                        @foreach ($result as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-navy-100 px-4 py-2.5 text-sm">
                                <span class="font-medium text-navy-900">{{ $row->batch?->name ?? 'Relief assistance' }}</span>
                                <span class="text-navy-600">
                                    {{ ($row->distribution_date?->format('M d, Y') ?? 'Date to be announced') }}
                                    &middot;
                                    {{ $row->status === \App\Models\ReliefDistribution::STATUS_RECEIVED ? 'Received' : 'Not yet received' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </x-card>
    </x-shell>
</x-public-layout>
