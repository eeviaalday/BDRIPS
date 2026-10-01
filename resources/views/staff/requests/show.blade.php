{{--
    The review desk for one request.

    Only the actions the current state supports are offered, and the server
    refuses the others just as firmly - a stale screen that quietly "succeeded"
    would leave a Secretary believing a request was approved when it was not.
--}}
<x-app-layout :user="auth()->user()" :title="$documentRequest->reference_number">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Document Requests', 'route' => 'staff.requests.index'],
            ['label' => $documentRequest->reference_number],
        ]" />

        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-navy-900">{{ $documentRequest->documentType?->label ?? 'Document request' }}</h1>
                <p class="mt-1 font-mono text-sm text-gray-600">{{ $documentRequest->reference_number }}</p>
            </div>
            <x-staff.status-badge :status="$documentRequest->status" />
        </header>

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Request</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resident</dt>
                    <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->user?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household</dt>
                    <dd class="mt-1 font-mono text-sm text-navy-900">{{ $documentRequest->user?->residentProfile?->household_number }}</dd>
                </div>
                  <div>
                      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                    <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->created_at?->format('j M Y, g:i a') }}</dd>
                </div>
                @if ($documentRequest->purpose)
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stated purpose</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-navy-900">{{ $documentRequest->purpose }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        @php
            $status = $documentRequest->status;
            $canReview = $status === \App\Models\DocumentRequest::STATUS_SUBMITTED;
            $canIssue = in_array($status, [
                \App\Models\DocumentRequest::STATUS_SUBMITTED,
                \App\Models\DocumentRequest::STATUS_UNDER_REVIEW,
            ], true);
            $canClaim = $status === \App\Models\DocumentRequest::STATUS_READY;
        @endphp

        <x-card tone="white" padding="p-6">
            <h2 class="mb-4 text-base font-bold text-navy-900">Actions</h2>

            <div class="flex flex-wrap gap-3">
                @if ($canReview)
                    <form method="POST" action="{{ route('staff.requests.review', $documentRequest) }}">
                        @csrf
                        <x-btn type="submit" variant="outline" size="md" icon="clock">Take for review</x-btn>
                    </form>
                @endif

                @if ($canIssue)
                    <form method="POST" action="{{ route('staff.requests.ready', $documentRequest) }}">
                        @csrf
                        <x-btn type="submit" variant="navy" size="md" icon="check-circle">Mark ready for pickup</x-btn>
                    </form>
                @endif

                @if ($canClaim)
                    <form method="POST" action="{{ route('staff.requests.claim', $documentRequest) }}">
                        @csrf
                        <x-btn type="submit" variant="navy" size="md" icon="check-circle">Mark collected</x-btn>
                    </form>
                @endif
            </div>

            @if ($canIssue)
                <div class="mt-6 border-t border-navy-100 pt-6">
                    {{-- The reason travels inside the dialog's own form, not in a
                         separate form a trigger would have to reach. --}}
                    <x-staff.confirm-dialog name="reject-request"
                        title="Reject this request?"
                        message="The resident will be told the reason and can apply again afterwards."
                        confirm-label="Reject, with this reason" tone="danger" method="POST"
                        :form="route('staff.requests.reject', $documentRequest)">
                        @error('rejection_reason')
                            <x-alert variant="danger" icon="x-circle" class="mb-3">{{ $message }}</x-alert>
                        @enderror

                        <label for="rejection_reason" class="block text-sm font-semibold text-navy-800">Reason for rejecting</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000" required
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                            placeholder="What is missing, and what should the resident do instead?"></textarea>
                    </x-staff.confirm-dialog>

                    <x-btn type="button" variant="danger" size="md" icon="x-circle"
                        x-data="{}"
                        x-on:click="$dispatch('open-modal', 'reject-request')">
                        Reject request
                    </x-btn>
                </div>
            @endif

            @unless ($canReview || $canIssue || $canClaim)
                <x-alert variant="info" icon="check-circle">
                    This request is {{ strtolower($documentRequest->statusLabel()) }} - nothing left to decide.
                </x-alert>
            @endunless
        </x-card>

        <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />
    </div>
</x-app-layout>
