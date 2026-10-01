@props([
    // The GET action. Unchanged from what each screen already posts to, so the
    // query-string parameters these filters have always used keep working.
    'action',
    // Whether anything is currently filtering the list. Drives the Clear link: a
    // "Clear filters" button on an unfiltered list is a control that does nothing,
    // which is the same defect as a dead button anywhere else.
    'hasFilters' => false,
    'clearHref' => null,
    'clearLabel' => 'Clear filters',
    // Extra classes on the Filter button. A bar with more controls than fit on one
    // line wraps, and the button then lands wherever the wrap put it; ml-auto pins
    // it to the end of whichever row it ends up on so it is never stranded.
    'buttonClass' => '',
    // The gap between controls, as a utility class that REPLACES the default.
    //
    // A prop rather than something a caller passes as class, because
    // $attributes->merge() appends: passing gap-2 would leave both gap-2 and
    // gap-3 on the element with equal specificity, and which one wins is decided
    // by the order they appear in the stylesheet. On Audit Logs that was the
    // difference between the Filter button fitting on the row and wrapping off it
    // by a single pixel.
    'gap' => 'gap-3',
])

{{--
    The filter bar: a GET form on one row from md up, stacked below it.

    A GET form because every staff list is a filtered view of a query string, and
    that is what makes a filtered list a real URL - refresh and the back button
    behave. Nothing here changes which parameters a screen uses.

    flex-wrap rather than a fixed grid: the widest thing in the bar is the search
    field, and a fixed column count left the search box squeezed to nothing beside a
    short select on a laptop.
--}}
<form method="GET" action="{{ $action }}" {{ $attributes->merge(["class" => "flex flex-wrap items-end ".$gap]) }}>
    {{ $slot }}

    <x-btn type="submit" variant="navy" size="md" icon="search" :class="trim('shrink-0 '.$buttonClass)">Filter</x-btn>

    @if ($hasFilters && $clearHref)
        <a href="{{ $clearHref }}"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-semibold text-navy-700 underline underline-offset-2 transition hover:bg-navy-50 hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400">
            {{ $clearLabel }}
        </a>
    @endif
</form>