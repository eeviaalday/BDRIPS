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

    <x-card tone="white" padding="p-6" :class="($focus ?? null) === 'password' ? 'ring-2 ring-gold/60' : ''">
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

        @if ($user->isResident())
            <p class="mt-4 border-t border-navy-100 pt-4 text-sm text-slate-600">
                Verification status:
                <span class="font-semibold text-navy-900">
                    {{ $user->residentProfile?->isVerified() ? 'Verified' : 'Pending review' }}
                </span>
            </p>
        @endif
    </x-card>
</div>
