{{--
    The new-request form.

    A pending resident reaches this page rather than finding the link missing,
    because a link that is simply absent gives them nothing to act on. The form
    is not rendered for them: the form request refuses the POST, so showing
    controls that cannot succeed would be a worse dead end than an explanation.

    Two columns because the two halves answer different questions. The left asks
    the resident to decide something - which document, and what it is for. The
    right tells them what they have decided, and what happens next, so they are
    never submitting into a blank space and finding out afterwards.

    The document chooser is four cards rather than a <select>. There are exactly
    four documents, each with a description a resident needs in order to choose
    between them, and a dropdown is the one control that cannot show a
    description at all. The radios are real radios with real labels, so the
    browser's own keyboard handling, grouping and form participation all still
    apply, and the gold outline is drawn with peer-checked so it is correct
    before Alpine has booted rather than after.
--}}
<x-public-layout>
    <x-slot name="title">Request a Document</x-slot>

    <x-page-header :crumbs="['Document Requests', 'New Request']" title="Request a Document"
        description="Choose the document you need and say what it is for." />

    <x-shell>
        @if ($isRestricted)
            <x-alert variant="danger" icon="lock">
                This account is temporarily restricted, so a new request cannot be submitted.
                Please contact the barangay office.
            </x-alert>
        @elseif (! $isVerified)
            <x-alert variant="warning" icon="lock">
                Verification required before you can apply. The Barangay Secretary is checking
                your details against the master list, and new document requests stay locked until
                that is done.
                <a href="{{ route('resident.verification') }}" class="font-semibold underline">Check your verification status</a>.
            </x-alert>
        @elseif ($slotsRemaining === 0)
            {{-- Amber, and the form still renders with Submit disabled, rather than
                 the form disappearing. A resident who is at their limit can still read
                 what the page says a request involves, and can see which of their own
                 requests is holding the slot. The server refuses the POST either way;
                 this is about not making them guess why nothing is there. --}}
            <x-alert variant="warning" icon="lock">
                You have {{ $openCount }} of {{ $limit }} requests open, so there are no slots
                left. Wait for one to be collected or rejected before applying for another.
            </x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @if ($isVerified && ! $isRestricted)
            {{-- One Alpine scope for the whole form: the chosen document, the
                 purpose, and whether Submit may be pressed. The cards and the
                 textarea are the real controls; this only keeps the summary
                 column and the disabled state in step with them. --}}
            <form method="POST" action="{{ route('resident.requests.store') }}"
                x-data="{
                    chosen: @js(old('document_type_id') !== null ? (string) old('document_type_id') : ''),
                    purpose: @js((string) old('purpose', '')),
                    labels: @js($types->mapWithKeys(fn ($type) => [(string) $type->id => $type->label])->all()),
                    locked: @js($slotsRemaining === 0),
                    get chosenLabel() { return this.chosen ? (this.labels[this.chosen] || 'Selected document') : 'Not chosen yet' },
                    get purposePreview() { return this.purpose.trim() || 'Not stated' },
                    get canSubmit() { return this.chosen !== '' && ! this.locked },
                    use(text) { this.purpose = text },
                }">

                @csrf

                <div class="grid gap-6 lg:grid-cols-3">
                    {{-- ============================ LEFT ============================ --}}
                    <div class="space-y-6 lg:col-span-2">

                        {{-- Step 1. --}}
                        <x-card tone="white" padding="p-6">
                            <div class="flex items-baseline gap-3">
                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-bold text-white">1</span>
                                <h2 class="text-lg font-bold text-navy-900">What do you need?</h2>
                            </div>

                            <p class="mt-1.5 pl-10 text-sm text-slate-600">
                                Pick one. Every barangay document is issued free of charge.
                            </p>

                            @if ($errors->has('document_type_id'))
                                <p class="mt-3 pl-10 text-sm font-medium text-red-700">
                                    {{ $errors->first('document_type_id') }}
                                </p>
                            @endif

                            {{-- A fieldset and a legend, because these four are one
                                 question with four answers. --}}
                            <fieldset class="mt-4">
                                <legend class="sr-only">What do you need?</legend>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($types as $type)
                                        <div>
                                            <input type="radio" name="document_type_id" value="{{ $type->id }}"
                                                id="document-type-{{ $type->id }}" x-model="chosen"
                                                @if ($slotsRemaining === 0) disabled @endif
                                                class="peer sr-only">

                                            <label for="document-type-{{ $type->id }}"
                                                @class([
                                                    'flex h-full cursor-pointer flex-col rounded-xl border-2 p-4 transition',
                                                    'border-gold bg-gold/5 ring-2 ring-gold/30 peer-focus-visible:ring-4 peer-focus-visible:ring-gold/40' => $slotsRemaining > 0,
                                                    'border-navy-100 bg-white hover:border-navy-300' => $slotsRemaining > 0,
                                                    'cursor-not-allowed border-navy-100 bg-slate-50 opacity-60' => $slotsRemaining === 0,
                                                ])>
                                                <span class="flex items-start justify-between gap-3">
                                                    <span @class([
                                                        'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                                        'bg-navy-50 text-navy-700' => $type->tone !== 'sky',
                                                        'bg-sky-50 text-sky-700' => $type->tone === 'sky',
                                                    ])>
                                                        <x-icon :name="$type->icon" class="h-5 w-5" />
                                                    </span>

                                                    {{-- The radio indicator. A dot when chosen, a
                                                         ring when not, so the state is visible
                                                         without relying on the outline alone. --}}
                                                    <span aria-hidden="true"
                                                        class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition peer-checked:border-gold has-[:checked]:border-gold"
                                                        style="border-color: inherit;">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-gold opacity-0 transition has-[:checked]:opacity-100"></span>
                                                    </span>
                                                </span>

                                                <span class="mt-3 block text-sm font-bold text-navy-900">{{ $type->label }}</span>

                                                @if ($type->description)
                                                    <span class="mt-1 block text-xs leading-relaxed text-slate-600">
                                                        {{ \Illuminate\Support\Str::limit($type->description, 96) }}
                                                    </span>
                                                @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        </x-card>

                        {{-- Step 2. --}}
                        <x-card tone="white" padding="p-6">
                            <div class="flex items-baseline gap-3">
                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-bold text-white">2</span>
                                <h2 class="text-lg font-bold text-navy-900">What is it for?</h2>
                            </div>

                            <p class="mt-1.5 pl-10 text-sm text-slate-600">
                                Optional, but it helps the Secretary process the request first time.
                            </p>

                            <div class="mt-4 pl-10">
                                <label for="purpose" class="sr-only">What is it for?</label>
                                <textarea id="purpose" name="purpose" rows="4" maxlength="1000"
                                    x-model="purpose"
                                    placeholder="Employment, scholarship, school enrolment…"
                                    class="w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm text-navy-900 placeholder:text-slate-400 focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"></textarea>

                                @if ($errors->has('purpose'))
                                    <p class="mt-1.5 text-sm font-medium text-red-700">
                                        {{ $errors->first('purpose') }}
                                    </p>
                                @endif

                                {{-- Quick picks. They fill the textarea rather than
                                     submitting a value of their own, so a resident can
                                     start from one and add to it. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick pick</span>

                                    @foreach ([
                                        'Employment',
                                        'Scholarship',
                                        'School enrolment',
                                        'Financial assistance',
                                        'Other',
                                    ] as $pick)
                                        <button type="button"
                                            x-on:click="use(@js($pick))"
                                            class="rounded-full border border-navy-200 bg-white px-3 py-1 text-xs font-semibold text-navy-800 transition hover:border-gold hover:bg-gold/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold">
                                            {{ $pick }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </x-card>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <x-btn :href="route('resident.requests.index')" variant="outline" size="md">Cancel</x-btn>

                            {{-- Gold, and disabled until a document is chosen. Two
                                 separate things turn it off and they are both here:
                                 the server renders disabled when there are no slots,
                                 and Alpine binds it to whether a document is chosen.

                                 :disabled rather than @disabled() on purpose. Blade's
                                 component tag compiler cannot parse @disabled() inside a
                                 component's attribute list - it half-compiles the tag and
                                 leaves an orphan endif, which is a fatal parse error in
                                 the rendered view, not a warning. A bound attribute is
                                 dropped from the bag entirely when it is false, so the
                                 button really is enabled; a plain :disabled on a raw
                                 <input> would render disabled="false", which HTML reads
                                 as disabled, which is why the radio below uses @if. --}}
                            <x-btn type="submit" variant="primary" size="md" icon="document"
                                :disabled="$slotsRemaining === 0"
                                x-bind:disabled="! canSubmit"
                                x-bind:class="canSubmit ? '' : 'cursor-not-allowed opacity-50'">
                                Submit request
                            </x-btn>
                        </div>
                    </div>

                    {{-- ============================ RIGHT =========================== --}}
                    <aside class="space-y-6 lg:col-span-1">
                        <x-card tone="white" padding="p-6">
                            <h2 class="text-base font-bold text-navy-900">Your request</h2>

                            <dl class="mt-4 space-y-4">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Document</dt>
                                    <dd class="mt-1 text-sm font-semibold text-navy-900" x-text="chosenLabel">Not chosen yet</dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</dt>
                                    <dd class="mt-1 text-sm text-slate-700" x-text="purposePreview">Not stated</dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slots</dt>
                                    <dd class="mt-1.5 flex items-center gap-2">
                                        {{-- One dot per slot, filled while it is still
                                             spendable. The count beside it is the
                                             sentence, because three dots are a
                                             shape and not a number. --}}
                                        <span class="flex items-center gap-1.5" aria-hidden="true">
                                            @for ($dot = 0; $dot < $limit; $dot++)
                                                <span @class([
                                                    'inline-block h-2.5 w-2.5 rounded-full',
                                                    'bg-gold' => $dot < $slotsRemaining,
                                                    'bg-navy-100' => $dot >= $slotsRemaining,
                                                ])></span>
                                            @endfor
                                        </span>
                                        <span class="text-sm font-semibold text-navy-900">
                                            {{ $slotsRemaining }} of {{ $limit }} slots left
                                        </span>
                                    </dd>
                                </div>
                            </dl>

                            <p class="mt-5 rounded-lg bg-navy-50 px-3.5 py-2.5 text-xs font-semibold text-navy-800">
                                No fee for barangay documents.
                            </p>
                        </x-card>

                        <x-card tone="tinted" padding="p-6">
                            <h2 class="text-base font-bold text-navy-900">What happens next</h2>

                            <ol class="mt-4 space-y-3.5">
                                @foreach ([
                                    ['Submit', 'Your request is filed with a reference number you can quote.'],
                                    ['Review', 'The Barangay Secretary checks it against the master list.'],
                                    ['Pick up', 'Collect it at the Barangay Hall within 7 working days of being marked ready.'],
                                    ['Bring a valid ID', 'You will be asked for it when you collect.'],
                                ] as $index => [$step, $detail])
                                    <li class="flex gap-3">
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-navy-700 ring-1 ring-navy-100">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-navy-900">{{ $step }}</span>
                                            <span class="mt-0.5 block text-xs leading-relaxed text-slate-600">{{ $detail }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </x-card>
                    </aside>
                </div>
            </form>
        @endif
    </x-shell>
</x-public-layout>
