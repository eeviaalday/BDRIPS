@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart',
    'tone' => 'navy',
    // 'link' renders an anchor, 'button' renders a real button, and 'disabled'
    // renders a button that cannot be activated.
    //
    // This exists because the shared stat-card renders a plain div when it has
    // no href, and a div cannot be focused or activated. A figure a resident is
    // meant to act on has to be a real control. That card is shared with the
    // staff dashboards, so the interactive version lives here rather than being
    // bolted onto a component three other areas depend on.
    'as' => 'div',
    'href' => null,
    'active' => false,
    'disabled' => false,
    'class' => '',
])

@php
    $badges = [
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'purple' => 'bg-purple-100 text-purple-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'navy' => 'bg-navy-100 text-navy-800',
        'sky' => 'bg-sky-100 text-sky-700',
        'gold' => 'bg-amber-100 text-amber-800',
    ];

    $tag = match ($as) {
        'link' => 'a',
        'button', 'disabled' => 'button',
        default => 'div',
    };

    $isButton = in_array($as, ['button', 'disabled'], true);

    /*
     * A gold ring when active, so the resident can see which filter the list is
     * showing without reading the URL. focus-visible rather than focus: a mouse
     * click should not leave a ring behind, but a keyboard user must always see
     * where they are.
     */
    $states = [
        'bg-white border border-navy-100 text-navy-900 shadow-card',
        $active ? 'ring-2 ring-gold' : 'hover:border-navy-200 hover:shadow-md',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2',
        $as === 'disabled' ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
    ];
@endphp

<{{ $tag }}
    @if ($as === 'link') href="{{ $href }}"
    @elseif ($isButton) type="button" @disabled($as === 'disabled')
    @endif

    @if (! $isButton && $as !== 'disabled') aria-pressed="{{ $active ? 'true' : 'false' }}"
    @elseif ($as === 'disabled') aria-disabled="true"
    @endif

    {{ $attributes->merge(['class' => trim('block w-full rounded-xl p-5 text-left '.implode(' ', array_filter($states)).' '.$class)]) }}
>
    <div class="flex items-center justify-between gap-3">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl {{ $badges[$tone] ?? $badges['navy'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
        <p class="text-3xl font-extrabold tracking-tight tabular-nums">{{ $value }}</p>
    </div>
    <p class="mt-3 text-sm font-semibold text-navy-700">{{ $label }}</p>

    @if ($hint)
        <p class="mt-1 text-sm text-navy-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
