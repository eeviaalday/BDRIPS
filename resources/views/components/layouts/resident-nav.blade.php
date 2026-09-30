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
            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                        class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                        <x-icon name="user" class="h-4 w-4" />
                        My Profile
                    </button>

                    <div x-show="open" x-cloak x-on:click.outside="open = false"
                        x-on:keydown.escape.window="open = false"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-navy-200 bg-white text-navy-900 shadow-lg"
                        role="menu" aria-label="My Profile">
                        @foreach ($bar['profile'] as $item)
                            @if ($item['route'] === 'logout')
                                {{-- Log out is a POST, never a link: a GET /logout can
                                     be fired by an image tag or a prefetcher. --}}
                                <form method="POST" action="{{ route('logout') }}" role="none">
                                    @csrf
                                    <button type="submit" role="menuitem"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-navy-900 transition hover:bg-navy-50">
                                        <x-icon name="lock" class="h-4 w-4 text-navy-400" />
                                        {{ $item['label'] }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route($item['route']) }}" role="menuitem"
                                    @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
                                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-navy-900 transition hover:bg-navy-50
                                           {{ request()->routeIs($item['pattern']) ? 'bg-navy-50 text-navy-900' : '' }}">
                                    <x-icon name="user" class="h-4 w-4 text-navy-400" />
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        @endforeach
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

            <div class="mt-4 border-t border-white/10 pt-4">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-navy-200/70">My Profile</p>
                @foreach ($bar['profile'] as $item)
                    @if ($item['route'] === 'logout')
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-3 text-left text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">
                                {{ $item['label'] }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route($item['route']) }}"
                            class="block rounded-lg px-3 py-3 text-base font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white">
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</header>
