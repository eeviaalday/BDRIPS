<x-public-layout>
    <x-slot name="title">Resident Profile</x-slot>

    {{--
        Read-only on purpose. This is "what does the barangay currently have on
        file for me", which is what a resident checks before submitting a
        request and what staff are told to verify against. The editable half
        lives on /resident/settings; offering a second place to change the same
        values is how two copies of a record come to disagree.
    --}}
    @if ($profile === null)
        <x-card tone="white" class="text-center">
            <x-icon name="id-badge" class="mx-auto h-10 w-10 text-navy-300" />
            <h2 class="mt-4 text-lg font-bold text-navy-900">No resident profile yet</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-navy-600">
                Your profile is created when you register. If you do not have one, the
                account may predate the registration wizard - ask at the Barangay Hall to have
                it set up.
            </p>
        </x-card>
    @else
        <x-card tone="white">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-navy-900">{{ $user->name }}</h2>
                    <p class="mt-0.5 text-sm text-navy-600">{{ $user->email }}</p>
                </div>
                <x-status-badge :status="$profile->verification_status" />
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'Date of birth' => $user->date_of_birth?->format('M d, Y'),
                    'Gender' => $user->gender,
                    'Civil status' => $user->civil_status,
                    'Nationality' => $user->nationality,
                    'Household number' => $profile->household_number,
                    'Address' => $profile->address,
                    'Sector / Purok' => $profile->sector?->sector_name,
                    'ID number on file' => $profile->national_or_voter_id,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        <x-card tone="white" class="mt-6">
            <h2 class="text-base font-bold text-navy-900">Submitted ID</h2>
            <p class="mt-1 text-sm text-navy-600">
                The document the Barangay Secretary checks. It is served through an authorised
                route rather than a public link, so it is not reachable by guessing a URL.
            </p>

            @if ($profile->id_photo_path)
                <img src="{{ route('staff.resident-verifications.id-photo', $profile) }}" alt="The valid ID you submitted"
                    class="mt-4 max-h-64 rounded-xl border border-navy-100 object-contain" />
                <p class="mt-2 text-xs text-navy-500">Submitted {{ $profile->created_at->format('M d, Y') }}</p>
            @else
                <x-empty-state icon="id-badge" title="No ID on file"
                    description="Submit a photograph or scan of a valid ID so the barangay can verify you." class="mt-4">
                    <a href="{{ route('resident.verification') }}"
                        class="mt-5 inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                        Submit my ID
                    </a>
                </x-empty-state>
            @endif

            @if ($profile->rejection_reason)
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <span class="font-semibold">Reason for rejection:</span> {{ $profile->rejection_reason }}
                </div>
            @endif
        </x-card>

        <div class="mt-6">
            <a href="{{ route('resident.settings') }}"
                class="inline-flex items-center justify-center rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                Account settings
            </a>
        </div>
    @endif
</x-public-layout>
