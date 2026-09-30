@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'left',
    'class' => '',
])

@php
    /*
     * BDRPIS button. Renders an <a> when :href is given, otherwise a <button>.
     *
     * Variants: primary (gold CTA on navy surfaces / navy on light),
     *           secondary, outline, ghost, danger.
     * Sizes:   sm, md, lg.
     */

    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition duration-200 ease-smooth focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

    $variants = [
        'primary' => 'bg-gold text-navy-900 hover:bg-gold-300 focus-visible:ring-navy-900 shadow-sm',
        'navy' => 'bg-navy text-white hover:bg-navy-700 focus-visible:ring-navy-600 shadow-sm',
        'sky' => 'bg-sky-600 text-white hover:bg-sky-700 focus-visible:ring-sky-600 shadow-sm',
        'secondary' => 'bg-navy-50 text-navy-900 border border-navy-200 hover:bg-navy-100 focus-visible:ring-navy-500',
        'outline' => 'border border-navy-300 bg-white text-navy-900 hover:bg-navy-50 focus-visible:ring-navy-500',
        'ghost' => 'text-navy-700 hover:bg-navy-50 focus-visible:ring-navy-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600 shadow-sm',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-6 py-3.5 text-base',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).' '.$class);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
        <span>{{ $slot }}</span>
        @if ($icon && $iconPosition === 'right')
            <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
        <span>{{ $slot }}</span>
        @if ($icon && $iconPosition === 'right')
            <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
        @endif
    </button>
@endif
