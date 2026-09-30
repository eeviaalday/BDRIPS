<x-public-layout>
    <x-slot name="title">Home</x-slot>

    @php
        /*
         * Public photography lives in public/images/. Each key falls back to the
         * neutral SVG artwork in public/images/placeholders/ when a photograph is
         * absent, so a public page never renders a broken image.
         *
         * @return \Closure(string): string
         */
        $imageUrl = function (string $key): string {
            $photograph = config('bdrpis.images.'.$key);

            return asset(is_file(public_path($photograph))
                ? $photograph
                : config('bdrpis.image_fallbacks.'.$key));
        };
    @endphp

    {{-- ============================================================
         1. HERO
         ============================================================ --}}
    {{--
        The section itself is not overflow-hidden so the floating "Our Barangay"
        card can straddle the wave divider; only the background layer clips its
        own contents. Every decorative layer sits at a negative z-index inside
        this isolate() stacking context, so nothing bleeds over the page.
    --}}
    <section id="hero" class="relative isolate text-white">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-20 overflow-hidden bg-navy">
            <img src="{{ $imageUrl('hero') }}" alt=""
                class="h-full w-full object-cover object-center lg:object-right" />
        </div>

        {{-- Base scrim. Below lg the hero copy spans the full width, so the whole
             photograph is dimmed there; from lg the copy is confined to the left
             column and the directional gradient below carries the contrast, which
             keeps the landscape bright and readable on the right. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-slate-900/70 lg:bg-slate-900/20"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-r from-slate-900/80 via-slate-900/40 to-transparent"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-t from-slate-900/50 via-transparent to-slate-900/20"></div>

        {{--
            Full-viewport hero, desktop only.

            Height is a viewport-relative min-height rather than more fixed
            padding, because the target is a fill of the initial viewport rather
            than a fixed height. The 162px offset is the distance from the top of
            the document to just below the top of the "Quick Services" heading:

                80px  sticky header (lg:h-20)
                48px  Quick Services top padding (x-shell top="lg:pt-12")
                34px  headroom the band keeps between the fold and its heading

            That headroom was where the "Quick Access" eyebrow used to sit: 16px
            of line box and 12px of margin beneath it. Both were removed, so the
            band's content moved up into that space and the constant stayed put -
            the hero still ends on the same line it always did, and the Quick
            Services title now starts on it. That is what lines the title up with
            the "View Details" line of the floating callout at the hero's foot.
            At 768px tall the min-height (606px) is barely more than the content
            needs, so a short laptop window simply falls back to its content
            height rather than clipping.

            This constant is coupled to the band below: it was 194px when the
            Quick Services band used the default x-shell top padding of 80px.
            Tightening that padding to 48px moved the band's content 32px closer
            to the header, so the constant dropped by the same 32px. If either
            value changes the other must follow, or the band will stop landing
            where the callout says it should.

            justify-center is applied from lg only: without it the surplus height
            would all collect beneath the copy on a tall display, leaving the
            photo as a large empty field. Below lg nothing changes - the hero
            stays padding-driven and top-aligned, so a phone or tablet never
            gets a full-viewport hero.
        --}}
        <div class="mx-auto w-full max-w-shell px-4 py-16 sm:px-6 sm:py-20 md:py-24 lg:flex lg:min-h-[calc(100vh-162px)] lg:flex-col lg:justify-center lg:px-8 lg:py-28 2xl:max-w-[90rem]">
            <div class="max-w-2xl">
                <p class="inline-flex items-center gap-2 rounded-full border border-gold/40 bg-gold/10 px-3 py-1 text-2xs font-bold uppercase tracking-[0.18em] text-gold">
                    <x-icon name="pin" class="h-3.5 w-3.5" />
                    Barangay Linasin
                </p>

                <h1 class="mt-4 text-3xl font-extrabold leading-[1.15] tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Welcome to Barangay Linasin,<br class="hidden sm:block" />
                    Zone 1, Bangued, Abra
                </h1>

                <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-100 sm:text-base">
                    Your one-stop online platform for document requests, public information and
                    barangay services. Fast. Easy. Secure.
                </p>

                <div class="mt-6 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:gap-3">
                    <a href="{{ auth()->check() ? route('resident.requests.create') : route('register') }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/30 transition duration-200 ease-smooth hover:bg-blue-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 sm:w-auto sm:text-base">
                        <x-icon name="document" class="h-4 w-4 shrink-0" />
                        Request a Document
                    </a>

                    <a href="{{ route('services.index') }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/40 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition duration-200 ease-smooth hover:border-white/60 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 sm:w-auto sm:text-base">
                        <x-icon name="clipboard" class="h-4 w-4 shrink-0" />
                        View Services
                    </a>
                </div>
            </div>
        </div>

        {{-- Wave divider. Filled with the surface tone of the section that
             follows (not pure white) so the curve reads as a seamless handover
             rather than leaving a hairline seam. The path amplitude is close to
             the full 96-unit viewBox height so the swoop reads clearly instead
             of flattening into a near-straight edge once stretched. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-16 text-surface md:h-24 lg:h-32">
            <svg class="h-full w-full" viewBox="0 0 1440 96" preserveAspectRatio="none" fill="currentColor" focusable="false">
                <path d="M0,52 C200,104 440,8 720,36 C1000,64 1240,110 1440,60 L1440,96 L0,96 Z" />
            </svg>
        </div>

        {{-- Floating "Our Barangay" callout. Shown from xl up: at 1024-1279px the
             24rem card would collide with the max-w-2xl copy column, and below xl
             it must not compete with the copy or risk a 390px overflow. The 16:9
             header crop and the tighter padding keep the card a wide horizontal
             rectangle rather than a tall panel. --}}
        <div class="absolute -bottom-16 right-4 z-10 hidden w-[24rem] xl:right-8 xl:block">
            {{-- Slogan, centred over the card and tilted so it reads as a
                 handwritten accent across the landscape. The right-hand end of
                 the hero has almost no scrim (the directional overlay fades to
                 transparent there), so white text needs a tight shadow rather
                 than relying on the photograph behind it. --}}
            <p class="pointer-events-none mb-3 -rotate-3 text-center font-serif text-lg font-semibold italic leading-snug text-white drop-shadow-[0_2px_10px_rgba(2,6,23,0.9)]">
                &ldquo;Mas Maayos na Serbisyo<br />Para Sa Bawat Pamilya!&rdquo;
            </p>

            <article class="overflow-hidden rounded-2xl bg-white text-navy-900 shadow-2xl ring-1 ring-black/5">
                <div class="aspect-video w-full overflow-hidden bg-navy-50">
                    <img src="{{ $imageUrl('about') }}"
                        alt="The multi-purpose hall of Barangay Linasin Zone 1"
                        class="h-full w-full object-cover object-center" loading="lazy" decoding="async"
                        width="640" height="360" />
                </div>

                <div class="p-4">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-2xs font-bold uppercase tracking-[0.16em] text-blue-700">
                        <x-icon name="building" class="h-3 w-3 shrink-0" />
                        Our Barangay
                    </span>

                    <h2 class="mt-2.5 text-base font-bold leading-snug text-navy-900">
                        Barangay Hall - Bangued, Abra
                    </h2>

                    <p class="mt-1 text-2xs leading-relaxed text-navy-600/90">
                        Document requests are processed and released at the Barangay Hall.
                    </p>

                    <a href="{{ route('about') }}"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white transition duration-200 ease-smooth hover:bg-blue-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                        View Details
                        <x-icon name="arrow-right" class="h-3.5 w-3.5 shrink-0" />
                    </a>
                </div>
            </article>
        </div>
    </section>

    {{-- ============================================================
         2. QUICK SERVICES
         ============================================================
         Five feature cards pointing at the site's destinations, replacing the
         document catalogue that used to sit here. The individual document types
         now live only on /services, so this band is navigation rather than a
         partial duplicate of that page.

         The tone must stay "surface": the hero's wave divider is filled with
         text-surface, and changing this band to plain white would leave a visible
         seam along that curve. The id stays "services" so any inbound #services
         fragment keeps working.

         The heading is the first thing in the band: the "Quick Access" eyebrow
         and the one-line description that used to sit above it were dropped as
         noise, and the title now carries no top margin, so it starts exactly on
         the band's top padding.

         The "top" override tightens only this band's top padding, from the shared
         x-shell default of pt-14 sm:pt-16 lg:pt-20 down to pt-8 sm:pt-10 lg:pt-12,
         so the heading sits close under the wave instead of floating 80px below
         it at desktop. The bottom padding is left at the default so the gap
         before the announcements band is unchanged. A negative margin was
         rejected here: the hero is `relative isolate`, so it paints above the
         following band and a -mt would have slid the band behind the wave and
         eaten its silhouette rather than closing the gap.

         At xl and up, that 48px is not just a tighter gap, it is the alignment
         that positions the band. The floating "Our Barangay" callout hangs 64px
         below the hero's bottom edge and ends with 16px of padding under its
         "View Details" button, so that button's baseline sits 48px below the
         hero - exactly this band's top padding, and therefore exactly where the
         title's top edge lands. The two read as one horizontal line. A negative
         margin was rejected for the same reason as above.

         That 48px top padding is load-bearing on the hero above: the hero's
         min-h-[calc(100vh-162px)] is derived from it. The two must stay in step.

         Each card is one link rather than a title link plus a separate arrow
         link, so the entire target is clickable and the arrow is decorative.
         Each card carries an aria-label, because the visible text alone does not
         name the destination.
    --}}
    <x-shell id="services" tone="surface" top="pt-8 sm:pt-10 lg:pt-12">
        <x-section-heading align="left" title="Quick Services" class="text-left" />

        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
            @foreach (config('bdrpis.quick_services') as $quick)
                <a href="{{ route($quick['route']) }}"
                    aria-label="{{ $quick['title'] }} — {{ $quick['description'] }}"
                    class="group flex h-full flex-col rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition-shadow duration-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
                    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $quick['badge'] }}">
                        <x-icon :name="$quick['icon']" class="h-6 w-6" />
                    </span>

                    <h3 class="mt-4 text-base font-bold leading-snug text-navy-900">
                        {{ $quick['title'] }}
                    </h3>

                    <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600/80">
                        {{ $quick['description'] }}
                    </p>

                    <span class="mt-4 inline-flex items-center text-sky-700 transition group-hover:translate-x-0.5" aria-hidden="true">
                        <x-icon name="arrow-right" class="h-4 w-4 shrink-0" />
                    </span>
                </a>
            @endforeach
        </div>
    </x-shell>

    {{-- ============================================================
         3. FEATURED ANNOUNCEMENTS
         ============================================================
         The three most recent published notices. The paginated archive lives
         on /announcements.

         The header is a single left-aligned block: an eyebrow that only repeated
         the section's own name was removed, and both lines carry text-left
         explicitly rather than relying on inheriting from a parent, so the
         header cannot drift back to centred if this band is ever reused. --}}
    <x-shell id="announcements">
        <div class="flex flex-col gap-6 text-left sm:flex-row sm:items-end sm:justify-between">
            <x-section-heading align="left" title="Latest News and Announcements"
                description="Notices, schedules, and updates published by Barangay Linasin."
                class="text-left sm:max-w-xl" />

            <x-btn :href="route('announcements.index')" variant="ghost" size="md" icon="arrow-right" icon-position="right" class="shrink-0 self-start sm:self-auto">
                View all
            </x-btn>
        </div>

        <div class="mt-10">
            @if ($announcements->isEmpty())
                <x-empty-state icon="megaphone" title="No announcements yet"
                    description="When the barangay publishes a notice, it will appear here." class="mx-auto max-w-2xl" />
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($announcements as $announcement)
                        <x-announcement-card :announcement="$announcement" :href="route('announcements.index')" />
                    @endforeach
                </div>
            @endif
        </div>
    </x-shell>
</x-public-layout>