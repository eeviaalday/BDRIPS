<x-public-layout>
    <x-slot name="title">Request a Document</x-slot>

    <div class="mx-auto max-w-3xl space-y-5">

        <x-breadcrumb :items="[
            ['label' => 'My Dashboard', 'route' => 'resident.dashboard'],
            ['label' => 'My Requests', 'route' => 'resident.requests.index'],
            ['label' => 'New Request'],
        ]" />

        {{-- ============================================================
             LOCKED STATES
             ============================================================
             Two independent reasons a resident cannot file right now, and they
             are not the same thing:

             1. RESTRICTED - an account-level penalty. Three abandoned requests
                freezes online applications for 30 days. Serving at the hall is
                still available. Only DocumentRequestController::store() decides
                this, from users.restricted_until.

             2. UNVERIFIED - a missing prerequisite. A document request is
                checked against the resident profile, so until the Barangay
                Secretary has approved the submitted ID there is nothing to
                check against.

             Both are shown as explanatory panels rather than an empty form, so a
             resident is told WHY rather than finding a button that does nothing.
             The POST is guarded server-side regardless - this is presentation,
             not enforcement.
        --}}
        @if ($isRestricted)
            <div class="rounded-xl border border-red-200 bg-red-50 p-6" role="alert">
                <div class="flex items-start gap-3">
                    <x-icon name="x-circle" class="mt-0.5 h-6 w-6 shrink-0 text-red-600" />
                    <div>
                        <h2 class="font-bold text-red-900">Online applications are restricted</h2>
                        <p class="mt-1 text-sm leading-relaxed text-red-800">
                            Repeated abandoned requests have temporarily restricted your online
                            application privileges until
                            <span class="font-semibold">{{ auth()->user()->restricted_until->format('M d, Y') }}</span>.
                            You can still follow your existing requests below, or visit the
                            Barangay Hall to apply in person.
                        </p>
                        <a href="{{ route('resident.requests.index') }}"
                            class="mt-4 inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400">
                            View my requests
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($profile === null || ! $profile->isVerified())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6" role="alert">
                <div class="flex items-start gap-3">
                    <x-icon name="clock" class="mt-0.5 h-6 w-6 shrink-0 text-amber-600" />
                    <div>
                        <h2 class="font-bold text-amber-900">
                            {{ $profile === null ? 'Your resident profile is not set up yet' : 'Verification required before you can apply' }}
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-amber-800">
                            @if ($profile === null)
                                A resident profile is created during registration. If you do not have
                                one, your account may predate the registration wizard - please ask at
                                the Barangay Hall to have it set up.
                            @else
                                Every document request is checked against your verified resident
                                profile, so the Barangay Secretary must first approve the ID you
                                submitted. You will be able to apply as soon as that is done.
                            @endif
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($profile !== null)
                                <a href="{{ route('resident.verification.status') }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                    Check verification status
                                </a>
                            @endif
                            <a href="{{ route('resident.profile') }}"
                                class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                View my profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ============================================================
                 THE WIZARD
                 ============================================================
                 Three client-side steps over ONE form and ONE POST. There is no
                 per-step endpoint and no wizard state on the server: the request
                 that arrives carries document_type and purpose together, exactly
                 as DocumentRequestController::store() has always expected.

                 That matters. Adding a multi-request wizard would mean new routes
                 and new server state to hold a half-finished application - and
                 the current design is deliberate, because a wizard the server
                 can pause is a wizard that can strand a resident's answers. The
                 steps here are a way of filling the form in, not three saves.

                 No step is required to be completed in order - the resident may
                 change their mind on the review step - because the backend
                 accepts the pair in any order.
            --}}
            <div class="rounded-xl border border-navy-100 bg-white p-6 shadow-card"
                x-data="{
                    step: 1,
                    lastStep: 3,
                    goTo(n) {
                        this.step = Math.min(Math.max(n, 1), this.lastStep);
                        this.$nextTick(() => this.$root.querySelector('[data-step-heading]')?.focus());
                    },
                    next() { this.goTo(this.step + 1) },
                    back() { this.goTo(this.step - 1) },
                    typeLabel: 'Barangay Clearance',
                    typeOptions: @js(collect([
                        'barangay_clearance' => 'Barangay Clearance',
                        'certificate_of_indigency' => 'Certificate of Indigency',
                        'first_time_jobseeker_certificate' => 'First-Time Jobseeker Certificate',
                        'barangay_residency_certificate' => 'Barangay Residency Certificate',
                    ])),
                    syncType() {
                        this.typeLabel = this.typeOptions[this.$refs.type.value] || 'the selected document';
                    },
                }">

                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-bold text-navy-900">New Document Request</h2>
                    <p class="text-sm text-navy-600">
                        Applying as <span class="font-semibold text-navy-900">{{ auth()->user()->name }}</span>
                        &middot; Household #{{ $profile->household_number }}
                    </p>
                </div>

                <div class="mt-6">
                    <x-stepper :steps="['Document Type', 'Purpose', 'Review']" bind="step" class="hidden sm:block" />
                </div>

                <form method="POST" action="{{ route('resident.requests.store') }}" class="mt-6">
                    @csrf

                    {{-- Step 1 --}}
                    <fieldset x-show="step === 1">
                        <legend class="sr-only">Step 1: choose the document you need</legend>
                        <h3 data-step-heading tabindex="-1" class="text-base font-bold text-navy-900">Which document do you need?</h3>
                        <p class="mt-1 text-sm text-navy-600">
                            Choose the one that matches what you are applying for. The Secretary
                            will confirm the type before your document is released.
                        </p>

                        <div class="mt-4">
                            <label for="document_type" class="block text-sm font-semibold text-navy-900">
                                Document type <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <select id="document_type" name="document_type" required
                                x-ref="type" x-on:change="syncType()"
                                class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none">
                                <option value="">Select a document...</option>
                                @foreach (config('bdrpis.services') as $service)
                                    <option value="{{ $service['key'] }}" @selected(old('document_type') === $service['key'])>
                                        {{ $service['title'] }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('document_type')" class="mt-1.5" />
                            <p class="mt-1.5 text-xs text-navy-500">
                                Only these four are processed by the portal.
                            </p>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                x-on:click="next">
                                Next: purpose
                            </button>
                        </div>
                    </fieldset>

                    {{-- Step 2 --}}
                    <fieldset x-show="step === 2" x-cloak>
                        <legend class="sr-only">Step 2: state your purpose</legend>
                        <h3 data-step-heading tabindex="-1" class="text-base font-bold text-navy-900">What do you need this for?</h3>
                        <p class="mt-1 text-sm text-navy-600">
                            The Secretary reads this to check the document is appropriate. A clear
                            one-line reason is usually enough.
                        </p>

                        <div class="mt-4">
                            <label for="purpose" class="block text-sm font-semibold text-navy-900">
                                Purpose of request <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <textarea id="purpose" name="purpose" rows="4" required x-ref="purpose"
                                maxlength="255"
                                class="mt-1.5 block w-full rounded-lg border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 transition placeholder:text-navy-400 focus:border-sky-600 focus:ring-2 focus:ring-sky-600/25 focus:outline-none"
                                placeholder="e.g. Employment requirement, bank application, school enrollment...">{{ old('purpose') }}</textarea>
                            <x-input-error :messages="$errors->get('purpose')" class="mt-1.5" />
                            <p class="mt-1.5 text-xs text-navy-500">Up to 255 characters.</p>
                        </div>

                        <div class="mt-6 flex items-center justify-between">
                            <button type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-5 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                x-on:click="back">
                                Back
                            </button>
                            <button type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                x-on:click="next">
                                Next: review
                            </button>
                        </div>
                    </fieldset>

                    {{-- Step 3: review. Reads the form rather than storing a
                         second copy, so what is confirmed is what will post. --}}
                    <fieldset x-show="step === 3" x-cloak>
                        <legend class="sr-only">Step 3: review and submit</legend>
                        <h3 data-step-heading tabindex="-1" class="text-base font-bold text-navy-900">Check your request</h3>
                        <p class="mt-1 text-sm text-navy-600">
                            Once submitted, a reference number is generated for you to track it with.
                        </p>

                        <dl class="mt-4 divide-y divide-navy-100 rounded-xl border border-navy-100">
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-sm font-semibold text-navy-700">Document type</dt>
                                <dd class="text-right text-sm text-navy-900" x-text="typeLabel"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-sm font-semibold text-navy-700">Purpose</dt>
                                <dd class="max-w-[60%] text-right text-sm text-navy-900" x-text="$refs.purpose.value"></dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-sm font-semibold text-navy-700">Household number</dt>
                                <dd class="text-right font-mono text-sm text-navy-900">{{ $profile->household_number }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-sm font-semibold text-navy-700">Fee</dt>
                                <dd class="text-right text-sm text-navy-900">Set by the Secretary — pay at the Hall</dd>
                            </div>
                        </dl>

                        @if ($errors->has('account'))
                            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                                {{ $errors->first('account') }}
                            </div>
                        @endif

                        <div class="mt-6 flex items-center justify-between">
                            <button type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-navy-200 bg-white px-5 py-2.5 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-400"
                                x-on:click="back">
                                Back
                            </button>
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-gold px-5 py-2.5 text-sm font-semibold text-navy-900 transition hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-400">
                                Submit request
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        @endif

        {{-- The rules are the backend's, restated. They are not a second source
             of truth: DocumentRequestController::store() enforces all three. --}}
        <x-card tone="white" class="mt-5">
            <h2 class="text-sm font-bold text-navy-900">Before you apply</h2>
            <ul class="mt-2 space-y-1.5 text-sm text-navy-700">
                <li class="flex gap-2">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-navy-400" />
                    <span>You may hold up to <span class="font-semibold">3 open requests</span> at a time.</span>
                </li>
                <li class="flex gap-2">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-navy-400" />
                    <span>Collect a document within <span class="font-semibold">7 working days</span> of it being marked ready.</span>
                </li>
                <li class="flex gap-2">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-navy-400" />
                    <span><span class="font-semibold">3 abandoned requests</span> restrict online applications for 30 days.</span>
                </li>
                <li class="flex gap-2">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-navy-400" />
                    <span>Documents are claimed at the <span class="font-semibold">Barangay Hall</span>; this portal does not deliver them.</span>
                </li>
            </ul>
        </x-card>
    </div>
</x-public-layout>
