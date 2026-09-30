@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    // Heading level must match the surrounding document outline so screen
    // reader users do not hit a skipped level (e.g. h1 -> h3).
    'level' => 'h3',
    'class' => '',
])

<div {{ $attributes->merge(['class' => trim('flex flex-col items-center rounded-xl border border-dashed border-navy-200 bg-navy-50/40 px-6 py-12 text-center '.$class)]) }}>
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-white text-navy-400 shadow-card ring-1 ring-navy-100">
        <x-icon :name="$icon" class="h-7 w-7" />
    </span>

    <{{ $level }} class="mt-5 text-base font-bold text-navy-900">{{ $title }}</{{ $level }}>

    @if ($description)
        <p class="mt-2 max-w-md text-sm leading-relaxed text-navy-600/80">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
