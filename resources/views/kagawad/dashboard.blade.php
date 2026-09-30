<x-app-layout>
    <x-slot name="title">Sector Dashboard</x-slot>

    <div class="space-y-5">
        <x-breadcrumb :items="[['label' => 'My Sector']]" />

        @if ($sectors->isEmpty())
            {{-- A kagawad with no sector is a real state, not an error: the
                 assignment is made by staff from the admin area. --}}
            <x-card tone="white" class="text-center">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <x-icon name="pin" class="h-7 w-7" />
                </span>
                <h2 class="mt-4 text-lg font-bold text-navy-900">No sector assigned to you yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-navy-700">
                    Relief checklists are scoped to a purok, and a purok has to be assigned to
                    a kagawad before there is anything to show. Please ask the Barangay
                    Secretary to assign you.
                </p>
            </x-card>
        @else
            {{-- ============================================================
                 ASSIGNED SECTORS
                 ============================================================
                 residents_count is loaded by withCount('residents') in the
                 controller. Without it the attribute does not exist on the
                 model and this figure silently rendered an em dash.
            --}}
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($sectors as $sector)
                    <x-card tone="white" class="bg-navy-50/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Your purok</p>
                                <p class="mt-1 text-lg font-bold text-navy-900">{{ $sector->sector_name }}</p>
                                <p class="mt-0.5 text-sm text-navy-600">
                                    {{ $sector->purok_name ?? 'No sub-purok recorded' }}
                                </p>
                            </div>
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy text-white">
                                <x-icon name="users" class="h-5 w-5" />
                            </span>
                        </div>
                        <p class="mt-4 text-sm text-navy-700">
                            <span class="text-2xl font-extrabold tabular-nums text-navy-900">{{ $sector->residents_count }}</span>
                            {{ str('resident')->plural($sector->residents_count) }} registered
                        </p>
                    </x-card>
                @endforeach
            </div>

            {{-- ============================================================
                 ACTIVE BATCHES
                 ============================================================
                 Previously this was a foreach over batches nested inside a
                 foreach over sectors, which rendered the SAME batch card once
                 per sector, each showing the combined counts for all of them -
                 so a kagawad covering two sectors saw every batch twice with
                 identical, misleading totals. The batch is now the outer loop
                 and the sector is named inside the card, because the counts
                 belong to the batch across this kagawad's sectors.
            --}}
            <section>
                <h2 class="text-base font-bold text-navy-900">Active relief batches</h2>
                <p class="mt-1 text-sm text-navy-600">
                    Only batches currently marked active are listed. Figures cover your purok.
                </p>

                @if ($batches->isEmpty())
                    <x-card tone="white" class="mt-4">
                        <x-empty-state icon="gift" title="No active relief batch"
                            description="Nothing is being distributed in your purok at the moment. When staff create a batch that includes your sector it will appear here with its household checklist." />
                    </x-card>
                @else
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ($batches as $batch)
                            @php
                                $total = $batch->received_count + $batch->pending_count;
                                $percent = $total > 0 ? round($batch->received_count / $total * 100) : 0;
                            @endphp

                            <x-card tone="white">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-navy-900">{{ $batch->title }}</h3>
                                        <p class="mt-0.5 text-sm text-navy-600">
                                            {{ $batch->distribution_date?->format('M d, Y') ?? 'Date to be confirmed' }}
                                            @if ($batch->getItemsAttribute())
                                                &middot; {{ implode(', ', array_slice($batch->getItemsAttribute(), 0, 3)) }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-status-badge :status="$batch->status" size="sm" />
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-semibold text-navy-800">Collection progress</span>
                                        <span class="tabular-nums text-navy-700">
                                            {{ $batch->received_count }} of {{ $total }}
                                            <span class="text-navy-500">({{ $percent }}%)</span>
                                        </span>
                                    </div>
                                    {{-- role="progressbar" with the value attributes, not a
                                         styled div: this is a quantity, and a screen
                                         reader should be able to read it as one. --}}
                                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-navy-100"
                                        role="progressbar"
                                        aria-valuenow="{{ $percent }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="{{ $batch->title }} collection progress">
                                        <div class="h-full rounded-full bg-navy transition-all duration-700" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                                    <span class="rounded-lg bg-emerald-50 px-3 py-1.5 font-semibold text-emerald-800">
                                        {{ $batch->received_count }} received
                                    </span>
                                    <span class="rounded-lg bg-amber-50 px-3 py-1.5 font-semibold text-amber-800">
                                        {{ $batch->pending_count }} pending
                                    </span>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    @foreach ($sectors as $sector)
                                        <a href="{{ route('kagawad.checklist', [$batch, $sector]) }}"
                                            class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                                            Open checklist
                                            <span class="ml-1.5 text-navy-300">{{ $sector->sector_name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
