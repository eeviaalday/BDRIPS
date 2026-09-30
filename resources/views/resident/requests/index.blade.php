<x-public-layout>
    <x-slot name="title">My Documents</x-slot>

    <x-page-header :crumbs="['My Documents']" title="My Documents"
        description="Every certificate and clearance you have asked the barangay for, with its reference number and where it has got to." />

    <x-shell>
        @php
            /*
             * One section, no tabs. The list of requests and the list of issued
             * documents used to be two pages, which meant a resident with a
             * ready-for-pickup certificate had to know which of the two to
             * refresh. They are the same rows in the same statuses, so they are
             * one table now.
             *
             * $openCount arrives from the controller, which counts on the same
             * DocumentRequest::OPEN_STATUSES that store() refuses on, so the "3"
             * the resident reads and the "3" the server enforces are one number
             * rather than two that can drift.
             */
            $readyCount = $requests->where('status', \App\Models\DocumentRequest::STATUS_READY)->count();
            $completedCount = $requests->where('status', \App\Models\DocumentRequest::STATUS_COMPLETED)->count();

            $isVerified = ($profile ?? null)?->isVerified() ?? false;
            $canCreate = $isVerified && ! ($user->isRestricted()) && $openCount < 3;

            $chips = [
                '' => 'All',
                'processing' => 'Processing',
                \App\Models\DocumentRequest::STATUS_READY => 'Ready for pickup',
                \App\Models\DocumentRequest::STATUS_COMPLETED => 'Completed',
                \App\Models\DocumentRequest::STATUS_REJECTED => 'Rejected',
            ];
        @endphp

        {{-- ============================================================
             Section head: title left, the new-request button right.
             ============================================================ --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-navy-900">My Documents</h2>
                <p class="mt-1 text-sm text-navy-600">
                    @if ($requests->isEmpty())
                        Nothing requested yet.
                    @else
                        {{ $requests->total() }} {{ str('request')->plural($requests->total()) }} on file.
                    @endif
                </p>
            </div>

            @if ($isVerified)
                @if ($canCreate)
                    <button type="button"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold px-5 py-2.5 text-sm font-bold text-navy-900 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                        x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'new-request' }))">
                        <x-icon name="clipboard" class="h-4 w-4" />
                        + New Request
                    </button>
                @else
                    {{-- The cap is reached, or the account is restricted. Either
                         way the button explains itself rather than opening a
                         form that would be refused on submit. --}}
                    <button type="button" disabled
                        aria-describedby="new-request-blocked"
                        class="inline-flex shrink-0 cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-gold/40 px-5 py-2.5 text-sm font-bold text-navy-900/70">
                        <x-icon name="lock" class="h-4 w-4" />
                        + New Request
                    </button>
                    <p id="new-request-blocked" class="text-sm text-navy-600 sm:max-w-xs sm:text-right">
                        @if ($user->isRestricted())
                            Your online applications are paused. Please visit the Barangay Hall.
                        @else
                            You already hold {{ $openCount }} of 3 open requests. Wait for one to be completed.
                        @endif
                    </p>
                @endif
            @else
                <button type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-navy-200 bg-white px-5 py-2.5 text-sm font-bold text-navy-800 transition hover:bg-navy-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'new-request-locked' }))">
                    <x-icon name="lock" class="h-4 w-4" />
                    + New Request
                </button>
            @endif
        </div>

        {{-- ============================================================
             Stats and filters. Stats only for a verified resident: they are
             the numbers the cap is built on, and showing a pending resident
             "0 / 3" would imply they can spend the three.
             ============================================================ --}}
        @if ($isVerified)
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <x-stat-card variant="badged" tone="navy" icon="inbox"
                    label="Open Requests" :value="$openCount.' / 3'"
                    :hint="$openCount === 0 ? 'You can request up to 3 at a time.' : 'Waiting on the Barangay.'" />
                <x-stat-card variant="badged" tone="gold" icon="clock"
                    label="Ready for Pickup" :value="$readyCount"
                    :hint="$readyCount > 0 ? 'Collect within 7 working days.' : 'Nothing to collect right now.'" />
                <x-stat-card variant="badged" tone="green" icon="check-circle"
                    label="Completed" :value="$completedCount"
                    hint="Collected from the Barangay Hall." />
            </div>
        @endif

        {{-- Filter chips. Real links, so a filtered list is a URL that can be
             shared or reloaded, and the active chip is the current state rather
             than a remembered one. --}}
        <div class="mt-6 flex flex-wrap items-center gap-2">
            @foreach ($chips as $value => $label)
                @php($isActive = (string) ($status ?? '') === (string) $value)
                <a href="{{ route('resident.requests.index', $value === '' ? [] : ['status' => $value]) }}"
                    @if ($isActive) aria-current="page" @endif
                    class="inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400
                           {{ $isActive ? 'border-navy bg-navy text-white' : 'border-navy-200 bg-white text-navy-700 hover:border-navy-400 hover:bg-navy-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @php($visible = $requests)

        {{-- ============================================================
             The table
             ============================================================ --}}
        @if ($visible->isEmpty())
            @if (($status ?? '') !== '')
                <x-card tone="white" class="mt-6">
                    <x-empty-state icon="search" title="Nothing in that filter"
                        description="You have no request in this state. Try another chip above.">
                        <a href="{{ route('resident.requests.index') }}"
                            class="mt-5 inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                            Show all
                        </a>
                    </x-empty-state>
                </x-card>
            @else
                <x-card tone="white" class="mt-6">
                    <x-empty-state icon="inbox" title="You have not requested any document yet"
                        description="Ask the barangay for a clearance, an indigency certificate, a first-time jobseeker certificate or a residency certificate, and it will appear here with its reference number." />
                </x-card>
            @endif
        @else
            <x-card tone="white" padding="p-0" class="mt-6">
                <h2 class="border-b border-navy-100 px-5 py-4 text-base font-bold text-navy-900">
                    Documents you have requested
                </h2>

                {{-- A plain table rather than x-data-table: the rows are a
                     @foreach, and a @foreach cannot wrap a component slot
                     without breaking the compiled view. The overflow wrapper
                     plus a min-width keeps it reachable on a phone. --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <caption class="sr-only">Documents you have requested, with status and actions</caption>
                        <thead>
                            <tr class="border-b border-navy-100 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">
                                <th scope="col" class="px-5 py-3">Document</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3">Date Requested</th>
                                <th scope="col" class="px-5 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-navy-50">
                            @foreach ($visible as $request)
                                <tr class="transition hover:bg-navy-50/40">
                                    <td class="px-5 py-3 align-middle">
                                        <span class="block font-semibold text-navy-900">{{ $request->documentTypeLabel() }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-navy-500">{{ $request->reference_number }}</span>
                                    </td>
                                    <td class="px-5 py-3 align-middle"><x-status-badge :status="$request->status" size="sm" /></td>
                                    <td class="px-5 py-3 align-middle whitespace-nowrap text-navy-700">
                                        {{ $request->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-5 py-3 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400"
                                                x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'track-{{ $request->id }}' }))">
                                                <x-icon name="search" class="h-3.5 w-3.5" />
                                                Track
                                            </button>

                                            @if (in_array($request->status, [
                                                \App\Models\DocumentRequest::STATUS_READY,
                                                \App\Models\DocumentRequest::STATUS_COMPLETED,
                                            ], true))
                                                <a href="{{ route('resident.requests.pdf', $request) }}" target="_blank" rel="noopener"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50">
                                                    <x-icon name="document" class="h-3.5 w-3.5" />
                                                    View PDF
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($requests->hasPages())
                    <div class="border-t border-navy-100 px-5 py-4">
                        {{ $requests->links() }}
                    </div>
                @endif
            </x-card>
        @endif

        {{-- The three rules the backend enforces, restated so a resident can
             see why a request was refused instead of guessing. --}}
        <x-alert variant="privacy" icon="shield" class="mt-6" title="How requests work">
            <ul class="mt-1.5 space-y-1.5">
                <li>You may hold up to <span class="font-semibold">3 open requests</span> at a time.</li>
                <li>Once marked ready, a document must be collected within <span class="font-semibold">7 working days</span>, or it is recorded as abandoned.</li>
                <li>Documents are collected at the <span class="font-semibold">Barangay Hall</span>. This site does not deliver them.</li>
            </ul>
        </x-alert>
    </x-shell>

    {{-- One track modal per request, pulled in with @include so the @foreach
         stays outside any component slot. --}}
    @foreach ($visible as $trackRequest)
        @include('resident.requests._track-modal', ['request' => $trackRequest])
    @endforeach

    {{-- ============================================================
         New request. Posts to the existing store route with the existing
         field names; the controller's four guards are unchanged, so this
         form is a convenience, never an authority.
         ============================================================ --}}
    @if ($isVerified)
        <x-modal name="new-request" maxWidth="md" dismissible>
            <form method="POST" action="{{ route('resident.requests.store') }}">
                @csrf

                <div class="px-6 pb-2 pt-6">
                    <h2 id="new-request-title" class="text-lg font-bold text-navy-900">New Request</h2>
                    <p class="mt-1 text-sm leading-relaxed text-navy-700">
                        Choose one document and say what it is for. The barangay will review it and you can
                        follow it from this page.
                    </p>
                </div>

                <div class="space-y-4 px-6 pb-2">
                    @if ($errors->has('document_type') || $errors->has('purpose'))
                        <x-alert variant="danger" icon="x-circle">
                            {{ $errors->first('document_type') ?: $errors->first('purpose') }}
                        </x-alert>
                    @endif
                    @if ($errors->has('account'))
                        <x-alert variant="warning" icon="lock">{{ $errors->first('account') }}</x-alert>
                    @endif

                    <div>
                        <label for="document_type" class="block text-sm font-semibold text-navy-900">
                            Document <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <select id="document_type" name="document_type" required
                            class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                            <option value="">Select a document…</option>
                            @foreach (\App\Models\DocumentRequest::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('document_type') === $type)>
                                    {{ \App\Models\DocumentRequest::typeLabel($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="purpose" class="block text-sm font-semibold text-navy-900">
                            Purpose <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <textarea id="purpose" name="purpose" rows="3" maxlength="255" required
                            placeholder="e.g. For a job application at the municipal office."
                            class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">{{ old('purpose') }}</textarea>
                    </div>

                    <p class="text-xs text-navy-500">
                        You hold {{ $openCount }} of 3 open requests.
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                        x-on:click="show = false">Cancel</button>
                    <button type="submit" @disabled($openCount >= 3)
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:opacity-50">
                        <x-icon name="clipboard" class="h-4 w-4" />
                        Submit request
                    </button>
                </div>
            </form>
        </x-modal>
    @else
        <x-modal name="new-request-locked" maxWidth="md" dismissible>
            <div class="px-6 pb-2 pt-6">
                <h2 id="new-request-locked-title" class="text-lg font-bold text-navy-900">Requests are locked</h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-700">
                    Document requests open once the Barangay Secretary has verified you against the master
                    list. Until then you can follow any request you already have, but you cannot start a new one.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 bg-navy-50/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50"
                    x-on:click="show = false">Close</button>
                <a href="{{ route('resident.verification.status') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                    <x-icon name="shield" class="h-4 w-4" />
                    Verification Status
                </a>
            </div>
        </x-modal>
    @endif
</x-public-layout>
