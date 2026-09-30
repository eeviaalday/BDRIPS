@props([
    'name',
    'label',
    'accept' => null,
    'hint' => null,
    'currentFile' => null,
    /*
     * "messages", not "errors". Laravel shares an $errors ViewErrorBag with
     * every view, and an anonymous component's prop default only applies when
     * the name is undefined - so a prop called "errors" was silently replaced by
     * the error bag, and rendering it blew up on htmlspecialchars() with an
     * array. x-input-error already calls this prop "messages"; this now matches.
     */
    'messages' => [],
    'required' => false,
])

@php
    /*
     * File input with the chosen filename shown in a separate read-only box and
     * a Browse button beside it, rather than the browser's own single control.
     *
     * A native file input draws its button and the chosen filename together
     * inside one box - there is no markup that lets you place them on opposite
     * sides - so the visible control here is a presentation, and the real
     * <input type="file"> is kept alongside it, clipped to nothing, still inside
     * the form and still carrying the name the server receives.
     *
     * The Browse control is a real <button> rather than a <label for>: a label is
     * not focusable, so a keyboard user could not open the picker at all.
     *
     * The filename box carries NO name attribute. It echoes the browser's own
     * value, and a name would post a second, untrusted value for a field the
     * server never reads.
     *
     * Nothing here validates. The accept attribute only filters the OS picker;
     * the authoritative checks are the controller's mimes and max rules, and
     * those messages arrive through $messages.
     */
    $controlId = 'upload-'.$name;
@endphp

<div>
    <label for="{{ $controlId }}" class="block text-sm font-semibold text-navy-900">
        {{ $label }}
        @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
    </label>

    <div
        class="mt-1.5 flex flex-col gap-3 rounded-xl border border-navy-200 bg-navy-50/50 p-3 sm:flex-row sm:items-center"
        x-data="{ fileName: '{{ $currentFile }}' }">

        <input
            id="{{ $controlId }}"
            name="{{ $name }}"
            type="file"
            x-ref="file"
            class="sr-only"
            @if ($accept) accept="{{ $accept }}" @endif
            @if ($required) required @endif
            @foreach ((array) $messages as $message) aria-invalid="true" @endforeach
            @if ($hint) aria-describedby="{{ $controlId }}-hint" @endif
            x-on:change="fileName = $event.target.files[0]?.name ?? ''"
        />

        <p class="min-w-0 flex-1 truncate text-sm text-navy-600" aria-live="polite">
            <span x-show="! fileName" class="text-navy-400">No file chosen</span>
            <span x-show="fileName" x-cloak class="font-medium text-navy-900" x-text="fileName"></span>
        </p>

        <button type="button"
            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
            x-on:click="$refs.file.click()">
            Browse&hellip;
        </button>
    </div>

    @if ($hint)
        <p id="{{ $controlId }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif

    @foreach ((array) $messages as $message)
        <p class="mt-1.5 flex items-start gap-1 text-xs font-medium text-red-700">
            <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @endforeach
</div>
