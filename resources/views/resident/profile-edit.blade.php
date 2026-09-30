{{--
    The "Edit Profile" tab, inside the resident portal.

    The form itself is the framework's own partial, so there is one set of
    account fields in the application rather than two that can disagree about
    the field names or the update route. Only the frame around it is new.
--}}
<x-public-layout>
    <x-slot name="title">Edit Profile</x-slot>

    <x-page-header :crumbs="['My Profile']" title="Edit Profile"
        description="Your name and email address. These are used on the documents the barangay issues to you." />

    <x-shell width="read">
        <x-resident.profile-tabs current="account" />

        <x-card tone="white">
            <h2 class="text-base font-bold text-navy-900">Account details</h2>
            <p class="mt-1 text-sm leading-relaxed text-navy-600">
                Changing your email address means confirming it again before the account can be used.
            </p>

            <div class="mt-5">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-card>

        <x-alert variant="privacy" icon="id-badge" class="mt-6" title="Your resident record is separate">
            Your household number, address, sector and submitted ID are part of the resident record the
            barangay verifies, not of your login. They are shown on the
            <a href="{{ route('resident.profile') }}" class="font-semibold underline underline-offset-2">resident profile</a>
            and are corrected by sending a new ID from the Verification Status tab.
        </x-alert>
    </x-shell>
</x-public-layout>
