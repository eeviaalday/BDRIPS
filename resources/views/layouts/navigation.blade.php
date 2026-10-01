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

    /*
     * "Still to collect" beside Relief Distribution, for a Kagawad only.
     *
     * Deliberately not in RoleNavigation: that file is the permission model and is
     * meant to be one list of PHP that a test can read, and a figure that needs a
     * database query is not a permission. It is drawn here instead, and only for
     * the one role whose whole job is the number.
     *
     * The count is scoped exactly as the checklist is - to this Kagawad's sectors,
     * never barangay-wide - because a badge is read without being asked for, and a
     * badge showing 40 would tell a Kagawad the size of a neighbouring purok's
     * list just by opening the page.
     *
     * One indexed COUNT(*), and only when the viewer is a Kagawad: Secretary and
     * Admin pay nothing for it.
     */
    $pendingRelief = $user->isKagawad()
        ? App\Models\ReliefDistribution::query()
            ->forSectors($user->assignedSectorIds())
            ->where('status', App\Models\ReliefDistribution::STATUS_PENDING)
            ->count()
        : null;

    // Which nav entries carry a badge, keyed by route so the link list itself
    // stays untouched.
    $badges = $pendingRelief === null || $pendingRelief < 1 ? [] : ['kagawad.relief' => $pendingRelief];
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
                @if (isset($badges[$link['route']]))
                    {{-- aria-label, because "0" alone read out loud is not a
                         sentence, and the visible number is already the message. --}}
                    <span class="ml-auto inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-gold px-1.5 py-0.5 text-2xs font-bold text-navy-900"
                        aria-label="{{ $badges[$link['route']] }} still to collect">
                        {{ $badges[$link['route']] }}
                    </span>
                @endif
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

{{--
    The phone navigation.

    The sidebar above is `hidden md:flex`, so below 768px this whole file rendered
    nothing and a staff member on a phone had no way to reach any page except by
    typing a URL. For a Kagawad that is not a cosmetic gap: this account is used
    outdoors, mid-distribution, and the relief list is the entire reason to open
    the app.

    A bottom bar rather than a hamburger, because the destinations are two or three
    and a thumb reaches the bottom of a phone. Every target is min-h-[44px].

    Driven by the same $groups the sidebar uses, so the permission model still
    lives only in RoleNavigation and this file still decides nothing about who may
    see what.

    It appears for every staff role, not just Kagawad, and that is deliberate even
    though the work was scoped to the Kagawad area: the gap is identical for the
    Secretary and the Admin on the same layout, and the alternative - a nav that
    exists for one role - would mean either a duplicated link list or a conditional
    around the shared shell. Nothing is added to any role's list; the links are the
    ones that role already had.
--}}
@php
    $mobileLinks = array_merge($groups['main'], $groups['account']);
@endphp

@if ($mobileLinks !== [])
    <nav class="md:hidden fixed inset-x-0 bottom-0 z-40 border-t border-navy-700 bg-navy"
        aria-label="Portal">
        <ul class="flex">
            @foreach ($mobileLinks as $link)
                <li class="flex-1">
                    <a href="{{ route($link['route']) }}"
                        @if (App\Support\RoleNavigation::isCurrent($link)) aria-current="page" @endif
                        class="relative flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2 text-center transition
                            {{ App\Support\RoleNavigation::isCurrent($link) ? 'text-gold' : 'text-sky-100' }}">
                        <x-icon :name="$link['icon']" class="h-5 w-5 flex-shrink-0" />
                        <span class="text-2xs font-semibold leading-tight">{{ $link['label'] }}</span>
                        @if (isset($badges[$link['route']]))
                            <span class="absolute right-1/2 top-1 translate-x-5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-gold px-1 py-px text-2xs font-bold text-navy-900"
                                aria-label="{{ $badges[$link['route']] }} still to collect">
                                {{ $badges[$link['route']] }}
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
