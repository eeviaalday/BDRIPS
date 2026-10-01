@props([
    'name' => null,
    'secondary' => null,
    // Makes the name a real link, which is what makes the row keyboard reachable
    // and gives a screen reader the row's destination.
    'href' => null,
    'mono' => false,
    // Trailing arrow. Sits inside the link so it is part of the same target, and
    // is aria-hidden because the link's own text already says where it goes.
    'chevron' => false,
    'class' => '',
])

@php
    /*
     * The primary cell of a staff row: who, and which of them.
     *
     * Six staff tables wrote this by hand and none of the six agreed on the initials
     * rule, the avatar size, or what the second line was - the same person appeared
     * with the email under one table and the household number under another.
     *
     * Initials rather than a photograph, for the reason the sidebar and the top bar
     * already give: this system stores no profile images, so an <img> would be a
     * broken image on every row. The initials are aria-hidden because the name
     * beside them is already read out; "AB" read before "Ana Bautista" is noise.
     *
     * "mono" is for the document-request table, whose first column is a reference
     * number rather than a person.
     */
    $display = $name ?: 'Unknown';
    $initials = collect(explode(' ', trim((string) $display)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';
@endphp

<span {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span aria-hidden="true"
        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-100 text-xs font-bold text-navy-800">
        {{ $initials }}
    </span>

    <span class="min-w-0 flex-1">
        @if ($href)
            {{-- The chevron lives inside the link rather than beside it, so it is
                 the same target and cannot be clicked without navigating. --}}
            <a href="{{ $href }}" @class(['flex min-w-0 items-center gap-2', 'hover:underline' => ! $chevron])>
                <span class="min-w-0">
                    <span @class(['block truncate text-sm font-semibold text-navy-900', 'font-mono text-xs' => $mono])>{{ $display }}</span>

                    @if ($secondary)
                        <span class="block truncate text-xs font-normal text-slate-500">{{ $secondary }}</span>
                    @endif
                </span>

                @if ($chevron)
                    <x-icon name="arrow-right" class="ml-auto h-4 w-4 shrink-0 text-navy-300" />
                @endif
            </a>
        @else
            <span class="block truncate text-sm font-semibold text-navy-900">{{ $display }}</span>

            @if ($secondary)
                <span class="block truncate text-xs font-normal text-slate-500">{{ $secondary }}</span>
            @endif
        @endif
    </span>
</span>