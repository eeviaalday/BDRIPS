{{--
    The resident's profile: one page, three tabs.

    Edit Profile, Change Password and Verification Status were three URLs
    rendering three near-copies of the same account screen. They are now tabs on
    one page, because they were always one thing - "my account" - and three
    addresses for one thing is three places for the resident's own permissions
    and the forms' rules to be wrong.

    The tab lives in the URL as ?tab=, not in local state, so a tab can be
    linked, bookmarked and reloaded, and the back button steps between tabs. The
    old URLs redirect here so nothing that already pointed at them breaks.

    The hero is the shared resident hero. The shared account partial suppresses
    its own heading when told to, so the page carries exactly one h1.
--}}
<x-public-layout>
    <x-slot name="title">My Profile</x-slot>

    <x-page-header :crumbs="['My Profile']" title="My Profile"
        description="Your name, your password, and where your verification stands." />

    <x-shell width="narrow">
        <x-account.tabs />

        @include('account.partials.details', ['hideHeader' => true])
    </x-shell>
</x-public-layout>
