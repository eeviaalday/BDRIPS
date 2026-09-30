{{--
    My Account for staff and Kagawads, in the staff layout.

    A different wrapper from the resident one only so the sidebar stays beside
    the page. The content is the same partial, so a fix to one role's account
    screen is a fix to every role's.
--}}
<x-app-layout :user="auth()->user()" title="My Account">
    <div class="mx-auto w-full max-w-3xl">
        @include('account.partials.details')
    </div>
</x-app-layout>
