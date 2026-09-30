@props([
    'tone' => 'white',
    'id' => null,
    'width' => 'default',
    'class' => '',
    'divider' => false,
    'top' => null,
])

@php
    /*
     * Vertical band + horizontal shell. Every homepage section and page body
     * uses this so container width, side padding and vertical rhythm are
     * defined in exactly one place.
     *
     * "top" overrides ONLY the band's top padding, for the rare band that has to
     * sit tighter against the element above it (the homepage quick services band
     * against the hero's wave divider). The bottom padding always keeps the
     * default rhythm, and omitting "top" reproduces the previous padding exactly.
     *
     * Top and bottom are emitted as separate utilities rather than as one `py-*`
     * so an override cannot be silently defeated by Tailwind's own rule order for
     * padding versus padding-top.
     */

    $tones = [
        'white' => 'bg-white',
        'surface' => 'bg-surface',
        'tinted' => 'bg-navy-50/50',
        'navy' => 'bg-navy text-white',
    ];

    $widths = [
        'default' => 'max-w-shell',
        'narrow' => 'max-w-4xl',
        'read' => 'max-w-3xl',
    ];

    $paddingTop = $top ?? 'pt-14 sm:pt-16 lg:pt-20';
    $paddingBottom = 'pb-14 sm:pb-16 lg:pb-20';
@endphp

<section
    @if ($id) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => trim(($tones[$tone] ?? $tones['white']).' '.($divider ? 'border-y border-navy-100' : '').' '.$class)]) }}
>
    <div class="mx-auto w-full {{ $widths[$width] ?? $widths['default'] }} px-4 {{ $paddingBottom }} sm:px-6 {{ $paddingTop }} lg:px-8">
        {{ $slot }}
    </div>
</section>
