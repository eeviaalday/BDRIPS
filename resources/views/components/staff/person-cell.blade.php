@props([
    'name' => null,
    'secondary' => null,
    // For the document-request table, whose first column is a reference number
    // rather than a person.
    'mono' => false,
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
     * NO href, NO chevron. Both used to be here, and both are gone.
     *
     * The name used to be the row's link, with a chevron beside it, inside a <tr>
     * that was also clickable. That gave a table three ways into the same record
     * and none of them was the row's own name to a screen reader: the <tr> was not
     * focusable, the chevron was aria-hidden, and the link's text was the same
     * string as the cell it sat in.
     *
     * A row is now reached through one control, in the Actions column, whose
     * accessible name says which record it opens ("View Ana Bautista"). Keeping
     * href and chevron available here would leave a second way to do it that no
     * caller uses and that nothing tests.
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
        <span @class(['block truncate text-sm font-semibold text-navy-900', 'font-mono text-xs' => $mono])>{{ $display }}</span>

        @if ($secondary)
            <span class="block truncate text-xs font-normal text-slate-500">{{ $secondary }}</span>
        @endif
    </span>
</span>
