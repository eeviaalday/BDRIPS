@props([
    'title',
    'description',
    'icon' => 'document',
    'tone' => 'navy',
    'href' => null,
    'cta' => 'Apply Now',
    'class' => '',
])

@php
    /*
     * E-Service card. Data comes from config('bdrpis.services'), which is
     * intentionally limited to the four document types BDRPIS can process.
     */

    $tones = [
        'navy' => 'bg-navy-50 text-navy-700 group-hover:bg-navy group-hover:text-white',
        'sky' => 'bg-sky-50 text-sky-700 group-hover:bg-sky-600 group-hover:text-white',
        'gold' => 'bg-gold-50 text-gold-700 group-hover:bg-gold-500 group-hover:text-navy-900',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => trim('group relative flex h-full flex-col rounded-xl border border-navy-100 bg-white p-6 shadow-card transition duration-200 ease-smooth hover:-translate-y-1 hover:border-navy-200 hover:shadow-card-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2 '.$class)]) }}
>
    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg transition duration-200 {{ $tones[$tone] ?? $tones['navy'] }}">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>

    <h3 class="mt-5 text-base font-bold leading-snug text-navy-900">{{ $title }}</h3>

    <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600/80">{{ $description }}</p>

    @if ($cta)
        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-bold text-sky-700 transition group-hover:text-navy-900">
            {{ $cta }}
            <x-icon name="arrow-right" class="h-4 w-4 transition duration-200 group-hover:translate-x-0.5" />
        </span>
    @endif
</{{ $tag }}>
