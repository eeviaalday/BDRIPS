@props([
    'label',
    'value',
    'icon' => 'chart',
    'tone' => 'navy',
    'hint' => null,
    // Optional destination. Renders an <a>, so the card is genuinely actionable
    // rather than a div carrying an href.
    'href' => null,
    'class' => '',
])

@php
    /*
     * One figure, in the resident card style.
     *
     * Four different markups were in use across the staff dashboards: the shared
     * x-stat-card in its "badged" variant on three pages, a second badge-on-the-left
     * layout elsewhere, and a bare number-and-label pair with no icon at all. This
     * is the one, and it is the resident treatment: a white card, a navy hairline, a
     * tinted icon badge, the figure in large tabular numerals, then the label.
     *
     * A row of four fully-saturated cards shouts over the content above it, which is
     * why the fill here is always white and only the badge takes the tone.
     */
    $badges = [
        'navy' => 'bg-navy-100 text-navy-800',
        'gold' => 'bg-amber-100 text-amber-800',
        'sky' => 'bg-sky-100 text-sky-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'red' => 'bg-red-100 text-red-700',
    ];

    $tag = $href !== null ? 'a' : 'div';
    $interactive = $href !== null ? ' transition hover:border-navy-200 hover:shadow-md' : '';
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => trim('block rounded-xl border border-navy-100 bg-white p-5 shadow-card '.$interactive.' '.$class)]) }}>
    <div class="flex items-center justify-between gap-3">
        <span aria-hidden="true"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $badges[$tone] ?? $badges['navy'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>

        <p class="text-3xl font-extrabold tabular-nums tracking-tight text-navy-900">{{ $value }}</p>
    </div>

    <p class="mt-3 text-sm font-semibold text-navy-700">{{ $label }}</p>

    @if ($hint)
        <p class="mt-1 text-sm text-navy-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>