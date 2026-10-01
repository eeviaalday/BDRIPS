@props([
    // Any LengthAwarePaginator. Typed loosely so a list screen can pass whatever
    // its controller gave it without this component caring.
    'paginator',
    'label' => 'Showing',
    // Noun after the total, so the line reads "1-15 of 42 entries" rather than
    // ending on a bare number.
    'totalLabel' => null,
    // Render even when everything fits on one page. Off by default, because on an
    // unfiltered list "Showing 1-4 of 4" is furniture with no job. On a *filtered*
    // list it has one: it is the answer to "did my filter actually exclude
    // anything", which nothing else on the screen says.
    'always' => false,
    'class' => '',
])

@php
    /*
     * The footer under a staff list: what range is on screen, out of how much, and
     * the paginator itself.
     *
     * Eight pages rendered a bare {{ $x->links() }} with nothing above it, so on a
     * long list there was no way to tell whether 15 rows meant 15 rows in total or
     * 15 of several hundred. A figure is the cheapest fix for the most common
     * misreading of a paginated table.
     *
     * "1-15 of 42" rather than "Page 1 of 3": the first says what is on the screen,
     * the second only where you are. Both go stale on the same page, but only one
     * of them answers the question a reader actually has.
     *
     * Hidden entirely on a single page - there is nothing to navigate and nothing to
     * summarise, so a footer reading "Showing 1-4 of 4" is furniture with no job.
     */
    $hasPages = $paginator->hasPages();
    $first = $paginator->firstItem();
    $last = $paginator->lastItem();
    $total = $paginator->total();
@endphp

@if ($always || $hasPages)
    <div {{ $attributes->merge(['class' => 'flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center '.$class]) }}>
        <p class="text-sm text-navy-600">
            {{ $label }} <span class="font-semibold text-navy-900">{{ number_format($first) }}&ndash;{{ number_format($last) }}</span>
            of <span class="font-semibold text-navy-900">{{ number_format($total) }}</span>@if ($totalLabel) {{ $totalLabel }}@endif
        </p>

        @if ($hasPages)
            <div>{{ $paginator->links() }}</div>
        @endif
    </div>
@endif