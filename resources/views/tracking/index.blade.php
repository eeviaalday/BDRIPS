{{--
    Public request tracking.

    Two fields, because a reference is four digits. The result deliberately shows
    no name, no address and no contact details: the person asking has proved they
    filed the request, and has no need of the rest.
--}}
<x-public-layout title="Track a Request">
    <div class="mx-auto w-full max-w-2xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold text-navy-900">Track a request</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Enter the reference number from your receipt and your household number. Both are
                on the copy you were given when you filed.
            </p>
        </header>

        <x-card tone="white" padding="p-6">
            <form method="GET" action="{{ route('requests.track') }}" class="space-y-4">
                <div>
                    <label for="reference" class="block text-sm font-semibold text-navy-800">Reference number</label>
                    <input id="reference" name="reference" type="text" required maxlength="50"
                        value="{{ $reference }}" placeholder="BRGY-2026-1234"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 font-mono text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                </div>

                <div>
                    <label for="household" class="block text-sm font-semibold text-navy-800">Household number</label>
                    <input id="household" name="household" type="text" required maxlength="50"
                        value="{{ $household }}" placeholder="HH-0001"
                        class="mt-1.5 w-full rounded-lg border border-navy-200 bg-white px-3.5 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    <p class="mt-1.5 text-xs text-slate-500">
                        Asked for so that a reference number on its own cannot be used to look up
                        somebody else's request.
                    </p>
                </div>

                <x-btn type="submit" variant="navy" size="md" icon="search">Track my request</x-btn>
            </form>
        </x-card>

        @if ($notFound)
            <x-alert variant="warning" icon="help-circle">
                We could not find that request. Check both numbers - the reference is printed as
                <span class="font-mono">BRGY-YYYY-0000</span>.
            </x-alert>
        @elseif ($tracked !== null)
            <x-card tone="white" padding="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-navy-900">{{ $tracked->documentType?->label ?? 'Document request' }}</h2>
                        <p class="mt-1 font-mono text-sm text-slate-500">{{ $tracked->reference_number }}</p>
                    </div>
                    <x-status-badge :status="$tracked->status" />
                </div>

                <dl class="mt-5 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Filed</dt>
                        <dd class="text-sm text-navy-900">{{ $tracked->created_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                        <dd class="text-sm text-navy-900">{{ $tracked->fee_amount ?? '—' }}</dd>
                    </div>
                </dl>

                <h3 class="mt-6 mb-3 text-sm font-bold text-navy-900">Progress</h3>
                <ol class="space-y-2">
                    @foreach ($timeline as $step)
                        <li class="flex items-center gap-2.5 text-sm">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-[10px] font-bold text-emerald-700"
                                aria-hidden="true">&#10003;</span>
                            <span class="text-navy-900">{{ $step['label'] }}</span>
                            <span class="text-xs text-slate-500">{{ $step['at']?->format('j M Y, g:i a') }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($tracked->status === \App\Models\DocumentRequest::STATUS_REJECTED)
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4">
                        <p class="text-sm font-semibold text-red-800">The office could not process this request</p>
                        <p class="mt-1 text-sm text-red-700">
                            {{ $tracked->rejection_reason ?? 'Ask at the barangay office what to change.' }}
                        </p>
                    </div>
                @endif
            </x-card>
        @endif

        <p class="text-center text-sm text-slate-500">
            Signed in? <a href="{{ route('login') }}" class="font-semibold text-navy-700 underline underline-offset-2">Your requests</a>
            are all on one page.
        </p>
    </div>
</x-public-layout>
