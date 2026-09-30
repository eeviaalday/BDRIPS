<x-app-layout>
    <x-slot name="title">Review {{ $documentRequest->reference_number }}</x-slot>

    <div class="mx-auto max-w-6xl space-y-5">
        <x-breadcrumb :items="[
            ['label' => 'Staff', 'route' => 'staff.dashboard'],
            ['label' => 'Document Queue', 'route' => 'staff.requests.index'],
            ['label' => $documentRequest->reference_number],
        ]" />

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- What was asked for --}}
                <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-navy-500">Reference</p>
                            <p class="font-mono text-lg font-bold text-navy-900">{{ $documentRequest->reference_number }}</p>
                        </div>
                        <x-status-badge :status="$documentRequest->status" />
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-navy-100 pt-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Document</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->documentTypeLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Submitted</dt>
                            <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Purpose</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-navy-900">{{ $documentRequest->purpose }}</dd>
                        </div>
                    </dl>

                    @if ($documentRequest->secretary_comment)
                        <div class="mt-5 rounded-lg border border-navy-200 bg-navy-50 px-4 py-3 text-sm text-navy-800">
                            <span class="font-semibold">Secretary comment:</span> {{ $documentRequest->secretary_comment }}
                        </div>
                    @endif

                    @if ($documentRequest->rejection_reason)
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <span class="font-semibold">Rejection reason:</span> {{ $documentRequest->rejection_reason }}
                        </div>
                    @endif
                </section>

                {{-- Same lifecycle the resident sees --}}
                <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                    <h2 class="text-base font-bold text-navy-900">Lifecycle</h2>
                    <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" class="mt-4" />
                </section>
            </div>

            <div class="space-y-5">
                {{-- Who is asking --}}
                <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                    <h2 class="text-base font-bold text-navy-900">Applicant</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-navy-500">Name</dt>
                            <dd class="text-right font-medium text-navy-900">{{ $documentRequest->user->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-navy-500">Email</dt>
                            <dd class="text-right font-medium text-navy-900">{{ $documentRequest->user->email }}</dd>
                        </div>

                        @if ($documentRequest->user->residentProfile)
                            <div class="flex justify-between gap-3">
                                <dt class="text-navy-500">Household #</dt>
                                <dd class="text-right font-mono font-medium text-navy-900">{{ $documentRequest->user->residentProfile->household_number }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-navy-500">Address</dt>
                                <dd class="text-right font-medium text-navy-900">{{ $documentRequest->user->residentProfile->address }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-navy-500">Sector</dt>
                                <dd class="text-right font-medium text-navy-900">{{ $documentRequest->user->residentProfile->sector?->sector_name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-navy-500">Verification</dt>
                                <dd class="text-right"><x-status-badge :status="$documentRequest->user->residentProfile->verification_status" /></dd>
                            </div>
                        @else
                            <p class="text-sm text-navy-500">This applicant has no resident profile on file.</p>
                        @endif
                    </dl>

                    @if ($documentRequest->user->residentProfile?->id_photo_path)
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-navy-500">Submitted ID</p>
                        <a href="{{ route('staff.resident-verifications.id-photo', $documentRequest->user->residentProfile) }}"
                            target="_blank" rel="noopener" class="mt-2 block">
                            <img src="{{ route('staff.resident-verifications.id-photo', $documentRequest->user->residentProfile) }}"
                                alt="Submitted ID of {{ $documentRequest->user->name }}"
                                class="max-h-48 w-full rounded-lg border border-navy-200 object-cover">
                        </a>
                    @endif
                </section>

                {{-- Actions --}}
                <section class="rounded-xl border border-navy-100 bg-white p-6 shadow-card">
                    <h2 class="text-base font-bold text-navy-900">Actions</h2>

                    @php($isActionable = in_array($documentRequest->status, ['submitted', 'under_review'], true))

                    @if (! $isActionable && $documentRequest->status !== 'ready_for_pickup' && $documentRequest->status !== 'completed')
                        <p class="mt-3 text-sm text-navy-500">
                            This request is {{ str_replace('_', ' ', $documentRequest->status) }}. No further action is available.
                        </p>
                    @endif

                    @if ($isActionable)
                        <form method="POST" action="{{ route('staff.requests.approve', $documentRequest) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="secretary_comment" class="block text-sm font-semibold text-navy-900">
                                    Secretary comment <span class="font-normal text-navy-500">(optional)</span>
                                </label>
                                <textarea id="secretary_comment" name="secretary_comment" rows="2" maxlength="1000"
                                    class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none"
                                    placeholder="Anything the resident should know about this request."></textarea>
                            </div>
                            <label class="flex items-start gap-2 text-sm text-navy-700">
                                <input type="checkbox" name="ready" value="1"
                                    class="mt-0.5 rounded border-navy-300 text-navy-600 focus:ring-navy-500">
                                Mark as ready for pickup immediately
                            </label>
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400">
                                <x-icon name="check" class="h-4 w-4" />
                                Approve &amp; move to review
                            </button>
                        </form>
                    @endif

                    @if ($isActionable)
                        <div class="mt-4 border-t border-navy-100 pt-4">
                            <button type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'reject-{{ $documentRequest->id }}' }))">
                                <x-icon name="x-circle" class="h-4 w-4" />
                                Reject this request
                            </button>
                        </div>
                    @endif

                    @if ($isActionable)
                        <div class="mt-4 border-t border-navy-100 pt-4">
                            <button type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'ready-{{ $documentRequest->id }}' }))">
                                <x-icon name="document" class="h-4 w-4" />
                                Mark ready for pickup
                            </button>
                        </div>
                    @endif

                    @if ($documentRequest->status === 'ready_for_pickup')
                        <div class="mt-4 space-y-3 border-t border-navy-100 pt-4">
                            <a href="{{ route('staff.requests.pdf', $documentRequest) }}" target="_blank" rel="noopener"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                                <x-icon name="document" class="h-4 w-4" />
                                View / print certificate
                            </a>

                            <button type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'claim-{{ $documentRequest->id }}' }))">
                                <x-icon name="check-circle" class="h-4 w-4" />
                                Mark claimed
                                <span class="block text-xs font-normal text-navy-500">Handing this over closes the request permanently.</span>
                            </button>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>

    {{-- The reason is typed inside the dialog, so the form that posts it is
         the form the resident's rejection will actually come from. --}}
    @if ($isActionable)
        <x-confirm-dialog name="reject-{{ $documentRequest->id }}"
            title="Reject {{ $documentRequest->reference_number }}?"
            message="The resident cannot request this document again until this is resolved, and they will see the reason you give them. Write something they can act on."
            confirm-label="Confirm rejection" tone="danger"
            :form="route('staff.requests.reject', $documentRequest)">
            <div>
                <label for="rejection_reason" class="block text-sm font-semibold text-navy-900">
                    Reason <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" required
                    placeholder="e.g. The ID photograph is too blurry to read the number."
                    class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none"></textarea>
            </div>
        </x-confirm-dialog>

        <x-confirm-dialog name="ready-{{ $documentRequest->id }}"
            title="Mark ready for pickup?"
            message="The resident will be able to come in and collect the certificate. You can still mark it claimed afterwards."
            confirm-label="Mark ready"
            :form="route('staff.requests.ready', $documentRequest)" />
    @endif

    @if ($documentRequest->status === 'ready_for_pickup')
        <x-confirm-dialog name="claim-{{ $documentRequest->id }}"
            title="Mark {{ $documentRequest->reference_number }} as claimed?"
            message="This records that the document was handed over. There is no way to undo it from the application - use it only when the resident has actually collected it."
            confirm-label="Yes, it was collected"
            :form="route('staff.requests.claim', $documentRequest)" />
    @endif
</x-app-layout>
