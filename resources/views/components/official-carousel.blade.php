@props([
    'title',
    'eyebrow' => null,
    'description' => null,
    'people' => [],
    'class' => '',
])

    @php
        /*
         * A row of official cards that slides horizontally.
         *
         * The track is a native overflow-x container, so a touch swipe, a
         * trackpad gesture or the keyboard all work without a slider library
         * reimplementing them. The arrows and the dots are Alpine, and the step
         * between cards is measured from the rendered slides at runtime because
         * the card width changes across breakpoints.
         *
         * One dot per card rather than one per "page": with a measured step and
         * a width that shifts per breakpoint, a dot per card is the only index
         * that is correct at every width, and the dot for the card at the left
         * edge is marked as current.
         *
     * Cards are the portrait card, whose picture is a drawn placeholder avatar
     * from public/images/avatar/ rather than the person named - see
     * config/bdrpis.php.
     */
        $labels = array_column($people, 'name');
    @endphp

<div x-data="officialCarousel(@js($labels))" {{ $attributes->merge(['class' => trim($class)]) }}>
    {{-- Header on the site's standard centred pattern: eyebrow, title,
         description. The controls live below the track instead of beside the
         title, because a heading centred over the band and a button pinned to
         its right edge pull in two directions at once. --}}
    <x-section-heading :eyebrow="$eyebrow" :title="$title" :description="$description" />

    {{-- The track. snap-mandatory is what makes a swipe land on a card rather
         than between two, and tabindex makes it reachable with the keyboard. --}}
    <div x-ref="track" @scroll="sync()" tabindex="0" role="region"
        aria-label="{{ $title }}, {{ count($people) }} officials"
        class="mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto py-2 scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($people as $official)
            {{-- 1 card on a phone, 2 from sm, 3 from lg, 4 from xl. --}}
            <div class="flex w-[78%] shrink-0 grow-0 snap-start sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.667rem)] xl:w-[calc(25%-0.75rem)]">
                <x-official-portrait-card :name="$official['name']" :position="$official['role']"
                    :photo="$official['photo'] ?? null" />
            </div>
        @endforeach
    </div>

    {{-- One control row: dots centred under the track, arrows flanking them so
         the two read as a pair. Shown at every width, because a swipe is not
         the only way to move a row of cards and the arrows are the control a
         keyboard and a screen reader can reach. Disabled at each end rather
         than wrapping, so a dot's position always means the same card. --}}
    <div class="mt-5 flex items-center justify-center gap-4">
        <button type="button" @click="prev()" :disabled="atStart" aria-label="Show the previous official in {{ $title }}"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-navy-200 bg-white text-lg leading-none text-navy-700 shadow-card transition duration-200 ease-smooth hover:border-navy-300 hover:bg-navy-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-navy-200 disabled:hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
            <span aria-hidden="true">&lsaquo;</span>
        </button>

        <div class="flex items-center gap-2">
            <template x-for="(label, index) in labels" :key="label">
                <button type="button" @click="goTo(index)" :aria-label="`Show ${label}`"
                    :aria-current="index === active ? 'true' : null"
                    :class="index === active ? 'w-6 bg-navy-700' : 'w-2 bg-navy-200 hover:bg-navy-400'"
                    class="h-2 rounded-full transition-all duration-200 ease-smooth focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2"></button>
            </template>
        </div>

        <button type="button" @click="next()" :disabled="atEnd" aria-label="Show the next official in {{ $title }}"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-navy-200 bg-white text-lg leading-none text-navy-700 shadow-card transition duration-200 ease-smooth hover:border-navy-300 hover:bg-navy-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-navy-200 disabled:hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
            <span aria-hidden="true">&rsaquo;</span>
        </button>
    </div>

    {{-- Swiping is the primary control on a phone, where the arrows are hidden,
         so the region is described rather than left silent. --}}
    <p class="sr-only" aria-live="polite">
        Showing {{ count($people) }} officials. Swipe the row, or use the arrow buttons, to move between them.
    </p>
</div>
