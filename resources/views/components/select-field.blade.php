@props([
    'label',
    'name',
    'options',
    'value' => null,
    'placeholder' => 'Select...',
    'clientInvalid' => null,
    'clientMessage' => null,
    'hint' => null,
    'id' => null,
    'class' => '',
    'variant' => 'default',
])

@php
    /*
     * Labelled <select>, the dropdown counterpart to <x-field>.
     *
     * The same accessibility contract as that component, deliberately: the id
     * lives only on the <control>, so <label for="..."> always resolves to the
     * control itself, and the hint and error paragraphs are wired up through
     * aria-describedby rather than merely placed next to it.
     *
     * "options" is a key => label map, so a stored value round-trips as the key
     * it was stored as rather than as the text shown. An empty "value" renders
     * the placeholder as a real disabled option, so a select that was never
     * answered cannot silently submit an empty string.
     *
     * :variant mirrors <x-field>'s, and "soft" is deliberately the same
     * treatment - a dropdown sitting in a row of inputs has to match them, or the
     * form reads as two different forms stapled together.
     */

    $controlId = $id ?? $attributes->get('id', $name);
    $describedBy = [];
    if ($hint) { $describedBy[] = $controlId.'-hint'; }
    if ($errors->has($name)) { $describedBy[] = $controlId.'-error'; }
    if ($clientMessage) { $describedBy[] = $controlId.'-client-error'; }

    $current = (string) old($name, $value ?? '');

    $surfaces = [
        'default' => 'mt-1.5 block w-full rounded-lg border-navy-200 bg-white py-2.5 pl-3 pr-9 text-sm text-navy-900 shadow-sm transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none',
        'soft' => 'mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:outline-none',
    ];
@endphp

<div class="{{ trim($class) }}">
    <label for="{{ $controlId }}" class="block text-sm font-semibold text-navy-900">{{ $label }}</label>

    <select
        id="{{ $controlId }}"
        name="{{ $name }}"
        @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($clientInvalid) x-bind:aria-invalid="{{ $clientInvalid }} ? 'true' : null" @endif
        {{ $attributes->only(['required', 'disabled', 'multiple'])->merge([
            'class' => $surfaces[$variant] ?? $surfaces['default'],
        ]) }}
    >
        @if ($placeholder)
            <option value="" @selected($current === '') disabled>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $key => $label)
            <option value="{{ $key }}" @selected($current === (string) $key)>{{ $label }}</option>
        @endforeach
    </select>

    @if ($hint)
        <p id="{{ $controlId }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $controlId }}-error" class="mt-1.5 flex items-start gap-1 text-xs font-medium text-red-700">
            <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @enderror

    @if ($clientMessage)
        <p id="{{ $controlId }}-client-error" x-show="{{ $clientInvalid }}" x-cloak
            class="mt-1.5 flex items-start gap-1 text-xs font-medium text-red-700">
            <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span>{{ $clientMessage }}</span>
        </p>
    @endif
</div>
