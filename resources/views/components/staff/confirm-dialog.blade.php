@props([
    'name',
    'title',
    'message',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    // 'danger' for anything irreversible (rejecting, deleting, unpublishing).
    // 'primary' for merely consequential.
    'tone' => 'primary',
    'form',
    'method' => 'POST',
])

@php
    /*
     * The staff-area confirmation dialog. A drop-in replacement for the shared
     * x-confirm-dialog, which stays byte-for-byte unchanged.
     *
     * It exists for two things the shared one does not do.
     *
     * 1. IT PASSES `focusable` TO x-modal. Without that attribute the shared
     *    x-modal's own x-init skips the focus() call entirely:
     *
     *        {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
     *
     *    so a staff dialog opened with the keyboard left focus on the page behind
     *    it. That is a dialog a keyboard user has to hunt for: the content is
     *    announced by nothing, and Tab continues from wherever they happened to be.
     *    With it, firstFocusable() resolves in document order, and because the slot
     *    renders ABOVE the buttons, that is the first field of the action - a
     *    reason box, a role select, a sector name - and the Cancel button when the
     *    dialog has no input at all.
     *
     * 2. IT GIVES FOCUS BACK. x-modal hides itself on Escape but never returns
     *    focus, so dismissing a dialog dropped the user at the top of the document
     *    with no idea where they had been. This remembers the element that opened
     *    it - which still has focus at that moment, because x-modal waits 100ms
     *    before moving focus into the panel - and restores it on Escape, on Cancel
     *    and on a programmatic close-modal.
     *
     * Everything else is deliberately identical to the shared component, so a
     * caller can be switched by changing one tag name and nothing else. Notably it
     * still owns the one and only <form> for the action, so a trigger outside it
     * carries no payload and an action's inputs cannot be posted under another
     * field's name.
     *
     * The wrapper is display:contents, so it creates no box and changes no layout
     * around a position:fixed modal.
     */
    $tones = [
        'primary' => 'bg-navy hover:bg-navy-800 focus-visible:outline-navy-400',
        'danger' => 'bg-red-600 hover:bg-red-700 focus-visible:outline-red-400',
    ];
@endphp

<div class="contents"
    x-data="{
        trigger: null,
        remember() { this.trigger = document.activeElement },
        restore() {
            const was = this.trigger
            this.trigger = null
            if (was && document.body.contains(was)) { was.focus() }
        },
    }"
    x-on:open-modal.window="$event.detail === @js($name) ? remember() : null"
    x-on:close-modal.window="$event.detail === @js($name) ? restore() : null"
    x-on:staff-dialog-closed="restore()"
    x-on:keydown.escape.window="restore()">

    <x-modal :name="$name" maxWidth="md" focusable>
        <form method="POST" action="{{ $form }}">
            @csrf
            @method($method)

            <div class="bg-white px-6 pb-2 pt-6">
                <h2 class="text-lg font-bold text-navy-900">{{ $title }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-700">{{ $message }}</p>
            </div>

            @if (trim((string) $slot) !== '')
                <div class="px-6 pb-2">
                    {{ $slot }}
                </div>
            @endif

            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                    x-on:click="show = false; $dispatch('staff-dialog-closed')">
                    {{ $cancelLabel }}
                </button>

                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 {{ $tones[$tone] ?? $tones['primary'] }}"
                    x-on:click="show = false">
                    {{ $confirmLabel }}
                </button>
            </div>
        </form>
    </x-modal>
</div>
