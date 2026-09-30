<x-app-layout>
    <x-slot name="title">{{ $isAdmin ? 'Administration' : 'Staff Dashboard' }}</x-slot>

    <div class="space-y-5">
        <x-breadcrumb :items="[['label' => $isAdmin ? 'Administration' : 'Staff Dashboard']]" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">
                    {{ $isAdmin ? 'System overview' : 'Good day, '.\Illuminate\Support\Str::before(auth()->user()->name, ' ') }}
                </h1>
                <p class="mt-1 text-sm text-navy-600">
                    @if ($isAdmin)
                        Registration, queues, relief and configuration across the barangay.
                    @else
                        Here is what is waiting on a decision from you today.
                    @endif
                </p>
            </div>
        </div>

        {{-- ============================================================
             WHAT IS WAITING
             ============================================================
             Ordered by what this role clears. The Secretary's two queues come
             first; the admin's system-wide figures come first for the admin.
             These are counts of real rows, not a summary of summaries.
        --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($isAdmin)
                <x-stat-card variant="badged" tone="blue" icon="users" label="Registered Residents"
                    :value="$residentCount" :href="route('staff.settings.index')" />
                <x-stat-card variant="badged" tone="green" icon="shield" label="Verified Profiles"
                    :value="$verifiedCount" :href="route('staff.verifications.index')" />
                <x-stat-card variant="badged" tone="navy" icon="pin" label="Sectors"
                    :value="$sectorCount" :href="route('staff.sectors.index')"
                    :hint="$unassignedSectors > 0 ? $unassignedSectors.' without a kagawad' : 'All have a kagawad'" />
                <x-stat-card variant="badged" tone="orange" icon="gift" label="Relief Received"
                    :value="$reliefReceived" :href="route('staff.batches.index')" />
            @else
                <x-stat-card variant="badged" tone="orange" icon="id-badge" label="Awaiting Verification"
                    :value="$pendingVerifications" :href="route('staff.verifications.index')" />
                <x-stat-card variant="badged" tone="blue" icon="inbox" label="Requests to Review"
                    :value="$pendingRequests" :href="route('staff.requests.index')" />
                <x-stat-card variant="badged" tone="green" icon="check-circle" label="Ready for Pickup"
                    :value="$readyForPickup" :href="route('staff.requests.index', ['status' => 'ready_for_pickup'])" />
                <x-stat-card variant="badged" tone="navy" icon="clock" label="Relief Outstanding"
                    :value="$reliefPending" :href="route('staff.batches.index')" />
            @endif
        </div>

        {{-- ============================================================
             THE QUEUES
             ============================================================
             A summary count on a card is not a worklist: somebody still has to
             open each item. Both queues are listed here in full, most urgent
             first, and link to the page that can act on them.
        --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

            <x-card tone="white" padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 class="text-base font-bold text-navy-900">
                        {{ $isAdmin ? 'Verification queue' : 'Waiting on your decision' }}
                    </h2>
                    <a href="{{ route('staff.verifications.index') }}"
                        class="text-sm font-semibold text-navy-700 hover:text-navy-900">View all &rarr;</a>
                </div>

                @if ($verificationsList->isEmpty())
                    <div class="px-5 py-10">
                        <x-empty-state icon="check-circle" title="Verification queue is clear"
                            description="No resident profile is waiting to be checked against the master list." />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($verificationsList as $profile)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-navy-900">
                                        {{ $profile->user?->name ?? 'Unknown resident' }}
                                    </p>
                                    <p class="truncate text-xs text-navy-500">
                                        <span class="font-mono">{{ $profile->household_number }}</span>
                                        &middot; {{ $profile->sector?->sector_name ?? 'No sector' }}
                                    </p>
                                </div>
                                <a href="{{ route('staff.verifications.show', $profile) }}"
                                    class="shrink-0 text-sm font-semibold text-navy-700 hover:text-navy-900">Review</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card tone="white" padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 class="text-base font-bold text-navy-900">Document requests</h2>
                    <a href="{{ route('staff.requests.index') }}"
                        class="text-sm font-semibold text-navy-700 hover:text-navy-900">View all &rarr;</a>
                </div>

                @if ($pendingRequestsList->isEmpty())
                    <div class="px-5 py-10">
                        <x-empty-state icon="check-circle" title="No requests waiting"
                            description="Nothing is sitting in submitted or under review." />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($pendingRequestsList as $request)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-navy-900">
                                        {{ $request->user?->name ?? 'Unknown resident' }}
                                    </p>
                                    <p class="truncate text-xs text-navy-500">
                                        {{ $request->documentTypeLabel() }}
                                        &middot; <span class="font-mono">{{ $request->reference_number }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-status-badge :status="$request->status" size="sm" />
                                    <a href="{{ route('staff.requests.show', $request) }}"
                                        class="text-sm font-semibold text-navy-700 hover:text-navy-900">Open</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Ready for pickup, oldest first: these are the ones about to be
             recorded as abandoned by the nightly policy, so they are the most
             time-sensitive thing on the page after the queues. --}}
        @if ($readyList->isNotEmpty())
            <x-card tone="white" padding="p-0" class="border-amber-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50/60 px-5 py-4">
                    <div>
                        <h2 class="text-base font-bold text-navy-900">Waiting to be collected</h2>
                        <p class="mt-0.5 text-xs text-navy-600">
                            Oldest first. A document uncollected for 7 working days is recorded as
                            abandoned by the nightly policy.
                        </p>
                    </div>
                    <a href="{{ route('staff.requests.index', ['status' => 'ready_for_pickup']) }}"
                        class="text-sm font-semibold text-navy-700 hover:text-navy-900">View all &rarr;</a>
                </div>

                <ul class="divide-y divide-navy-50">
                    @foreach ($readyList as $request)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-navy-900">
                                    {{ $request->user?->name ?? 'Unknown resident' }}
                                </p>
                                <p class="truncate text-xs text-navy-500">
                                    Ready since
                                    <span class="font-medium text-navy-700">
                                        {{ $request->ready_at?->diffForHumans() ?? 'unknown' }}
                                    </span>
                                    &middot; <span class="font-mono">{{ $request->reference_number }}</span>
                                </p>
                            </div>
                            <a href="{{ route('staff.requests.show', $request) }}"
                                class="shrink-0 text-sm font-semibold text-navy-700 hover:text-navy-900">Process</a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        {{-- ============================================================
             RELIEF AND PUBLIC INFORMATION
             ============================================================
             The admin sees the configuration picture; both roles can open the
             batch list, which is the only place batches can be created.
        --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <x-card tone="white" padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 class="text-base font-bold text-navy-900">Relief batches</h2>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('staff.batches.index') }}"
                            class="font-semibold text-navy-700 hover:text-navy-900">View all &rarr;</a>
                        <a href="{{ route('staff.batches.create') }}"
                            class="font-semibold text-navy-700 hover:text-navy-900">New batch</a>
                    </div>
                </div>

                @if ($activeBatches->isEmpty())
                    <div class="px-5 py-8">
                        <x-empty-state icon="gift" title="No active batch"
                            description="No relief batch is currently running. Creating one maps it automatically to every verified household." />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($activeBatches as $batch)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-navy-900">{{ $batch->title }}</p>
                                    <p class="text-xs text-navy-500">
                                        {{ $batch->distribution_date?->format('M d, Y') ?? 'Date to be confirmed' }}
                                        &middot; {{ $batch->distributions_count }} households mapped
                                    </p>
                                </div>
                                <a href="{{ route('staff.batches.show', $batch) }}"
                                    class="shrink-0 text-sm font-semibold text-navy-700 hover:text-navy-900">Open</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card tone="white" padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
                    <h2 class="text-base font-bold text-navy-900">Recent announcements</h2>
                    <a href="{{ route('staff.announcements.index') }}"
                        class="text-sm font-semibold text-navy-700 hover:text-navy-900">Manage &rarr;</a>
                </div>

                @if ($latestAnnouncements->isEmpty())
                    <div class="px-5 py-8">
                        <x-empty-state icon="megaphone" title="Nothing published"
                            description="No announcement has been published. Drafts are not shown to the public until they carry a publication date." />
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach ($latestAnnouncements as $announcement)
                            <li class="px-5 py-3">
                                <p class="truncate text-sm font-medium text-navy-900">{{ $announcement->title }}</p>
                                <p class="text-xs text-navy-500">
                                    {{ $announcement->categoryLabel() }}
                                    @if ($announcement->isPinned())
                                        &middot; <span class="font-semibold text-gold-800">Pinned</span>
                                    @endif
                                    &middot; {{ $announcement->published_at->format('M d, Y') }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- The staff shortcuts. Every one of these is an existing route the
             role middleware already protects - nothing here is a link to
             something a Secretary cannot open. --}}
        <x-card tone="white">
            <h2 class="text-sm font-bold text-navy-900">Quick actions</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    ['route' => 'staff.requests.index', 'label' => 'Review requests', 'icon' => 'inbox'],
                    ['route' => 'staff.verifications.index', 'label' => 'Verify residents', 'icon' => 'id-badge'],
                    ['route' => 'staff.announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone'],
                    ['route' => 'staff.batches.index', 'label' => 'Relief batches', 'icon' => 'gift'],
                    ['route' => 'staff.sectors.index', 'label' => 'Sectors', 'icon' => 'pin'],
                    ['route' => 'staff.settings.index', 'label' => 'Settings', 'icon' => 'lock'],
                ] as $action)
                    <a href="{{ route($action['route']) }}"
                        class="flex flex-col items-center gap-2 rounded-xl border border-navy-100 bg-navy-50/50 px-3 py-4 text-center transition hover:border-navy-200 hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-navy-700 shadow-card">
                            <x-icon :name="$action['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-semibold leading-tight text-navy-800">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    </div>
</x-app-layout>
