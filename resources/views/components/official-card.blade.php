@props([
    'name',
    'position',
    'meta' => null,
    'initials' => null,
    'tone' => 'navy',
    'featured' => false,
    'class' => '',
])

@php
    /*
     * Official card. Intentionally uses a neutral generated avatar
     * (initials on a tinted disc) rather than a fabricated portrait,
     * because no official photographs exist in the system.
     */

    $tones = [
        'navy' => 'bg-navy-50 text-navy-700 ring-navy-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
        'gold' => 'bg-gold-50 text-gold-700 ring-gold-100',
    ];

    $initials = $initials ?? \Illuminate\Support\Str::of($name)
        ->replace(['Hon.', 'Hon', 'Sir', 'Ms', 'Mrs'], ' ')
        ->squish()
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');

    $initials = $initials !== '' ? $initials : '?';
@endphp

<div {{ $attributes->merge(['class' => trim('relative flex h-full flex-col items-center rounded-xl border p-6 text-center transition duration-200 '.($featured ? 'border-navy-200 bg-navy text-white shadow-card-hover md:flex-row md:items-center md:gap-6 md:p-7 md:text-left' : 'border-navy-100 bg-white shadow-card hover:border-navy-200 hover:shadow-card-hover').' '.$class)]) }}>
    <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-lg font-extrabold ring-4 {{ $featured ? 'bg-white/10 text-gold ring-white/10' : ($tones[$tone] ?? $tones['navy']) }}">
        {{ $initials }}
    </span>

    <div class="mt-4 min-w-0 md:mt-0">
        <h3 class="text-base font-bold leading-snug {{ $featured ? 'text-white' : 'text-navy-900' }}">{{ $name }}</h3>

        <p class="mt-1 text-sm font-semibold {{ $featured ? 'text-gold' : 'text-sky-700' }}">{{ $position }}</p>

        @if ($meta)
            <p class="mt-1 text-xs {{ $featured ? 'text-navy-200' : 'text-navy-500' }}">{{ $meta }}</p>
        @endif
    </div>
</div>
