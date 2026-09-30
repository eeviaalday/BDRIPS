@props([
    'variant' => 'info',
    'title' => null,
    'icon' => 'shield',
    'class' => '',
])

@php
    $variants = [
        'info' => ['wrap' => 'bg-sky-50 border-sky-200 text-sky-900', 'icon' => 'text-sky-600 bg-sky-100'],
        'success' => ['wrap' => 'bg-green-50 border-green-200 text-green-900', 'icon' => 'text-green-700 bg-green-100'],
        'warning' => ['wrap' => 'bg-gold-50 border-gold-200 text-gold-800', 'icon' => 'text-gold-700 bg-gold-100'],
        'danger' => ['wrap' => 'bg-red-50 border-red-200 text-red-900', 'icon' => 'text-red-700 bg-red-100'],
        'privacy' => ['wrap' => 'bg-navy-50 border-navy-200 text-navy-800', 'icon' => 'text-navy-700 bg-navy-100'],
        // For use on navy hero/page-header surfaces. Keeps AA contrast.
        'on-dark' => ['wrap' => 'border-white/20 bg-white/10 text-navy-100', 'icon' => 'text-gold bg-white/10'],
    ];

    $v = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => trim('flex gap-3 rounded-lg border px-4 py-3.5 text-sm leading-relaxed '.$v['wrap'].' '.$class)]) }}
    @if ($title) role="status" @endif
>
    <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $v['icon'] }}">
        <x-icon :name="$icon" class="h-4 w-4" />
    </span>

    <div class="min-w-0">
        @if ($title)
            <p class="font-bold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
