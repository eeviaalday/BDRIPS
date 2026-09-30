{{--
    The checklist, grouped by sector.

    The mark-received control is a plain form rather than a live toggle: goods are
    handed over in person, the act is deliberate, and a checklist that fires an
    update on a stray tap is worse than one that takes a click.
--}}
<x-app-layout :user="auth()->user()" :title="$batch->title">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kagawad', 'route' => 'kagawad.dashboard'],
            ['label' => 'Relief Distribution', 'route' => 'kagawad.relief'],
            ['label' => $batch->title],
        ]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $batch->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $batch->distribution_date?->format('j M Y') ?? 'No date set' }}
                </p>
            </div>
            <x-status-badge :status="$batch->isActive() ? 'active' : 'inactive'" />
        </header>

        @unless ($batch->isActive())
            <x-alert variant="warning" icon="lock">
                This batch is closed, so the checklist is a record and cannot be changed.
            </x-alert>
        @endunless

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert variant="warning" icon="x-circle">{{ session('error') }}</x-alert>
        @endif

        @if ($bySector->isEmpty())
            <x-card tone="soft" padding="p-8" class="text-center">
                <p class="text-sm leading-relaxed text-navy-700">
                    None of this batch's households are in your sectors, so there is nothing
                    for you to work through.
                </p>
            </x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="On your list" :value="$total" tone="navy" icon="users" variant="badged" />
                <x-stat-card label="Collected" :value="$received" tone="green" icon="check-circle" variant="badged" />
                <x-stat-card label="To go" :value="$total - $received" tone="gold" icon="clock" variant="badged" />
            </div>

            @if ($batch->items() !== [])
                <x-card tone="white" padding="p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Goods in this batch</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($batch->items() as $item)
                            <li class="rounded-full bg-navy-50 px-3 py-1 text-xs font-medium text-navy-800">{{ $item }}</li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @foreach ($bySector as $group)
                <x-card tone="white" padding="p-6">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-bold text-navy-900">
                            {{ $group['sector']?->sector_name ?? 'Sector '.$group['rows']->first()->sector_id }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ $group['received'] }} of {{ $group['total'] }} collected
                        </p>
                    </div>

                    <ul class="divide-y divide-navy-50">
                        @foreach ($group['rows'] as $row)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-navy-900">{{ $row->household_number }}</p>
                                    @if ($row->isReceived())
                                        <p class="text-xs text-emerald-700">
                                            Collected by {{ $row->received_by_name }}
                                            @if ($row->claimed_at)
                                                &middot; {{ $row->claimed_at->format('j M, g:i a') }}
                                            @endif
                                        </p>
                                    @else
                                        <p class="text-xs text-slate-500">Not collected yet</p>
                                    @endif
                                </div>

                                @if ($row->isReceived())
                                    <x-status-badge status="ready" size="sm" />
                                @elseif ($batch->isActive())
                                    <form method="POST" class="flex flex-wrap items-center gap-2"
                                        action="{{ route('kagawad.relief.received', [$batch, $row->id]) }}">
                                        @csrf
                                        <label class="sr-only" for="received-{{ $row->id }}">
                                            Who collected for {{ $row->household_number }}
                                        </label>
                                        <input id="received-{{ $row->id }}" name="received_by_name" type="text"
                                            maxlength="255" placeholder="Who collected (optional)"
                                            class="w-44 rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-xs focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                                        <x-btn type="submit" variant="navy" size="sm" icon="check">
                                            Mark collected
                                        </x-btn>
                                    </form>
                                @else
                                    <x-btn variant="ghost" size="sm" disabled>Closed</x-btn>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        @endif
    </div>
</x-app-layout>
