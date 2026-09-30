import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * A horizontal carousel over a native overflow-x track.
 *
 * Built on the platform's own scrolling rather than a slider library: the track
 * is a plain scroll container, so touch swiping, trackpad gestures, keyboard
 * scrolling and scroll anchoring all work without anything reimplementing
 * them. This only adds what the platform does not give us - previous/next
 * buttons, and a dot per card that tracks which card is at the left edge.
 *
 * The step is measured from the rendered slides rather than hard-coded, because
 * the card width changes across breakpoints: it is the distance between the
 * first two slides, which is the card plus the gap.
 */
Alpine.data('officialCarousel', (labels) => ({
    labels,
    active: 0,
    atStart: true,
    atEnd: false,

    get slides() {
        return this.$refs.track ? Array.from(this.$refs.track.children) : [];
    },

    /** Horizontal distance from one card to the next, gap included. */
    get step() {
        const [first, second] = this.slides;

        return second ? second.offsetLeft - first.offsetLeft : 0;
    },

    get maxScroll() {
        return this.$refs.track
            ? this.$refs.track.scrollWidth - this.$refs.track.clientWidth
            : 0;
    },

    init() {
        // Cards are laid out by the time the component initialises, but the
        // measurement waits a tick so a late web font cannot skew the step.
        this.$nextTick(() => this.sync());
    },

    /**
     * Re-read the track after any scroll, so a swipe and a button press leave
     * the dots and the disabled state telling the same story.
     */
    sync() {
        if (! this.$refs.track) {
            return;
        }

        const step = this.step;

        this.active = step
            ? Math.max(0, Math.min(this.labels.length - 1, Math.round(this.$refs.track.scrollLeft / step)))
            : 0;
        this.atStart = this.$refs.track.scrollLeft <= 2;
        this.atEnd = this.$refs.track.scrollLeft >= this.maxScroll - 2;
    },

    goTo(index) {
        if (! this.$refs.track) {
            return;
        }

        this.$refs.track.scrollTo({
            left: index * this.step,
            // A programmatic smooth scroll ignores the stylesheet's reduced
            // motion override, so the preference is honoured here instead.
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'auto'
                : 'smooth',
        });
    },

    next() {
        this.goTo(Math.min(this.active + 1, this.labels.length - 1));
    },

    prev() {
        this.goTo(Math.max(this.active - 1, 0));
    },
}));

Alpine.start();
