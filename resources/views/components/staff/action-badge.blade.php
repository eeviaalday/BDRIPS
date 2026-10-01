@props([
    // An audit_log.action value, e.g. "relief.received".
    'action',
    // The model's own wording, used when this component has nothing to say about
    // the action. Falling back means a new action is still legible on the page
    // rather than rendering as an empty pill.
    'label' => null,
    'size' => 'md',
    'class' => '',
])

@php
    /*
     * An audit action, as a badge.
     *
     * A separate component rather than a dozen new keys in x-staff.status-badge,
     * because an audit action is not an application status. "Relief marked
     * received" is a thing that happened; "Collected" is a state a record is in.
     * They want the same shape and nothing else in common, and mixing them would
     * mean a key like "relief_received" sitting next to "claimed" in a map that
     * every other staff page also reads.
     *
     * COLOUR BY FAMILY, not by action. Six families, six colours, so the log can
     * be skimmed by hue: a run of green is relief work, a run of red is something
     * being refused or removed. The per-action exceptions below are the ones where
     * the family's colour would say the wrong thing - "Account restricted" is a
     * user action, and colouring it navy would make a lockout look like a rename.
     *
     * Colour is never the only cue: every badge carries the action's own wording
     * and a dot, so the family is a second and third signal rather than the only
     * one.
     */
    $family = Str::before($action, '.');
    $name = Str::after($action, '.');

    $tones = [
        'resident' => ['bg-indigo-50 text-indigo-800 border-indigo-200', 'bg-indigo-500'],
        'request' => ['bg-sky-50 text-sky-800 border-sky-200', 'bg-sky-500'],
        'relief' => ['bg-emerald-50 text-emerald-800 border-emerald-200', 'bg-emerald-500'],
        'announcement' => ['bg-amber-50 text-amber-800 border-amber-200', 'bg-amber-500'],
        'account' => ['bg-navy-50 text-navy-800 border-navy-200', 'bg-navy-400'],
        'user' => ['bg-navy-50 text-navy-800 border-navy-200', 'bg-navy-400'],
        'settings' => ['bg-slate-50 text-slate-800 border-slate-200', 'bg-slate-400'],
        'sector' => ['bg-slate-50 text-slate-800 border-slate-200', 'bg-slate-400'],
    ];

    /*
     * Actions whose family's colour would be misleading. A restriction is a user
     * action that closes an account; a rejection is a request action that turns
     * something down; a deletion removes something. All three are red, whatever
     * family they belong to, because that is the thing a reader is scanning for.
     */
    $negative = [
        'user.restricted',
        'resident.rejected',
        'request.rejected',
        'announcement.deleted',
        'announcement.unpublished',
        'relief.batch_closed',
    ];

    // The counterpart that undoes one of the above, so lifting a restriction does
    // not read as the same event as imposing it.
    $restorative = [
        'user.unrestricted',
        'announcement.published',
        'relief.batch_reopened',
    ];

    if (in_array($action, $negative, true)) {
        [$classes, $dot] = ['bg-red-50 text-red-800 border-red-200', 'bg-red-500'];
    } elseif (in_array($action, $restorative, true)) {
        [$classes, $dot] = ['bg-emerald-50 text-emerald-800 border-emerald-200', 'bg-emerald-500'];
    } else {
        [$classes, $dot] = $tones[$family] ?? ['bg-navy-50 text-navy-800 border-navy-200', 'bg-navy-400'];
    }

    $sizes = [
        'sm' => 'px-2 py-0.5 text-2xs',
        'md' => 'px-2.5 py-1 text-xs',
    ];

    $text = $label ?? Str::headline(str_replace('_', ' ', $action));
@endphp

<span
    class="inline-flex items-center gap-1.5 rounded-full border font-medium {{ $sizes[$size] ?? $sizes['md'] }} {{ $classes }} {{ $class }}">
    <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dot }}"></span>
    {{ $text }}
</span>
