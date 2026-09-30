{{--
    A resident's verification state, and the details behind it.

    A rejected resident is not locked out of this page - they are told what to
    change and where to change it, because the lock and the way out of it are
    the same thing. The ID they submitted is shown so they can check it against
    what they sent, which is the common cause of a rejection.
--}}
<x-public-layout>
    <x-slot name="title">Verification Status</x-slot>

    <x-page-header :crumbs="['Verification Status']" title="My Verification"
        description="Where your details stand, and the information the Barangay Secretary checks." />

    <x-shell width="narrow">
        @if (session('success'))
            <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
        @endif

        @if ($profile === null)
            <x-empty-state icon="id-badge" title="No resident record yet"
                description="Your account exists but no resident profile is attached to it. Please contact the barangay office." />
        @else
            <x-card tone="white" padding="p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-navy-900">Current status</h2>
                    <x-status-badge
                        :status="$profile->isVerified() ? 'verified' : $profile->verification_status"
                        size="sm" />
                </div>

                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    @if ($profile->isVerified())
                        Your details have been checked against the barangay master list. Document
                        requests and relief records are unlocked.
                    @elseif ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED)
                        <strong class="font-semibold text-navy-900">Your details could not be matched.</strong>
                        {{ $profile->rejection_reason ?? 'No reason was recorded.' }}
                        Correct them below and submit again.
                    @else
                        The Barangay Secretary is checking your details against the master list.
                        Document requests and relief records stay locked until that is done.
                    @endif
                </p>

                @if ($profile->id_photo_path)
                    <p class="mt-3 text-xs text-slate-500">
                        ID on file: <span class="font-mono">{{ basename($profile->id_photo_path) }}</span>
                        @if ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED)
                            — upload a replacement below if this is the document that was refused.
                        @endif
                    </p>
                @endif
            </x-card>

            <x-card tone="white" padding="p-6">
                <h2 class="mb-1 text-base font-bold text-navy-900">My details</h2>
                <p class="mb-5 text-sm text-slate-600">
                    @if ($profile->isVerified())
                        Changing anything here sends your record back to pending, because a human
                        checked the details that are on file now and those are not the ones on file
                        after an edit.
                    @else
                        These are the details the Secretary checks. Correct anything that is wrong
                        and submit again.
                    @endif
                </p>

                @if ($errors->any())
                    <x-alert variant="danger" icon="x-circle" class="mb-5">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('resident.verification.update') }}"
                    enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <x-field id="household_number" name="household_number" label="Household reference number"
                        icon="home" variant="soft" :value="old('household_number', $profile->household_number)" />

                    <x-field id="national_or_voter_id" name="national_or_voter_id" label="National or voter ID"
                        icon="id-badge" variant="soft" :value="old('national_or_voter_id', $profile->national_or_voter_id)"
                        hint="Optional. Leave blank if you do not have one to hand." />

                    <div>
                        <label for="address" class="block text-sm font-semibold text-navy-800">House address</label>
                        <textarea id="address" name="address" rows="3" maxlength="255" required
                            class="mt-1.5 w-full rounded-lg border border-navy-200 bg-navy-50/60 px-3.5 py-2.5 text-sm text-navy-900 focus:border-navy-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-200">{{ old('address', $profile->address) }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>

                    <x-select-field id="sector_id" name="sector_id" label="Sector / Purok" variant="soft" required
                        :options="\App\Models\Sector::orderBy('id')->pluck('sector_name', 'id')->all()" />

                    <x-file-upload name="id_photo" label="Replace valid ID"
                        accept=".jpg,.jpeg,.png,.pdf" class="mt-1"
                        hint="Optional. Only needed if the ID you sent before was refused, or has expired." />

                    <div class="flex justify-end border-t border-navy-100 pt-5">
                        <x-btn type="submit" variant="navy" size="md" icon="check-circle">Submit for verification</x-btn>
                    </div>
                </form>
            </x-card>
        @endif
    </x-shell>
</x-public-layout>
