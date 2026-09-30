<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Official digital services portal of Barangay Linasin, Zone 1, Bangued, Abra. Request barangay documents online and track your transactions.">

        <title>{{ $title ?? 'BDRPIS' }} &middot; {{ $barangayName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Per-page assets. The about page's map is the only one that uses it:
             a stylesheet or script that only one page needs is not in the
             bundle every visitor would otherwise download. --}}
        @stack('head')

        {{-- Respect reduced-motion preferences for every reveal animation. --}}
        <style>
            @media (prefers-reduced-motion: reduce) {
                *, *::before, *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                    scroll-behavior: auto !important;
                }
            }
        </style>
    </head>

    <body class="bg-white font-sans text-navy-800 antialiased">
        <a href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-navy focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white">
            Skip to main content
        </a>

        {{-- ============================ HEADER ============================
             One of three headers, and the choice is made before any of them
             is rendered:

               - a signed-in RESIDENT gets the resident navbar, which is this
                 bar plus Document Requests and Relief Transparency in place of
                 Log in / Register.
               - a signed-in staff member or kagawad keeps the single "My
                 Dashboard" button, which is where the role redirect lives.
               - a guest gets this bar exactly as it was written.

             The guest markup below is deliberately untouched rather than
             branched into "guest and resident" variants: it is a finished
             public page, and a resident's header lives in
             components/layouts/resident-nav instead. --}}
        @if (auth()->check() && auth()->user()->isResident())
            <x-layouts.resident-nav />
        @else
        <header x-data="{ navOpen: false }" class="sticky top-0 z-50 border-b border-white/10 bg-navy text-white shadow-header">
            <nav class="mx-auto w-full max-w-shell px-4 sm:px-6 lg:px-8 2xl:max-w-[90rem]" aria-label="Main navigation">
                <div class="flex h-16 items-center justify-between gap-4 lg:h-20">
                    @php
                        /*
                         * Five primary destinations, each a standalone route rather
                         * than an in-page "#section" anchor, so a visitor can link
                         * straight to one area and the bar can mark where they are.
                         * "Map" was removed: it resolved to the same #contact
                         * anchor as "Contact", and there is no standalone map page.
                         *
                         * The active pattern is a wildcard so nested routes under
                         * the same section keep the tab lit while browsing.
                         */
                        $primaryNav = [
                            ['label' => 'Home', 'url' => route('home'), 'active' => request()->routeIs('home')],
                            ['label' => 'Services', 'url' => route('services.index'), 'active' => request()->routeIs('services.*')],
                            ['label' => 'About', 'url' => route('about'), 'active' => request()->routeIs('about')],
                            ['label' => 'Announcements', 'url' => route('announcements.index'), 'active' => request()->routeIs('announcements.*')],
                            ['label' => 'Contact', 'url' => route('contact'), 'active' => request()->routeIs('contact')],
                        ];

                        /*
                         * Secondary destinations keep their place in the mobile
                         * menu and in the footer so nothing becomes
                         * unreachable, but they stay out of the crowded
                         * desktop bar.
                         */
                        $secondaryNav = [
                            ['label' => 'Officials', 'url' => route('public.directory'), 'active' => request()->routeIs('public.directory')],
                            ['label' => 'Relief Transparency', 'url' => route('transparency.index'), 'active' => request()->routeIs('transparency.*')],
                            ['label' => 'Track a Request', 'url' => route('requests.track'), 'active' => request()->routeIs('requests.track')],
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

                    {{-- Desktop navigation. A natural flex group, not a stretched
                         one: justify-between on the parent row already centres this
                         cluster in the space between the brand and the auth
                         actions. The gap steps up to gap-6 from xl because the
                         five labels plus the brand and auth groups leave only
                         roughly 25px of slack at a 1024px viewport. --}}
                    <ul class="hidden items-center gap-4 lg:flex xl:gap-6">
                        @foreach ($primaryNav as $item)
                            <li>
                                <a href="{{ $item['url'] }}"
                                    @if (! empty($item['active'])) aria-current="page" @endif
                                    class="block rounded-lg px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold
                                           {{ ! empty($item['active']) ? 'bg-white/10 text-gold' : 'text-navy-100 hover:bg-white/10 hover:text-white' }}">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Auth actions --}}
                    <div class="hidden shrink-0 items-center gap-2 lg:flex">
                        @auth
                            <a href="{{ route('dashboard') }}"
                                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                                <x-icon name="user" class="h-4 w-4" />
                                My Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="rounded-lg px-4 py-2.5 text-sm font-semibold text-navy-100 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold">
                                Log in
                            </a>
                            <a href="{{ route('register') }}"
                                class="rounded-lg border border-gold/70 px-4 py-2.5 text-sm font-bold text-gold transition hover:bg-gold hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                                Register
                            </a>
                        @endauth
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

            {{-- Mobile menu --}}
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
                        @foreach (array_merge($primaryNav, $secondaryNav) as $item)
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

                    <div class="mt-4 grid gap-2 border-t border-white/10 pt-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gold px-4 py-3 text-sm font-bold text-navy-900">
                                <x-icon name="user" class="h-4 w-4" /> My Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg border border-white/20 px-4 py-3 text-center text-sm font-semibold text-white">Log in</a>
                            <a href="{{ route('register') }}" class="rounded-lg bg-gold px-4 py-3 text-center text-sm font-bold text-navy-900">Register</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>
        @endif

        {{-- ============================ RESIDENT GATE ============================
             Sits directly under the navbar, and only for a signed-in resident.

             The component renders nothing at all when the profile is verified,
             and nothing for a guest or a staff member, so on every public page
             and for every non-resident this is an empty string - the markup
             below it is unchanged. --}}
        @if (auth()->check() && auth()->user()->isResident())
            <x-resident.verification-banner :profile="auth()->user()->residentProfile" />
        @endif

        @auth
            {{-- ============================ BACK BUTTON ============================
                 A no-store response tells the browser not to keep the page, but
                 "persisted" can still be true on a page restored from its own
                 history entry - the back-forward cache, or a history entry that
                 was written before the session ended. Reloading on pageshow
                 forces the server to be asked, and the server answers the login
                 screen because the session is gone. Without this, Back after
                 logout can redisplay the account page the person just left.

                 Guarded on auth() so a public page does not reload itself. --}}
            <script>
                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        window.location.reload();
                    }
                });
            </script>
        @endauth

        <main id="main-content">
            {{ $slot }}
        </main>

        {{-- ============================ FOOTER ============================
             A full-bleed page footer: no rounded panel, no side margins, just a
             deep blue band that runs to both screen edges and closes the page.

             The barangay photograph sits on the right of that band and the blue
             runs across it in one gradient, so the image reads as the far end
             of the same surface rather than a second box beside the text. There
             is no seam, no border and no column split between them - the only
             edges in here are the band's own.

             Two overlays sit on the photograph, both inside this isolate()
             stacking context at a negative z-index so neither can bleed over
             the page:

               - the horizontal one is the design: solid on the left for the
                 link contrast, thinning out to the right so the landscape shows.
               - the vertical one is legibility insurance. The columns and the
                 copyright bar sit in the lower half, which is exactly where a
                 landscape photograph is brightest, so without it the small link
                 text on the right would be read against bare sky. It also
                 carries the band on a phone, where the gradient's right-hand
                 end is proportionally much wider.

             The content wrapper is the same max-w-7xl on every public page, so
             the four columns line up with the page body above them.
        --}}
        <footer class="relative isolate bg-slate-900 text-slate-200">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
                {{-- object-right keeps the village and the hills in frame; the
                     hero crops to its own composition, not to a thin band. --}}
                <img src="{{ $footerPhoto }}" alt=""
                    class="h-full w-full object-cover object-right" loading="lazy" decoding="async"
                    width="1440" height="480" />
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-900/90 to-slate-900/55"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/40 to-transparent"></div>
            </div>

            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Brand + about --}}
                    <div>
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/Zone 1_logo.png') }}" alt="" width="40" height="40"
                                class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-gold/50" />
                            <p class="min-w-0">
                                <span class="block truncate text-base font-extrabold text-white">Barangay Linasin</span>
                                <span class="block truncate text-2xs font-medium text-slate-400">Zone 1, Bangued, Abra</span>
                            </p>

                            {{-- Official page. An icon button rather than another
                                 line of text, and the only link in here that
                                 leaves the site, so it opens in a new tab and
                                 severs the opener reference. --}}
                            <a href="{{ $officeFacebook }}" target="_blank" rel="noopener noreferrer"
                                aria-label="Barangay Linasin on Facebook"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/25 text-slate-200 transition duration-200 ease-smooth hover:border-gold hover:text-gold focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                                <x-icon name="facebook" class="h-5 w-5" />
                            </a>
                        </div>

                        <p class="mt-4 text-xs leading-relaxed text-slate-300">
                            {{-- System full name, per the official BDRPIS identity. --}}
                            The Barangay Directory &amp; Document Request and Public Information System
                            provides online access to barangay information, document requests, and
                            relief distribution records.
                        </p>
                    </div>

                    {{-- E-Services --}}
                    <div>
                        <p class="text-2xs font-bold uppercase tracking-[0.18em] text-white">E-Services</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach (config('bdrpis.services') as $service)
                                <li>
                                    <a href="{{ route('services.index') }}"
                                        class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">
                                        {{ $service['title'] }}
                                    </a>
                                </li>
                            @endforeach
                            <li>
                                <a href="{{ route('transparency.index') }}"
                                    class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">
                                    Relief Transparency
                                </a>
                            </li>
                        </ul>
                    </div>

                    {{-- Quick links --}}
                    <div>
                        <p class="text-2xs font-bold uppercase tracking-[0.18em] text-white">Explore</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('about') }}" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">About</a></li>
                            <li><a href="{{ route('contact') }}" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">Contact</a></li>
                            <li><a href="{{ route('services.index') }}#how-it-works" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">How It Works</a></li>
                            <li><a href="{{ route('public.directory') }}" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">Officials Directory</a></li>
                            <li><a href="{{ route('announcements.index') }}" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">Announcements</a></li>
                            <li><a href="{{ route('requests.track') }}" class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">Track a Request</a></li>
                        </ul>
                    </div>

                    {{-- Contact (values come from the settings table) --}}
                    <div>
                        <p class="text-2xs font-bold uppercase tracking-[0.18em] text-white">Contact</p>
                        <ul class="mt-3 space-y-2.5 text-sm">
                            <li class="flex gap-2.5">
                                <x-icon name="pin" class="mt-0.5 h-4 w-4 shrink-0 text-gold" />
                                <span>{{ $barangayAddress }}</span>
                            </li>
                            <li class="flex gap-2.5">
                                <x-icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-gold" />
                                {{-- One number, not two: the office line and the
                                     hotline are the same contact for this barangay,
                                     and printing it twice cost a line of height
                                     and said nothing extra. --}}
                                <span>
                                    <span class="block text-2xs text-slate-400">Contact No.</span>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $officePhone) }}" class="rounded transition hover:text-gold">{{ $officePhone }}</a>
                                </span>
                            </li>
                            <li class="flex gap-2.5">
                                <x-icon name="mail" class="mt-0.5 h-4 w-4 shrink-0 text-gold" />
                                <a href="mailto:{{ $officeEmail }}" class="rounded break-all transition hover:text-gold">{{ $officeEmail }}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Legal. One line from xl up, where the sentence and the
                     BDRPIS mark both fit inside the 7xl wrapper; below that the
                     nowrap is dropped rather than forced, so a phone still wraps
                     the notice instead of overflowing the page sideways. --}}
                <div class="mt-6 flex flex-col gap-3 border-t border-white/10 pt-4 text-xs text-slate-400 md:flex-row md:items-center md:justify-between">
                    <p class="leading-relaxed xl:whitespace-nowrap">
                        &copy; {{ date('Y') }} Barangay Linasin. Personal information is processed in
                        accordance with the <span class="font-semibold text-slate-200">Data Privacy Act of 2012 (R.A. 10173)</span>
                        and is used solely for official barangay services.
                    </p>
                    <p class="shrink-0 font-semibold tracking-wide text-white">BDRPIS</p>
                </div>
            </div>
        </footer>
    </body>
</html>
