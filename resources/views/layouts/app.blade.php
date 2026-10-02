<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'BDRPIS' }} - BDRPIS</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 text-gray-800 font-sans antialiased">
        @php
            $user = auth()->user();

            // Initials stand in for a photograph: this system stores no profile
            // images, and a broken avatar reads worse than a neutral one. The
            // sidebar builds its own copy because an include does not share
            // variables with the view that includes it in a usable way here.
            $initials = collect(explode(' ', trim($user->name)))
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp

        <div class="flex min-h-screen">
            @include('layouts.navigation')

            <div class="flex-1 flex flex-col min-w-0">
                <!-- Topbar -->
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="flex items-center justify-between gap-4 px-6 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <img src="{{ asset('images/Zone 1_logo.png') }}" alt=""
                                class="h-10 w-10 rounded-full object-cover ring-2 ring-sky-200 flex-shrink-0" />
                            <div class="min-w-0">
                                <p class="font-bold leading-tight text-navy-900">BDRPIS</p>
                                <p class="text-xs text-gray-500 truncate">Barangay Linasin, Zone 1, Bangued, Abra</p>
                            </div>
                        </div>

                        {{-- The notification bell and the settings cog that used to sit
                             here are gone, and nothing replaces them.

                             The bell was not a notification centre - its own comment
                             said so. It was a link to a page with a count beside it,
                             and there is no record anywhere of what a person has seen,
                             so a dot on it could never mean "new to you". With no
                             notification system, keeping the icon would keep a control
                             that lies about what the system knows.

                             The cog was a second route to System Settings, which is a
                             single Admin destination now reachable from the sidebar.
                             Two doors to an Admin-only page is one more place for the
                             role check to be missing. --}}
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 py-1 pl-1 pr-3">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-navy text-xs font-bold text-white flex-shrink-0"
                                    aria-hidden="true">{{ $initials }}</span>
                                <span class="hidden sm:block min-w-0">
                                    <span class="block text-sm font-semibold leading-tight text-navy-900 truncate max-w-[10rem]">{{ $user->name }}</span>
                                    <span class="block text-[11px] leading-tight text-gray-500">{{ ucfirst($user->role) }}</span>
                                </span>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="text-sm font-medium text-gray-600 hover:text-gray-900 transition">
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- pb-24 below md clears the fixed phone navigation in
                     layouts/navigation, which sits at the bottom of the viewport. Without
                     it the last card on every staff page is unreachable behind the bar. --}}
<main class="flex-1 p-6 pb-24 md:pb-6">
{{-- No heading here. $title reaches this layout for the document title in <head>
     only, because AppLayout is a class component and would have had to declare it
     to get it that far. Printing it as an <h1> as well would give every staff page
     a second one, next to the heading the page already renders. --}}

                    @if (session('success'))
                        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Back after logout must not redisplay the page that was left.
             The no-store headers tell the browser not to keep it, but a page
             restored from the back-forward cache still fires pageshow with
             persisted = true. Reloading makes the server answer instead, and
             the server sends the login screen because the session is gone.

             This is a script and nothing else: it changes no markup and no
             styling, so the staff and kagawad screens look exactly as they did. --}}
        <script>
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>