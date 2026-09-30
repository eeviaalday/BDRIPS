{{--
    The signed-in resident's navigation.

    This is the navbar the public site would show a guest, with two additions and
    one substitution:

      * "Document Requests" and "Relief Transparency" join the primary row,
        because those are the two things a resident signs in to do and the guest
        bar has no room for them.
      * "Log in" and "Register" are replaced by a single gold "My Profile"
        button, which is the same gold CTA the guest bar already uses.

    It is a separate component rather than a branch inside the public layout so
    that the guest navbar is left byte-for-byte as it was. A guest is not a
    resident who has lost a button, and editing one is how the other drifts.

    The dropdown closes on an outside click and on Escape. Alpine's
    x-on:click.outside fires for a click anywhere outside the button, and
    x-on:keydown.escape.window catches Escape even while focus is inside the
    menu, which a plain keydown listener on the trigger would miss.
--}}
<header x-data="{ navOpen: false, profileOpen: false }" class="sticky top-0 z-50 border-b border-white/10 bg-navy text-white shadow-header">
    <nav class="mx-auto w-full max-w-shell px-4 sm:px-6 lg:px-8 2xl:max-w-[90rem]" aria-label="Main navigation">
        <div class="flex h-16 items-center justify-between gap-4 lg:h-20">
            @php
                $primaryNav = [
                    ['label' => 'Home', 'url' => route('home'), 'active' => request()->routeIs('home')],
                    ['label' => 'Services', 'url' => route('services.index'), 'active' => request()->routeIs('services.*')],
                    ['label' => 'About', 'url' => route('about'), 'active' => request()->routeIs('about')],
                    ['label' => 'Announcements', 'url' => route('announcements.index'), 'active' => request()->routeIs('announcements.*')],
                    ['label' => 'Contact', 'url' => route('contact'), 'active' => request()->routeIs('contact')],
                    [
                        'label' => 'Document Requests',
                        'url' => route('resident.requests.index'),
                        // A wildcard: /resident/requests, /resident/requests/create
                        // and /resident/requests/{id} are the same destination, so
                        // the tab has to stay lit while browsing into one.
                        'active' => request()->routeIs('resident.requests.*') || request()->routeIs('resident.documents'),
                    ],
                    [
                        'label' => 'Relief Transparency',
                        'url' => route('transparency.index'),
                        'active' => request()->routeIs('transparency.*') || request()->routeIs('resident.relief'),
                    ],
                ];
            @endphp

            {{-- Brand --}}
            <a href="{{ route('home') }}" class="group flex min-w-0 shrink-0 items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-gold">
                <img src="{{ asset('images/Zone 1_logo.png') }}" alt=""
                    width="44" height="44"
                    class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-gold/60 transition group-hover:ring-gold lg:h-11 lg:w-11" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-extrabold leading-tight tracking-wide text-white lg:text-base">BDRPIS</span>
                    <span class="block truncate text-2xs font-medium tracking-wide text-navy-200">Barangay Linasin, Zone 1</span>
                </span>
            </a>

            {{-- Desktop navigation. Same "a natural flex group" treatment as the
                 public bar: justify-between on the row already centres this
                 cluster between the brand and the profile button. --}}
            <ul class="hidden items-center gap-3 xl:flex">
                @foreach ($primaryNav as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                            @if (! empty($item['active'])) aria-current="page" @endif
                            class="block rounded-lg px-2.5 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold
                                   {{ ! empty($item['active']) ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- My Profile --}}
            <div class="relative hidden shrink-0 xl:block">
                <button type="button"
                    x-on:click="profileOpen = !profileOpen"
                    x-on:click.outside="profileOpen = false"
                    x-on:keydown.escape.window="profileOpen = false"
                    :aria-expanded="profileOpen ? 'true' : 'false'"
                    aria-haspopup="true"
                    aria-controls="resident-profile-menu"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <x-icon name="user" class="h-4 w-4" />
                    My Profile
                </button>

                <div id="resident-profile-menu" x-show="profileOpen" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @keydown.escape.window="profileOpen = false"
                    class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-navy-100 bg-white py-1.5 text-navy-800 shadow-xl"
                    role="menu" aria-label="My Profile">
                    <a href="{{ route('resident.profile.edit') }}" role="menuitem"
                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold transition hover:bg-navy-50 focus:outline-none focus-visible:bg-navy-50">
                        <x-icon name="user" class="h-4 w-4 shrink-0 text-navy-400" />
                        Edit Profile
                    </a>
                    <a href="{{ route('resident.profile.change-password') }}" role="menuitem"
                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold transition hover:bg-navy-50 focus:outline-none focus-visible:bg-navy-50">
                        <x-icon name="lock" class="h-4 w-4 shrink-0 text-navy-400" />
                        Change Password
                    </a>
                    <a href="{{ route('resident.verification.status') }}" role="menuitem"
                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold transition hover:bg-navy-50 focus:outline-none focus-visible:bg-navy-50">
                        <x-icon name="shield" class="h-4 w-4 shrink-0 text-navy-400" />
                        Verification Status
                    </a>

                    {{-- Logout is a POST with a CSRF token, never a link: a GET
                         /logout can be triggered by any image tag on any page a
                         resident visits, and browsers and proxies prefetch links. --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" role="menuitem"
                            class="flex w-full items-center gap-2.5 border-t border-navy-100 px-4 py-2.5 text-left text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:bg-red-50">
                            <x-icon name="lock" class="h-4 w-4 shrink-0 text-red-500" />
                            Log out
                        </button>
                    </form>
                </div>
            </div>

            {{-- Mobile toggle --}}
            <button type="button" @click="navOpen = !navOpen"
                :aria-expanded="navOpen ? 'true' : 'false'" aria-controls="mobile-menu"
                class="-mr-1 inline-flex h-11 w-11 items-center justify-center rounded-lg text-white transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold xl:hidden">
                <span class="sr-only">Toggle main menu</span>
                <svg x-show="!navOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="navOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </nav>

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-show="navOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="border-t border-white/10 bg-navy xl:hidden">
        <div class="mx-auto w-full max-w-shell px-4 pb-5 pt-2 sm:px-6">
            <ul class="space-y-1">
                @foreach ($primaryNav as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                            @if (! empty($item['active'])) aria-current="page" @endif
                            class="block rounded-lg px-3 py-3 text-base font-semibold transition
                                   {{ ! empty($item['active']) ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 space-y-1 border-t border-white/10 pt-4">
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-navy-300">My Profile</p>
                <a href="{{ route('resident.profile.edit') }}"
                    class="block rounded-lg px-3 py-3 text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">Edit Profile</a>
                <a href="{{ route('resident.profile.change-password') }}"
                    class="block rounded-lg px-3 py-3 text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">Change Password</a>
                <a href="{{ route('resident.verification.status') }}"
                    class="block rounded-lg px-3 py-3 text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">Verification Status</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="mt-2 w-full rounded-lg bg-gold px-4 py-3 text-center text-sm font-bold text-navy-900">Log out</button>
                </form>
            </div>
        </div>
    </div>
</header>
