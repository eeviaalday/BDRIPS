@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart',
    'tone' => 'navy',
    // "filled" paints the whole card in the tone. "badged" keeps the card white
    // and puts the tone on a small icon badge instead, which is what a row of
    // four dashboard figures needs - four fully saturated cards side by side
    // shout over the content they sit above.
    'variant' => 'filled',
    'href' => null,
    'class' => '',
])

@php
    $filled = [
        'navy' => 'bg-navy text-white',
        'white' => 'bg-white border border-navy-100 text-navy-900 shadow-card',
        'sky' => 'bg-sky-600 text-white',
        'gold' => 'bg-gold text-navy-900',
    ];

    /*
     * Each badge pairs a saturated fill with a darker text tone, rather than
     * reusing the white-on-colour of the filled variant, so the glyph keeps
     * contrast against its own badge at 500-weight.
     */
    $badges = [
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'purple' => 'bg-purple-100 text-purple-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'navy' => 'bg-navy-100 text-navy-800',
        'sky' => 'bg-sky-100 text-sky-700',
        'gold' => 'bg-amber-100 text-amber-800',
    ];

    $isBadged = $variant === 'badged';

    /*
     * Rendered as an <a> when given an href, not a <div> carrying an href
     * attribute - which is what a bare $attributes merge would have produced on
     * this component, and is not a link to anything, keyboard-reachable or
     * otherwise.
     */
    $tag = $href !== null ? 'a' : 'div';
    $classes = trim(
        'block rounded-xl p-5 '
        .($isBadged ? 'bg-white border border-navy-100 text-navy-900 shadow-card' : ($filled[$tone] ?? $filled['white']))
        .($href !== null ? ' transition hover:border-navy-200 hover:shadow-md' : '')
        .' '.$class
    );
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
    @if ($isBadged)
        <div class="flex items-center justify-between gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl {{ $badges[$tone] ?? $badges['navy'] }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
            <p class="text-3xl font-extrabold tracking-tight tabular-nums">{{ $value }}</p>
        </div>
        <p class="mt-3 text-sm font-semibold text-navy-700">{{ $label }}</p>
    @else
        <div class="flex items-center gap-2.5">
            <x-icon :name="$icon" class="h-5 w-5 shrink-0 opacity-80" />
            <p class="text-xs font-bold uppercase tracking-wider opacity-80">{{ $label }}</p>
        </div>

        <p class="mt-2.5 text-3xl font-extrabold tracking-tight">{{ $value }}</p>
    @endif

    @if ($hint)
        <p class="mt-1 text-sm {{ $isBadged ? 'text-navy-500' : 'opacity-75' }}">{{ $hint }}</p>
    @endif
</{{ $tag }}>
