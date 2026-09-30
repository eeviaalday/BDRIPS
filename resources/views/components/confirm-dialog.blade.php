@props([
    'name',
    'title',
    'message',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    // 'danger' for anything irreversible (rejecting, deleting, unpublishing).
    // 'primary' for merely consequential.
    'tone' => 'primary',
    'form',
    'method' => 'POST',
])

@php
    /*
     * A confirmation step for an action the backend cannot undo.
     *
     * This is a confirmation DIALOG, not the enforcement. The route's own
     * middleware and the controller's guards remain the only thing standing
     * between a crafted POST and the action - hiding or disabling a button never
     * is. It exists so staff do not reject a resident, or delete a published
     * notice, on a mis-click.
     *
     * The dialog owns the one and only <form> for the action, so the trigger
     * outside it carries no payload. An action that needs input - a rejection
     * reason, a name, a reason for archiving - passes that input as the
     * component's slot and it is submitted from inside the dialog. That is
     * deliberate: the alternative, an invisible form elsewhere on the page whose
     * values a dialog button has to reach, is how a form ends up posting one
     * field's value under another field's name.
     */
    $tones = [
        'primary' => 'bg-navy hover:bg-navy-800 focus-visible:outline-navy-400',
        'danger' => 'bg-red-600 hover:bg-red-700 focus-visible:outline-red-400',
    ];
@endphp

<x-modal :name="$name" maxWidth="md">
    <form method="POST" action="{{ $form }}">
        @csrf
        @method($method)

        <div class="bg-white px-6 pb-2 pt-6">
            <h2 class="text-lg font-bold text-navy-900">{{ $title }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-navy-700">{{ $message }}</p>
        </div>

        @if (trim((string) $slot) !== '')
            <div class="px-6 pb-2">
                {{ $slot }}
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
            <button type="button"
                class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                x-on:click="show = false">
                {{ $cancelLabel }}
            </button>

            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 {{ $tones[$tone] ?? $tones['primary'] }}"
                x-on:click="show = false">
                {{ $confirmLabel }}
            </button>
        </div>
    </form>
</x-modal>
