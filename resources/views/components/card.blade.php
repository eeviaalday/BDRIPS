@props([
    'tone' => 'white',
    'padding' => 'p-6',
    'class' => '',
    'href' => null,
])

@php
    $tones = [
        'white' => 'bg-white border-navy-100',
        'tinted' => 'bg-navy-50/60 border-navy-100',
        'navy' => 'bg-navy text-white border-navy-800',
        'outline' => 'bg-transparent border-navy-200',
    ];

    $classes = trim('rounded-xl border shadow-card transition duration-200 '.($tones[$tone] ?? $tones['white']).' '.$padding.' '.$class);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if ($href)
        <a href="{{ $href }}" class="absolute inset-0 z-10" aria-hidden="true" tabindex="-1"></a>
    @endif

    {{ $slot }}
</div>
