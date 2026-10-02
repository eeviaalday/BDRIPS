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
     * The count badges, keyed by route name.
     *
     * Deliberately not in RoleNavigation: that file is the permission model and is
     * meant to be one list of PHP that a test can read without a database, and a
     * figure that needs a query is not a permission. The counts live in
     * App\Support\NavigationBadges, which is also where the two dashboards read
     * theirs, so a badge and the card beside it cannot disagree.
     *
     * forUser() memoises, so the three places that draw a badge - the sidebar, the
     * phone bar, and whatever asks next - cost one set of queries per request and
     * not one each.
     */
    $badges = App\Support\NavigationBadges::forUser($user);
@endphp

{{--
    THE SIDEBAR IS FIXED, NOT STICKY.

    F4 in docs/STAFF_UI_AUDIT.md: the aside was `min-h-screen` inside a
    `flex min-h-screen` wrapper, so it was `position: static` and travelled with the
    document. On any page taller than the viewport - and most of them are - a reader
    who scrolled down had lost the navigation entirely, and the only thing left
    scrolling inside it (`overflow-y-auto` on the nav) was useless once the aside
    itself had left the screen.

    `fixed inset-y-0` pins it to the viewport for the whole scroll. `h-[100dvh]`
    is the belt to that braces: dvh is the dynamic viewport unit, so the sidebar
    matches the visible area on a phone with a collapsing browser chrome rather
    than the taller lvh and leaving a gap.

    The three children are then the whole design:
      - the logo block takes its natural height and never moves;
      - the nav is `flex-1 min-h-0 overflow-y-auto`, so an Admin's ten
        destinations scroll INSIDE the sidebar and the logo and user block stay put;
      - the user block takes its natural height and never moves.

    `min-h-0` on the nav is load-bearing, not decoration: a flex child defaults to
    `min-height: auto`, which refuses to shrink below its content and pushes the
    user block out of the viewport instead of letting the nav scroll.

    The document is the scroller. The main column takes a matching `md:pl-64` in
    layouts/app, so the two never overlap and only the content moves.

    Mobile and tablet are untouched: this aside is `hidden md:flex`, and the phone
    bottom bar further down this file is a separate, in-flow element.
--}}
<aside class="fixed inset-y-0 left-0 z-30 flex h-[100dvh] w-64 flex-col bg-navy text-white hidden md:flex">
    <div class="shrink-0 px-6 py-5 border-b border-white/10">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/Zone 1_logo.png') }}" alt="Barangay Linasin Seal"
                class="w-10 h-10 rounded-full ring-2 ring-sky-400/60 object-cover flex-shrink-0" />
            <div>
                <p class="font-bold leading-tight">Barangay Linasin</p>
                <p class="text-xs text-sky-300">Zone 1, Bangued, Abra</p>
            </div>
        </a>
    </div>

    {{-- flex-1 + min-h-0 + overflow-y-auto: an Admin's ten destinations scroll
         inside the sidebar while the logo above and the user block below stay
         put. See the note on the <aside> for why min-h-0 is not optional.

         ONE LOOP PER GROUP, AND A HEADING ONLY WHEN THERE IS MORE THAN ONE.

         M1/M2 in docs/STAFF_UI_AUDIT.md: availableSidebarGroupsFor() folded
         everything that was not 'main' into a single bucket, discarding the
         `group => 'admin'` that RoleNavigation declares on the Captain's four
         destinations, and this file then drew one hard-coded heading for it. The
         result was User Accounts, Sectors & Kagawad, Settings and Audit Logs
         sitting under a heading that said "My Account".

         Both halves are fixed. The buckets are separate, and a heading is drawn
         per bucket - but only when the page has more than one bucket, so a
         Secretary or a Kagawad (whose lists declare no 'admin' group) keeps the
         shape it always had: their destinations with no heading of their own,
         then My Account. Labelling a lone group "Workspace" would add a heading
         that distinguishes nothing.

         The link markup is written once, in a partial, because the badge and the
         current-page treatment have to be identical in all three places and three
         copies is how one of them ends up missing its badge. --}}
    <nav class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-1" aria-label="Portal">
        @php
            /*
             * The heading each group is drawn under, and which of them this role
             * actually has something to put there.
             *
             * ARRAY_FILTER_USE_BOTH, because the test is on the GROUP while the
             * element being filtered is the heading: array_intersect_key would
             * return the heading strings and hand them to the callback as if they
             * were the link arrays.
             */
            $headingFor = ['main' => 'Workspace', 'admin' => 'Administration', 'account' => 'My Account'];

            $buckets = array_filter(
                $headingFor,
                fn (string $heading, string $bucket): bool => ($groups[$bucket] ?? []) !== [],
                ARRAY_FILTER_USE_BOTH,
            );

            /*
             * Which groups get a heading, and which do not.
             *
             * An Admin's sidebar is genuinely divided - Workspace, Administration,
             * My Account - so all three are labelled, Workspace included. The test
             * for that is the admin bucket, not the number of buckets: a Secretary
             * and a Kagawad have two buckets too (main plus My Account), so counting
             * them would label their single run of workspace links "Workspace" - a
             * heading that distinguishes nothing, and a change to the grouping they
             * were supposed to keep.
             *
             * So for those two roles the workspace links stay unlabelled, as they
             * always were, and My Account keeps the heading it has always had.
             */
            $showHeadings = ($groups['admin'] ?? []) !== [];
        @endphp

        @foreach ($buckets as $bucket => $heading)
            {{--
                The group wrapper is always emitted, and only its border and its
                heading are conditional. Splitting the <div> itself across a @if on
                $loop->first / $loop->last would put an open tag in one branch and a
                close tag in another - the shape that produced the "managing is not
                defined" bug on the sectors page, where a <tbody> was closed outside
                the row that opened it.
            --}}
            <div @class([
                'mt-4 border-t border-white/10 pt-4' => ! $loop->first,
            ])>
                @if ($showHeadings || $bucket !== 'main')
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-sky-300/70">{{ $heading }}</p>
                @endif

                @foreach ($groups[$bucket] as $link)
                    @include('layouts.partials.sidebar-link', ['link' => $link, 'badges' => $badges])
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="shrink-0 px-6 py-4 border-t border-white/10 text-sm">
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

    THERE IS NO MOBILE DRAWER, and adding one is not on the table. The choice above
    was a bottom bar rather than a hamburger, so the two navigation surfaces in
    this file - this bar below md, the sidebar from md up - are the whole of it.
    Both draw the same badges from the same $badges, so a reader gets the same
    figures on a phone as on a desktop.
--}}
@php
    /*
     * The phone bar carries every destination the desktop sidebar does, in the
     * same order, minus the headings: a heading is a line of text the bar has no
     * room for, and the bar is a flat strip of icons either way.
     *
     * 'admin' is in this merge as well as 'main' and 'account'. It was left out
     * when the buckets were merged into one, so it was harmless then; now that
     * Administration is a bucket of its own, dropping it here would quietly take
     * the Captain's four destinations off the phone.
     *
     * PHP comment syntax, not a Blade one, because this is inside a @php block:
     * Blade strips its own comment markers only outside PHP, so a Blade comment
     * here reaches the parser as text and the page 500s.
     */
    $mobileLinks = array_merge($groups['main'], $groups['admin'], $groups['account']);
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
                            {{--
                                A pill on the icon rather than under the label: the
                                label is already two lines on a narrow phone and a
                                third would push the tap target around.

                                Same aria-label as the desktop badge and for the same
                                reason, with the number itself aria-hidden so it is
                                not announced twice.
                            --}}
                            <span class="absolute right-1/2 top-1 translate-x-5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-gold px-1 py-px text-2xs font-bold tabular-nums text-navy-900"
                                title="{{ $badges[$link['route']]['label'] }}"
                                aria-label="{{ $badges[$link['route']]['label'] }}">
                                <span aria-hidden="true">{{ App\Support\NavigationBadges::display($badges[$link['route']]['count']) }}</span>
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
