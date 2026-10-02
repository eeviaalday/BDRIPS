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
     *
     * THE SHAPE IS DECIDED BY array_is_list(), NOT BY is_int($key). That is the fix
     * for a filter that looked right and filtered nothing. `is_int()` cannot tell
     * a list of slugs from a map keyed by a database id, because PHP normalises the
     * string key "5" to the integer 5 on the way in. So for an option list built
     * with `->pluck('sector_name', 'id')` — [5 => 'Purok 5', 7 => 'Purok 7'] —
     * is_int() said yes, the id was thrown away, and the option rendered as
     *     <option value="Purok 5">Purok 5</option>
     * Submitting that sent `sector_id=Purok 5`, which `$request->integer()` turned
     * into 0, which the controller read as "no filter at all". The Sector filter on
     * the Residents list, the Account filter on Audit Logs, and both the Sector and
     * the Batch filter on the Kagawad resident list could not narrow the list from
     * the interface - and arriving at ?sector_id=5 narrowed the table while the
     * select still read "All sectors".
     *
     * A list has keys 0, 1, 2 and nothing else, and a database id is never 0, so
     * array_is_list() separates the two cases without guessing. A map whose keys
     * happened to be 0, 1, 2 is not a thing that can be built from a primary key.
     *
     * THE LOOP VARIABLE IS NOT CALLED $value. It used to be, and that was a second,
     * separate bug in the same component.
     *
     * $value is the PROP - the thing the caller passed as the current filter. The
     * loop then reused the name, so after the loop finished $value held the LAST
     * option's value, and every `@selected($value === $optionValue)` below was
     * really testing the last option against itself. The result was that the
     * control always displayed its last option, whatever the query string said.
     *
     * On User Accounts that is exactly what was reported: $role was '' — no
     * filter, every role listed — while the select showed "Resident", because
     * User::roles() ends with 'resident' and that is the value the loop left in
     * $value. The label and the filter disagreed, and the reason was neither the
     * controller nor the option list: it was a loop variable shadowing a prop.
     *
     * $optionValue is used below instead, and the loop writes to $choiceValue, so
     * nothing in this file can overwrite $value.
     */
    $isList = array_is_list($options);

    $choices = [];

    foreach ($options as $optionKey => $optionItem) {
        $choiceValue = $isList ? (string) $optionItem : (string) $optionKey;

        $choices[$choiceValue] = $mapLabel ? $mapLabel($choiceValue) : (string) $optionItem;
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
            {{-- (string) on BOTH sides, and not only on $value. A key that came from
                 a database id is the string '5' on the way into $choices and the
                 integer 5 on the way back out, because PHP normalises numeric
                 array keys. Comparing (string) $value === $optionValue would then be
                 '5' === 5, which is false, and an id-keyed filter would show the
                 wrong option as selected - the Sector, Account and Batch filters
                 all being id-keyed. --}}
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>