@props([
    'status',
    // Overrides the wording without inventing a state: a caller can say
    // "3 pending" instead of "Pending" and the badge is still reporting one
    // state, with the same colour and the same dot.
    'label' => null,
    'size' => 'md',
    // The leading dot. On by default because it is the second, non-colour cue that
    // keeps the state legible. Off for the pills that are tags rather than states -
    // an item in a batch, say - where a status dot would imply something the list
    // does not mean.
    'dot' => true,
    'class' => '',
])

@php
    /*
     * One badge for the whole staff area.
     *
     * The staff pages were reaching for the shared x-status-badge and for hand-rolled
     * pills, and between them the same idea wore five different sets of classes. Some
     * states the shared map does not have a key for were being rendered as something
     * loosely adjacent - a closed batch as "inactive", for instance - because there
     * was nowhere else to go.
     *
     * So the map below is the whole of it, and it covers every state the staff area
     * actually shows. Colours follow the resident badge: a tinted fill, a matching
     * border and a darker text, plus a dot.
     *
     * Colour is never the only cue. Every badge carries its own word and the dot is a
     * second, redundant shape cue, so a state is legible without colour vision and
     * without relying on a screen reader to rescue it.
     *
     * An unrecognised status falls back to neutral rather than to nothing, so a new
     * state is visible-and-unknown instead of invisible.
     */
    $map = [
        // Resident verification
        'pending' => ['Pending', 'bg-gold-50 text-gold-800 border-gold-300', 'bg-gold-500'],
        'verified' => ['Verified', 'bg-green-50 text-green-800 border-green-200', 'bg-green-600'],
        'rejected' => ['Rejected', 'bg-red-50 text-red-800 border-red-200', 'bg-red-500'],

        // Document requests
        'submitted' => ['Submitted', 'bg-sky-50 text-sky-800 border-sky-200', 'bg-sky-500'],
        'under_review' => ['Under review', 'bg-indigo-50 text-indigo-800 border-indigo-200', 'bg-indigo-500'],
        'ready_for_pickup' => ['Ready for pickup', 'bg-gold-50 text-gold-800 border-gold-300', 'bg-gold-500'],
        'claimed' => ['Collected', 'bg-green-50 text-green-800 border-green-200', 'bg-green-600'],

        // Relief batches
        'active' => ['Active', 'bg-green-50 text-green-800 border-green-200', 'bg-green-600'],
        'closed' => ['Closed', 'bg-navy-50 text-navy-700 border-navy-200', 'bg-navy-400'],

        // A household that signed for its relief goods. The checklist was
        // rendering these as ready_for_pickup, which is a *document request*
        // state: a household that has already collected its goods is not waiting
        // to collect them, and a Kagawad reading that badge mid-distribution is
        // being told the opposite of what happened.
        'collected' => ['Collected', 'bg-green-50 text-green-800 border-green-200', 'bg-green-600'],

        // Announcements
        'draft' => ['Draft', 'bg-navy-50 text-navy-700 border-navy-200', 'bg-navy-400'],
        'scheduled' => ['Scheduled', 'bg-sky-50 text-sky-800 border-sky-200', 'bg-sky-500'],
        'published' => ['Published', 'bg-green-50 text-green-800 border-green-200', 'bg-green-600'],

        // Accounts
        'restricted' => ['Restricted', 'bg-red-50 text-red-800 border-red-200', 'bg-red-500'],

        // A neutral chip for text that is a state rather than a status - the
        // action on an audit entry, for instance, which is always labelled from the
        // model and never picks a colour of its own.
        'neutral' => ['Label', 'bg-navy-50 text-navy-800 border-navy-200', 'bg-navy-400'],
    ];

    [$fallbackLabel, $fallbackWrap, $fallbackDot] = ['Unknown', 'bg-navy-50 text-navy-700 border-navy-200', 'bg-navy-400'];

    [$defaultLabel, $wrap, $dotColour] = $map[$status] ?? [$fallbackLabel, $fallbackWrap, $fallbackDot];

    $text = $label ?? $defaultLabel;
    $pad = $size === 'sm' ? 'px-2 py-0.5 text-2xs' : 'px-2.5 py-1 text-xs';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border font-medium '.$pad.' '.$wrap.' '.$class]) }}>
    @if ($dot)
        <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotColour }}"></span>
    @endif
    <span class="truncate">{{ $text }}</span>
</span>