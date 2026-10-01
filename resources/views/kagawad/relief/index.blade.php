{{--
    A Kagawad's batches, with their own share of each.

    A Kagawad sees every batch, because a closed one is a record of a handout
    they worked - but the counts on it are only their own sectors. Showing the
    barangay-wide count here would tell them the size of a neighbouring purok's
    list, which is the one number the sector split is there to keep private.

    TWO RENDERINGS OF THE SAME ROWS.

    Below md this is a table inside overflow-x-auto, which means a phone gets a
    46rem-wide table the reader has to scroll sideways to read - the opposite of
    usable for someone holding a clipboard in the street. So below md the same
    rows are drawn as stacked cards, one card per batch, each label sitting
    directly above its own value so nothing has to be matched back to a header.

    Both renderings are always in the DOM and one is display:none. They cannot
    disagree, because the markup for a value is written once per rendering and
    both read the same three attributes off the same $batch.
--}}
<x-app-layout :user="auth()->user()" title="Relief Distribution">
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kagawad', 'route' => 'kagawad.dashboard'],
            ['label' => 'Relief Distribution'],
        ]" />

        @if ($sectorIds === [])
            <header>
                <h1 class="text-xl font-bold text-navy-900">Relief Distribution</h1>
            </header>

            <x-staff.unassigned-sector />
        @else
            <header class="space-y-3">
                <h1 class="text-xl font-bold text-navy-900">Relief Distribution</h1>
                <p class="mt-1 text-sm text-gray-600">Your sectors:</p>

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

            {{-- The flash is rendered by layouts/app for every staff page, so this
                 view must not render it again or the Kagawad reads it twice. --}}

            @if ($batches->isEmpty())
                <x-staff.empty-state icon="gift" title="No batches yet"
                    description="When the Secretary opens a batch that covers your sector, it appears here." />
            @else
                {{-- Phone: one card per batch. --}}
                <ul class="space-y-3 md:hidden">
                    @foreach ($batches as $batch)
                        @php
                            $total = (int) $batch->households_total;
                            $received = (int) $batch->households_received;
                            $remaining = max(0, $total - $received);
                            $percent = $total > 0 ? (int) round($received / $total * 100) : 0;
                        @endphp
                        <li>
                            <a href="{{ route('kagawad.relief.show', $batch) }}"
                                class="block rounded-xl border border-navy-100 bg-white p-4 shadow-card transition duration-200 active:scale-[0.99]">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-bold text-navy-900">{{ $batch->title }}</p>
                                    <x-staff.status-badge :status="$batch->isActive() ? 'active' : 'closed'"
                                        size="sm" />
                                </div>
                                <p class="mt-1 text-xs text-navy-500">
                                    {{ $batch->distribution_date?->format('j M Y') ?? 'No date set' }}
                                </p>

                                <div class="mt-3 flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-navy-100"
                                        role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="{{ $received }} of {{ $total }} households collected">
                                        <div class="h-full rounded-full bg-gold-400" style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums text-navy-700">
                                        {{ $received }} / {{ $total }}
                                    </span>
                                </div>

                                <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-lg bg-navy-50/60 py-2">
                                        <dt class="text-2xs font-semibold uppercase tracking-wide text-navy-500">
                                            Households
                                        </dt>
                                        <dd class="mt-0.5 text-sm font-bold tabular-nums text-navy-900">
                                            {{ $total }}
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-emerald-50 py-2">
                                        <dt class="text-2xs font-semibold uppercase tracking-wide text-emerald-700">
                                            Collected
                                        </dt>
                                        <dd class="mt-0.5 text-sm font-bold tabular-nums text-emerald-800">
                                            {{ $received }}
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-gold-50 py-2">
                                        <dt class="text-2xs font-semibold uppercase tracking-wide text-gold-800">
                                            Remaining
                                        </dt>
                                        <dd class="mt-0.5 text-sm font-bold tabular-nums text-gold-900">
                                            {{ $remaining }}
                                        </dd>
                                    </div>
                                </dl>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Tablet and up: the real table. --}}
                <x-staff.table class="hidden md:block" :columns="[
                        ['label' => 'Batch'],
                        ['label' => 'Date'],
                        ['label' => 'Households', 'align' => 'right'],
                        ['label' => 'Collected', 'align' => 'right'],
                        ['label' => 'Remaining', 'align' => 'right'],
                        ['label' => 'Progress'],
                        ['label' => 'Status'],
                    ]"
                    caption="Relief batches" empty-title="No batches yet"
                    empty-description="When the Secretary opens a batch that covers your sector, it appears here."
                    empty-icon="gift">
                    @foreach ($batches as $batch)
                        @php
                            $total = (int) $batch->households_total;
                            $received = (int) $batch->households_received;
                            $remaining = max(0, $total - $received);
                            $percent = $total > 0 ? (int) round($received / $total * 100) : 0;
                        @endphp
                        <tr x-on:click="openRow($event, @js(route('kagawad.relief.show', $batch)))">
                            <th scope="row">
                                <x-staff.person-cell :name="$batch->title"
                                    :href="route('kagawad.relief.show', $batch)" chevron />
                            </th>
                            <td class="text-sm text-slate-600">
                                {{ $batch->distribution_date?->format('j M Y') ?? '—' }}
                            </td>
                            <td class="text-right tabular-nums text-slate-600">{{ $total }}</td>
                            <td class="text-right tabular-nums text-slate-600">{{ $received }}</td>
                            <td class="text-right tabular-nums font-semibold text-navy-800">{{ $remaining }}</td>
                            <td class="min-w-[9rem]">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-full max-w-[7rem] overflow-hidden rounded-full bg-navy-100"
                                        role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="{{ $received }} of {{ $total }} households collected">
                                        <div class="h-full rounded-full bg-gold-400" style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-2xs font-semibold tabular-nums text-navy-600">
                                        {{ $percent }}%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <x-staff.status-badge :status="$batch->isActive() ? 'active' : 'closed'" size="sm" />
                            </td>
                        </tr>
                    @endforeach
                </x-staff.table>
            @endif

            <x-staff.pagination :paginator="$batches" />
        @endif
    </div>
</x-app-layout>
