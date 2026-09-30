{{--
    One track modal, for one request.

    Kept in its own file and pulled in with @include rather than written inline
    inside a @foreach. Blade will not compile a @foreach that wraps a component
    with a slot - the tag's content is captured as the slot and the loop's
    endforeach is left uncompiled, which is a parse error in the compiled view.
    An @include has no such boundary, so the loop stays outside and this file
    owns the whole modal.

    Everything shown here comes off the resident's own row, so there is no
    reference field to submit and nothing on the page to tamper with.
--}}
@php
    $messages = [
        \App\Models\DocumentRequest::STATUS_SUBMITTED => 'Received. Waiting for review.',
        \App\Models\DocumentRequest::STATUS_UNDER_REVIEW => 'The Barangay Secretary is reviewing your request.',
        \App\Models\DocumentRequest::STATUS_READY => 'Collect at the Barangay Hall within 7 working days. Bring a valid ID and your reference number.',
        \App\Models\DocumentRequest::STATUS_COMPLETED => $request->claimed_at
            ? 'Released on '.$request->claimed_at->format('M d, Y').'.'
            : 'Collected from the Barangay Hall.',
        \App\Models\DocumentRequest::STATUS_REJECTED => 'This request was not approved. The reason is below, and you can submit a new request once it is corrected.',
        \App\Models\DocumentRequest::STATUS_ABANDONED => 'This request was recorded as abandoned because it was not collected within the 7 working days.',
    ];
@endphp

<x-modal name="track-{{ $request->id }}" maxWidth="lg" dismissible>
    <div class="px-6 pb-2 pt-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-navy-500">Tracking</p>
        <h2 id="track-{{ $request->id }}-title" class="mt-0.5 text-lg font-bold text-navy-900">
            {{ $request->documentTypeLabel() }}
        </h2>
    </div>

    <div class="px-6">
        <dl class="grid grid-cols-1 gap-3 rounded-xl border border-navy-100 bg-navy-50/50 px-4 py-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Reference</dt>
                <dd class="mt-0.5 font-mono font-semibold text-navy-900">{{ $request->reference_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Requested</dt>
                <dd class="mt-0.5 font-semibold text-navy-900">{{ $request->created_at->format('M d, Y') }}</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-xl border border-navy-100 px-4 py-3.5">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-navy-900">Current status</h3>
                <x-status-badge :status="$request->status" size="sm" />
            </div>
            <p class="mt-2 text-sm leading-relaxed text-navy-700">
                {{ $messages[$request->status] ?? 'This request is '.$request->status.'.' }}
            </p>

            @if ($request->status === \App\Models\DocumentRequest::STATUS_REJECTED && $request->rejection_reason)
                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                    <span class="font-semibold">Reason:</span> {{ $request->rejection_reason }}
                </p>
            @endif

            @if ($request->status === \App\Models\DocumentRequest::STATUS_READY)
                <a href="{{ route('resident.requests.pdf', $request) }}" target="_blank" rel="noopener"
                    class="mt-3 inline-flex items-center gap-2 rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800">
                    <x-icon name="document" class="h-4 w-4" />
                    View PDF
                </a>
            @endif
        </div>

        <x-request-timeline :status="$request->status" :request="$request" class="mt-4" />
    </div>

    <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button"
            class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
            x-on:click="show = false">Close</button>
        <a href="{{ route('resident.requests.show', $request) }}"
            class="inline-flex items-center justify-center rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
            Full details
        </a>
    </div>
</x-modal>
