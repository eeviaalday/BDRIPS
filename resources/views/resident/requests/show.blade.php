{{--
    One request, in full.

    The timeline is drawn from the request's own state rather than from what the
    server would accept next, so it cannot promise a step that does not exist.
    The staff desk has its own view of the same record; this one says nothing the
    resident could not already see on the list.
--}}
<x-public-layout>
    <x-slot name="title">Request {{ $documentRequest->reference_number }}</x-slot>

    <x-shell width="narrow">
        <x-page-header :crumbs="['Document Requests', $documentRequest->reference_number]"
            :title="$documentRequest->documentType?->label ?? 'Document request'"
            :description="'Reference '.$documentRequest->reference_number" />

        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        <x-card tone="white" padding="p-6" class="space-y-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1"><x-status-badge :status="$documentRequest->status" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                    <dd class="mt-1 text-sm font-medium text-navy-900">
                        {{ $documentRequest->fee_amount == 0 ? 'Free' : 'PHP '.number_format((float) $documentRequest->fee_amount, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                    <dd class="mt-1 text-sm font-medium text-navy-900">
                        {{ $documentRequest->created_at?->format('j M Y, g:i a') }}
                    </dd>
                </div>
                @if ($documentRequest->purpose)
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stated purpose</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-navy-900">{{ $documentRequest->purpose }}</dd>
                    </div>
                @endif
            </dl>

            @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_REJECTED)
                <x-alert variant="danger" icon="x-circle">
                    <strong class="font-semibold">This request was not approved.</strong>
                    {{ $documentRequest->rejection_reason ?? 'The Secretariat did not record a reason. Please ask at the Barangay Hall.' }}
                </x-alert>
            @endif

            @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_READY)
                <x-alert variant="info" icon="check-circle">
                    Your document is ready. Collect it at the Barangay Hall, quoting
                    <strong class="font-mono font-semibold">{{ $documentRequest->reference_number }}</strong>.
                </x-alert>
            @endif
        </x-card>

        <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />

        <div class="flex justify-start">
            <x-btn :href="route('resident.requests.index')" variant="ghost" size="sm" icon="arrow-right" icon-position="left">
                Back to my requests
            </x-btn>
        </div>
    </x-shell>
</x-public-layout>
