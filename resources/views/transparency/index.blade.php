{{--
    The public relief transparency board.

    Aggregates only. No household number appears anywhere on this page, and that
    is not an oversight - a batch is a roll call, and publishing it would turn a
    transparency page into a directory of who is poor enough to need help. The
    search below is the only way in, and it answers about one household only.
--}}
<x-public-layout :title="'Relief Transparency'">
    <div class="mx-auto w-full max-w-5xl space-y-8">
        <header class="text-center">
            <h1 class="text-2xl font-bold text-navy-900">Relief Transparency</h1>
            <p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-navy-700">
                Every relief batch {{ $barangayName }} hands out is listed here, with how many
                households it covers and how many have collected. Household numbers are not
                published - you can check your own below.
            </p>
        </header>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Batches run" :value="$totals['batches']" tone="navy" icon="gift" variant="badged" />
            <x-stat-card label="Household entries" :value="$totals['households']" tone="gold" icon="users" variant="badged" />
            <x-stat-card label="Collected" :value="$totals['received']" tone="green" icon="check-circle" variant="badged" />
        </div>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-1 text-base font-bold text-navy-900">Is my household on a list?</h2>
            <p class="mb-4 text-sm text-slate-600">
                Asking about your own household needs you to sign in. There is no household-number
                search on a public page, because household numbers are short enough to guess and a
                public answer would confirm which ones are real.
            </p>

            @auth
                @if (auth()->user()->isResident())
                    <x-btn :href="route('resident.relief')" variant="navy" size="md" icon="gift">
                        See my household's relief record
                    </x-btn>
                @else
                    <p class="text-sm text-slate-600">
                        Relief records are shown to residents. You are signed in as
                        {{ \App\Models\User::roleLabel(auth()->user()->role) }}.
                    </p>
                @endif
            @else
                <x-btn :href="route('login')" variant="navy" size="md" icon="users">Sign in to check</x-btn>
            @endauth
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Batches</h2>

            <x-data-table :columns="[['label' => 'Batch'], ['label' => 'Date'], ['label' => 'Households'], ['label' => 'Collected'], ['label' => 'State']]"
                caption="Relief batches" empty-title="No batches yet"
                empty-description="Nothing has been handed out through this system so far.">
                @foreach ($batches as $batch)
                    <tr>
                        <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
                            {{ $batch->title }}
                            @if ($batch->items() !== [])
                                <span class="mt-1 block text-xs font-normal text-slate-500">
                                    {{ implode(', ', $batch->items()) }}
                                </span>
                            @endif
                        </th>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ $batch->distribution_date?->format('j M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $batch->households_total }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $batch->households_received }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$batch->isActive() ? 'active' : 'closed'" size="sm" />
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-card>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Sectors</h2>
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sectors as $sector)
                    <li class="flex items-baseline justify-between rounded-lg bg-navy-50/60 px-3.5 py-2.5 text-sm">
                        <span class="font-medium text-navy-900">{{ $sector->sector_name }}</span>
                        <span class="tabular-nums text-slate-600">
                            {{ $sector->residents_count }} household{{ $sector->residents_count === 1 ? '' : 's' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-public-layout>
