<x-public-layout>
    <x-slot name="title">Track Request</x-slot>

    <x-page-header :crumbs="['Track a Request']" title="Track Your Document Request"
        description="Enter the reference number from your application to see the current status of your request." />

    <x-shell width="read">
        {{-- Below the banner, not in it. The note is five lines of reference
             format, and anything of that length in the banner would push this
             page past the 300px every other page shares. It is the first thing
             in the body, where it still reads as guidance before the form. --}}
        <x-alert variant="info" icon="search" class="mb-6">
            Your reference number is shown in your resident dashboard and on your submission
            confirmation (format: <span class="font-mono font-semibold text-navy-900">BRGY-YYYY-NNNN</span>).
        </x-alert>

        {{-- ============================================================
             Search
             ============================================================ --}}
        <div class="rounded-2xl border border-navy-100 bg-white p-6 shadow-card sm:p-8">
            <form method="GET" action="{{ route('requests.track') }}" class="flex flex-col gap-3 sm:flex-row">
                <div class="flex-1">
                    <x-field id="reference" name="reference" label="Reference number"
                        :value="$reference ?? ''" placeholder="BRGY-2026-1234" mono icon="document" />
                </div>
                <div class="flex items-end gap-2">
                    <x-btn type="submit" variant="navy" size="lg" icon="search" class="flex-1 sm:flex-none">
                        Track
                    </x-btn>

                    @if ($reference)
                        <x-btn :href="route('requests.track')" variant="ghost" size="lg"
                            class="flex-1 sm:flex-none" aria-label="Clear search">
                            Clear
                        </x-btn>
                    @endif
                </div>
            </form>
        </div>

        {{-- ============================================================
             Result
             ============================================================ --}}
        <div aria-live="polite" class="mt-8">
            @if ($reference && $documentRequest)
                <div class="overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-card">
                    <div class="flex flex-col gap-4 border-b border-navy-100 bg-surface px-6 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-8">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-navy-500">Reference number</p>
                            <p class="mt-1 break-all font-mono text-xl font-extrabold text-navy-900 sm:text-2xl">
                                {{ $documentRequest->reference_number }}
                            </p>
                        </div>

                        <x-status-badge :status="$documentRequest->status" class="self-start" />
                    </div>

                    <dl class="grid gap-x-8 gap-y-5 px-6 py-6 sm:grid-cols-2 sm:px-8">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Document type</dt>
                            <dd class="mt-1 font-semibold text-navy-900">{{ $documentRequest->documentTypeLabel() }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Stated purpose</dt>
                            <dd class="mt-1 text-sm text-navy-700">{{ $documentRequest->purpose }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Submitted</dt>
                            <dd class="mt-1 text-sm text-navy-700">{{ $documentRequest->created_at->format('M d, Y g:i A') }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Last updated</dt>
                            <dd class="mt-1 text-sm text-navy-700">{{ $documentRequest->updated_at->format('M d, Y g:i A') }}</dd>
                        </div>
                    </dl>

                    @if ($documentRequest->rejection_reason)
                        <div class="px-6 pb-6 sm:px-8">
                            <x-alert variant="danger" icon="x-circle" title="Reason for rejection">
                                {{ $documentRequest->rejection_reason }}
                            </x-alert>
                        </div>
                    @endif

                    {{-- Next action --}}
                    <div class="border-t border-navy-100 bg-surface px-6 py-5 sm:px-8">
                        @php
                            [$nextHeading, $nextBody] = match ($documentRequest->status) {
                                \App\Models\DocumentRequest::STATUS_SUBMITTED => [
                                    'Your request has been received',
                                    'The barangay has received your request and will review it. You can check back here for updates.',
                                ],
                                \App\Models\DocumentRequest::STATUS_UNDER_REVIEW => [
                                    'Your request is being reviewed',
                                    'A staff member is currently reviewing your request. No action is needed from you right now.',
                                ],
                                \App\Models\DocumentRequest::STATUS_READY => [
                                    'Ready for pickup',
                                    'Please claim your document at the Barangay Hall within the release period. Bring a valid government ID and your reference number.',
                                ],
                                \App\Models\DocumentRequest::STATUS_COMPLETED => [
                                    'Document claimed',
                                    'This request has been completed and the document has been released. Thank you for using BDRPIS.',
                                ],
                                \App\Models\DocumentRequest::STATUS_REJECTED => [
                                    'Action may be required',
                                    'This request was not approved. Please review the reason above, then contact the Barangay Hall if you need to submit a new request.',
                                ],
                                \App\Models\DocumentRequest::STATUS_ABANDONED => [
                                    'No longer active',
                                    'This request was closed as abandoned. You may submit a new request from your resident dashboard if you still need this document.',
                                ],
                                default => ['Request found', 'Contact the Barangay Hall for the current status of this request.'],
                            };
                        @endphp

                        <h2 class="flex items-center gap-2 text-sm font-bold text-navy-900">
                            <x-icon name="arrow-right" class="h-4 w-4 text-sky-700" />
                            {{ $nextHeading }}
                        </h2>
                        <p class="mt-1.5 pl-6 text-sm leading-relaxed text-navy-600">{{ $nextBody }}</p>
                    </div>
                </div>

                {{-- Privacy note: tracking is behind a sign-in, so only the
                     signed-in resident's own requests resolve at all. --}}
                <x-alert variant="privacy" icon="shield" class="mt-6">
                    This page shows only the status of a request, and only for requests made from
                    your own account. Your resident dashboard has the full document history and any
                    secretary comments.
                </x-alert>
            @elseif ($reference)
                <x-empty-state icon="search" title="No request found" level="h2" class="sm:max-w-2xl">
                    No request of yours was found with reference number
                    <span class="font-mono font-bold text-navy-900">{{ $reference }}</span>.
                    Please check the number and try again, or contact the Barangay Hall for assistance.
                </x-empty-state>
            @else
                <x-empty-state icon="document" title="Enter a reference number" level="h2"
                    description="Type the reference number from your application above to see the current status of your request."
                    class="sm:max-w-2xl" />
            @endif
        </div>
    </x-shell>
</x-public-layout>
