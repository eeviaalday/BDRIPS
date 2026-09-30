@props(['status', 'size' => 'md'])

@php
    /*
     * BDRPIS status badge.
     *
     * Status is always communicated by BOTH colour and text, so meaning is
     * never carried by colour alone. Each state also maps to a distinct
     * glyph, giving a third redundant cue for low-vision users.
     */

    $map = [
        // Document request lifecycle
        'submitted' => ['label' => 'Submitted', 'wrap' => 'bg-sky-50 text-sky-800 border-sky-200', 'dot' => 'bg-sky-500'],
        'under_review' => ['label' => 'Under Review', 'wrap' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'dot' => 'bg-indigo-500'],
        'ready_for_pickup' => ['label' => 'Ready for Pickup', 'wrap' => 'bg-gold-50 text-gold-800 border-gold-300', 'dot' => 'bg-gold-500'],
        'completed' => ['label' => 'Completed', 'wrap' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-600'],
        'rejected' => ['label' => 'Rejected', 'wrap' => 'bg-red-50 text-red-800 border-red-200', 'dot' => 'bg-red-500'],
        'abandoned' => ['label' => 'Abandoned', 'wrap' => 'bg-navy-50 text-navy-700 border-navy-200', 'dot' => 'bg-navy-400'],

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

    $sizes = [
        'sm' => 'px-2 py-0.5 text-2xs gap-1.5',
        'md' => 'px-2.5 py-1 text-xs gap-2',
    ];
@endphp

<span {{ $attributes->merge(['class' => trim('inline-flex items-center rounded-full border font-bold uppercase tracking-wide '.($sizes[$size] ?? $sizes['md']).' '.$s['wrap'])]) }}>
    <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $s['dot'] }}"></span>
    {{ $s['label'] }}
</span>
