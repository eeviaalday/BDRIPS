{{--
    The staff landing page.

    Reached by /dashboard after signing in, and by the sidebar's Dashboard link.
    It carries the queue counts a Secretary opens the portal to see, and nothing
    else: the rest of the staff area is built in later phases, and a page that
    pretended otherwise would offer links that do not work.
--}}
<x-app-layout :user="auth()->user()" title="Dashboard">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <header>
            <h1 class="text-xl font-bold text-navy-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600">
                Signed in as {{ ucfirst(auth()->user()->role) }}. These are the queues waiting on a decision.
            </p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Verifications pending" :value="$pendingVerifications" tone="gold" icon="id-badge" variant="badged" />
            <x-stat-card label="Requests to review" :value="$awaitingDecision" tone="sky" icon="document" variant="badged" />
            <x-stat-card label="Ready for pickup" :value="$readyForPickup" tone="navy" icon="archive" variant="badged" />
            <x-stat-card label="Active relief batches" :value="$activeBatches" tone="green" icon="gift" variant="badged" />
        </div>

        <x-alert variant="info" icon="clock">
            The rest of the staff area is still being built. The destinations in the sidebar
            appear here as each one is finished.
        </x-alert>
    </div>
</x-app-layout>
