@props([
    'status',
    // The record, for the real timestamps. Passed so the component never has to
    // invent a date.
    'request' => null,
])

@php
    /*
     * The document request lifecycle, drawn from the statuses that actually
     * exist in App\Models\DocumentRequest.
     *
     * The order below is the real pipeline: submitted -> under_review ->
     * ready_for_pickup -> completed, with rejected and abandoned as the two
     * ways out. The order is NOT a set of allowed transitions - the controllers
     * do not enforce a state machine - so this component only ever reports where
     * the record IS, and marks the rest as not reached. It never implies a step
     * is available.
     *
     * A rejected or abandoned request is shown on the terminal path it took
     * rather than being drawn as if it were still travelling the main line, so a
     * resident is never told their document is "on its way" when it is not.
     *
     * Every marker carries text as well as colour, and the current one is
     * marked aria-current="step" - the timeline is a status, not decoration.
     */
    use App\Models\DocumentRequest;

    $main = [
        DocumentRequest::STATUS_SUBMITTED => 'Submitted',
        DocumentRequest::STATUS_UNDER_REVIEW => 'Under Review',
        DocumentRequest::STATUS_READY => 'Ready for Pickup',
        DocumentRequest::STATUS_COMPLETED => 'Completed',
    ];

    $isOffTrack = in_array($status, [
        DocumentRequest::STATUS_REJECTED,
        DocumentRequest::STATUS_ABANDONED,
    ], true);

    // How far along the main line the record has travelled.
    $reached = $isOffTrack
        ? 1
        : array_search($status, array_keys($main), true);

    $steps = [];

    foreach ($main as $key => $label) {
        $steps[] = [
            'label' => $label,
            'state' => $key === $status ? 'current' : ($reached !== false && array_search($key, array_keys($main), true) < $reached ? 'done' : 'todo'),
            'note' => null,
        ];
    }

    if ($status === DocumentRequest::STATUS_REJECTED) {
        $steps[] = [
            'label' => 'Rejected',
            'state' => 'current',
            'note' => $request?->rejection_reason,
        ];
    }

    if ($status === DocumentRequest::STATUS_ABANDONED) {
        $steps[] = [
            'label' => 'Abandoned',
            'state' => 'current',
            'note' => 'Not collected within the 7 working day pickup window.',
        ];
    }

    $markers = [
        'done' => 'bg-navy text-white',
        'current' => 'bg-gold text-navy-900 ring-4 ring-gold-200',
        'todo' => 'bg-navy-100 text-navy-400',
    ];
@endphp

<ol class="space-y-0">
    @foreach ($steps as $index => $step)
        <li class="relative flex gap-4 pb-6 last:pb-0">
            @unless ($loop->last)
                {{-- The connector is aria-hidden: it is a visual link between
                     markers, and announcing it would just repeat the list. --}}
                <span class="absolute left-[15px] top-9 h-[calc(100%-2.25rem)] w-0.5 rounded {{ $step['state'] === 'todo' ? 'bg-navy-100' : 'bg-navy-200' }}"
                    aria-hidden="true"></span>
            @endunless

            <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $markers[$step['state']] }}"
                @if ($step['state'] === 'current') aria-current="step" @endif>
                @if ($step['state'] === 'done')
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{-- The tick is aria-hidden, so without this a screen reader
                         hears a bare "1", "2" and cannot tell a reached step from
                         an unreached one. --}}
                    <span class="sr-only">Completed:</span>
                @else
                    {{ $index + 1 }}
                @endif
            </span>

            <div class="min-w-0 pt-1">
                <p @class([
                    'text-sm font-semibold',
                    'text-navy-900' => $step['state'] !== 'todo',
                    'text-navy-400' => $step['state'] === 'todo',
                ])>
                    {{ $step['label'] }}
                    @if ($step['state'] === 'current')
                        <span class="sr-only">(current status)</span>
                    @endif
                </p>

                @if ($step['note'])
                    <p class="mt-1 text-sm text-navy-600">{{ $step['note'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>

@if ($request !== null)
    <dl class="mt-4 grid grid-cols-1 gap-3 border-t border-navy-100 pt-4 sm:grid-cols-2">
        @foreach ([
            'Submitted' => $request->created_at,
            'Reviewed' => $request->reviewed_at,
            'Ready' => $request->ready_at,
            'Collected' => $request->claimed_at,
        ] as $label => $date)
            @if ($date)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-navy-800">{{ $date->format('M d, Y g:i A') }}</dd>
                </div>
            @endif
        @endforeach
    </dl>
@endif
