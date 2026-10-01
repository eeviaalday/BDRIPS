@props([
    'title',
    'description' => null,
    'crumbs' => [],
    'class' => '',
])

@php
    /*
     * The banner every inner public page opens with, shared by one component
     * so the services, about, announcements, contact, directory, transparency
     * and request-tracking pages cannot drift apart.
     *
     * The photograph is resolved through the same public image map the rest of
     * the site uses, and checked against the disk, so a missing file falls back
     * to the neutral hall artwork rather than leaving a broken banner.
     *
     * "crumbs" holds the trail BELOW the title, oldest first, as plain labels.
     * Only the last one is the current page, so the markup cannot mark a link as
     * current that leads away from the page being read, and every crumb is a
     * real trail rather than decorative text. Home is prepended here because
     * every public page hangs off it.
     */
    $photograph = config('bdrpis.images.page_banner');

    if (! filled($photograph) || ! is_file(public_path($photograph))) {
        $photograph = config('bdrpis.image_fallbacks.page_banner');
    }
@endphp

{{--
      Banner for every inner public page (/services, /about, /announcements,
      /contact, /directory, /transparency). One component so the six banners
      cannot drift apart in height, padding or alignment.

    LAYOUT: one section, full bleed. The photograph is an absolutely positioned
    layer behind everything and the content is a single left-aligned column
    above it - no grid, no columns, nothing beside the text. A letterboxed or
    two-column arrangement reads as a dark box with a picture stuck to it, which
    is not what this is.

    BACKGROUND: object-cover, so the photograph spans the whole band. The photo
    is 1919x820 and the band is roughly 4:1, so cover always takes a horizontal
    slice of it, and the slice has to be chosen. Measured on this photograph,
    the top half is open sky and the building runs from about 50% down to 95%,
    with the strongest detail - the signage band - at 85-95%. Centring the crop
    keeps rows 22-77%, which shows sky and cuts the sign off the bottom.
    object-[center_72%] keeps rows 45-100% instead: the roofline and the
    "BRGY ZONE 1 / MULTI-PURPOSE HALL" sign are both inside the frame, and only
    sky is cropped. Horizontally the image scales to exactly the band width, so
    there is no horizontal slack to position.

    OVERLAY: one gradient across the whole band - dense slate where the white
    headline, breadcrumb and description sit, thinning to fully clear on the
    right so the hall is unobstructed. The section keeps a background colour
    underneath so nothing shows white while the photograph loads.

    HEIGHT: a 300px band, with a 360px backstop. The floor is what makes the
    pages match - content of different lengths is centred inside the same band
    rather than producing seven different heights, and at 375px and up nothing
    comes near it. The ceiling only exists so a pathological title or
    description cannot turn the banner into a wall; it sits above the tallest
    real case, which is this page's two buttons wrapping on a 320px phone at
    348px, because a ceiling below that would clip content rather than bound it.

    ALIGNMENT: strictly left. The content lives in a left-bound column under a
    max-w-shell wrapper, and the section is a row flex whose only in-flow child
    is that wrapper - so items-center here centres the block vertically, which
    is what a banner wants, without ever centring the text inside it.
--}}

<section {{ $attributes->merge(['class' => trim('relative isolate flex min-h-[300px] max-h-[360px] items-center overflow-hidden bg-slate-900 text-white '.$class)]) }}>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <img src="{{ asset($photograph) }}" alt=""
            class="h-full w-full object-cover object-[center_72%]" loading="lazy" decoding="async"
            width="1919" height="820" />
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-900/60 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-gold/50 to-transparent"></div>
    </div>

    <div class="mx-auto w-full max-w-shell px-4 py-10 text-left sm:px-6 md:px-8 md:py-14">
        <div class="max-w-3xl text-left">
            <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-[2.75rem] lg:leading-[1.15]">
                {{ $title }}
            </h1>

            <nav aria-label="Breadcrumb" class="mt-3">
                {{-- items-center lines the separators up with the crumb text
                     inside the row; it does not centre the row itself, which
                     stays flush with the left edge of this column. --}}
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-slate-300">
                    <li>
                        <a href="{{ route('home') }}"
                            class="rounded transition hover:text-gold focus:outline-none focus-visible:text-gold">
                            Home
                        </a>
                    </li>

                    @foreach ($crumbs as $crumb)
                        <li aria-hidden="true" class="text-slate-500">&gt;</li>
                        <li @if ($loop->last) aria-current="page" @endif>
                            <span @class([
                                'rounded',
                                'text-white font-semibold' => $loop->last,
                                'text-slate-300' => ! $loop->last,
                            ])>{{ $crumb }}</span>
                        </li>
                    @endforeach
                </ol>
            </nav>

            @if ($description)
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-200 sm:text-lg">{{ $description }}</p>
            @endif
        </div>

        @if (! $slot->isEmpty())
            {{-- Under the description, inside the same left-bound column, and
                 tight: the band is 300px, and this block is the one thing that
                 could otherwise push its page past the height every other page
                 sits on. --}}
            <div class="mt-4 flex flex-wrap items-start gap-2 text-left">{{ $slot }}</div>
        @endif
    </div>
</section>
