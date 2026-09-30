{{--
    The Kagawad's landing page.

    Everything on it is scoped to the sectors this Kagawad is responsible for.
    An unassigned Kagawad gets the empty state rather than every sector, which is
    what a missing scope would produce.
--}}
<x-app-layout :user="auth()->user()" title="Dashboard">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <header>
            <h1 class="text-xl font-bold text-navy-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600">
                Signed in as Kagawad.
                @if ($sectorIds !== [])
                    Your {{ $sectors->count() === 1 ? 'sector' : 'sectors' }}:
                    {{ $sectors->pluck('sector_name')->implode(', ') }}.
                @endif
            </p>
        </header>

        @if ($sectorIds === [])
            <x-alert variant="warning" icon="lock">
                You have not been assigned a sector yet, so there is nothing to show you.
                The barangay office assigns sectors from Sectors &amp; Kagawad.
            </x-alert>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="Active batches" :value="$activeBatches" tone="navy" icon="gift" variant="badged" />
                <x-stat-card label="Still to collect" :value="$pendingCount" tone="gold" icon="clock" variant="badged" />
                <x-stat-card label="Collected" :value="$receivedCount" tone="green" icon="check-circle" variant="badged" />
            </div>

            <x-alert variant="info" icon="clock">
                The relief checklist is still being built. It will appear here under
                Relief Distribution.
            </x-alert>
        @endif
    </div>
</x-app-layout>
