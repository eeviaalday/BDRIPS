@props([
    // Column labels, in order. The cells themselves go in the slot as ordinary
    // <tr> markup - see the note below for why they are not passed as data.
    'columns' => [],
    'caption' => null,
    // Shown instead of the table when the slot is empty.
    'emptyTitle' => 'Nothing here yet',
    'emptyDescription' => null,
    'emptyIcon' => 'inbox',
])

@php
    /*
     * A table that stays usable on a phone.
     *
     * The overflow wrapper alone is not enough: a five-column table in a
     * horizontally scrolling box is technically reachable but unreadable at
     * 360px. So the table is also given a min-width, and the wrapper is
     * focusable and labelled - which means a keyboard user can scroll it with the
     * arrow keys instead of hunting for a scrollbar they cannot see.
     *
     * Every table gets a caption, visually hidden by default. A table with no
     * accessible name is announced only as "table", which is useless on a page
     * that has two of them.
     *
     * WHY THE ROWS ARE A SLOT AND NOT A PROP: this was originally built to take
     * pre-rendered cell HTML in a :rows attribute, and that cannot work. Blade
     * parses a component attribute with a pattern that stops at the first double
     * quote, so any cell containing one - and a status badge or an <a> both do -
     * truncates the attribute and the tag silently stops being recognised as a
     * component at all. The failure is invisible: the whole <x-data-table> tag
     * renders as literal text. Writing the rows as Blade markup in the slot
     * sidesteps the parser entirely and keeps type safety in the template.
     *
     * There is deliberately no row-level link prop. A <tr> is not a link, and
     * faking one with a click handler would break middle-click, "open in new tab"
     * and keyboard activation. Put a real link in a cell.
     */
    // isset(), not $slot directly: a self-closing <x-data-table /> has no slot,
    // and an undefined-variable error would take the whole page down over a
    // component that legitimately has nothing to show.
    $hasBody = isset($slot) && trim((string) $slot) !== '';
@endphp

@if (! $hasBody)
    <x-empty-state :title="$emptyTitle" :description="$emptyDescription" :icon="$emptyIcon" />
@else
    <div class="overflow-x-auto"
        tabindex="0"
        role="region"
        aria-label="{{ $caption ?? 'Table' }}"
        {{ $attributes->merge(['class' => 'rounded-xl border border-navy-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400']) }}>
        <table class="w-full min-w-[46rem] text-sm">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            <thead>
                <tr class="border-b border-navy-100 bg-navy-50/60 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">
                    @foreach ($columns as $column)
                        <th scope="col" class="px-4 py-3 {{ $column['class'] ?? '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-navy-50">
                {{ $slot ?? '' }}
            </tbody>
        </table>
    </div>
@endif
