@props([
    'name' => 'app',
    // How long it stays before dismissing itself, in milliseconds. Long enough to
    // read the longest message this is used for twice.
    'duration' => 9000,
])

{{--
    A transient message for something a resident just tried to do.

    This exists because the resident area had three ways to answer a press of a
    button and only two of them were honest. A control that is present, looks
    live, and then does nothing at all is the worst of the three: the resident
    cannot tell a broken page from a rule they have hit, so they press it again,
    and then again. The lock dialog answers the unverified case, so the button
    did open something - but for a resident at their slot limit the button
    dispatched an event no listener was mounted for, and the press was simply
    swallowed. A toast says the answer out loud, in the place the resident is
    already looking.

    Behaviours, and what each is for:

      * It is a live region, so the message is announced rather than only being
        visible. A resident using a screen reader has no visual cue that
        anything happened at all, which is the original defect in another form.
      * It dismisses itself, because the information is transient by nature: it
        explains one refusal and the resident's next move is to wait. A toast
        that had to be dismissed by hand would become the thing they had to
        clear before doing anything else.
      * It carries its own close button as well. Auto-dismiss is a kindness for
        the common case, not a decision made on the reader's behalf: somebody
        reading the wording carefully, or using a screen reader at their own
        pace, must be able to end it now.
      * It is fixed to the top right and sits above the navbar and the dialogs
        (z-[60], over the z-50 both of those use), so it cannot end up behind an
        overlay that was opened by the same press, nor clipped by the page
        container.
      * Left-aligned and full-width on a narrow screen rather than pinned to the
        right edge, where it would run off the side of a 400px viewport.

    Opened by dispatching show-toast on the window with a detail payload:

        $dispatch('show-toast', { name: 'app', title: 'Request limit reached', message: '…' })

    The name check is what lets one page carry more than one of these without
    them all reacting to the same press.
--}}
<div
    x-data="{
        visible: false,
        title: '',
        message: '',
        timer: null,
        show(detail) {
            this.title = detail.title || ''
            this.message = detail.message || ''
            this.visible = true
            if (this.timer) clearTimeout(this.timer)
            this.timer = setTimeout(() => { this.visible = false }, {{ (int) $duration }})
        },
        hide() {
            this.visible = false
            if (this.timer) clearTimeout(this.timer)
        },
    }"
    x-on:show-toast.window="if (($event.detail || {}).name === '{{ $name }}') show($event.detail)"
    x-on:keydown.escape.window="if (visible) hide()"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex justify-center px-4 pt-4 sm:inset-x-auto sm:right-0 sm:justify-end sm:px-6 sm:pt-6"
>
    <div
        x-show="visible"
        x-cloak
        x-transition:enter="transform ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        role="status"
        aria-live="polite"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-gold-200 bg-white p-4 shadow-xl"
    >
        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gold-100 text-gold-700">
            <x-icon name="lock" class="h-4 w-4" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-navy-900" x-text="title"></p>
            <p class="mt-1 text-sm leading-relaxed text-navy-700" x-text="message"></p>
        </div>

        {{-- Not sr-only: the dismiss control is a real button, so the label is
             spoken from the accessible name rather than read out separately. --}}
        <button type="button" x-on:click="hide()"
            class="-m-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400">
            <span class="sr-only">Dismiss notification</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>