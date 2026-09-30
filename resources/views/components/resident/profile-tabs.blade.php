@props([
    // Which tab is showing: 'account' | 'password' | 'verification'
    'current',
])

@php
    /*
     * The My Profile tabs.

     * Each tab is its own page and its own URL rather than a JS toggle, so a
     * tab can be linked to, bookmarked and reloaded, and the server decides
     * which one is showing. The tab bar is therefore navigation, not state.

     * The three tabs are the three things a resident can actually do to their
     * own account here: change the name and email, change the password, and see
     * where the resident verification has got to. Nothing is listed that is not
     * a page behind this bar.
     */
    $tabs = [
        'account' => [
            'label' => 'Edit Profile',
            'route' => 'resident.profile.edit',
            'active' => $current === 'account',
        ],
        'password' => [
            'label' => 'Change Password',
            'route' => 'resident.profile.change-password',
            'active' => $current === 'password',
        ],
        'verification' => [
            'label' => 'Verification Status',
            'route' => 'resident.verification.status',
            'active' => $current === 'verification',
        ],
    ];
@endphp

<nav aria-label="Profile sections" class="mb-6 overflow-x-auto">
    <div class="inline-flex min-w-full gap-1 rounded-xl border border-navy-100 bg-white p-1 shadow-card">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}"
                @if ($tab['active']) aria-current="page" @endif
                class="flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400
                       {{ $tab['active'] ? 'bg-navy text-white' : 'text-navy-700 hover:bg-navy-50' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
