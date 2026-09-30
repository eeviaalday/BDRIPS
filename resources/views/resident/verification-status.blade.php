{{--
    Where the resident verification has got to, read only.

    Every state says what happens next. A resident who can only see "pending"
    cannot tell whether they should be waiting or doing something, and a hold they
    cannot act on is indistinguishable from one they can.

    In the public layout, on the same x-page-header as the other resident pages.
    It had been wearing the staff sidebar, which put a resident-only page inside
    the shell that a Secretary sees - a resident would have arrived here with a
    navigation full of links to screens that refuse them.
--}}
<x-public-layout>
    <x-slot name="title">Verification Status</x-slot>

    <x-page-header :crumbs="['Verification Status']" title="Verification Status"
        description="Where your registration is, and what happens next." />

    <x-shell width="narrow">

        <x-card tone="white" padding="p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current state</p>
                    <p class="mt-1 text-lg font-bold text-navy-900">{{ $profile->statusLabel() }}</p>
                </div>
                <x-status-badge :status="$profile->verification_status" />
            </div>

            <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-navy-50 pt-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</dt>
                    <dd class="text-sm text-navy-900">{{ $profile->household_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt>
                    <dd class="text-sm text-navy-900">{{ $profile->sector?->sector_name ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">ID on file</dt>
                    <dd class="text-sm text-navy-900">
                        {{ filled($profile->id_photo_path) ? 'Yes' : 'Not uploaded yet' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decided on</dt>
                    <dd class="text-sm text-navy-900">{{ $profile->verified_at?->format('j M Y') ?? 'Not yet decided' }}</dd>
                </div>
            </dl>
        </x-card>

        @if ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_PENDING)
            <x-alert variant="info" icon="clock">
                The office is checking your details against the master list. There is nothing to
                do while you wait - you can read your existing requests in the meantime.
            </x-alert>
        @elseif ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED)
            <x-card tone="white" padding="p-6">
                <h2 class="text-base font-bold text-navy-900">What to change</h2>
                <p class="mt-2 text-sm leading-relaxed text-navy-800">
                    {{ $profile->rejection_reason ?? 'Ask at the barangay office what to correct.' }}
                </p>
                <div class="mt-5">
                    <x-btn :href="route('resident.verification')" variant="navy" size="md" icon="edit">
                        Correct my details
                    </x-btn>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Saving puts your registration back in the queue for a person to look at. It
                    does not mark you as verified.
                </p>
            </x-card>
        @elseif ($profile->verification_status === \App\Models\ResidentProfile::VERIFICATION_VERIFIED)
            <x-alert variant="success" icon="check-circle">
                You are verified. You can submit document requests and appear on relief batches.
            </x-alert>
        @endif
    </x-shell>
</x-public-layout>
