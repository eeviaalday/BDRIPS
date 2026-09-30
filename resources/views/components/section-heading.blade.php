@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'center',
    'tone' => 'light',
    'id' => null,
    'class' => '',
])

@php
    /*
     * Section heading used at the top of every homepage band and
     * standalone content block. Kept in one place so vertical rhythm
     * and type scale stay consistent.
     *
     * The title only carries a top margin when something sits above it. A
     * heading with no eyebrow is the first thing in its band, and that margin
     * would push the title off the band's top padding - which on the homepage
     * is what lines the heading up with the element beside it.
     */

    $alignClass = $align === 'center' ? 'text-center mx-auto max-w-2xl' : 'max-w-2xl';
    $onDark = $tone === 'dark';

    $eyebrowClass = $onDark ? 'text-gold' : 'text-sky-700';
    $titleClass = $onDark ? 'text-white' : 'text-navy-900';
    $descClass = $onDark ? 'text-navy-200' : 'text-navy-600/80';
    $titleSpacing = $eyebrow ? 'mt-3' : '';
@endphp

<div {{ $attributes->merge(['class' => trim($alignClass.' '.$class)]) }} @if ($id) id="{{ $id }}" @endif>
    @if ($eyebrow)
        <p class="text-xs font-bold uppercase tracking-[0.18em] {{ $eyebrowClass }}">{{ $eyebrow }}</p>
    @endif

    <h2 class="{{ trim($titleSpacing.' text-2xl font-extrabold tracking-tight sm:text-3xl lg:text-[2.1rem] lg:leading-[1.2] '.$titleClass) }}">
        {{ $title }}
    </h2>

    @if ($description)
        <p class="mt-3 text-base leading-relaxed {{ $descClass }}">{{ $description }}</p>
    @endif
</div>
