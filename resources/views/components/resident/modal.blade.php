@props([
    'name',
    'title',
    'maxWidth' => 'lg',
])

{{--
    A dialog for the resident area, built to the full behaviour a modal has to
    have rather than only to look like one.

    This exists because the shared modal is a visual shell: it opens, it closes
    and it traps Tab, but it never moves focus into the panel and never gives it
    back. A dialog that opens without focusing itself leaves a keyboard user's
    focus on the button behind it - on the page underneath, which is now covered
    by a backdrop. Returning focus matters for the same reason: closing a dialog
    and landing at the top of the document loses the reader's place.

    Behaviours, and what each is for:

      * Backdrop click closes, so a click aimed at nothing dismisses rather than
        doing nothing.
      * Escape closes, from anywhere, including from inside the panel.
      * Tab is cycled within the panel, so focus cannot walk onto the covered page.
      * The body scroll is locked while open, so the page underneath does not
        move under the dialog on a wheel or a swipe.
      * Focus moves in on open and returns to the trigger on close.
      * x-cloak plus an inline display:none, so it is not on the page at all before
        Alpine has booted. Without that there is a flash of the dialog on every
        load - which is what a panel rendered outside its own x-data scope does.
--}}
<div
    x-data="{
        open: false,
        trigger: null,
        show() {
            this.trigger = document.activeElement
            this.open = true
            document.body.classList.add('overflow-y-hidden')
            this.$nextTick(() => this.$refs.panel.focus())
        },
        hide() {
            this.open = false
            document.body.classList.remove('overflow-y-hidden')
            if (this.trigger) { this.trigger.focus(); this.trigger = null }
        },
        focusables() {
            const selector = 'a[href], button:not([disabled]), input:not([type=\"hidden\"]), textarea, select, [tabindex]:not([tabindex=\"-1\"])'
            return [...this.$refs.panel.querySelectorAll(selector)].filter(el => el.offsetParent !== null)
        },
        onTab(e) {
            const items = this.focusables()
            if (items.length === 0) { e.preventDefault(); return }
            const first = items[0], last = items[items.length - 1]
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
            else if (! e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
        },
    }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') show()"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') hide()"
    x-on:keydown.escape.window="if (open) hide()"
    x-show="open"
    x-cloak
    style="display: none;"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
>
    {{-- The backdrop. A click target, not content, so it is not named: naming it
         would put a second anonymous region in the accessibility tree. --}}
    <div
        x-show="open"
        x-on:click="hide()"
        class="fixed inset-0 transform transition-opacity duration-200"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-slate-900/60"></div>
    </div>

    <div class="relative flex min-h-full items-center justify-center">
        <div
            x-ref="panel"
            x-show="open"
            x-on:keydown.tab="onTab($event)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $name }}-title"
            tabindex="-1"
            class="relative w-full {{ $maxWidth === 'md' ? 'sm:max-w-md' : 'sm:max-w-lg' }} overflow-hidden rounded-2xl bg-white shadow-2xl outline-none"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        >
            <button type="button" x-on:click="hide()"
                class="absolute right-3 top-3 z-10 inline-flex h-9 w-9 items-center justify-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400">
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M18 6L6 18" />
                </svg>
            </button>

            {{ $slot }}
        </div>
    </div>
</div>
