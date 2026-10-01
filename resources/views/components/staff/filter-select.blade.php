@props([
    'name',
    'label',
    // [value => label] pairs, or a plain list of values when every label is the
    // value. The list of labels is what the width below is measured from.
    'options' => [],
    'value' => '',
    'allLabel' => null,
    // Turns a value into its label when the option values are internal slugs -
    // a role filter, say, where the reader wants "Kagawad" and the parameter is
    // "kagawad". Called once per option.
    'mapLabel' => null,
])

@php
    /*
     * The select half of the filter bar, sized to its own longest option.
     *
     * This is the truncation fix. Every filter select on the staff pages was
     * written without a width class and sat inside a flex row beside a search box,
     * so it shrank to whatever was left. Measured in Chrome at 1440px:
     *
     *     #role   93px wide, 63px of content space, "Resident" needs 55px
     *     #status 152px wide, "Awaiting a decision" needs 122px of 122px - 0px spare
     *
     * The second one was on the edge of clipping with the longest option selected,
     * and every one of them was narrower than its own longest option by
     * construction.
     *
     * The width is therefore computed from the labels rather than guessed:
     *
     *     min-width: (longest label) ch + room for the arrow and padding
     *
     * `ch` is the advance width of "0", which is a fair proxy here because these
     * labels are mostly lowercase words and the value is a floor, not a cap - a
     * select is free to be wider than its floor if the browser wants it to be, and
     * this component no longer constrains that with w-full.
     *
     * The room added for the arrow matters because @tailwindcss/forms sets
     * appearance: none, which removes the native control's own padding for it.
     */
    /*
     * Normalised to value => label.
     *
     * Two shapes are accepted, because two shapes are natural to pass:
     *
     *     ['queue' => 'Awaiting a decision']   value = the key, label = the value
     *     ['kagawad', 'resident']              value = the item, label = the item
     *
     * A list is a list of values, so the item is the value. Reading the array key
     * instead would emit value="0" and value="1" and lose both roles entirely.
     */
    $choices = [];

    foreach ($options as $key => $item) {
        $value = is_int($key) ? (string) $item : (string) $key;

        $choices[$value] = $mapLabel ? $mapLabel($value) : (string) $item;
    }

    $labels = $allLabel !== null ? [$allLabel, ...array_values($choices)] : array_values($choices);

    $longest = 0;

    foreach ($labels as $optionLabel) {
        $longest = max($longest, mb_strlen((string) $optionLabel));
    }
@endphp

<div class="min-w-0">
    <label for="filter-{{ $name }}" class="block text-sm font-semibold text-navy-800">{{ $label }}</label>
    <select id="filter-{{ $name }}" name="{{ $name }}"
        @style(['min-width: '.($longest + 4).'ch'])
        class="mt-1.5 rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
        @if ($allLabel !== null)
            <option value="" @selected((string) $value === '' || $value === null)>{{ $allLabel }}</option>
        @endif

        @foreach ($choices as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>