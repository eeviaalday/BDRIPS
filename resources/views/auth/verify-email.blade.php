<x-guest-layout>
    {{-- The introduction has to be true of what actually happened. On the
         failure path the default wording below would be describing an email
         that was never sent, so the two cases get their own text. --}}
    @if (session('status') == 'verification-notice-failed')
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Thanks for signing up! Your account was created, but we could not send the verification email just now. This is a problem with our email settings, not with yours. Please press Resend Verification Email below in a moment, then check your inbox.') }}
        </div>
    @else
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>
    @endif

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
