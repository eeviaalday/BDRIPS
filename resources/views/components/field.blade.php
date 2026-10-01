@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'icon' => null,
    'mono' => false,
    'id' => null,
    'class' => '',
    'revealable' => false,
    'variant' => 'default',
    'clientInvalid' => null,
    'clientMessage' => null,
])

@php
    /*
     * Labelled form control.
     *
     * The id lives ONLY on the <input> so <label for="..."> always resolves to
     * the control itself. Any id passed by the caller is reused rather than
     * duplicated onto the wrapper.
     *
     * :revealable adds a show/hide toggle for password fields. It is opt-in, so
     * the four other views that use this component are unaffected.
     *
     * :variant swaps the control's own surface. "soft" is the filled treatment
     * the sign-in card uses - grey field, blue focus ring, no leading icon - and
     * exists so that card keeps this component's label, aria-describedby, hint
     * and error wiring instead of hand-rolling a second input to look different.
     * Passing extra classes through $class cannot do this: Tailwind resolves
     * conflicting utilities by stylesheet order, not by which one is written
     * last in the attribute, so the component's bg-white would win over a
     * caller's bg-slate-50 half the time.
     */

    $inputId = $id ?? $attributes->get('id', $name);
    $describedBy = [];
    if ($hint) { $describedBy[] = $inputId.'-hint'; }
    if ($errors->has($name)) { $describedBy[] = $inputId.'-error'; }
    if ($clientMessage) { $describedBy[] = $inputId.'-client-error'; }

    $surfaces = [
        'default' => 'block w-full rounded-lg border-navy-200 bg-white text-sm text-navy-900 shadow-sm transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none',
        'soft' => 'block w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:outline-none',
    ];

    $surface = $surfaces[$variant] ?? $surfaces['default'];

    // A file input cannot carry a value: browsers refuse to let a page set one,
    // and a "value" attribute on <input type="file"> is ignored, so emitting it
    // would be misleading rather than merely redundant. The value comes from the
    // resident's own selection instead.
    //
    // A file control is also drawn by the browser as one piece - the button and
    // the chosen filename are painted together inside the same box - so it
    // cannot be laid out with the button on one side and the filename on the
    // other. Getting that arrangement means rendering the filename as a separate
    // read-only box beside a button that opens the picker, and keeping the real
    // input alongside them for the form to submit. That is what $isFile selects.
    $isFile = $type === 'file';

    // Alpine state, for whichever feature this control carries. Only one can be
    // in play at a time, so the wrapper declares the scope it needs rather than
    // carrying an empty object on every field.
    $scope = match (true) {
        $revealable => '{ shown: false }',
        $isFile => "{ fileName: '' }",
        default => null,
    };

    // Horizontal padding is resolved here, once, rather than by writing a base
    // and an override into the class list. pl-4 and pl-11 are both
    // padding-left, and Tailwind picks between them by stylesheet order, not by
    // which appears last in the attribute - so emitting both would make the
    // icon and the reveal toggle fight over the build.
    //
    // The icon drives this on every variant, "soft" included. Gating it on the
    // variant was only ever safe because the one place "soft" was introduced -
    // the sign-in card - has no leading icon; combine "soft" with an icon, as
    // the registration form does, and the input kept pl-4 while the glyph still
    // rendered at left-0, so the placeholder started behind the icon and ran
    // underneath it. 44px clears a 14px inset plus a 20px glyph with room to
    // spare.
    $left = $icon ? 'pl-11' : 'pl-4';
    $right = $revealable ? 'pr-11' : 'pr-4';
@endphp

<div class="{{ trim($class) }}">
    <label for="{{ $inputId }}" class="block text-sm font-semibold text-navy-900">{{ $label }}</label>

    <div @if ($scope) x-data="{{ $scope }}" @endif class="relative mt-1.5">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-navy-400">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        @if ($isFile)
            {{--
                The real control, kept in the form and in the tab order but
                clipped to nothing. display:none would drop it from the tab
                sequence and can stop a button pointing at it working; clipping
                leaves it focusable, so a submission with no file chosen still
                gets the browser's own "please choose a file" prompt.

                Its name is what the server receives. The filename box below is
                a display only and carries no name, so nothing about the
                presentation can reach the request.
            --}}
            <input
                id="{{ $inputId }}"
                name="{{ $name }}"
                type="file"
                x-ref="file"
                @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                @if ($errors->has($name)) aria-invalid="true" @endif
                @if ($clientInvalid) x-bind:aria-invalid="{{ $clientInvalid }} ? 'true' : null" @endif
                {{ $attributes->only(['required', 'disabled', 'accept'])->merge(['class' => 'file-input']) }}
                x-on:change="fileName = $event.target.files[0]?.name ?? ''"
            />

            <div class="file-row">
                {{-- The chosen name, echoed. tabindex="-1" keeps it out of the
                     tab sequence: it cannot be typed into, and a keyboard user
                     should land on the button that does something. aria-live
                     announces the change when a file is picked. --}}
                <div aria-live="polite" class="min-w-0 flex-1">
                    <input
                        type="text"
                        readonly
                        tabindex="-1"
                        placeholder="No file chosen"
                        class="file-name"
                        x-bind:value="fileName"
                    />
                </div>

                {{-- A real button, not a <label for>. A label is not focusable,
                     so a keyboard user could not reach the picker at all; this is
                     and can be activated with Enter or Space. --}}
                <button type="button" class="file-browse" x-on:click="$refs.file.click()">Browse...</button>
            </div>
        @else
            <input
                id="{{ $inputId }}"
                name="{{ $name }}"
                type="{{ $type }}"
                value="{{ $value }}"
                @if ($revealable) x-bind:type="shown ? 'text' : 'password'" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                @if ($errors->has($name)) aria-invalid="true" @endif
                @if ($clientInvalid) x-bind:aria-invalid="{{ $clientInvalid }} ? 'true' : null" @endif
                {{ $attributes->only(['required', 'autocomplete', 'readonly', 'disabled', 'min', 'max', 'step', 'accept'])->merge([
                    'class' => trim($surface.' '.$left.' '.$right.($mono ? ' font-mono' : '')),
                ]) }}
            />

            @if ($revealable)
                <button type="button" x-on:click="shown = ! shown"
                    x-bind:aria-label="shown ? 'Hide password' : 'Show password'"
                    x-bind:aria-pressed="shown ? 'true' : 'false'"
                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-navy-400 transition hover:text-navy-700 focus:outline-none focus-visible:text-navy-900">
                    {{-- Eye --}}
                    <svg x-show="! shown" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{-- Eye with slash --}}
                    <svg x-show="shown" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.586 10.586a2 2 0 001.414 1.414M9.879 9.879A3 3 0 004.879 4.879m-2.843 1.443A11.96 11.96 0 002.036 12.322a1.012 1.012 0 000 .639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    </svg>
                </button>
            @endif
        @endif
    </div>

    @if ($hint)
        <p id="{{ $inputId }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $inputId }}-error" class="mt-1.5 flex items-start gap-1 text-xs font-medium text-red-700">
            <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @enderror

      @if ($clientMessage)
          <p id="{{ $inputId }}-client-error" x-show="{{ $clientInvalid }}" x-cloak
              class="mt-1.5 flex items-start gap-1 text-xs font-medium text-red-700">
              <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
              <span>{{ $clientMessage }}</span>
          </p>
      @endif
</div>
