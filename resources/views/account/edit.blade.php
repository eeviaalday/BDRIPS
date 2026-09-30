{{--
    My Account for a resident, in the public layout.

    A resident arrives here from the public top navbar, so it is wrapped in the
    public layout - the same header and footer they see everywhere else. The
    shared content lives in partials/details.blade.php.
--}}
<x-public-layout>
    <x-slot name="title">My Account</x-slot>

    <x-shell>
        @include('account.partials.details')
    </x-shell>
</x-public-layout>
