{{--
    My Account, shared by every role.

    Deliberately not a layout wrapper. A resident reaches this through the public
    top navbar and a Secretary through the staff sidebar, so there are two thin
    wrappers around this partial rather than one view that has to pick a layout
    from inside itself. A Blade view cannot choose its own layout; it can only
    be wrapped by one.

    "hideHeader" is set only by the resident wrapper, which has a full-bleed
    hero carrying the page title. Without it that hero and the heading below it
    would both be an <h1>, and a page carrying two of them has an ambiguous
    title for a screen reader. For every other role the flag is never set, so
    this header renders exactly as it did before.

    The block below is deliberately kept at its original indentation rather than
    indented to sit inside the conditional. Blade does not reindent output, so
    matching the original whitespace is what makes the staff page's rendered HTML
    byte-for-byte identical, which is a stronger thing to be able to claim than
    "it looks the same".
--}}
@php
    /*
     * Tabs, or not.
     *
     * Keyed on the same hideHeader flag that already separates the resident
     * wrapper from the staff one, so the two cannot disagree: a resident gets
     * one panel at a time, and every other role gets all three sections exactly
     * as before, because $tab stays null and each condition below is true.
     *
     * Only the selected panel renders. Hiding the other two with CSS would leave
     * a password form sitting in the DOM while the visitor reads their name.
     */
    $isTabbed = (bool) ($hideHeader ?? false);
    $tab = $isTabbed
        ? App\Http\Controllers\AccountController::normaliseTab(request()->query('tab'))
        : null;

    $show = fn (string $panel): bool => ! $isTabbed || $tab === $panel;
@endphp

<div class="space-y-6">
@unless ($hideHeader ?? false)
    <header>
        <h1 class="text-2xl font-extrabold tracking-tight text-navy-900">My Account</h1>
        <p class="mt-1 text-sm text-slate-600">
            Your name and sign-in address. Your role and verification status are set by the
            barangay office and cannot be changed from here.
        </p>
    </header>
@endunless

    @if (session('success'))
        <x-alert variant="success" icon="check-circle">{{ session('success') }}</x-alert>
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

    @if ($show('profile'))
    <div role="tabpanel" id="panel-profile" @if ($isTabbed) aria-labelledby="tab-profile" @endif>
    <x-card tone="white" padding="p-6">
        <h2 class="mb-5 text-base font-bold text-navy-900">Profile details</h2>

        <form method="POST" action="{{ route('account.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <x-field id="name" name="name" label="Full name" icon="user" variant="soft"
                :value="old('name', $user->name)" autocomplete="name" />

            <x-field id="email" name="email" label="Email address" icon="mail" type="email" variant="soft"
                :value="old('email', $user->email)" autocomplete="email" />

            <p class="text-xs leading-relaxed text-slate-500">
                Changing your address means confirming the new one before you can sign in again.
            </p>

            <div class="flex justify-end">
                <x-btn type="submit" variant="navy">Save changes</x-btn>
            </div>
        </form>
    </x-card>
    </div>
    @endif

    @if ($show('password'))
    <div role="tabpanel" id="panel-password" @if ($isTabbed) aria-labelledby="tab-password" @endif>
    <x-card tone="white" padding="p-6">
        <h2 class="mb-5 text-base font-bold text-navy-900">Change password</h2>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <x-field id="current_password" name="current_password" label="Current password"
                    type="password" variant="soft" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-1" />
            </div>

            <div>
                <x-field id="new_password" name="new_password" label="New password" type="password"
                    variant="soft" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('new_password')" class="mt-1" />
            </div>

            <div>
                <x-field id="new_password_confirmation" name="new_password_confirmation"
                    label="Confirm new password" type="password" variant="soft"
                    autocomplete="new-password" />
                <x-input-error :messages="$errors->get('new_password_confirmation')" class="mt-1" />
            </div>

            <div class="flex justify-end">
                <x-btn type="submit" variant="navy">Update password</x-btn>
            </div>
        </form>
    </x-card>
    </div>
    @endif

    @if ($show('verification'))
    <div role="tabpanel" id="panel-verification" @if ($isTabbed) aria-labelledby="tab-verification" @endif>
    <x-card tone="white" padding="p-6">
        <h2 class="mb-5 text-base font-bold text-navy-900">Role and status</h2>

        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</dt>
                <dd class="mt-1 text-sm font-medium text-navy-900">{{ ucfirst($user->role) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email confirmed</dt>
                <dd class="mt-1 text-sm font-medium text-navy-900">
                    {{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt>
                <dd class="mt-1 text-sm font-medium text-navy-900">
                    {{ $user->created_at?->format('M Y') }}
                </dd>
            </div>
        </dl>

    </x-card>

        {{-- The resident's own verification state, moved here from the
             standalone Verification Status page. It was always this account's own
             status, shown on a second URL that duplicated the one screen a
             resident actually uses.

             Every state says what happens next. A resident who can only see
             "pending" cannot tell whether they should be waiting or doing
             something, and a hold they cannot act on is indistinguishable from
             one they can. --}}
        @if ($user->isResident() && $user->residentProfile)
            @php($profile = $user->residentProfile)

            <x-card tone="white" padding="p-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-navy-900">Your verification</h2>
                    <x-status-badge :status="$profile->verification_status" soft />
                </div>

                {{-- The details a Secretary actually checks, so a resident can see
                     whether what they submitted is what is on file. Without them
                     the tab says "pending" and nothing about the number being
                     checked, which is the thing they would want to verify. --}}
                <dl class="mb-6 grid gap-x-6 gap-y-3 border-b border-navy-100 pb-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</dt>
                        <dd class="mt-1 text-sm font-medium text-navy-900">{{ $profile->household_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector</dt>
                        <dd class="mt-1 text-sm font-medium text-navy-900">
                            {{ $profile->sector?->sector_name ?? 'Not set' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">ID on file</dt>
                        <dd class="mt-1 text-sm font-medium text-navy-900">
                            {{ filled($profile->id_photo_path) ? 'Yes' : 'Not uploaded yet' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decided on</dt>
                        <dd class="mt-1 text-sm font-medium text-navy-900">
                            {{ $profile->verified_at?->format('j M Y') ?? 'Not yet decided' }}
                        </dd>
                    </div>
                </dl>

                @if ($profile->verification_status === App\Models\ResidentProfile::VERIFICATION_PENDING)
                    <p class="text-sm leading-relaxed text-slate-600">
                        The office is checking your details against the master list. There is
                        nothing to do while you wait - you can read your existing document
                        requests in the meantime.
                    </p>
                @elseif ($profile->verification_status === App\Models\ResidentProfile::VERIFICATION_REJECTED)
                    <p class="text-sm leading-relaxed text-slate-600">
                        {{ $profile->rejection_reason ?? 'Ask at the barangay office what to correct.' }}
                    </p>
                    <div class="mt-5">
                        <x-btn :href="route('resident.verification')" variant="navy" size="md" icon="edit">
                            Correct my details
                        </x-btn>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Saving puts your registration back in the queue for a person to look at.
                        It does not mark you as verified.
                    </p>
                @else
                    <p class="text-sm leading-relaxed text-slate-600">
                        You are verified. You can submit document requests and appear on relief
                        batches.
                    </p>
                @endif
            </x-card>
        @endif
    </div>
    @endif
</div>
