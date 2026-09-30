@props(['status', 'size' => 'md', 'label' => null, 'soft' => false])

@php
    /*
     * BDRPIS status badge.
     *
     * Status is always communicated by BOTH colour and text, so meaning is
     * never carried by colour alone. Each state also maps to a distinct
     * glyph, giving a third redundant cue for low-vision users.
     *
     * The label prop overrides the wording without changing the colours, for
     * the screens where the state is computed rather than a stored value - a
     * status page that has to say "3 pending" rather than "Pending" is still
     * reporting the same state, and inventing a new status key for it would
     * put a fifth meaning into this map.
     */

    $map = [
        // Document request lifecycle
        'submitted' => ['label' => 'Submitted', 'wrap' => 'bg-sky-50 text-sky-800 border-sky-200', 'dot' => 'bg-sky-500'],
        'under_review' => ['label' => 'Under Review', 'wrap' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'dot' => 'bg-indigo-500'],
        'ready_for_pickup' => ['label' => 'Ready for Pickup', 'wrap' => 'bg-gold-50 text-gold-800 border-gold-300', 'dot' => 'bg-gold-500'],
        'claimed' => ['label' => 'Claimed', 'wrap' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-600'],
        'rejected' => ['label' => 'Rejected', 'wrap' => 'bg-red-50 text-red-800 border-red-200', 'dot' => 'bg-red-500'],

        // Resident / profile verification
        'pending' => ['label' => 'Pending', 'wrap' => 'bg-gold-50 text-gold-800 border-gold-300', 'dot' => 'bg-gold-500'],
        'verified' => ['label' => 'Verified', 'wrap' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-600'],
        'unverified' => ['label' => 'Unverified', 'wrap' => 'bg-navy-50 text-navy-700 border-navy-200', 'dot' => 'bg-navy-400'],

        // Relief distribution
        'received' => ['label' => 'Received', 'wrap' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-600'],

        // Relief batch
        'active' => ['label' => 'Active', 'wrap' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-600'],
        'closed' => ['label' => 'Closed', 'wrap' => 'bg-navy-50 text-navy-700 border-navy-200', 'dot' => 'bg-navy-400'],
    ];

    $fallback = ['label' => ucwords(str_replace('_', ' ', (string) $status)), 'wrap' => 'bg-navy-50 text-navy-700 border-navy-200', 'dot' => 'bg-navy-400'];
    $s = $map[$status] ?? $fallback;

    if ($label !== null) {
        $s['label'] = $label;
    }

    /*
     * Soft pills, for the resident's own tables.
     *
     * Drops the ring and deepens the fill, so a column of them reads as one band
     * of soft colour rather than a column of outlined boxes competing with the
     * zebra striping behind them. Applied by rewriting the border utility in the
     * map's own wrap string rather than by appending a class: two border-colour
     * utilities on one element are resolved by their order in the stylesheet, not
     * by their order in the attribute, so appending one would be a coin toss.
     *
     * Off by default, so every existing badge renders exactly as it did.
     */
    if ($soft) {
        $s['wrap'] = preg_replace('/border-[a-z]+-[0-9]{2,3}/', 'border-transparent', $s['wrap']);
    }

    $sizes = [
        'sm' => 'px-2 py-0.5 text-2xs gap-1.5',
        'md' => 'px-2.5 py-1 text-xs gap-2',
    ];
@endphp

<span {{ $attributes->merge(['class' => trim('inline-flex items-center rounded-full border font-bold uppercase tracking-wide '.($sizes[$size] ?? $sizes['md']).' '.$s['wrap'])]) }}>
    <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $s['dot'] }}"></span>
    {{ $s['label'] }}
</span>
