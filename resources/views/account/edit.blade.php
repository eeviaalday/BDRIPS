{{--
    My Account for a resident, in the public layout.

    A resident arrives here from the public top navbar, so it is wrapped in the
    public layout - the same header and footer they see everywhere else. The
    shared content lives in partials/details.blade.php.

    The hero is the same x-page-header the other resident pages use, and it sits
    as a sibling of x-shell rather than inside it, so it is the full-bleed band
    rather than an inset box. That means this wrapper sets hideHeader for the
    shared partial, which would otherwise render a second <h1> underneath it.
    The staff wrapper does not set the flag, so the Secretary's page is unchanged.
--}}
<x-public-layout>
    <x-slot name="title">My Account</x-slot>

    <x-page-header :crumbs="[($focus ?? null) === 'password' ? 'Change Password' : 'Edit Profile']"
        :title="($focus ?? null) === 'password' ? 'Change Password' : 'My Account'"
        :description="($focus ?? null) === 'password'
            ? 'Choose a new password for this account.'
            : 'Your name and sign-in address. Your role and verification status are set by the barangay office.'" />

    <x-shell width="narrow">
        {{-- hideHeader is passed explicitly rather than set with a @php block
             above: a local template variable does not cross an @include boundary,
             so the flag would silently arrive as unset and the shared header
             would render under the hero, giving the page two h1s. --}}
        @include('account.partials.details', ['hideHeader' => true])
    </x-shell>
</x-public-layout>
