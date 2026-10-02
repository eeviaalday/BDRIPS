{{--
    One sidebar link, with its count badge.

    Included by layouts/navigation for every destination in every bucket, which is
    the point: the Workspace, Administration and My Account groups all draw their
    links through here, so the current-page treatment and the badge cannot be right
    in one group and wrong in another. Three copies of this markup is how the
    Captain's Sectors badge came to be missing for a whole task - the loop that drew
    the account group was written separately and forgot it.

    $link    one entry from RoleNavigation::sidebarFor()
    $badges  App\Support\NavigationBadges::forUser(), or [] for no badges
--}}
@php
    $isCurrent = App\Support\RoleNavigation::isCurrent($link);
    $badge = $badges[$link['route']] ?? null;
@endphp
<a href="{{ route($link['route']) }}"
    @if ($isCurrent) aria-current="page" @endif
    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
        {{ $isCurrent ? 'bg-sky-400 text-navy font-semibold' : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
    <x-icon :name="$link['icon']" class="w-5 h-5 flex-shrink-0" />
    <span class="min-w-0 truncate">{{ $link['label'] }}</span>

    @if ($badge !== null)
        {{--
            aria-label, because a bare "3" read aloud is not a sentence, and the
            visible number is only a cue for a sighted reader. The label says what
            the number is of.

            The number itself is aria-hidden: without that a screen reader is told
            "3 pending verifications" and then "3" again.
        --}}
        <span class="ml-auto inline-flex min-w-[1.5rem] flex-shrink-0 items-center justify-center rounded-full bg-gold px-1.5 py-0.5 text-2xs font-bold tabular-nums text-navy-900"
            title="{{ $badge['label'] }}"
            aria-label="{{ $badge['label'] }}">
            <span aria-hidden="true">{{ App\Support\NavigationBadges::display($badge['count']) }}</span>
        </span>
    @endif
</a>
