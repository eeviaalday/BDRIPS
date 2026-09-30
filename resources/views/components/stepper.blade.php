@props([
    // ['label' => 'Document Type'] - the order is the order.
    'steps' => [],
    // 1-based. Callers keep this in step with the markup showing the step.
    'current' => 1,
    // Name of an Alpine variable holding the current step, for a stepper inside
    // a multi-step form. When set the marker is bound to it instead of being
    // printed once, so the indicator advances with the form. Left null for a
    // static indicator that only ever shows one position.
    'bind' => null,
    'variant' => 'horizontal',
])

@php
    /*
     * Progress through a multi-step form.
     *
     * Rendered as an ordered list inside a nav landmark with
     * aria-current="step", so it is announced as a position in a sequence rather
     * than as a row of unrelated circles. Completed steps are marked with a tick
     * AND with sr-only "completed" text - a tick alone is invisible to a screen
     * reader, and colour alone would fail anyone who cannot distinguish the
     * shades.
     *
     * This reports where the resident IS. It is not an indicator of what they may
     * skip to: the form posts as a whole, so the only thing a step can do is
     * show the order.
     */
    $total = count($steps);
@endphp

<nav aria-label="Progress" {{ $attributes->merge(['class' => 'mb-6']) }}>
    <ol @class([
        'flex items-start',
        'gap-1' => $variant === 'horizontal',
        'flex-col gap-3' => $variant !== 'horizontal',
    ])>
        @foreach ($steps as $index => $label)
            @php
                $number = $index + 1;
                $isCurrent = $number === $current;
                $isDone = $number < $current;
            @endphp

            <li @class([
                'flex items-center',
                'flex-1' => $variant === 'horizontal',
            ])>
                <div class="flex items-center gap-2.5">
                    {{-- When bound, the state classes come from x-bind:class
                         alone. Emitting them here as well would put all three on
                         the element at once, and since x-bind replaces the whole
                         class list the static ones would be discarded anyway -
                         while still being wrong in any render where Alpine has
                         not yet started. --}}
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition',
                    ])
                        @if ($bind)
                            x-bind:class="step === {{ $number }} ? 'bg-gold text-navy-900 ring-4 ring-gold-200' : (step > {{ $number }} ? 'bg-navy text-white' : 'bg-navy-100 text-navy-400')"
                            x-bind:aria-current="step === {{ $number }} ? 'step' : false"
                        @else
                            @class([
                                'bg-gold text-navy-900 ring-4 ring-gold-200' => $isCurrent,
                                'bg-navy text-white' => $isDone,
                                'bg-navy-100 text-navy-400' => ! $isCurrent && ! $isDone,
                            ])
                            @if ($isCurrent) aria-current="step" @endif
                        @endif>
                        <template x-if="{{ $bind }} && step > {{ $number }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </template>
                        <span @if ($bind) x-show="! ({{ $bind }} && step > {{ $number }})" @endif>{{ $number }}</span>
                        <span class="sr-only" x-show="{{ $bind }} && step > {{ $number }}">Completed:</span>
                    </span>

                    <span @class([
                        'text-sm font-semibold',
                        'text-navy-900' => $isCurrent,
                        'text-navy-600' => $isDone,
                        'text-navy-400' => ! $isCurrent && ! $isDone,
                    ])
                        @if ($bind)
                            x-bind:class="step === {{ $number }} ? 'text-sm font-semibold text-navy-900' : (step > {{ $number }} ? 'text-sm font-semibold text-navy-600' : 'text-sm font-semibold text-navy-400')"
                        @endif>
                        {{ $label }}
                        @if ($isCurrent && ! $bind)<span class="sr-only">(current step)</span>@endif
                    </span>
                </div>

                @if (! $loop->last && $variant === 'horizontal')
                    <span class="mx-3 h-0.5 flex-1 rounded bg-navy-100"
                        @if ($bind) x-bind:class="step > {{ $number }} ? 'bg-navy-300' : 'bg-navy-100'" @endif
                        aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
