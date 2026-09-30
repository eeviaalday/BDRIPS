{{--
    The resident dashboard.

    One view, three real states, because a resident's account is genuinely in
    one of three places and each one deserves a different page rather than the
    same page with a banner glued to the top:

      * No profile        - an account exists before a profile does. Nothing to
                           report yet, so say that and point at the one action.
      * Waiting (pending  - the Secretary is reviewing the ID. Show what was
        or rejected)        submitted and when, what is still outstanding, and
                           why document requests are locked.
      * Verified           - the working dashboard: summary figures, the work in
                           progress, and the published notices.

    The lock explanation is $documentLockReason, computed by the controller from
    the same checks store() enforces. The dashboard can therefore never offer a
    request the form is going to refuse.
--}}
<x-public-layout>
    <x-slot name="title">Resident Dashboard</x-slot>

    @php
        // $state arrives from ResidentDashboardController so the badge, the
        // panels below and the lock reason are all describing one decision.
        $state = $verificationState;
        $firstName = \Illuminate\Support\Str::before(Auth::user()->name, ' ');
    @endphp

    {{-- ============================================================
         WELCOME HEADER
         The one place the resident's own name and verification state
         appear together. Kept as a band rather than a card so it
         reads as the top of the page and not as one more panel.
    --}}
    <section class="rounded-xl bg-navy px-6 py-7 text-white shadow-header sm:px-8"
        aria-labelledby="welcome-heading">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-gold">Barangay Linasin, Zone 1</p>
                <h1 id="welcome-heading" class="mt-1.5 text-2xl font-extrabold leading-tight sm:text-3xl">
                    Welcome, {{ $firstName }}
                </h1>
                <p class="mt-1.5 text-sm text-navy-200">
                    @switch($state)
                        @case('verified')
                            Your resident record is verified. You can request documents and check your household relief record.
                            @break

                        @case('pending')
                            Your details are with the Barangay Secretary for review. Document requests open once the verification is approved.
                            @break

                        @case('rejected')
                            Your verification needs attention before you can request a document.
                            @break

                        @default
                            Set up your resident profile so the barangay can verify you and issue documents.
                            @break
                    @endswitch
                </p>
            </div>

            {{-- The badge mirrors the status the pages act on, which is the
                 profile's, never the account's email confirmation. --}}
            <span @class([
                'inline-flex shrink-0 items-center gap-2 self-start rounded-full px-4 py-2 text-sm font-bold sm:self-center',
                'bg-emerald-400 text-navy-900' => $state === 'verified',
                'bg-gold text-navy-900' => $state === 'pending',
                'bg-red-500 text-white' => $state === 'rejected',
                'bg-white/15 text-white' => $state === 'none',
            ])>
                <x-icon :name="$state === 'verified' ? 'shield-check' : ($state === 'rejected' ? 'x-circle' : 'clock')"
                    class="h-4 w-4" />
                @switch($state)
                    @case('verified') Verified
                    @case('pending') Pending Verification
                    @case('rejected') Verification Rejected
                    @default Profile Not Set Up
                @endswitch
            </span>
        </div>
    </section>

    {{-- ============================================================
         NO PROFILE
         There is no household to report on, so a panel explaining the
         single next action beats a page full of zeroes.
    --}}
    @if ($state === 'none')
        <x-card tone="white" class="mt-6 text-center">
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
    @endif

    {{-- ============================================================
         WAITING ON VERIFICATION
         Only shown while unverified. Answers the two questions a waiting
         resident actually has: what did I submit, and what happens now.
         A verified resident has none of these questions, so the panel is not
         on their page at all.
    --}}
    @if (in_array($state, ['pending', 'rejected'], true))
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card tone="white">
                <h2 class="text-base font-bold text-navy-900">Your verification</h2>

                <div class="mt-4">
                    <x-alert :variant="$state === 'rejected' ? 'danger' : 'warning'"
                        :icon="$state === 'rejected' ? 'x-circle' : 'clock'"
                        :title="$state === 'rejected' ? 'Verification was not approved' : 'Verification is pending'">
                        @if ($state === 'rejected')
                            {{ $profile->rejection_reason ?: 'The barangay could not verify your submitted ID. Please check it and submit again.' }}
                        @else
                            The Barangay Secretary is checking your submitted ID. You will be able to
                            request documents once it is approved.
                        @endif
                    </x-alert>
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-navy-50 pb-3">
                        <dt class="text-navy-600">Submitted</dt>
                        <dd class="font-semibold text-navy-900">
                            {{ $profile->created_at->format('M d, Y') }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-navy-50 pb-3">
                        <dt class="text-navy-600">Household reference</dt>
                        <dd class="font-mono text-xs font-semibold text-navy-900">
                            {{ $profile->household_number ?: 'Not set' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-3">
                    <x-btn :href="route('resident.verification.status')" variant="navy" size="sm" icon="clock">
                        View status
                    </x-btn>
                    @if ($state === 'rejected')
                        <x-btn :href="route('resident.verification')" variant="outline" size="sm" icon="upload">
                            Submit again
                        </x-btn>
                    @endif
                </div>
            </x-card>

            {{-- Profile completion. Only meaningful while unverified: once the
                 record is approved there is nothing left to nag about, and the
                 verification itself has already confirmed the details. --}}
            <x-card tone="white">
                <h2 class="text-base font-bold text-navy-900">Profile completion</h2>

                @if ($missingProfileDetails === [])
                    <div class="mt-4">
                        <x-alert variant="success" icon="check-circle" title="All required details are filled in">
                            Your profile has everything the verification needs. {{ $state === 'rejected'
                                ? 'Submitting it again now would send it straight back to the review queue.'
                                : 'Nothing else is needed from you while it is being reviewed.' }}
                        </x-alert>
                    </div>
                @else
                    <p class="mt-2 text-sm text-navy-600">
                        These are still blank. A submission missing any of them cannot be verified.
                    </p>

                    <ul class="mt-4 space-y-2.5">
                        @foreach (\App\Models\ResidentProfile::requiredDetailLabels() as $column => $label)
                            <li class="flex items-center gap-3 text-sm">
                                @if (array_key_exists($column, $missingProfileDetails))
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                        <x-icon name="minus" class="h-3 w-3" />
                                    </span>
                                    <span class="font-medium text-amber-900">{{ $label }}</span>
                                    <span class="ml-auto text-xs font-semibold text-amber-700">Missing</span>
                                @else
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                        <x-icon name="check" class="h-3 w-3" />
                                    </span>
                                    <span class="text-navy-500 line-through decoration-navy-200">{{ $label }}</span>
                                    <span class="ml-auto text-xs font-semibold text-emerald-700">Filled in</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-5">
                        <x-btn :href="route('resident.verification')" variant="navy" size="sm" icon="edit">
                            Complete my profile
                        </x-btn>
                    </div>
                @endif
            </x-card>
        </div>
    @endif

    {{-- The lock, stated on the dashboard rather than discovered on the request
         form. The reason comes from the server.

         Shown for ANY locked state, not only the unverified ones. A verified
         resident who already holds the maximum number of open requests is
         locked too, and their reason is the cap rather than the verification -
         so gating this on the verification state left them with a dead end and
         a quick action labelled "Locked until verified", which would have been
         plainly untrue. --}}
    @if ($documentLockReason !== null)
        <x-card tone="white" class="mt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-500">
                    <x-icon name="lock" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-bold text-navy-900">Document requests are locked</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-navy-600">{{ $documentLockReason }}</p>
                    <p class="mt-2 text-sm text-navy-500">
                        You can still read every request you have already made, and check your household
                        relief record, while you wait.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <x-btn :href="route('resident.requests.index')" variant="secondary" size="sm" icon="document">
                            My requests
                        </x-btn>
                        <x-btn :href="route('resident.relief')" variant="ghost" size="sm" icon="gift">
                            Relief transparency
                        </x-btn>
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- ============================================================
         SUMMARY FIGURES
         Counts are real queries from ResidentDashboardController, not
         placeholders. "Active" excludes completed, rejected and abandoned
         requests so the number is not propped up by finished work, and
         collected work is shown separately rather than being lost.
    --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card variant="badged" tone="blue" icon="document" label="Active Requests"
            :value="$activeCount" class="cursor-pointer" :href="route('resident.requests.index')" />

        <x-stat-card variant="badged" tone="green" icon="check-circle" label="Ready for Pickup"
            :value="$readyCount" class="cursor-pointer" :href="route('resident.requests.index')" />

        <x-stat-card variant="badged" tone="purple" icon="archive" label="Completed"
            :value="$completedCount" class="cursor-pointer" :href="route('resident.requests.index')" />

        <x-stat-card variant="badged" tone="orange" icon="gift" label="Relief Received"
            :value="$reliefCount" class="cursor-pointer" :href="route('resident.relief')" />
    </div>

    {{-- ============================================================
         QUICK ACTIONS
         The five destinations named in the resident brief, ordered by
         how soon a resident needs them.
    --}}
    <x-card tone="white" class="mt-6">
        <h2 class="text-base font-bold text-navy-900">Quick actions</h2>

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
            @if ($documentLockReason === null)
                @php $actions = [
                    ['route' => 'resident.requests.create', 'label' => 'Request a Document', 'icon' => 'document', 'hint' => 'Clearance or certificate'],
                ]; @endphp
            @else
                @php
                    /*
                     * Locked: the request form becomes a link to the reason
                     * instead. It is not a dead link and it does not pretend the
                     * form is available - it explains the lock where it is met.
                     *
                     * The hint has to match WHICH lock it is. A verified
                     * resident at the open-request cap is not waiting on
                     * anybody, so "Locked until verified" on their page would be
                     * simply untrue. Restriction is tested first because a
                     * restricted resident who is also verified is restricted,
                     * not "waiting on a limit".
                     */
                    $lockHint = match (true) {
                        $isRestricted => 'Account restricted',
                        $state === 'verified' => 'Limit reached for now',
                        default => 'Locked until verified',
                    };

                    $actions = [
                        ['route' => 'resident.requests.index', 'label' => 'Request a Document', 'icon' => 'lock', 'hint' => $lockHint],
                    ];
                @endphp
            @endif

            {{-- array_merge, not +. PHP's array union keeps the left-hand
                 keys and discards same-numbered keys from the right, so with
                 $actions already holding key 0 the union below would silently
                 drop every entry but the first and the resident would be left
                 with a single quick action and no clue why. --}}
            @foreach (array_merge($actions, [
                ['route' => 'resident.requests.index', 'label' => 'Track My Requests', 'icon' => 'search', 'hint' => 'Status and history'],
                ['route' => 'resident.documents', 'label' => 'My Documents', 'icon' => 'folder', 'hint' => 'Everything issued'],
                ['route' => 'resident.relief', 'label' => 'Relief Transparency', 'icon' => 'gift', 'hint' => 'Your household record'],
                ['route' => $state === 'verified' ? 'resident.profile' : 'resident.verification.status',
                    'label' => $state === 'verified' ? 'My Profile' : 'Verification Status',
                    'icon' => 'user', 'hint' => 'Details and settings'],
            ]) as $action)
                <a href="{{ route($action['route']) }}"
                    class="flex flex-col items-center gap-2 rounded-xl border border-navy-100 bg-navy-50/50 px-3 py-4 text-center transition hover:border-navy-200 hover:bg-navy-50">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-navy-700 shadow-card">
                        <x-icon :name="$action['icon']" class="h-5 w-5" />
                    </span>
                    <span class="text-xs font-semibold leading-tight text-navy-800">{{ $action['label'] }}</span>
                    <span class="text-[11px] leading-tight text-navy-500">{{ $action['hint'] }}</span>
                </a>
            @endforeach
        </div>
    </x-card>

    {{-- ============================================================
         RECENT REQUESTS  +  HERO
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
                        @if ($documentLockReason === null)
                            <x-btn :href="route('resident.requests.create')" variant="navy" icon="document" class="mt-5">
                                Request a document
                            </x-btn>
                        @else
                            {{-- The empty state offers the next real step instead
                                 of a button the server would refuse. --}}
                            <p class="mt-4 max-w-md text-sm text-navy-600">{{ $documentLockReason }}</p>
                            <x-btn :href="route('resident.requests.index')" variant="secondary" class="mt-5">
                                Back to my requests
                            </x-btn>
                        @endif
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
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
         LATEST ANNOUNCEMENTS  +  QUICK LINKS
    --}}
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card tone="white" class="xl:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-navy-900">Latest announcements</h2>
                    <p class="mt-1 text-sm text-navy-500">
                        {{ $announcementCount }}
                        {{ \Illuminate\Support\Str::plural('notice', $announcementCount) }}
                        published in the last 14 days.
                    </p>
                </div>
                <a href="{{ route('announcements.index') }}"
                    class="shrink-0 text-sm font-semibold text-navy-700 hover:text-navy-900">
                    View all
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            @if ($latestAnnouncements->isEmpty())
                <x-empty-state icon="megaphone" title="No announcements right now"
                    description="Notices published by the barangay hall will appear here as soon as they go out." class="mt-4" />
            @else
                <ul class="mt-4 divide-y divide-navy-50">
                    @foreach ($latestAnnouncements as $announcement)
                        <li class="flex items-start gap-4 py-4">
                            <img src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->imageAlt() }}"
                                width="72" height="72" loading="lazy"
                                class="h-14 w-14 shrink-0 rounded-xl object-cover ring-1 ring-navy-100" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[11px] font-semibold text-navy-700">
                                        {{ $announcement->categoryLabel() }}
                                    </span>
                                    <time datetime="{{ $announcement->published_at->toDateString() }}"
                                        class="text-xs text-navy-500">
                                        {{ $announcement->published_at->format('M d, Y') }}
                                    </time>
                                </div>
                                <p class="mt-1.5 font-semibold text-navy-900">{{ $announcement->title }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-navy-600">{{ $announcement->excerpt(140) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card tone="white" class="xl:col-span-1">
            <h2 class="text-base font-bold text-navy-900">Quick Links</h2>

            <div class="mt-4 grid grid-cols-2 gap-3">
                {{-- Every destination below is a real portal route. The FAQ
                     entry used to point at the officials directory, which is a
                     different page and left the resident with no FAQ anywhere. --}}
                @foreach ([
                    ['route' => 'resident.directory', 'label' => 'Barangay Officials', 'icon' => 'users'],
                    ['route' => 'resident.faq', 'label' => 'FAQs', 'icon' => 'help-circle'],
                    ['route' => 'resident.directory', 'label' => 'Contact Information', 'icon' => 'phone'],
                    ['route' => 'resident.profile', 'label' => 'My Profile', 'icon' => 'user'],
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
    </div>

    {{-- ============================================================
         UPCOMING EVENTS
    --}}
    <x-card tone="white" class="mt-6">
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
</x-public-layout>