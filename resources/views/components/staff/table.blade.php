@props([
    // Ordered column definitions. Each is ['label' => string, 'align' => 'left'|'right'|'center'].
    // "align" is applied to the header AND is what a caller should put on the matching
    // cell, so a numeric column can be right-aligned as a pair.
    'columns' => [],
    'caption' => null,
    // Shown instead of the table when the slot is empty.
    'emptyTitle' => 'Nothing here yet',
    'emptyDescription' => null,
    'emptyIcon' => 'inbox',
    'emptyActionLabel' => null,
    'emptyActionHref' => null,
    // Optional vertical cap. The header only has something to stick to when the
    // table can scroll inside the wrapper, so this defaults to none.
    'maxHeight' => null,
    // The slot supplies its own <tbody> elements instead of bare <tr>s.
    //
    // For a table where each row carries its own Alpine scope - a per-row
    // disclosure, say. A <tbody> cannot wrap a <tr>, so a second <tr> belonging
    // to the same row is a SIBLING of the first, and any x-show on it is outside
    // the first row's x-data. That is not a styling problem, it is a ReferenceError
    // on every row: "managing is not defined".
    //
    // The fix is the HTML that was always available: one <tbody> per group of rows,
    // with the scope on the <tbody>, which legally contains both rows. Only the
    // sectors screen needs this, so it is opt-in.
    'grouped' => false,
])

@php
    /*
     * The staff table.
     *
     * Built because the shared x-data-table cannot be changed, and the six staff
     * tables had two real defects that lived in the markup around it.
     *
     * THE ALIGNMENT BUG. Every staff table except Audit Logs marked its first cell
     * as a row header:
     *
     *     <th scope="row" class="px-4 py-3 text-sm font-semibold text-navy-900">
     *
     * The browser's default stylesheet sets `th { text-align: center }`. That is a
     * declaration on the th itself, so it beats anything inherited from an ancestor -
     * including the text-left that the header row carries. The result was measured in
     * Chrome at 1280px as:
     *
     *     thead th          left
     *     tbody th[scope=row]  center    <-- the name cell, centred
     *     tbody td          start
     *
     * so the name in every row sat centred under a left-aligned header while every
     * other cell beside it was left-aligned. Audit Logs looked right only because it
     * uses a <td> for that cell. The fix below is [&_th]:text-left, which is an author
     * rule on the th itself and therefore wins - and it keeps <th scope="row">, which
     * is worth keeping: it is what tells a screen reader which cell names the row.
     *
     * CELL PADDING. px-4 py-3 was repeated on every cell in every table, with no two
     * tables agreeing. It is applied here through child variants instead, so a caller's
     * cell carries only its own meaning - colour, size, alignment - and cannot be a
     * half-chamberpied out of alignment with the rest of the table.
     */
    $hasBody = isset($slot) && trim((string) $slot) !== '';

    $headerCell = 'px-4 py-3 text-xs font-bold uppercase tracking-wider';

    /*
     * Applies to the tbody, so it covers whatever the caller writes. align-middle is
     * here because a person cell (two lines) beside a single-line badge used to sit on
     * the text baseline rather than centred against it.
     */
    $body = 'divide-y divide-navy-50'
        .' [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-left'
        .' [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:align-middle [&_th]:font-semibold [&_th]:text-navy-900'
        .' [&_tr]:transition-colors [&_tr:hover]:bg-navy-50/40';

    /*
     * The same rules again, as child variants on the <table> rather than as
     * classes on a <tbody>.
     *
     * Needed for `grouped`, where the caller supplies its own <tbody> elements
     * because each row needs its own x-data scope. With the rules living only on
     * this component's own <tbody>, a caller-supplied one would get no padding,
     * no divider and no hover - the table would look broken in a way that reads as
     * a Tailwind problem rather than a component one.
     *
     * Expressed from the table it covers both shapes, so there is one description
     * of what a cell looks like instead of two that can disagree.
     */
    $cellRules = '[&_tbody]:divide-y [&_tbody]:divide-navy-50'
        .' [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-left'
        .' [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:align-middle [&_th]:font-semibold [&_th]:text-navy-900'
        .' [&_tr]:transition-colors [&_tr:hover]:bg-navy-50/40';

    /*
     * The wrapper. A vertical cap is expressed as a Tailwind arbitrary max-height
     * rather than an inline style, so the whole component needs no style attribute.
     */
    $wrapperClass = $maxHeight
        ? 'overflow-x-auto overflow-y-auto max-h-['.$maxHeight.']'
        : 'overflow-x-auto';
@endphp

@if (! $hasBody)
    <x-staff.empty-state :icon="$emptyIcon" :title="$emptyTitle" :description="$emptyDescription"
        :action-label="$emptyActionLabel" :action-href="$emptyActionHref" />
@else
    {{--
    Whole-row navigation.

    The real link is the one in the primary cell - that is what a keyboard or a
    screen reader follows, and it is the only part of this that has to be right.
    This is the convenience on top: a person aiming at a row expects the row.

    It bails out when the click landed on anything interactive. Without that, the
    Publish, Unpin and Delete controls in an actions column - real buttons inside
    real forms - would also fire the row and navigate out from under them, which is
    the worst possible answer to a click on Delete.

    NOTE: this lives here rather than in a Blade comment because a Blade comment
    inside an attribute is not stripped - it is rendered as literal text inside the
    value. An earlier version had the explanation in here, and its double quotes
    closed the attribute early and left the page with a syntax error on every table.
--}}
    {{--
        ONE class attribute, deliberately.

        This wrapper used to be written as

            <div class="{{ $wrapperClass }}" {{ $attributes->merge([...]) }}>

        which is two class attributes on one element whenever a caller passes its
        own class - and two class attributes is not valid HTML. The browser keeps
        the first and discards the second without a word, so a caller asking for
        `hidden md:block` got a table that was still visible on a phone, and
        nothing in the console said so. $wrapperClass is now merged into the
        single attribute, which keeps both sets of classes and keeps the element
        valid.

        The explanation lives here, above the element, and not inside the
        attribute list: Blade does not strip a comment written there, it renders
        it as literal text, and the note's own quotes would close the attribute.
        That mistake was made once already in this file and is the reason the
        first version of this paragraph is in a comment rather than in x-data.
    --}}
    <div x-data="{ openRow(event, url) { if (! url) return; if (event.target.closest('a, button, form, input, select, textarea, label, summary, [role=dialog]')) return; window.location.href = url } }"
        tabindex="0"
        role="region"
        aria-label="{{ $caption ?? 'Table' }}"
        {{ $attributes->merge(['class' => trim($wrapperClass.' rounded-xl border border-navy-100 shadow-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400')]) }}>
        <table class="w-full min-w-[46rem] text-sm {{ $grouped ? $cellRules : '' }}">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            <thead class="border-b border-navy-100 bg-navy-50/60">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col"
                            class="{{ $headerCell }} {{ ($column['align'] ?? 'left') === 'left' ? 'text-left' : 'text-'.$column['align'] }}">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            @if ($grouped)
                {{-- The caller writes its own <tbody> elements, each with the scope
                     its rows share. No <tbody> is added here. --}}
                {{ $slot }}
            @else
                <tbody class="{{ $body }}">
                    {{ $slot }}
                </tbody>
            @endif
        </table>
    </div>
@endif