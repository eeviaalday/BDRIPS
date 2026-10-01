@props([
    'name',
    'label',
    'type' => 'search',
    'value' => '',
    'placeholder' => null,
    // How much of the bar's width the search takes. The searches are the longest
    // text on the page and were the thing being squeezed.
    'grow' => true,
    // A Tailwind max-width class for the growing field.
    //
    // Without a cap, flex-1 lets the search absorb every spare pixel, and on a bar
    // with five controls that is exactly the pixel the Filter button needed: at
    // 1440px the search grew to 324px and pushed the button onto a row of its own.
    // Capping it leaves the slack for the controls that cannot shrink.
    'maxWidth' => null,
])

{{--
    The search half of the filter bar.

    w-full with a min-width rather than a fixed size, so it takes the slack on a
    wide screen and still stops collapsing on a narrow one. The old markup gave the
    wrapper flex-1 with a min-w, and the input inside it was w-full - which works,
    but the select beside it had no width of its own and took whatever was left.
--}}
<div @class(['min-w-[14rem]', 'flex-1' => $grow, $maxWidth])>
    <label for="filter-{{ $name }}" class="block text-sm font-semibold text-navy-800">{{ $label }}</label>
    <input id="filter-{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ is_array($value) ? '' : $value }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
</div>