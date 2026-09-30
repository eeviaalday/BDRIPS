@php
    $user = auth()->user();
    $links = [];

    if ($user->role === 'resident') {
        /*
         * Every one of these stays inside /resident. The sidebar used to link
         * out to the public Relief Transparency and Official Directory pages,
         * which dropped the resident into the public layout and left them with
         * no way back to the portal except the browser's back button. The
         * resident-scoped equivalents live on ResidentPortalController and show
         * the same underlying data.
         */
        $links[] = ['route' => 'resident.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'];
        $links[] = ['route' => 'resident.requests.index', 'label' => 'My Requests', 'icon' => 'M9 12h6m-6 4h6M9 8h6M6 4h12a1 1 0 011 1v14a1 1 0 01-1 1H6a1 1 0 01-1-1V5a1 1 0 011-1z'];
        $links[] = ['route' => 'resident.requests.create', 'label' => 'Request a Document', 'icon' => 'M12 4v16m8-8H4'];
        $links[] = ['route' => 'resident.documents', 'label' => 'My Documents', 'icon' => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M9 15h6'];
        $links[] = ['route' => 'resident.directory', 'label' => 'Public Information', 'icon' => 'M3 21v-2a4 4 0 014-4h6a4 4 0 014 4v2M10 11a4 4 0 100-8 4 4 0 000 8z'];
        $links[] = ['route' => 'resident.relief', 'label' => 'Relief Program', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6M5 17v-8m3 8v-7m3 7v-9m3 9V9m3 8v-4m0-10l2 2m0 0l-2 2'];
        $links[] = ['route' => 'resident.faq', 'label' => 'Help & FAQ', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'];
    } elseif ($user->role === 'kagawad') {
        $links[] = ['route' => 'kagawad.dashboard', 'label' => 'Sector Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'];
        $links[] = ['route' => 'transparency.index', 'label' => 'Transparency Board', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6M5 17v-8m3 8v-7m3 7v-9m3 9V9m3 8v-4m0-10l2 2m0 0l-2 2'];
    } else {
        $links[] = ['route' => 'staff.dashboard', 'label' => 'Admin Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'];
        $links[] = ['route' => 'staff.requests.index', 'label' => 'Document Review Queue', 'icon' => 'M9 12h6m-6 4h6M9 8h6M6 4h12a1 1 0 011 1v14a1 1 0 01-1 1H6a1 1 0 01-1-1V5a1 1 0 011-1z'];
        $links[] = ['route' => 'staff.verifications.index', 'label' => 'Resident Verification', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21v-1a7 7 0 0114 0v1'];
        $links[] = ['route' => 'staff.batches.index', 'label' => 'Relief Batches', 'icon' => 'M4 5h16M4 5a2 2 0 002-2M4 5v10a2 2 0 002 2h12a2 2 0 002-2V5M4 19h16'];
        $links[] = ['route' => 'staff.announcements.index', 'label' => 'Announcements', 'icon' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'];
        $links[] = ['route' => 'staff.sectors.index', 'label' => 'Sectors & Kagawads', 'icon' => 'M3 21v-2a4 4 0 014-4h6a4 4 0 014 4v2M10 11a4 4 0 100-8 4 4 0 000 8z'];
        $links[] = ['route' => 'staff.settings.index', 'label' => 'System Settings', 'icon' => 'M10.3 3.9c.2-.7 1.2-.7 1.4 0l.6 2.3h2.6l.6-2.3c.2-.7 1.2-.7 1.4 0l1.1 4.3 2.2 1.4c.6.4.3 1.4-.5 1.5l-2.5.4 1.1 4.1-2.2-1-1.3 4.3c-.2.7-1.2.7-1.4 0l-.8-2.9-2.6.1.6 2.8c.1.7-.9 1-1.2.3l-1-2.8-2.2-1.4c-.6-.4-.3-1.4.5-1.5l2.2-.8-1-4.1-2 .8c-.6.4-1.3-.3-1-.9l1.2-3.4z'];
    }

    /*
     * The resident's own record and account, kept as a separate group so it
     * reads as "you" rather than as two more places to go. Only shown to
     * residents: kagawads and staff manage their sector and the system, not a
     * resident profile.
     */
    $accountLinks = $user->role === 'resident'
        ? [
            ['route' => 'resident.profile', 'label' => 'Resident Profile', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21v-1a7 7 0 0114 0v1'],
            ['route' => 'resident.settings', 'label' => 'Account Settings', 'icon' => 'M10.3 3.9c.2-.7 1.2-.7 1.4 0l.6 2.3h2.6l.6-2.3c.2-.7 1.2-.7 1.4 0l1.1 4.3 2.2 1.4c.6.4.3 1.4-.5 1.5l-2.5.4 1.1 4.1-2.2-1-1.3 4.3c-.2.7-1.2.7-1.4 0l-.8-2.9-2.6.1.6 2.8c.1.7-.9 1-1.2.3l-1-2.8-2.2-1.4c-.6-.4-.3-1.4.5-1.5l2.2-.8-1-4.1-2 .8c-.6.4-1.3-.3-1-.9l1.2-3.4z'],
        ]
        : [];
@endphp

<aside class="w-64 bg-navy text-white flex-shrink-0 min-h-screen hidden md:flex flex-col">
    <div class="px-6 py-5 border-b border-white/10">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/Zone 1_logo.png') }}" alt="Barangay Linasin Seal"
                class="w-10 h-10 rounded-full ring-2 ring-sky-400/60 object-cover flex-shrink-0" />
            <div>
                <p class="font-bold leading-tight">Barangay Linasin</p>
                <p class="text-xs text-sky-300">Zone 1, Bangued, Abra</p>
            </div>
        </a>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                    {{ request()->routeIs($link['route']) ? 'bg-sky-400 text-navy font-semibold' : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/>
                </svg>
                {{ $link['label'] }}
            </a>
        @endforeach

        {{-- The "Official Directory" and "Back to Homepage" links that used to
             sit here are gone for every role. Both left the portal: one dumped a
             signed-in resident into the public layout, and the other sent staff
             and kagawads out of the system entirely. Residents now have Public
             Information above, which is the directory in portal form. --}}

        @if ($accountLinks !== [])
            <div class="pt-4 mt-4 border-t border-white/10">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-sky-300/70">My Account</p>
                @foreach ($accountLinks as $link)
                    <a href="{{ route($link['route']) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                            {{ request()->routeIs($link['route']) ? 'bg-sky-400 text-navy font-semibold' : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/>
                        </svg>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="px-6 py-4 border-t border-white/10 text-sm">
        <p class="font-medium text-sky-100">{{ $user->name }}</p>
        <p class="text-xs text-sky-300">{{ $user->email }}</p>
    </div>
</aside>