<x-public-layout>
    <x-slot name="title">About</x-slot>

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

        /*
         * The "Find Us" map. All three URLs are built from the configured
         * coordinates so the frame, the marker drawn over its centre and the
         * directions link all point at the same spot.
         *
         * disableDefaultUI is requested, but the keyless embed endpoint honours
         * it inconsistently: where Google's own controls survive, they land in
         * the two corners the badge and the marker already occupy.
         */
        $mapCoordinates = config('bdrpis.about.map.lat').','.config('bdrpis.about.map.lng');
        $mapEmbedUrl = 'https://maps.google.com/maps?q='.$mapCoordinates
            .'&z='.config('bdrpis.about.map.zoom')
            .'&hl=en&disableDefaultUI=true&output=embed';
        $mapDirectionsUrl = 'https://www.google.com/maps/dir/?api=1&destination='.$mapCoordinates;
    @endphp

    {{-- ============================================================
         ABOUT PAGE
         ============================================================
         Carries the history / vision / mission copy and the demographic profile
         that used to sit on the homepage as a single inline section, plus the
         community activities gallery that also used to live there.

         Banner: the shared <x-page-header>, so every inner page opens with the
         same photograph, gradient and breadcrumb trail.
    --}}

    <x-page-header :crumbs="['About Us']" title="About Our Barangay"
        description="Barangay Linasin is a rural community in Bangued, Abra, served online through the Barangay Directory and Document Request and Public Information System." />

    {{-- History / vision / mission + demographics --}}
    <x-shell>
        {{-- The band's own header, on the same centred eyebrow / title /
             description pattern as every other section on the site. Named for
             what the grid below actually holds, because the banner above
             already titles the page "About Our Barangay" and repeating it here
             read as a duplicate. --}}
        <x-section-heading align="center" eyebrow="Overview" title="Barangay Profile & Background"
            description="Discover the history, vision, mission, and key demographics of Barangay Linasin, Zone 1." />

        {{-- items-start so nothing in the row is stretched to the tallest
             column. Without it the photograph was pulled down to the height of
             the history column, and the picture sat in a bordered card with
             empty space under it. --}}
        <div class="mt-12 grid items-start gap-8 lg:grid-cols-12">
            {{-- Far left: photograph. Height is left to the file - h-auto with a
                 cap, and object-cover, so a wider photograph crops into the
                 frame instead of being squashed, and the card is exactly as tall
                 as the picture in it. Rounding and the shadow come from the
                 figure, which clips them: putting them on the image as well
                 would draw a second shadow inside the border. --}}
            <figure class="w-full max-w-xs overflow-hidden rounded-2xl border border-navy-100 shadow-card-hover lg:col-span-4 xl:col-span-3">
                <img src="{{ asset('images/barangay-hall.png') }}"
                    alt="The multi-purpose hall of Barangay Linasin Zone 1, where barangay services are delivered"
                    class="h-auto w-full max-h-80 object-cover object-center md:max-h-96" loading="lazy" decoding="async"
                    width="885" height="885" />
            </figure>

            {{-- Middle: history / vision / mission. Heading and body carry
                 their own spacing, so the space-y rhythm below is the only
                 thing setting the gap between blocks. The privacy notice sits
                 outside that rhythm, where its margin is not overridden. --}}
            <div class="lg:col-span-8 xl:col-span-6">
                <div class="space-y-5">
                    @foreach ([
                        config('bdrpis.about.history'),
                        config('bdrpis.about.vision'),
                        config('bdrpis.about.mission'),
                    ] as $block)
                        <div>
                            <h2 class="mb-1.5 text-lg font-bold text-slate-900 md:text-xl">{{ $block['title'] }}</h2>
                            <p class="mb-4 text-sm leading-relaxed text-slate-600 md:text-base">{{ $block['body'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Retained: this is a Data Privacy Act notice, not design
                     copy, so it is not dropped in the relocation. --}}
                <x-alert variant="privacy" icon="lock" class="mt-8">
                    <span class="font-semibold">Data Privacy.</span>
                    Personal data is handled in accordance with the Data Privacy Act of 2012 (R.A. 10173).
                </x-alert>
            </div>

            {{-- Far right: demographic statistics --}}
            <aside class="lg:col-span-12 xl:col-span-3">
                <div class="mx-auto w-full max-w-sm rounded-2xl border border-blue-100 bg-blue-50/50 p-5 sm:p-6">
                    <h2 class="text-lg font-extrabold text-navy-900">
                        {{ config('bdrpis.demographics.name') }}
                    </h2>

                    <p class="mt-1.5 flex items-center gap-1.5 text-sm font-medium text-navy-600">
                        <x-icon name="pin" class="h-4 w-4 shrink-0 text-navy-500" />
                        {{ config('bdrpis.demographics.location') }}
                    </p>

                    <dl class="mt-5 border-t border-blue-100">
                        @foreach (config('bdrpis.demographics.stats') as $stat)
                            <div class="flex items-center gap-3 border-b border-blue-100 py-3 last:border-b-0 last:pb-0">
                                <dt class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-navy-700 ring-1 ring-blue-100">
                                        <x-icon :name="$stat['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="text-sm font-medium text-navy-600">{{ $stat['label'] }}</span>
                                </dt>
                                <dd class="text-base font-extrabold tabular-nums text-navy-900">{{ $stat['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-5 text-2xs leading-relaxed text-navy-500">
                        Figures are indicative barangay profile data and are not updated live.
                    </p>
                </div>
            </aside>
        </div>
    </x-shell>


    {{-- Community activities, relocated from the homepage --}}
    <x-shell tone="surface">
        <x-section-heading eyebrow="Community" title="Community Activities"
            description="The kinds of programmes a barangay community runs day to day, from clean-up drives to health and youth activities." />

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('bdrpis.activities') as $activity)
                <article class="group flex flex-col overflow-hidden rounded-xl border border-navy-100 bg-white shadow-card transition duration-200 hover:-translate-y-1 hover:shadow-card-hover">
                    <div class="overflow-hidden bg-navy-50">
                        <img src="{{ $imageUrl($activity['image']) }}"
                            alt="{{ $activity['alt'] }}"
                            class="aspect-[16/9] w-full object-cover object-center transition duration-300 group-hover:scale-[1.03]"
                            loading="lazy" decoding="async" width="1600" height="900" />
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="text-base font-bold text-navy-900">{{ $activity['title'] }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600/80">{{ $activity['description'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-navy-500">
            Photographs are of Barangay Linasin community activities. The programme categories shown
            above are general examples and do not refer to a specific dated event.
        </p>
    </x-shell>

    {{-- ============================================================
         OFFICIALS
         ============================================================
         The two councils, each in its own carousel, embedded here rather than
         behind a "view the full directory" link: a visitor reading the about
         page should not have to leave it to meet their officials.

         No heading above them. The two carousels carry their own eyebrow and
         title - Leadership / Barangay Officials, and Youth Council / SK Council
         - and a third block saying "Barangay Officials & Directory" on top of
         two subsections whose own headings already say that was saying it twice.

         Title escaping, for the record: the ampersand in a heading is written as
         a plain &, never &amp;. A Blade component attribute carrying an entity
         passes the literal string "&amp;" through to {{ }}, which escapes the
         ampersand again and the page then shows "&amp;" on screen.
    --}}
    <x-shell id="officials" class="scroll-mt-16 lg:scroll-mt-20">
        {{-- scroll-mt offsets the anchor target by the height of the sticky
             navbar, so following a link to #officials lands on the section rather
             than tucking its first heading underneath the bar. It changes where the
             page stops when you arrive, not how it looks. --}}
        <div class="space-y-14">
            @foreach (['barangay', 'sk'] as $group)
                <x-official-carousel
                    :eyebrow="config('bdrpis.officials.'.$group.'.eyebrow')"
                    :title="config('bdrpis.officials.'.$group.'.title')"
                    :description="config('bdrpis.officials.'.$group.'.description')"
                    :people="config('bdrpis.officials.'.$group.'.people')" />
            @endforeach
        </div>

        {{-- ============================================================
             EXECUTIVE, THE PUBLIC INFORMATION LINE, AND THE SECTOR KAGAWADS
             ============================================================
             These three blocks used to be the whole of a separate /directory
             page. They are here now, unchanged in markup and reading order, and
             /directory redirects to this section - so nothing that linked to the
             old page lands anywhere that has lost the information.

             The two carousels above stay first and stay as they were: they are
             the About page's own officials content, and the blocks below were
             always the supplementary detail, not a replacement for them.

             Data sources are unchanged. The Punong Barangay, the Secretary and
             the hotline come from the settings table, so the admin Settings
             screen still edits them. The sector cards are read live from the
             sectors table with its kagawad relation, so a Kagawad appointed in
             the admin area appears here without a template edit - and a sector
             with nobody assigned still says so rather than showing a blank.
             ============================================================ --}}

        <section aria-labelledby="executive-heading" class="mt-14">
            <h2 id="executive-heading" class="sr-only">Barangay Executive</h2>

            <x-section-heading eyebrow="Leadership" title="Barangay Executive"
                description="The Punong Barangay, the Barangay Secretary, and the public information line." />

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <x-official-card featured
                    :name="$officials['punong_barangay']"
                    position="Punong Barangay"
                    meta="Village head & chief executive of the barangay" />

                <x-official-card featured
                    :name="$officials['secretary']"
                    position="Barangay Secretary"
                    meta="Records, documents & resident verification" />
            </div>

            <div class="mt-6 flex flex-col gap-4 rounded-xl border border-red-100 bg-red-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white text-red-600 shadow-card">
                        <x-icon name="phone" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-700">Emergency Hotline</p>
                        <p class="mt-0.5 text-sm text-red-700/80">Public information line for the barangay.</p>
                    </div>
                </div>

                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $officials['hotline']) }}"
                    class="text-xl font-extrabold text-red-800 underline underline-offset-4 sm:text-2xl">
                    {{ $officials['hotline'] }}
                </a>
            </div>
        </section>

        <section class="mt-14" aria-labelledby="kagawad-heading">
            <h2 id="kagawad-heading" class="sr-only">Sector Kagawads</h2>

            <x-section-heading eyebrow="Sectors" title="Sector Kagawads"
                description="Each sector is handled by a kagawad who assists residents of that purok." />

            @if ($sectors->isEmpty())
                <x-empty-state icon="users" title="Sector directory is being established"
                    description="Sector assignments have not been published yet. Please check back later." class="mt-8" />
            @else
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sectors as $sector)
                        <x-card tone="white" padding="p-5" class="flex flex-col">
                            <div class="flex items-center gap-3.5">
                                @php
                                    $kagawadName = $sector->kagawad?->name ?? 'Vacant';
                                    $initials = \Illuminate\Support\Str::of($kagawadName)
                                        ->replace(['Hon.', 'Hon', 'Sir', 'Ms', 'Mrs'], ' ')
                                        ->squish()->explode(' ')->filter()->take(2)
                                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
                                    $initials = $initials !== '' ? $initials : '?';
                                @endphp

                                <span @class([
                                    'inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-extrabold ring-4',
                                    'bg-navy-50 text-navy-700 ring-navy-100' => $sector->kagawad,
                                    'bg-sky-50 text-sky-700 ring-sky-100' => ! $sector->kagawad,
                                ])>
                                    {{ $initials }}
                                </span>

                                <div class="min-w-0">
                                    <p class="text-base font-bold leading-snug text-navy-900">{{ $kagawadName }}</p>
                                    <p class="text-sm font-semibold {{ $sector->kagawad ? 'text-sky-700' : 'text-navy-500' }}">
                                        {{ $sector->kagawad ? 'Sector Kagawad' : 'Unassigned' }}
                                    </p>
                                </div>
                            </div>

                            <dl class="mt-4 space-y-2 border-t border-navy-100 pt-4 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-navy-500">Sector</dt>
                                    <dd class="text-right font-semibold text-navy-900">{{ $sector->sector_name }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-navy-500">Purok</dt>
                                    <dd class="text-right font-semibold text-navy-900">{{ $sector->purok_name ?? 'Not set' }}</dd>
                                </div>
                            </dl>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </section>
    </x-shell>
    {{-- ============================================================
         FIND US
         ============================================================
         The last band on the page, directly above the footer: everything above
         it is about the barangay, and this is the one thing a reader may want
         to act on.

         The map is Leaflet over OpenStreetMap tiles rather than a Google iframe,
         for one reason - the Google Maps JS API only renders with a key, and a
         key in the markup of a public page is a key anyone can lift, while OSM
         tiles need no account. The marker is a real geographic marker built
         from a divIcon at the barangay's coordinates, so it stays on the hall
         when the map is panned or zoomed. A CSS element centred over a frame
         cannot do that: it drifts off the barangay on the first pan.

         Controls are trimmed to what a visitor needs: no scroll-wheel zoom,
         which would otherwise swallow the page's scrolling on a long read, and
         the zoom buttons moved to the top right so the bottom edge carries
         nothing but the attribution. That attribution is required by the
         Open Database License and stays.

         The action bar puts the three things a visitor actually wants beside
         the map: reach the barangay, copy the address to paste into a message,
         or get turn-by-turn directions (which still go to Google Maps - it is
         the better product for a phone, and a directions link needs no key).

         The copy button prefers navigator.clipboard and falls back to a
         selection-and-execCommand copy, which is what Safari on http and older
         browsers still need. The confirmation is announced as well as shown.
    --}}
    <x-shell id="find-us" tone="surface">
        <x-section-heading eyebrow="Location" title="Find Us"
            description="Barangay Linasin, {{ config('bdrpis.about.map.address') }}. The Barangay Hall is the drop-off point for every document the portal issues." />

        <div class="mt-8 overflow-hidden rounded-3xl bg-slate-900 p-4 shadow-xl">
            <div class="relative overflow-hidden rounded-2xl">
                {{-- The map reads its settings from data attributes so the
                     script below stays a plain script and the location stays
                     editable in config. --}}
                <div
                    id="find-us-map"
                    class="h-80 w-full bg-navy-50 sm:h-[26rem]"
                    role="application"
                    aria-label="Map of Barangay Linasin, {{ config('bdrpis.about.map.address') }}"
                    data-lat="{{ config('bdrpis.about.map.lat') }}"
                    data-lng="{{ config('bdrpis.about.map.lng') }}"
                    data-zoom="{{ config('bdrpis.about.map.zoom') }}"
                    data-tiles="{{ config('bdrpis.about.map.tile_url') }}"
                    data-tile-max-zoom="{{ config('bdrpis.about.map.tile_max_zoom') }}"
                    data-logo="{{ asset('images/Zone 1_logo.png') }}"
                    data-name="Barangay Linasin"></div>

                {{-- Top-left badge, in the corner a map's own "view larger map"
                     control would occupy. Decorative layer: the place is stated
                     in the bar below in words as well. --}}
                <div class="pointer-events-none absolute left-4 top-4 z-[500] flex items-center gap-2 rounded-xl bg-slate-900/90 px-4 py-2 text-sm font-semibold text-white backdrop-blur-sm">
                    <span aria-hidden="true">📍</span>
                    Zone 1 Barangay Hall
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl bg-slate-900 p-4 text-white">
                <p class="flex items-center gap-2 text-sm font-medium text-slate-200">
                    <span aria-hidden="true">📍</span>
                    {{ config('bdrpis.about.map.short_address') }}
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ $officeFacebook }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/25 px-4 py-2 text-sm font-semibold text-white transition duration-200 ease-smooth hover:border-gold hover:text-gold focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Contact Us
                        <span class="sr-only">(opens the barangay's Facebook page in a new tab)</span>
                    </a>

                    {{-- Copies the address rather than opening a mail client: the
                         visitor is most often pasting it into a message. --}}
                    <button type="button"
                        x-data="{
                            copied: false,
                            timer: null,
                            confirm() {
                                this.copied = true;
                                clearTimeout(this.timer);
                                this.timer = setTimeout(() => { this.copied = false; }, 2000);
                            },
                            legacyCopy(text) {
                                const field = document.createElement('textarea');
                                field.value = text;
                                field.setAttribute('readonly', '');
                                field.style.position = 'fixed';
                                field.style.opacity = '0';
                                document.body.appendChild(field);
                                field.select();
                                try { document.execCommand('copy'); this.confirm(); } catch (e) { /* no fallback left to try */ }
                                document.body.removeChild(field);
                            },
                            async copy(text) {
                                if (navigator.clipboard?.writeText) {
                                    try {
                                        await navigator.clipboard.writeText(text);
                                        this.confirm();
                                        return;
                                    } catch (e) { /* permission denied: fall through */ }
                                }

                                this.legacyCopy(text);
                            },
                        }"
                        @click="copy(@js($officeEmail))"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/25 px-4 py-2 text-sm font-semibold text-white transition duration-200 ease-smooth hover:border-gold hover:text-gold focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        <span aria-live="polite" x-text="copied ? 'Copied!' : 'Email'">Email</span>
                    </button>

                    <a href="{{ $mapDirectionsUrl }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-full bg-gold px-4 py-2 text-sm font-semibold text-navy-900 transition duration-200 ease-smooth hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Get Directions
                        <span class="sr-only">(opens Google Maps directions in a new tab)</span>
                    </a>
                </div>
            </div>
        </div>

        <noscript>
            <p class="mt-4 text-sm text-navy-600">
                The interactive map needs JavaScript. The barangay is at
                {{ config('bdrpis.about.map.address') }} - use “Get Directions”
                above for turn-by-turn directions.
            </p>
        </noscript>
    </x-shell>

    @push('head')
        {{-- Leaflet 1.9.4, pinned, from a CDN: it is the only page that needs
             it, so it is not in the bundle every visitor downloads. --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    @endpush

    @push('head')
        <script>
            /*
             * Runs on DOMContentLoaded rather than inline, because Leaflet is
             * deferred: an inline script written next to a deferred script would
             * execute first, before Leaflet exists.
             *
             * Nothing here is a positioning trick. The marker is created at
             * [lat, lng] and Leaflet keeps it projected onto those coordinates
             * through every pan, drag and zoom.
             */
            document.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('find-us-map');

                // Leaflet is served from a CDN, so it can fail to load on a
                // restricted connection. The canvas, the badge and the action
                // bar are all still there; the directions link still works.
                if (! canvas || typeof L === 'undefined') {
                    return;
                }

                const position = [parseFloat(canvas.dataset.lat), parseFloat(canvas.dataset.lng)];

                const map = L.map(canvas, {
                    center: position,
                    zoom: parseInt(canvas.dataset.zoom, 10),
                    // Scroll-wheel zoom would swallow the scrolling of the page
                    // this map sits on; drag, pinch and the buttons still work.
                    scrollWheelZoom: false,
                    zoomControl: false,
                    attributionControl: true,
                });

                L.control.zoom({ position: 'topright' }).addTo(map);

                L.tileLayer(canvas.dataset.tiles, {
                    maxZoom: parseInt(canvas.dataset.tileMaxZoom, 10),
                    // Required by the Open Database License: the tile source has
                    // to be credited on the map.
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                }).addTo(map);

                // A divIcon, so the marker is the barangay's own logo rather
                // than a generic pin. The pulsing ring is inside the icon's own
                // box, so it travels with the marker instead of the page.
                const icon = L.divIcon({
                    className: '',
                    html: '<div class="relative flex h-10 w-10 items-center justify-center">'
                        + '<span class="absolute -inset-2 rounded-full bg-blue-500/50 animate-ping"></span>'
                        + '<img src="' + canvas.dataset.logo + '" alt="" class="relative z-20 h-10 w-10 rounded-full border-2 border-white bg-white p-0.5 object-cover shadow-lg">'
                        + '</div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20],
                    popupAnchor: [0, -20],
                });

                L.marker(position, { icon: icon, title: canvas.dataset.name })
                    .addTo(map)
                    .bindPopup(canvas.dataset.name);
            });
        </script>
    @endpush
</x-public-layout>
