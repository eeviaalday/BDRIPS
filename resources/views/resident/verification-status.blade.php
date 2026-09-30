<x-public-layout>
    <x-slot name="title">Verification Status</x-slot>

    <x-page-header :crumbs="['My Profile']" title="Verification Status"
        description="Where your resident verification has got to, and what to do next." />

    <x-shell width="read">
        <x-resident.profile-tabs current="verification" />

        @if ($profile === null)
            <x-card tone="white">
                <x-empty-state icon="id-badge" title="You have not sent an ID yet"
                    description="Resident verification checks your details against the barangay master list. It is what opens document requests and relief records.">
                    <a href="{{ route('resident.verification') }}"
                        class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                        <x-icon name="id-badge" class="h-4 w-4" />
                        Start verification
                    </a>
                </x-empty-state>
            </x-card>
        @else
            @php
                /*
                 * A progress timeline, not a spinner. The point of this tab is
                 * that the resident can see which step they are on and what is
                 * still to happen, so the steps are the real ones the system
                 * goes through - send, review, decision - and the step they are
                 * on is marked as such rather than implied by a colour.
                 */
                $steps = [
                    'ID sent',
                    'Secretary review',
                    'Decision',
                ];

                $reached = match ($profile->verification_status) {
                    \App\Models\ResidentProfile::VERIFICATION_VERIFIED => 3,
                    \App\Models\ResidentProfile::VERIFICATION_REJECTED => 3,
                    default => 1,
                };
            @endphp

            <x-card tone="white">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-navy-900">Your verification</h2>
                        <p class="mt-1 text-sm text-navy-600">
                            Last updated {{ $profile->updated_at?->format('M d, Y') ?? '—' }}
                        </p>
                    </div>
                    <x-status-badge :status="$profile->verification_status" />
                </div>

                <ol class="mt-6 space-y-4">
                    @foreach ($steps as $index => $label)
                        @php($isDone = $reached > $index + 1)
                        @php($isCurrent = $reached === $index + 1)
                        <li class="flex items-start gap-3">
                            <span @if ($isDone)
                                    class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                                @elseif ($isCurrent)
                                    class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED ? 'bg-red-600' : 'bg-gold' }} text-navy-900 ring-4 ring-gold/20"
                                @else
                                    class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 border-navy-200 bg-white text-navy-300"
                                @endif>
                                @if ($isDone)
                                    <x-icon name="check" class="h-4 w-4" />
                                @elseif ($isCurrent && $profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED)
                                    <x-icon name="x-circle" class="h-4 w-4" />
                                @else
                                    <span class="text-xs font-bold">{{ $index + 1 }}</span>
                                @endif
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-navy-900">
                                    {{ $label }}
                                    @if ($isCurrent)
                                        <span class="ml-1 text-xs font-medium text-navy-500">(you are here)</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-sm text-navy-600">
                                    @if ($index === 0)
                                        Your ID photograph and household details are on file.
                                    @elseif ($index === 1)
                                        The Barangay Secretary is checking them against the master list.
                                    @else
                                        You are told the outcome here.
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            @if ($profile->isVerified())
                <x-alert variant="success" icon="check-circle" class="mt-6" title="You are verified">
                    Your details were matched to the master list. Document requests and your household's
                    relief records are both open to you.
                    @if ($profile->verified_at)
                        <span class="mt-1 block text-xs">Verified {{ $profile->verified_at->format('M d, Y') }}.</span>
                    @endif
                </x-alert>
            @else
                <x-alert variant="danger" icon="x-circle" class="mt-6" title="Verification not approved">
                    <p>{{ $profile->rejection_reason ?? 'Your details could not be matched to the master list.' }}</p>
                    <p class="mt-2">
                        Correct the problem and send a clearer photograph of the same ID. Sending a new one
                        puts the profile back to pending so the Secretary reviews the newer file.
                    </p>
                </x-alert>
            @endif

            <x-card tone="white" class="mt-6">
                <h2 class="text-base font-bold text-navy-900">Details on file</h2>

                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Household number</dt>
                        <dd class="mt-1 font-mono text-sm text-navy-900">{{ $profile->household_number }}</dd>
                    </div>
                    @if ($profile->national_or_voter_id)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">National / Voter ID</dt>
                            <dd class="mt-1 font-mono text-sm text-navy-900">{{ $profile->national_or_voter_id }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Address</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->address }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-navy-500">Sector</dt>
                        <dd class="mt-1 text-sm text-navy-900">{{ $profile->sector?->sector_name ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($profile->id_photo_path)
                    <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-navy-500">ID you sent</p>
                    <a href="{{ route('resident.verification.id-photo') }}" target="_blank" rel="noopener"
                        class="mt-2 block w-fit">
                        <img src="{{ route('resident.verification.id-photo') }}" alt="The ID you submitted"
                            class="max-h-48 rounded-lg border border-navy-200 object-cover">
                    </a>
                @else
                    <x-alert variant="warning" icon="id-badge" class="mt-4">
                        No ID photograph is on file, which is expected for an account created before the
                        registration form collected one.
                    </x-alert>
                @endif
            </x-card>

            <div class="mt-6">
                <x-btn :href="route('resident.verification')" variant="primary" icon="id-badge">
                    {{ $profile->isVerified() ? 'Replace ID' : 'Send a new ID' }}
                </x-btn>
            </div>
        @endif
    </x-shell>
</x-public-layout>
