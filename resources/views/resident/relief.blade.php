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
                                        {{ $distribution->batch?->title ?? 'Relief assistance' }}
                                    </p>
                                    {{-- This is the resident's OWN household, so the name of
                                         whoever signed for the relief is their own business
                                         and is shown. The arbitrary-number lookup further
                                         down deliberately omits it, because that one can be
                                         pointed at any household in the barangay. --}}
                                    @if ($wasReceived && $distribution->received_by_name)
                                        <p class="mt-1 text-xs text-navy-500">
                                            Received by {{ $distribution->received_by_name }}
                                        </p>
                                    @endif
                                    <p class="mt-0.5 text-sm text-navy-600">
                                        @if ($distribution->sector?->sector_name)
                                            {{ $distribution->sector->sector_name }} &middot;
                                        @endif
                                        {{-- claimed_at is when this household
                                             actually collected; the batch date is
                                             only the scheduled day. The row has no
                                             distribution_date of its own. --}}
                                        @if ($wasReceived && $distribution->claimed_at)
                                            Received {{ $distribution->claimed_at->format('M d, Y') }}
                                        @elseif ($distribution->batch?->distribution_date)
                                            Scheduled {{ $distribution->batch->distribution_date->format('M d, Y') }}
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
                            <p class="font-semibold text-navy-900">{{ $batch->title }}</p>
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

        {{-- The household lookup. Posts back to THIS page rather than to the
             public transparency route, which used to throw the resident out of
             the portal and render a different page with its own form on it.
             The controller runs the same scope the public board does, so the
             answer is the same one either page gives. --}}
        <x-card tone="white" class="mt-6">
            <h2 class="text-base font-bold text-navy-900">Look up a household number</h2>
            <p class="mt-1 text-sm text-navy-600">
                Check the status recorded against any household number.
            </p>

            {{-- GET on purpose: the reference is in the URL so a result can be
                 bookmarked or sent to the barangay desk, and a search that
                 changes nothing on the server has no reason to be a POST.

                 x-data is additive, not load-bearing: with scripting off the
                 form submits exactly as before and :disabled never binds. The
                 spin is here because on a slow connection a GET that returns
                 the same page looks identical to a button that is broken. --}}
            <form method="GET" action="{{ route('resident.relief') }}"
                x-data="{ searching: false }"
                x-on:submit="searching = true"
                :aria-busy="searching ? 'true' : 'false'"
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="household_number" class="block text-sm font-semibold text-navy-900">Household number</label>
                    <input id="household_number" name="household_number" type="text" maxlength="50"
                        value="{{ old('household_number', $searchedNumber ?? '') }}"
                        autocomplete="off" spellcheck="false"
                        @error('household_number') aria-invalid="true" aria-describedby="household_number-error" @enderror
                        class="mt-1.5 block w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:ring-2 focus:outline-none
                               {{ $errors->has('household_number') ? 'border-red-400 focus:border-red-500 focus:ring-red-500/25' : 'border-navy-200 focus:border-sky-600 focus:ring-sky-600/25' }}">
                    <p class="mt-1.5 text-xs text-navy-500">Letters, numbers and hyphens, e.g. HH-0001.</p>
                    @error('household_number')
                        <p id="household_number-error" class="mt-1.5 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" :disabled="searching"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-progress disabled:opacity-70">
                    <span x-show="!searching">Search</span>
                    <span x-show="searching" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Searching
                    </span>
                    <span class="sr-only" x-show="searching" role="status">Looking up that household number.</span>
                </button>
            </form>

            {{-- aria-live so the result is announced when it appears, rather
                 than the page silently growing a list below the fold. --}}
            <div aria-live="polite">
                @if ($searchedNumber !== null)
                    @if ($result->isEmpty())
                        <x-alert variant="warning" icon="search" class="mt-4">
                            No relief record is filed against <span class="font-mono font-semibold">{{ $searchedNumber }}</span>.
                        </x-alert>
                    @else
                        <p class="mt-5 text-sm font-semibold text-navy-900">
                            {{ $result->count() }}
                            {{ \Illuminate\Support\Str::plural('record', $result->count()) }}
                            for <span class="font-mono">{{ $searchedNumber }}</span>
                        </p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($result as $row)
                                {{-- Deliberately batch, date and status only.
                                     The distribution also records the name of
                                     whoever collected it; a household lookup is
                                     not a place to read another resident's
                                     name, so it is not shown here. --}}
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-navy-100 px-4 py-2.5 text-sm">
                                    <span class="font-medium text-navy-900">{{ $row->batch?->title ?? 'Relief assistance' }}</span>
                                    <span class="text-navy-600">
                                        {{ ($row->batch?->distribution_date?->format('M d, Y') ?? 'Date to be announced') }}
                                        &middot;
                                        {{ $row->status === \App\Models\ReliefDistribution::STATUS_RECEIVED ? 'Received' : 'Not yet received' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>

            <x-alert variant="privacy" icon="lock" class="mt-5">
                This lookup returns the relief records filed against the exact number you enter.
                It does not show another household&rsquo;s name, address or personal details.
            </x-alert>
        </x-card>
    </x-shell>
</x-public-layout>
