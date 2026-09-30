@props([
    // The profile, or null. Passed in rather than read from auth() so a page can
    // decide explicitly which state it is showing.
    'profile' => null,
])

@php
    /*
     * The gate banner.
     *
     * Pending and verified residents share one layout and one navbar, so this
     * is the only place the difference is announced. It says what is locked and
     * links to the page that unlocks it, rather than leaving the resident to
     * discover the lock by pressing a button that refuses to work.
     *
     * The gate is ResidentProfile::isVerified(), not users.is_verified. The
     * account column records that the email address is confirmed; the profile
     * records that a person was checked against the master list. DocumentRequest
     * Controller::store() already refuses on the profile, so a banner that keyed
     * off the account would promise a door the server will not open.
     *
     * A rejected profile gets the same banner with the reason on it, because
     * the lock and the way out are identical: fix the problem, resubmit.
     *
     * A resident with no profile at all has not been refused anything, so there
     * is nothing to lock yet - they are told what to do instead.
     */
    $isPending = $profile !== null && ! $profile->isVerified();
    $isRejected = $profile?->verification_status === \App\Models\ResidentProfile::VERIFICATION_REJECTED;
@endphp

@if ($isPending)
    <div role="status"
        class="border-b border-gold-300 bg-gold-50">
        <div class="mx-auto flex w-full max-w-shell flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 2xl:max-w-[90rem]">
            <div class="flex items-start gap-3 sm:items-center">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gold-100 text-gold-700 sm:mt-0">
                    <x-icon name="clock" class="h-4 w-4" />
                </span>
                <div class="min-w-0 text-sm leading-relaxed">
                    <p class="font-bold text-navy-900">
                        @if ($isRejected)
                            Your verification was not approved
                        @else
                            Your verification is pending
                        @endif
                    </p>
                    <p class="text-navy-700">
                        @if ($isRejected)
                            {{ $profile->rejection_reason ?? 'The details submitted could not be matched to the master list.' }}
                            You can send a new ID document once you have corrected them.
                        @else
                            The Barangay Secretary is checking your details against the master list. You can follow your existing requests, but new document requests and relief records stay locked until you are verified.
                        @endif
                    </p>
                </div>
            </div>

            <a href="{{ route('account.edit', ['tab' => 'verification']) }}"
                class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg bg-navy px-4 py-2.5 text-sm font-bold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gold-50 sm:self-auto">
                <x-icon name="shield" class="h-4 w-4" />
                View status
            </a>
        </div>
    </div>
@elseif ($profile === null)
    <div role="status"
        class="border-b border-sky-200 bg-sky-50">
        <div class="mx-auto flex w-full max-w-shell flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 2xl:max-w-[90rem]">
            <div class="flex items-start gap-3 sm:items-center">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700 sm:mt-0">
                    <x-icon name="id-badge" class="h-4 w-4" />
                </span>
                <div class="min-w-0 text-sm leading-relaxed">
                    <p class="font-bold text-navy-900">You have not sent your ID yet</p>
                    <p class="text-sky-900">
                        Send a photo of a valid ID to open document requests and relief records.
                    </p>
                </div>
            </div>

            {{-- Both branches point at the same place. The old file had a second
                 route, resident.verification, for "you have not sent your ID
                 yet" - a separate upload form. That form is part of the resident
                 area and is being rebuilt, so in the meantime this points at the
                 status page, which is the one verification destination the
                 profile menu offers anyway. --}}
            <a href="{{ route('account.edit', ['tab' => 'verification']) }}"
                class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg bg-navy px-4 py-2.5 text-sm font-bold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-50 sm:self-auto">
                <x-icon name="id-badge" class="h-4 w-4" />
                Start verification
            </a>
        </div>
    </div>
@endif
