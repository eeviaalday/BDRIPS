@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    // id of the element naming this dialog, for aria-labelledby. Falls back to
    // a generated one bound to the dialog itself when the caller does not pass
    // a heading, so the dialog is never left with no accessible name.
    'labelledby' => null,
    // The X. OFF by default: the modals this component already wrapped on the
    // kagawad checklist and the staff request screen did not have one, and
    // adding it to those would change pages that are meant to stay as they
    // are. The resident modals ask for it explicitly.
    'dismissible' => false,
])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth];

    $titleId = $labelledby ?? ($name.'-title');
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    {{-- The backdrop. aria-hidden because it is decoration - it is a click
         target, not content, and naming it would put a second anonymous region
         in the accessibility tree. --}}
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    {{-- role="dialog" with aria-modal is what tells a screen reader this is a
         modal layer and not a panel further down the page; without them the
         content behind it stays reachable. The tab trap above is the keyboard
         half of the same promise. --}}
    <div
        x-show="show"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        class="relative mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        @if ($dismissible)
            {{-- Placed on the panel rather than inside the caller's slot, so it
                 lands in the same top-right corner no matter what content the
                 caller passed, and so it is not duplicated per call site. --}}
            <button type="button" x-on:click="show = false"
                class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400">
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif

        {{ $slot }}
    </div>
</div>
