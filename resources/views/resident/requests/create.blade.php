{{--
    The new-request form.

    A pending resident reaches this page rather than finding the link missing,
    because a link that is simply absent gives them nothing to act on. The
    wizard is not rendered for them: the form request refuses the POST, so
    showing controls that cannot succeed would be a worse dead end than an
    explanation.
--}}
<x-public-layout>
    <x-slot name="title">Request a Document</x-slot>

    <x-page-header :crumbs="['Document Requests', 'New Request']" title="Request a Document"
        description="Choose the document you need and say what it is for." />

    <x-shell width="narrow">
        <x-alert :variant="$isRestricted ? 'danger' : 'warning'" icon="lock">
            @if ($isRestricted)
                This account is temporarily restricted, so a new request cannot be submitted.
                Please contact the barangay office.
            @elseif (! $isVerified)
                Verification required before you can apply. The Barangay Secretary is checking
                your details against the master list, and new document requests stay locked until
                that is done.
                <a href="{{ route('resident.verification') }}" class="font-semibold underline">Check your verification status</a>.
            @else
                You have {{ $openCount }} of {{ $limit }} requests open
                ({{ $slotsRemaining }} {{ $slotsRemaining === 1 ? 'slot' : 'slots' }} remaining).
                @if ($slotsRemaining === 0)
                    Wait for one to be collected or rejected before applying for another.
                @endif
            @endif
        </x-alert>

        @if ($errors->any())
            <x-alert variant="danger" icon="x-circle">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @if ($isVerified && ! $isRestricted && $slotsRemaining > 0)
            <x-card tone="white" padding="p-6">
                <form method="POST" action="{{ route('resident.requests.store') }}" class="space-y-6">
                    @csrf

                    {{-- A key => label map, not a slot: the component writes the
                         options itself so the stored value round-trips as the key
                         it was stored as. Barangay documents are free, so a price
                         is not part of what a resident is choosing between. --}}
                    <x-select-field id="document_type_id" name="document_type_id"
                        label="What do you need?" variant="soft" required
                        placeholder="Choose a document"
                        :options="$types->mapWithKeys(fn ($type) => [
                            $type->id => $type->label,
                        ])->all()" />

                    <div>
                        <label for="purpose" class="block text-sm font-semibold text-navy-800">What is it for?</label>
                        <textarea id="purpose" name="purpose" rows="4" maxlength="1000"
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm text-navy-900 placeholder:text-slate-400 focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200"
                            placeholder="Employment, scholarship, school enrolment…">{{ old('purpose') }}</textarea>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Optional, but it helps the Secretary process the request first time.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-navy-100 pt-5">
                        <x-btn :href="route('resident.requests.index')" variant="outline" size="md">Cancel</x-btn>
                        <x-btn type="submit" variant="navy" size="md" icon="document">Submit request</x-btn>
                    </div>
                </form>
            </x-card>
        @endif
    </x-shell>
</x-public-layout>
