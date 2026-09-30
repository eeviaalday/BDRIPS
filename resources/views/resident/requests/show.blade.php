<x-public-layout>
    <x-slot name="title">Request {{ $documentRequest->reference_number }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'My Dashboard', 'route' => 'resident.dashboard'],
            ['label' => 'My Requests', 'route' => 'resident.requests.index'],
            ['label' => $documentRequest->reference_number],
        ]" />

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            {{-- ============================================================
                 TIMELINE
                 ============================================================
                 Drawn from the record's real status. It reports where the request
                 IS and never implies a step is available: the backend has no
                 state machine, so a crafted POST can move a completed request
                 back to under review, and a timeline that read as a menu of next
                 steps would be quietly wrong about that.
            --}}
            <x-card tone="white" class="lg:col-span-1">
                <h2 class="text-base font-bold text-navy-900">Progress</h2>
                <p class="mt-1 text-sm text-navy-600">Where this request has reached.</p>

                <div class="mt-5">
                    <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />
                </div>
            </x-card>

            <div class="space-y-5 lg:col-span-2">

                <x-card tone="white">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-navy-900">{{ $documentRequest->documentTypeLabel() }}</h2>
                            <p class="mt-1 font-mono text-sm text-navy-600">{{ $documentRequest->reference_number }}</p>
                        </div>
                        <x-status-badge :status="$documentRequest->status" />
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-navy-100 pt-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Purpose</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->purpose }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Fee</dt>
                            <dd class="mt-1 text-sm text-navy-900">
                                @if ($documentRequest->fee_amount)
                                    &p;{{ number_format($documentRequest->fee_amount, 2) }} — payable at the Barangay Hall
                                @else
                                    Set by the Secretary — payable at the Barangay Hall
                                @endif
                            </dd>
                        </div>
                        @if ($documentRequest->secretary_comment)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Note from the Secretary</dt>
                                <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->secretary_comment }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-card>

                {{-- Rejection carries a reason, so it is shown in full rather
                     than summarised into the badge. --}}
                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_REJECTED && $documentRequest->rejection_reason)
                    <div class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
                        <div class="flex items-start gap-3">
                            <x-icon name="x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                            <div>
                                <h2 class="text-sm font-bold text-red-900">This request was not approved</h2>
                                <p class="mt-1 text-sm leading-relaxed text-red-800">{{ $documentRequest->rejection_reason }}</p>
                                <p class="mt-2 text-sm text-red-700">
                                    If you think this is a mistake, or your circumstances have changed,
                                    speak to the Secretary at the Barangay Hall.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Released: the resident's one action on this page. There IS a
                     resident PDF endpoint - ResidentDocumentPdfController, which
                     renders the same certificate the Secretary sees and refuses
                     any row that is not this resident's and has not been
                     released. This comment used to claim the endpoint did not
                     exist, which left the certificate reachable only by hand-
                     typing the URL. --}}
                @if (in_array($documentRequest->status, [
                    \App\Models\DocumentRequest::STATUS_READY,
                    \App\Models\DocumentRequest::STATUS_COMPLETED,
                ], true))
                    <x-card tone="white" class="border-navy-200">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy text-white">
                                    <x-icon name="document" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h2 class="text-base font-bold text-navy-900">Your certificate</h2>
                                    <p class="mt-1 text-sm text-navy-600">
                                        Read or print a copy of the
                                        {{ $documentRequest->documentTypeLabel() }}.
                                    </p>
                                </div>
                            </div>

                            {{-- target=_blank so the PDF opens in its own tab and
                                 the tracking page stays underneath, and
                                 rel=noopener because a new tab given a
                                 reference back can reach this one. --}}
                            <a href="{{ route('resident.requests.pdf', $documentRequest) }}"
                                target="_blank" rel="noopener"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                                <x-icon name="download" class="h-4 w-4" />
                                View PDF
                            </a>
                        </div>
                    </x-card>
                @endif

                {{-- Ready only: the pickup window. --}}
                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_READY)
                    <x-card tone="white" class="border-gold-300 bg-gold-50/40">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold text-navy-900">
                                <x-icon name="document" class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-base font-bold text-navy-900">Ready for pickup</h2>
                                {{-- Kept as one unbroken sentence: it is the wording
                                     residents have been shown all along, and
                                     ResidentDocumentRequestDetailTest matches
                                     this exact string. The date is a separate
                                     line so nothing can come between the
                                     reminder and its full stop. --}}
                                <p class="mt-1 text-sm leading-relaxed text-navy-800">
                                    Please claim your document at the Barangay Hall within 7 working days.
                                    After that window the request is recorded as abandoned.
                                </p>
                                @if ($documentRequest->ready_at)
                                    <p class="mt-1 text-xs text-navy-500">
                                        Marked ready on {{ $documentRequest->ready_at->format('F d, Y') }}.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </x-card>
                @endif

                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_ABANDONED)
                    <div class="rounded-xl border border-navy-200 bg-navy-50/60 p-5">
                        <h2 class="text-sm font-bold text-navy-900">Recorded as abandoned</h2>
                        <p class="mt-1 text-sm leading-relaxed text-navy-700">
                            This document was not collected within the 7 working day pickup window and
                            is now recorded as abandoned. Three abandoned requests restrict online
                            applications for 30 days. If you still need it, please apply again at the
                            Barangay Hall.
                        </p>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('resident.requests.index') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                        Back to my requests
                    </a>

                    <a href="{{ route('requests.track', ['reference' => $documentRequest->reference_number]) }}"
                        class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                        Track by reference number
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
