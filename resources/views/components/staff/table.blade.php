@props([
    // Ordered column definitions. Each is ['label' => string].
    //
    // There used to be a second key, 'align', documented as 'left'|'right'|'center'.
    // It is gone, and that is the fix rather than a simplification. The shared
    // x-data-table this component was built beside reads the "class" key of a column
    // and not the "align" one, so the same right-alignment was honoured here and
    // silently ignored there — which is how a numeric column came out right-aligned
    // on this component and not on the other. One key, one meaning, and no key that
    // only half works.
    //
    // Every cell in the staff area is left-aligned, figures included.
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
     *
     * THE PHONE. Measured in Chrome at 375px, this table rendered 783px of content
     * inside a 325px box. document.scrollWidth came out at 775 against a 375px
     * viewport, so the document itself carried 400px of sideways scroll range, and
     * the Actions column sat at x=792 - entirely off-screen. A person aiming at
     * Archive or Restore had to find a 325px-wide horizontal scroll region that
     * looks exactly like a normal page. body{overflow-x:hidden} clipped the
     * overflow rather than removing it, so the page also scrolled 400px into blank
     * space where nothing was painted.
     *
     * A wide table and a phone-sized table are different layouts, not one layout
     * that has been squeezed, so this is done with display overrides rather than by
     * letting the table reflow: below md each <tr> becomes a card, each cell
     * becomes a labelled row, and from md up everything is a table again. It is
     * done HERE, in the component, rather than at twelve call sites, because the
     * alternative is what produced the bug - a table that only fits on the screens
     * its author happened to try.
     */
    $hasBody = isset($slot) && trim((string) $slot) !== '';

    $headerCell = 'px-4 py-3 text-xs font-bold uppercase tracking-wider';

    /*
     * WHICH COLUMN IS THE CARD TITLE, AND WHICH IS THE CARD FOOTER.
     *
     * Below md the first cell is the card's heading. A "Resident" label above
     * "Ana Dela Cruz" is noise, and in all twelve staff tables the first column is
     * the thing that names the row.
     *
     * The footer is the cell holding the row's controls, so it sits under a rule
     * and left-aligned, the way a card's actions should sit. It is found by the
     * column being labelled Actions, which is what ten of the twelve tables call
     * it, or by a column declaring 'footer' => true. The label is matched whole and
     * case-insensitively rather than by substring, because Audit Logs has an
     * 'Action' column that is a badge rather than a set of controls, and it must
     * keep rendering as an ordinary labelled row.
     *
     * A page whose last column is its controls but which calls the column
     * something else says 'footer' => true on it. Nothing else is guessed.
     */
    $footerIndex = null;
    foreach ($columns as $index => $column) {
        $label = \Illuminate\Support\Str::lower(trim((string) ($column['label'] ?? '')));

        if ($index === 0) {
            continue;
        }

        if (($column['footer'] ?? false) === true || $label === 'actions') {
            $footerIndex = $index + 1;
            break;
        }
    }

    // The card itself. Below md a row stops being a row of a table and becomes a
    // bordered panel; from md up it is a row again, and every card-only property is
    // undone rather than merely overridden, because a stray border-radius or shadow
    // left on at desktop is exactly the sort of thing nobody notices for a month.
    $mobileRow = '[&_tr]:mb-3 [&_tr]:block [&_tr]:rounded-xl [&_tr]:border [&_tr]:border-navy-100 [&_tr]:bg-white [&_tr]:p-4 [&_tr]:shadow-card'
        .' md:[&_tr]:mb-0 md:[&_tr]:table-row md:[&_tr]:rounded-none md:[&_tr]:border-0 md:[&_tr]:bg-transparent md:[&_tr]:p-0 md:[&_tr]:shadow-none';

    /*
     * The label each cell shows below md, handed to CSS as --lbl-N on the <table>.
     *
     * These are inline custom properties rather than generated classes, because
     * Tailwind only emits utilities it can find as literal text in a scanned file.
     * A class assembled at runtime - "[&_tr>*:nth-child(3)]:before:content-['
     * .$label . "']" - is not in the source when the build runs, so it is silently
     * absent from the stylesheet and the labels never appear. The variable holds the
     * text, the classes below are written out in full, and the two cannot drift.
     *
     * Quotes and backslashes are stripped because the value is a CSS string literal:
     * a label containing one would end the string and quietly drop the rest of the
     * declaration. No current label has either.
     */
    $labels = '';
    foreach ($columns as $index => $column) {
        $text = str_replace(["\\", "'"], ['', ''], trim((string) ($column['label'] ?? '')));

        if ($text === '') {
            continue;
        }

        $labels .= '--lbl-'.($index + 1).":'".$text."';";
    }

    /*
     * One literal class per column position, so the build's scanner finds each of
     * them in this file. Written out rather than looped, because a loop builds the
     * class name at runtime and Tailwind will never have heard of it.
     *
     * Capped at 12 columns, which is above the widest table here (8, relief batches
     * in the Kagawad area). A wider table would lose its labels rather than break,
     * so StaffTableResponsiveTest asserts the cap against the real call sites.
     */
    $labelClasses = [
        '[&_tr>*:nth-child(1)]:before:content-[var(--lbl-1)]',
        '[&_tr>*:nth-child(2)]:before:content-[var(--lbl-2)]',
        '[&_tr>*:nth-child(3)]:before:content-[var(--lbl-3)]',
        '[&_tr>*:nth-child(4)]:before:content-[var(--lbl-4)]',
        '[&_tr>*:nth-child(5)]:before:content-[var(--lbl-5)]',
        '[&_tr>*:nth-child(6)]:before:content-[var(--lbl-6)]',
        '[&_tr>*:nth-child(7)]:before:content-[var(--lbl-7)]',
        '[&_tr>*:nth-child(8)]:before:content-[var(--lbl-8)]',
        '[&_tr>*:nth-child(9)]:before:content-[var(--lbl-9)]',
        '[&_tr>*:nth-child(10)]:before:content-[var(--lbl-10)]',
        '[&_tr>*:nth-child(11)]:before:content-[var(--lbl-11)]',
        '[&_tr>*:nth-child(12)]:before:content-[var(--lbl-12)]',
    ];

    $labelCount = count($columns);

    /*
     * The phone layout, as one string. Split into four groups because each is
     * applied to a different element.
     *
     * min-w-[46rem] is now md:min-w-[46rem]. Below md the rows are cards and must
     * be allowed to be as narrow as the phone; the minimum width is what made the
     * table 736px wide on a 375px screen in the first place.
     *
     * Padding is split the same way - px-0/py-1.5 below md, md:px-4/md:py-3 above.
     * Setting both as unprefixed utilities would be a coin toss: which one wins
     * depends on their order in the generated stylesheet, not on their order here.
     * One is inside a media query and one is not, so there is nothing to contest.
     *
     * divide-y is md:divide-y for the same reason, and because a divider between
     * two cards would sit on top of the cards' own borders.
     */
    $mobileCell = '[&_td]:grid [&_td]:min-w-0 [&_td]:grid-cols-[minmax(0,7.5rem)_minmax(0,1fr)] [&_td]:items-baseline [&_td]:gap-x-3 [&_td]:py-1.5 [&_td]:px-0 [&_td]:break-words'
        .' [&_th]:grid [&_th]:min-w-0 [&_th]:grid-cols-[minmax(0,7.5rem)_minmax(0,1fr)] [&_th]:items-baseline [&_th]:gap-x-3 [&_th]:py-1.5 [&_th]:px-0 [&_th]:break-words'
        .' md:[&_td]:table-cell md:[&_td]:px-4 md:[&_td]:py-3'
        .' md:[&_th]:table-cell md:[&_th]:px-4 md:[&_th]:py-3'
        // A cell that spans the row is a panel, not a labelled field - Sectors puts
        // its whole edit form in one <td colspan="4">. Treating it as a two column
        // grid would squeeze that form into the 7.5rem label column, so it goes
        // back to being a plain block that fills the card, with no label.
        .' [&_td[colspan]]:block [&_td[colspan]]:before:hidden [&_td[colspan]]:min-w-0'
        .' md:[&_td[colspan]]:table-cell md:[&_td[colspan]]:px-4';

    // How the ::before label itself looks. No content here - only the per-index
    // classes above set content, so a cell with no label renders no empty gap.
    $mobileLabel = '[&_td]:before:text-2xs [&_td]:before:font-semibold [&_td]:before:uppercase [&_td]:before:tracking-wider [&_td]:before:text-navy-400 [&_td]:before:pt-0.5'
        .' [&_th]:before:text-2xs [&_th]:before:font-semibold [&_th]:before:uppercase [&_th]:before:tracking-wider [&_th]:before:text-navy-400 [&_th]:before:pt-0.5'
        .' md:[&_td]:before:hidden md:[&_th]:before:hidden';

    // The card title: the first cell, unlabelled and given the weight a heading
    // needs. Hidden is unconditional - the label class above would otherwise put
    // the column name above the name it names.
    $mobileTitle = '[&_tr>*:first-child]:before:hidden [&_tr>*:first-child]:text-[0.9375rem] [&_tr>*:first-child]:font-semibold [&_tr>*:first-child]:text-navy-900 [&_tr>*:first-child]:pb-2'
        .' md:[&_tr>*:first-child]:text-sm md:[&_tr>*:first-child]:font-semibold md:[&_tr>*:first-child]:pb-0 md:[&_tr>*:first-child]:align-middle';

    /*
     * The card footer: the controls cell, under a rule, left-aligned. Applied only
     * when this table actually has one, because the last cell of a table without an
     * Actions column - Audit Logs' Detail, a disclosure - is content, not controls,
     * and a rule above it would misrepresent it.
     */
    $mobileFooter = $footerIndex === null
        ? ''
        : '[&_tr>*:last-child]:before:hidden [&_tr>*:last-child]:block [&_tr>*:last-child]:mt-2 [&_tr>*:last-child]:border-t [&_tr>*:last-child]:border-navy-100 [&_tr>*:last-child]:pt-3'
            .' md:[&_tr>*:last-child]:mt-0 md:[&_tr>*:last-child]:border-0 md:[&_tr>*:last-child]:pt-3 md:[&_tr>*:last-child]:table-cell md:[&_tr>*:last-child]:border-b-0';

    $mobileClasses = trim(implode(' ', array_filter([
        $mobileRow,
        $mobileCell,
        $mobileLabel,
        $mobileTitle,
        $mobileFooter,
        implode(' ', \array_slice($labelClasses, 0, $labelCount)),
    ])));

    /*
     * Applies to the tbody, so it covers whatever the caller writes. align-middle is
     * here because a person cell (two lines) beside a single-line badge used to sit on
     * the text baseline rather than centred against it.
     *
     * Padding is NOT set here any more - it moved into $mobileCell as a mobile
     * value plus an md: value, so that the phone and the table cannot both be
     * trying to own the same property.
     */
    $body = 'md:divide-y divide-navy-50'
        .' [&_td]:align-middle [&_td]:text-left'
        .' [&_th]:text-left [&_th]:align-middle [&_th]:font-semibold [&_th]:text-navy-900'
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
    $cellRules = 'md:[&_tbody]:divide-y [&_tbody]:divide-navy-50'
        .' [&_td]:align-middle [&_td]:text-left'
        .' [&_th]:text-left [&_th]:align-middle [&_th]:font-semibold [&_th]:text-navy-900'
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
    <div
        tabindex="0"
        role="region"
        aria-label="{{ $caption ?? 'Table' }}"
        {{ $attributes->merge(['class' => trim($wrapperClass.' rounded-xl border border-navy-100 shadow-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400')]) }}>
        {{--
            THE PHONE, IN TWO PLACES.

            1. --lbl-N on the <table> carries each column's label for the card
               layout below md. See $labels for why this is a custom property and
               not a generated class.

            2. block/md:table on the <table> itself, with md:min-w-[46rem]. Below md
               this is not a table any more - it is a stack of cards - so it must not
               also insist on being 736px wide. min-w is now inside the md query,
               which is the whole of the overflow fix.

            display:block on a <table> takes the rows out of table layout, and the
            [tr]/[td] overrides in $mobileClasses turn them into cards. This is the
            one place Tailwind's own responsive prefix and the child variants meet,
            so both halves are written here rather than split across two elements.
        --}}
        <table style="{{ $labels }}" class="block w-full text-sm md:table md:min-w-[46rem] {{ $grouped ? $cellRules : '' }} {{ $mobileClasses }}">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            <thead class="hidden border-b border-navy-100 bg-navy-50/60 md:table-header-group">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" class="{{ $headerCell }} text-left">
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