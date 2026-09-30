{{--
    The signed-in resident's navigation.

    The public navbar a guest sees, with two additions and one substitution:

      * "Document Requests" and "Relief Transparency" join the primary row,
        because those are the things a resident signs in to do and the guest bar
        has no room for them.
      * "Log in" and "Register" are replaced by a single gold "My Profile"
        button, which is the same gold CTA the guest bar already uses.

    There is no Dashboard entry. A resident's home is the public home page, and
    an old version led this bar with a Dashboard that sent them to a page of
    counts duplicating the two things this bar already links to. There is no
    "Account Settings" entry either: My Account is one screen for every role, and
    a second name for it was a second place for the role split to be wrong.

    The link lists come from App\Support\RoleNavigation rather than being written
    here, so the resident's permissions are as assertable as a staff member's, and
    so this file and the staff sidebar cannot disagree about what a resident may
    reach. Both lists are filtered to routes that are registered, because the
    portal is being built in phases and route() throws on an unknown name rather
    than producing a dead link.

    The dropdown is defined once and looped by both the desktop dropdown and the
    mobile panel. The previous version wrote it out twice, once per breakpoint,
    which is how the two drifted apart.

    It closes on an outside click and on Escape. Alpine's x-on:click.outside
    fires for a click anywhere outside the button, and x-on:keydown.escape.window
    catches Escape even while focus is inside the menu, which a plain keydown
    listener on the trigger would miss.
--}}
@php
    use App\Support\RoleNavigation;

    $bar = RoleNavigation::availableResidentBar();

    $primaryNav = array_map(
        fn (array $item): array => [
            'label' => $item['label'],
            'url' => route($item['route']),
            'active' => request()->routeIs($item['pattern']),
        ],
        $bar['primary']
    );
@endphp

<header x-data="{ navOpen: false, profileOpen: false }" class="sticky top-0 z-50 border-b border-white/10 bg-navy text-white shadow-header">
    <nav class="mx-auto w-full max-w-shell px-4 sm:px-6 lg:px-8 2xl:max-w-[90rem]" aria-label="Main navigation">
        <div class="flex h-16 items-center justify-between gap-4 lg:h-20">
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

            {{-- Desktop row: the public destinations, then the two portal ones. --}}
            <ul class="hidden items-center gap-4 lg:flex xl:gap-6">
                @foreach ($primaryNav as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            class="block rounded-lg px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold
                                   {{ $item['active'] ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Auth actions: the gold My Profile dropdown replaces Log in / Register. --}}
            {{-- ============================================================
                 PROFILE
                 ============================================================
                 A round gold button, not a labelled one. The word "My Profile"
                 was carrying no information the icon did not: the guest bar
                 beside it has two labelled buttons, and a third word next to a
                 familiar silhouette made the bar wider without making it clearer.

                 The pending dot is the reason the button is worth noticing at
                 all. A resident who has not been verified yet has something
                 outstanding, and a dot in the top right corner of the navbar is
                 the one place they will see it on every page. It is aria-hidden
                 because the menu below already states the state in words - a dot
                 read out as "bullet" is noise, and the text is what a screen
                 reader user needs.

                 aria-haspopup and aria-expanded are on the trigger so the menu's
                 existence and open state are announced, not just visible.
                 --}}
            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                @php
                    $profileUser = auth()->user();
                    $profileInitial = mb_substr((string) ($profileUser->name ?: '?'), 0, 1);
                    $profileIsVerified = $profileUser->residentProfile?->isVerified() === true;
                    $profileIsPending = ! $profileIsVerified;
                @endphp

                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                        @click="open = !open"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-haspopup="true"
                        aria-label="My Profile menu"
                        title="My Profile"
                        class="relative inline-flex h-[46px] w-[46px] items-center justify-center rounded-full bg-gold text-navy-900 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                        <x-icon name="user" class="h-5 w-5" />

                        @if ($profileIsPending)
                            <span aria-hidden="true"
                                class="absolute -right-0.5 -top-0.5 h-3.5 w-3.5 rounded-full bg-orange-500 ring-2 ring-navy"></span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak x-on:click.outside="open = false"
                        x-on:keydown.escape.window="open = false"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute right-0 z-50 mt-2 w-[290px] overflow-hidden rounded-2xl border border-navy-100 bg-white text-navy-900 shadow-xl"
                        role="menu" aria-label="My Profile">

                        {{-- Header: who you are, and what state your account is in.
                             It is here rather than in the account page because this
                             is the one question a resident opens this menu with. --}}
                        <div class="bg-gradient-to-br from-navy-700 to-navy-900 px-4 py-4 text-white">
                            <div class="flex items-center gap-3">
                                <span aria-hidden="true"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gold text-sm font-extrabold text-navy-900">
                                    {{ mb_strtoupper($profileInitial) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-white">{{ $profileUser->name }}</p>
                                    <p class="truncate text-xs text-navy-200">{{ $profileUser->email }}</p>
                                </div>
                            </div>

                            <span class="mt-3 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-2xs font-bold uppercase tracking-wide
                                         {{ $profileIsVerified ? 'bg-emerald-400/20 text-emerald-200' : 'bg-orange-400/20 text-orange-200' }}">
                                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full {{ $profileIsVerified ? 'bg-emerald-300' : 'bg-orange-300' }}"></span>
                                {{ $profileIsVerified ? 'Verified' : 'Pending verification' }}
                            </span>
                        </div>

                        {{-- Two items. The three the old menu carried - Edit
                             Profile, Change Password, Verification Status - are all
                             tabs of one page now, and three links to three corners
                             of a single page is a menu that is really a table of
                             contents. --}}
                        <div class="py-1.5">
                            <a href="{{ route('account.edit') }}" role="menuitem"
                                @if (request()->routeIs('account.*')) aria-current="page" @endif
                                class="flex items-start gap-3 px-4 py-2.5 text-left transition hover:bg-navy-50 focus:outline-none focus-visible:bg-navy-50">
                                <x-icon name="user" class="mt-0.5 h-4 w-4 shrink-0 text-navy-400" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-navy-900">My Profile</span>
                                    <span class="block text-xs text-slate-500">Name, password and verification status</span>
                                </span>
                            </a>

                            {{-- Log out is a POST, never a link: a GET /logout can be
                                 fired by an image tag or a link prefetcher. --}}
                            <form method="POST" action="{{ route('logout') }}" role="none">
                                @csrf
                                <button type="submit" role="menuitem"
                                    class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition hover:bg-red-50 focus:outline-none focus-visible:bg-red-50">
                                    <x-icon name="lock" class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-red-600">Log out</span>
                                        <span class="block text-xs text-slate-500">Sign out of this device</span>
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mobile toggle --}}
            <button type="button" @click="navOpen = !navOpen"
                :aria-expanded="navOpen ? 'true' : 'false'" aria-controls="mobile-menu"
                class="-mr-1 inline-flex h-11 w-11 items-center justify-center rounded-lg text-white transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold lg:hidden">
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

    {{-- Mobile menu: the same two lists, in one panel. --}}
    <div id="mobile-menu" x-show="navOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="border-t border-white/10 bg-navy lg:hidden">
        <div class="mx-auto w-full max-w-shell px-4 pb-5 pt-2 sm:px-6">
            <ul class="space-y-1">
                @foreach ($primaryNav as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            class="block rounded-lg px-3 py-3 text-base font-semibold transition
                                   {{ $item['active'] ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- The same two items as the dropdown. They used to be four, which
                 meant the phone panel and the dropdown disagreed about how many
                 things a resident could do from here. --}}
            <div class="mt-4 border-t border-white/10 pt-4">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-navy-200/70">My Profile</p>

                <a href="{{ route('account.edit') }}"
                    @if (request()->routeIs('account.*')) aria-current="page" @endif
                    class="flex items-center gap-2 rounded-lg px-3 py-3 text-left transition
                           {{ request()->routeIs('account.*') ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                    <x-icon name="user" class="h-4 w-4 shrink-0" />
                    <span>
                        <span class="block text-base font-semibold">My Profile</span>
                        <span class="block text-xs text-navy-200/80">Name, password and verification status</span>
                    </span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-3 text-left text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">
                        <x-icon name="lock" class="h-4 w-4 shrink-0" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
