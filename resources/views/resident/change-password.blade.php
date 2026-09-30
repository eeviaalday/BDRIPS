{{--
    The "Change Password" tab, inside the resident portal.

    The form is the framework's own partial, which posts to password.update and
    carries its current_password rule - so the password is changed by exactly
    one piece of code, and a resident who does not know the current password
    cannot set a new one. This file is only the frame.
--}}
<x-public-layout>
    <x-slot name="title">Change Password</x-slot>

    <x-page-header :crumbs="['My Profile']" title="Change Password"
        description="Choose a password you do not use anywhere else. You will stay signed in on this device." />

    <x-shell width="read">
        <x-resident.profile-tabs current="password" />

        <x-card tone="white">
            <h2 class="text-base font-bold text-navy-900">Change your password</h2>
            <p class="mt-1 text-sm leading-relaxed text-navy-600">
                Your current password is required, so nobody who finds an open device can take the
                account over by guessing.
            </p>

            <div class="mt-5">
                @include('profile.partials.update-password-form')
            </div>
        </x-card>
    </x-shell>
</x-public-layout>
