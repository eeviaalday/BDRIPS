{{--
    The staff sidebar.

    The link list is not written here. It lives in App\Support\RoleNavigation, so
    the permission model is one file of PHP that can be asserted in a test rather
    than an if/else chain in a view that cannot. This template only draws it.

    Two things it deliberately does not do:
      - it does not decide what a role may see; RoleNavigation does that;
      - it does not offer a notification bell. The previous version had one and
        its own comment admitted it was not a notification centre - it was a link
        to a page with a count beside it. There is no notification system, so
        there is nothing to replace it with, and a bell that is not a bell is a
        control that lies about what the system knows.
--}}
@php
    $groups = App\Support\RoleNavigation::availableSidebarGroupsFor(auth()->user()->role);
    $user = auth()->user();

    // Initials stand in for a photograph: this system stores no profile images,
    // and a broken avatar reads worse than a neutral one.
    $initials = collect(explode(' ', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<aside class="w-64 bg-navy text-white flex-shrink-0 min-h-screen hidden md:flex flex-col">
    <div class="px-6 py-5 border-b border-white/10">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/Zone 1_logo.png') }}" alt="Barangay Linasin Seal"
                class="w-10 h-10 rounded-full ring-2 ring-sky-400/60 object-cover flex-shrink-0" />
            <div>
                <p class="font-bold leading-tight">Barangay Linasin</p>
                <p class="text-xs text-sky-300">Zone 1, Bangued, Abra</p>
            </div>
        </a>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto" aria-label="Portal">
        @foreach ($groups['main'] as $link)
            <a href="{{ route($link['route']) }}"
                @if (App\Support\RoleNavigation::isCurrent($link)) aria-current="page" @endif
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                    {{ App\Support\RoleNavigation::isCurrent($link) ? 'bg-sky-400 text-navy font-semibold' : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                <x-icon :name="$link['icon']" class="w-5 h-5 flex-shrink-0" />
                {{ $link['label'] }}
            </a>
        @endforeach

        @if ($groups['account'] !== [])
            <div class="pt-4 mt-4 border-t border-white/10">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-sky-300/70">My Account</p>
                @foreach ($groups['account'] as $link)
                    <a href="{{ route($link['route']) }}"
                        @if (App\Support\RoleNavigation::isCurrent($link)) aria-current="page" @endif
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                            {{ App\Support\RoleNavigation::isCurrent($link) ? 'bg-sky-400 text-navy font-semibold' : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                        <x-icon :name="$link['icon']" class="w-5 h-5 flex-shrink-0" />
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="px-6 py-4 border-t border-white/10 text-sm">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gold text-xs font-bold text-navy-900 flex-shrink-0"
                aria-hidden="true">{{ $initials }}</span>
            <div class="min-w-0">
                <p class="font-medium text-sky-100 truncate">{{ $user->name }}</p>
                <p class="text-xs text-sky-300">{{ ucfirst($user->role) }}</p>
            </div>
        </div>
    </div>
</aside>
