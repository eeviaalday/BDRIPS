<x-public-layout>
    <x-slot name="title">Account Settings</x-slot>

    {{--
        The framework's own profile partials, unchanged, at a URL inside
        /resident. Two pages of settings forms would be two places to fix the
        same field, so this reuses the partials and differs only in where the
        request lands and what the page is called.

        Note what is NOT here: household number, address, sector and the
        submitted ID. Those belong to the resident record, not to the account,
        and staff verify them - so they are on /resident/profile (read-only)
        and changed through the verification flow, not editable from settings.
    --}}
    <div class="max-w-3xl space-y-6">
        <x-card tone="white">
            <h2 class="text-base font-bold text-navy-900">Profile Information</h2>
            <p class="mt-1 text-sm text-navy-600">Your name and email address.</p>
            <div class="mt-5">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-card>

        <x-card tone="white">
            <h2 class="text-base font-bold text-navy-900">Update Password</h2>
            <p class="mt-1 text-sm text-navy-600">
                Use a long, random password you do not use anywhere else.
            </p>
            <div class="mt-5">
                @include('profile.partials.update-password-form')
            </div>
        </x-card>

        {{-- No API tokens section: Jetstream is not installed in this project,
             and profile.partials.api-token-form does not exist, so there is
             nothing to include. --}}
    </div>
</x-public-layout>
