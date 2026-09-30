@props([
    'action',
    'method' => 'GET',
    'placeholder' => 'Search',
    // ['name' => 'status', 'label' => 'Status', 'options' => [...], 'value' => ...]
    'selects' => [],
    'submitLabel' => 'Filter',
    'showReset' => true,
])

{{--
    A search and filter bar.

    Submits by GET so a filtered view is a real, shareable, bookmarkable URL
    rather than a POST that dies on refresh - which matters for a staff queue
    somebody is working through and a checklist they may need to come back to.

    Every control is labelled, including the search box, whose placeholder is not
    a label: a placeholder disappears the moment anyone types, so it cannot be
    the only thing naming the field.

    "Preserve the current sort" is deliberately absent. Nothing in the
    controllers accepts a sort parameter, so offering one would be a control
    that silently does nothing.
--}}
<form method="{{ $method }}" action="{{ $action }}"
    {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-navy-100 bg-navy-50/50 p-3 sm:flex-row sm:items-end']) }}>

    <div class="flex-1">
        <label for="filter-search" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-navy-500">
            {{ $label ?? 'Search' }}
        </label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-navy-400">
                <x-icon name="search" class="h-4 w-4" />
            </span>
            <input id="filter-search" type="search" name="q" value="{{ request('q') }}"
                placeholder="{{ $placeholder }}"
                class="block w-full rounded-lg border border-navy-200 bg-white py-2 pl-9 pr-3 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none" />
        </div>
    </div>

    @foreach ($selects as $select)
        <div class="sm:w-48">
            <label for="filter-{{ $select['name'] }}" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-navy-500">
                {{ $select['label'] }}
            </label>
            <select id="filter-{{ $select['name'] }}" name="{{ $select['name'] }}"
                class="block w-full rounded-lg border border-navy-200 bg-white px-3 py-2 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                @foreach ($select['options'] as $value => $text)
                    <option value="{{ $value }}" @selected((string) request($select['name']) === (string) $value)>
                        {{ $text }}
                    </option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div class="flex gap-2 sm:pb-0.5">
        <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
            {{ $submitLabel }}
        </button>

        @if ($showReset && request()->hasAny(array_merge(['q'], array_column($selects, 'name'))))
            <a href="{{ $action }}"
                class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                Reset
            </a>
        @endif
    </div>
</form>
