<x-public-layout>
    <x-slot name="title">Resident Verification</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="rounded-xl bg-white border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900">One-time Profile Verification</h2>
            <p class="text-sm text-gray-500 mt-1">
                Upload a photo of your ID. The Barangay Secretary will cross-check it, and the details you
                gave at sign-up, against the Barangay Master List.
            </p>

            @if ($profile?->id_photo_path)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    You already uploaded an ID photo (status:
                    <span class="uppercase font-semibold">{{ $profile->verification_status }}</span>).
                    Uploading another re-queues your profile for verification.
                </div>
            @endif

            {{-- ============================================================
                 WHAT WAS ALREADY COLLECTED
                 ============================================================
                 Address, household number, national ID and sector were asked for
                 during registration and are read-only here. Repeating them would
                 mean typing the same details twice and risking the two copies
                 disagreeing, so the Secretary verifies what was submitted rather
                 than what was retyped.
            --}}
            @if ($profile)
                <dl class="mt-5 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                    @foreach ([
                        'Full name' => auth()->user()->name,
                        'Date of birth' => auth()->user()->date_of_birth?->format('M d, Y'),
                        'Household number' => $profile->household_number,
                        'Address' => $profile->address,
                        'Sector' => $profile->sector?->sector_name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm text-gray-900">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach

                    {{-- The valid ID is a document now, not a number, so it is
                         shown as what it is: the file that was submitted. Older
                         profiles still hold a typed number and are shown that
                         way instead, so the Secretary can read either. --}}
                    @if ($profile->national_or_voter_id)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">ID number on file</dt>
                            <dd class="mt-0.5 text-sm text-gray-900">{{ $profile->national_or_voter_id }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="mt-3 text-xs text-gray-500">
                    Something wrong here? Update it in your
                    <a href="{{ route('resident.profile') }}" class="font-medium text-blue-700 underline underline-offset-2">profile settings</a>
                    before submitting.
                </p>
            @else
                <x-alert variant="info" icon="shield" class="mt-4">
                    Your profile details are missing. Please
                    <a href="{{ route('resident.profile') }}" class="font-semibold underline underline-offset-2">complete your profile</a>
                    before uploading an ID photo.
                </x-alert>
            @endif

            <form method="POST" action="{{ route('resident.verification.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="id_photo" value="{{ $profile?->id_photo_path ? 'Replace your valid ID photo (JPG/PNG, max 4MB)' : 'Valid ID Photo (JPG/PNG, max 4MB)' }}" />
                    <input id="id_photo" name="id_photo" type="file" accept="image/*" required
                        class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-white file:text-sm file:font-semibold hover:file:bg-blue-800" />
                    <x-input-error :messages="$errors->get('id_photo')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('resident.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancel</a>
                    <button type="submit"
                        class="rounded-lg bg-blue-700 hover:bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white transition">
                        Submit for Verification
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-public-layout>
