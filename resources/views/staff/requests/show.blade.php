{{--
    The review desk for one request.

    Only the actions the current state supports are offered, and the server
    refuses the others just as firmly - a stale screen that quietly "succeeded"
    would leave a Secretary believing a request was approved when it was not.
--}}

{{-- Bare name, because these views call the model's static policy readers and
     the fully-qualified \App\Models\ prefix in a Blade expression reads as
     noise rather than as a namespace. The same file already does this for the
     timeline. --}}
@php
    use App\Models\DocumentRequest;
@endphp

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
                @if ($documentRequest->ready_at)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ready for pickup</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->ready_at->format('j M Y, g:i a') }}</dd>
                    </div>
                @endif
                @if ($documentRequest->unclaimed_at)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marked unclaimed</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->unclaimed_at->format('j M Y, g:i a') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request slot held until</dt>
                        <dd class="mt-1 text-sm text-navy-900">
                            {{ $documentRequest->slotHoldExpiresAt()?->format('j M Y') ?? 'Released' }}
                        </dd>
                    </div>
                @endif
                @if ($documentRequest->cancelled_at)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cancelled by resident</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $documentRequest->cancelled_at->format('j M Y, g:i a') }}</dd>
                    </div>
                @endif
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
            $canReview = $status === DocumentRequest::STATUS_SUBMITTED;
            $canIssue = in_array($status, [
                DocumentRequest::STATUS_SUBMITTED,
                DocumentRequest::STATUS_UNDER_REVIEW,
            ], true);
            // Collection is offered from unclaimed as well, because a resident who
            // turns up late has still collected the document and the office should
            // be able to say so. The strike stays on their record either way.
            $canClaim = in_array($status, [
                DocumentRequest::STATUS_READY,
                DocumentRequest::STATUS_UNCLAIMED,
            ], true);
            // The server refuses this before the 14-day window, so the button
            // offers it only once the same check agrees.
            $canUnclaim = $status === DocumentRequest::STATUS_READY
                && $documentRequest->isOverdue();
            // From canBeExcused(), not from the status. A resident who came back
            // and collected late is exactly when the desk learns the absence was
            // not habit, so that request must still be excusable. Keyed on
            // status it would be unappealable, and the answer would be to stop
            // marking documents at all.
            $canExcuse = $documentRequest->canBeExcused();
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
                        <x-btn type="submit" variant="navy" size="md" icon="check-circle">
                            {{ $canUnclaim || $status === DocumentRequest::STATUS_UNCLAIMED ? 'Mark collected (late)' : 'Mark collected' }}
                        </x-btn>
                    </form>
                @endif
            </div>

            {{-- Marking unclaimed is the only action here that records something
                 against the resident, so the dialog states both halves of it: the
                 slot is still held for a while, and the resident will not be
                 notified. A Secretary should not have to read the policy to learn
                 that clicking this suspends someone's access. --}}
            @if ($canUnclaim)
                <div class="mt-6 border-t border-navy-100 pt-6">
                    <x-staff.confirm-dialog name="unclaim-request"
                        title="Mark this document as not collected?"
                        :message="'This document has been ready for '.$documentRequest->daysReady().' days. Marking it unclaimed records a strike against this resident, and '.DocumentRequest::strikesToSuspend().' unexcused strikes in '.DocumentRequest::strikeWindowDays().' days pause new requests. The request slot stays held until '.($documentRequest->slotHoldExpiresAt()?->format('j M Y') ?? 'shortly').'. No message is sent to the resident.'"
                        confirm-label="Mark unclaimed" tone="danger" method="POST"
                        :form="route('staff.requests.unclaim', $documentRequest)" />

                    <x-btn type="button" variant="danger" size="md" icon="help-circle"
                        x-data="{}"
                        x-on:click="$dispatch('open-modal', 'unclaim-request')">
                        Mark unclaimed
                    </x-btn>
                </div>
            @endif

            {{-- Excusing is the correction, so it is offered as prominently as the
                 marking and the note is required: an excuse with no reason written
                 down is indistinguishable from a mistake. --}}
            @if ($canExcuse)
                <div class="mt-6 border-t border-navy-100 pt-6">
                    <x-staff.confirm-dialog name="excuse-request"
                        title="Excuse this unclaimed document?"
                        message="The strike is removed from this resident's record straight away. They do not need to be at the strike limit for an excuse to be allowed. Write what actually happened - it is kept on the request and shown to staff."
                        confirm-label="Excuse, with this note" tone="primary" method="POST"
                        :form="route('staff.requests.excuse', $documentRequest)">
                        @error('note')
                            <x-alert variant="danger" icon="x-circle" class="mb-3">{{ $message }}</x-alert>
                        @enderror

                        <label for="excuse_note" class="block text-sm font-semibold text-navy-800">What happened?</label>
                        <textarea id="excuse_note" name="note" rows="3" minlength="10" maxlength="1000" required
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                            placeholder="For example: resident was out of town until last week."></textarea>
                        <p class="mt-1.5 text-xs text-slate-500">At least 10 characters. Kept on the record with your name and today's date.</p>
                    </x-staff.confirm-dialog>

                    <x-btn type="button" variant="outline" size="md" icon="check-circle"
                        x-data="{}"
                        x-on:click="$dispatch('open-modal', 'excuse-request')">
                        Excuse this one
                    </x-btn>
                </div>
            @endif

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

            @unless ($canReview || $canIssue || $canClaim || $canExcuse)
                <x-alert variant="info" icon="check-circle">
                    This request is {{ strtolower($documentRequest->statusLabel()) }} - nothing left to decide.
                </x-alert>
            @endunless

            {{-- Once a request has been marked unclaimed the desk needs to see
                 when it happened, whether it has been excused, and by whom.
                 Without this the only place the excuse note exists is in the
                 audit log, which is not where anyone looks to answer "did we
                 already sort this one out?".
                 Shown while unclaimed_at is set, which outlives the status: a
                 request collected late is claimed now, and the strike - the part
                 anybody came here for - is still on it. --}}
            @if ($documentRequest->unclaimed_at)
                <div @class([
                    'mt-6 rounded-lg border p-4 text-sm',
                    'border-green-200 bg-green-50' => $documentRequest->unclaimed_excused_at !== null,
                    'border-amber-200 bg-amber-50' => $documentRequest->unclaimed_excused_at === null,
                ])>
                    @if ($documentRequest->unclaimed_excused_at !== null)
                        <p class="font-semibold text-green-900">Excused - no strike on this request</p>
                        <p class="mt-1 text-green-800">
                            Excused by {{ $documentRequest->excusedByName() }}
                            on {{ $documentRequest->unclaimed_excused_at->format('j M Y') }}.
                            {{ $documentRequest->excuseNote() }}
                        </p>
                    @else
                        <p class="font-semibold text-amber-900">Unclaimed - counting as a strike</p>
                        <p class="mt-1 text-amber-800">
                            Marked {{ $documentRequest->unclaimed_at?->format('j M Y') ?? 'recently' }}.
                            @if ($status === DocumentRequest::STATUS_UNCLAIMED)
                                This request slot is held until
                                {{ $documentRequest->slotHoldExpiresAt()?->format('j M Y') ?? 'shortly' }}.
                            @else
                                Collected since, so the slot is free - but the strike is not.
                            @endif
                            If this was not the resident's fault, excuse it - that removes the strike immediately.
                        </p>
                    @endif
                </div>
            @endif
        </x-card>

        {{-- Digital copies, newest first. Its own card rather than a line in the
             Request card because the question it answers is a different one: not
             "what does this request say" but "has this resident already got the
             document". A Secretary asked whether they still need to prepare a
             copy for the counter needs that answer before they prepare anything.

             Only ever shown when a copy has actually been issued, so an untouched
             request does not carry an empty card explaining a feature nobody used.
             And it deliberately carries no date of birth, no password and nothing
             else about the resident: this is the one staff page where the fact
             that a download happened is worth recording, and the derived password
             is not anybody's business but the resident's. --}}
        @if ($documentRequest->deliveries->isNotEmpty())
            <x-card tone="white" padding="p-6">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-base font-bold text-navy-900">Digital copies issued</h2>
                    <span class="text-xs text-slate-500">
                        {{ $documentRequest->deliveries->count() }}
                        {{ $documentRequest->deliveries->count() === 1 ? 'copy' : 'copies' }}
                    </span>
                </div>

                <p class="mb-3 text-sm text-slate-600">
                    Issued to the resident (downloaded or emailed) from their own page. Each row is one issuance, so a
                    resident who came back for a second copy leaves two rows - which is how
                    somebody whose password kept failing becomes visible.
                </p>

                <ol class="space-y-2">
                    @foreach ($documentRequest->deliveries as $delivery)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-navy-100 bg-navy-50/40 px-3.5 py-2.5 text-sm">
                            <span class="font-medium text-navy-900">
                                {{ $delivery->created_at?->format('j M Y, g:i a') ?? 'Date not recorded' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="font-mono">{{ $delivery->user?->name ?? 'Resident' }}</span>
                                <span class="rounded-full bg-navy-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-navy-700">
                                    {{ $delivery->channel ?? 'download' }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                @if ($documentRequest->status === \App\Models\DocumentRequest::STATUS_UNCLAIMED)
                    <p class="mt-4 text-sm text-amber-800">
                        This document was downloaded but was not collected. A copy was issued and
                        the resident still came to the hall for it, so the two are worth reading
                        together rather than as a contradiction.
                    </p>
                @endif
            </x-card>
        @endif

        {{-- The shared timeline resolves position with array_search over
             submitted/under-review/ready/claimed, so unclaimed and cancelled
             render as four grey steps - "nothing has happened yet", which is the
             opposite of what happened. It is a shared component and out of scope,
             so those two states are left off it rather than misdrawn. --}}
        @if (! in_array($status, [
            DocumentRequest::STATUS_UNCLAIMED,
            DocumentRequest::STATUS_CANCELLED,
        ], true))
            <x-request-timeline :status="$documentRequest->status" :request="$documentRequest" />
        @endif
    </div>
</x-app-layout>
