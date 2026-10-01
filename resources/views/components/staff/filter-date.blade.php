@props([
    'name',
    'label',
    'value' => '',
])

{{--
    A date filter.

    Sized rather than squeezed. <input type="date"> is a fixed-format control - it
    never wraps, and it ellipsises the moment its box is too narrow, so a flex row
    that let it share the slack was quietly hiding part of a date nobody can guess
    at from context. The width below is the widest a dd/mm/yyyy date needs at
    text-sm plus its own padding; it is a floor, so the box only grows.

    No flex-1, deliberately. With it, a date field GREW to fill whatever was left
    on its row - measured on Audit Logs at 1280px, the "To" field was 875px wide
    and the only thing on the second row, which pushed the Filter button down with
    it. A control has no business being eight times wider than the value it holds.

    This is the From/To pair on Audit Logs, which is the only place the staff area
    filters by date.
--}}
<div class="min-w-[9.5rem] shrink-0">
    <label for="filter-{{ $name }}" class="block text-sm font-semibold text-navy-800">{{ $label }}</label>
    <input id="filter-{{ $name }}" name="{{ $name }}" type="date" value="{{ $value }}"
        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
</div>