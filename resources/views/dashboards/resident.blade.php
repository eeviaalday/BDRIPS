<x-public-layout>
    <x-slot name="title">Resident Dashboard</x-slot>

    @if ($profile === null)
        {{-- No profile yet, so there is nothing to show a household view about.
             This is a real state: an account can exist before a profile does. --}}
        <x-card tone="white" class="text-center">
            <x-icon name="id-badge" class="mx-auto h-10 w-10 text-navy-300" />
            <h2 class="mt-4 text-lg font-bold text-navy-900">Your resident profile is not set up yet</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-navy-600">
                A resident profile is what the barangay verifies and what document requests are
                checked against. Set yours up to unlock certificates and clearances.
            </p>
            <a href="{{ route('resident.profile') }}"
                class="mt-5 inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                View my profile
            </a>
        </x-card>
    @elseif (! $profile->isVerified())
        {{-- Unverified residents see a banner, not a dead dashboard. The stat
             cards still count their own real figures rather than being zeroed
             or hidden, so the page does not look broken before approval. --}}
        <div class="mb-6 flex flex-col gap-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <x-icon name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div>
                    <p class="font-semibold text-amber-900">Verification {{ $profile->verification_status === 'rejected' ? 'was not approved' : 'is pending' }}</p>
                    <p class="mt-0.5 text-sm text-amber-800">
                        @if ($profile->verification_status === 'rejected')
                            {{ $profile->rejection_reason ?: 'The barangay could not verify your submitted ID. Please check it and submit again.' }}
                        @else
                            The Barangay Secretary is checking your submitted ID. You will be able to
                            request documents once it is approved.
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('resident.verification.status') }}"
                class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700">
                View status
            </a>
        </div>
    @endif

    {{-- ============================================================
         STAT CARDS
         ============================================================
         Four figures the resident can act on. Counts are real queries from
         ResidentDashboardController, not placeholders: "active" excludes
         completed, rejected and abandoned requests so the number is not
         propped up by finished work.
    --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card variant="badged" tone="blue" icon="document" label="My Requests"
            :value="$activeCount" class="cursor-pointer" :href="route('resident.requests.index')" />

        <x-stat-card variant="badged" tone="green" icon="check-circle" label="Documents Ready"
            :value="$readyCount" class="cursor-pointer" :href="route('resident.requests.index')" />

        <x-stat-card variant="badged" tone="purple" icon="megaphone" label="Announcements"
            :value="$announcementCount" class="cursor-pointer" :href="route('resident.directory')" />

        <x-stat-card variant="badged" tone="orange" icon="gift" label="Relief Assistance"
            :value="$reliefCount" class="cursor-pointer" :href="route('resident.relief')" />
    </div>

    {{-- ============================================================
         RECENT REQUESTS  +  HERO
         ============================================================
         The table is the working half of this page: what did I ask for and
         where has it got to. The hero is the one piece of atmosphere, kept to
         a single card so it cannot compete with the table.
    --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card tone="white" padding="p-0" class="xl:col-span-2">
            <div class="flex items-center justify-between gap-4 border-b border-navy-100 px-5 py-4">
                <h2 class="text-base font-bold text-navy-900">Recent Requests</h2>
                <a href="{{ route('resident.requests.index') }}"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-navy-700 hover:text-navy-900">
                    View All
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            @if ($requests->isEmpty())
                <div class="px-5 py-10">
                    <x-empty-state icon="inbox" title="No document requests yet"
                        description="When you request a certificate or clearance it will appear here, with its current status.">
                        <a href="{{ route('resident.requests.create') }}"
                            class="mt-5 inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                            Request a document
                        </a>
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Your five most recent document requests</caption>
                        <thead>
                            <tr class="border-b border-navy-100 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">
                                <th scope="col" class="px-5 py-3">Document Type</th>
                                <th scope="col" class="px-5 py-3">Reference No.</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3">Date</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-navy-50">
                            @foreach ($requests as $request)
                                <tr class="hover:bg-navy-50/40">
                                    <td class="px-5 py-3 font-medium text-navy-900">{{ $request->documentTypeLabel() }}</td>
                                    <td class="px-5 py-3 font-mono text-xs text-navy-600">{{ $request->reference_number }}</td>
                                    <td class="px-5 py-3"><x-status-badge :status="$request->status" size="sm" /></td>
                                    <td class="px-5 py-3 whitespace-nowrap text-navy-600">
                                        {{ $request->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('resident.requests.show', $request) }}"
                                            class="font-semibold text-navy-700 hover:text-navy-900">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card tone="white" padding="p-0" class="relative overflow-hidden">
            @php
                $heroImage = config('bdrpis.images.hero');
                $heroFallback = config('bdrpis.image_fallbacks.hero');
                $heroSrc = file_exists(public_path($heroImage)) ? asset($heroImage) : asset($heroFallback);
            @endphp
            <img src="{{ $heroSrc }}" alt="A Barangay Linasin community scene"
                class="h-64 w-full object-cover xl:h-full" />
            <div class="absolute inset-0 bg-gradient-to-t from-navy-900/85 via-navy-900/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gold">Barangay Linasin</p>
                <h2 class="mt-2 text-xl font-extrabold leading-tight text-white">
                    A Stronger Barangay Builds a Brighter Future
                </h2>
                <p class="mt-2 text-sm text-white/85">
                    {{ config('bdrpis.demographics.location') }} &middot;
                    {{ config('bdrpis.demographics.stats.0.value') }} residents
                </p>
            </div>
        </x-card>
    </div>

    {{-- ============================================================
         QUICK LINKS  +  UPCOMING EVENTS
         ============================================================
    --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card tone="white" class="xl:col-span-1">
            <h2 class="text-base font-bold text-navy-900">Quick Links</h2>

            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ([
                    ['route' => 'resident.directory', 'label' => 'Barangay Officials', 'icon' => 'users'],
                    ['route' => 'resident.directory', 'label' => 'Contact Information', 'icon' => 'phone'],
                    ['route' => 'resident.profile', 'label' => 'Barangay Profile', 'icon' => 'home'],
                    ['route' => 'resident.directory', 'label' => 'FAQs', 'icon' => 'search'],
                ] as $link)
                    <a href="{{ route($link['route']) }}"
                        class="flex flex-col items-center gap-2 rounded-xl border border-navy-100 bg-navy-50/50 px-3 py-4 text-center transition hover:border-navy-200 hover:bg-navy-50">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-navy-700 shadow-card">
                            <x-icon :name="$link['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-semibold leading-tight text-navy-800">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>

        <x-card tone="white" class="xl:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-base font-bold text-navy-900">Upcoming Events</h2>
                {{-- There is no events table in this system, so there is no
                     calendar page to link to. The heading is honest about it
                     rather than pointing somewhere that does not exist. --}}
                <span class="text-sm font-medium text-navy-500">View Calendar</span>
            </div>

            @if ($upcomingEvents->isEmpty())
                <p class="mt-4 text-sm text-navy-500">
                    No events are scheduled at the moment. Watch this space, or ask at the
                    barangay hall for the current calendar.
                </p>
            @else
                <ul class="mt-4 divide-y divide-navy-50">
                    @foreach ($upcomingEvents as $event)
                        <li class="flex items-center gap-4 py-3">
                            <span class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-navy text-white">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-sky-300">
                                    {{ \Illuminate\Support\Carbon::parse($event['date'])->format('M') }}
                                </span>
                                <span class="text-lg font-extrabold leading-none">
                                    {{ \Illuminate\Support\Carbon::parse($event['date'])->format('j') }}
                                </span>
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-navy-900">{{ $event['title'] }}</p>
                                <p class="text-sm text-navy-500">
                                    {{ $event['time'] }} &middot; {{ $event['where'] }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-public-layout>
