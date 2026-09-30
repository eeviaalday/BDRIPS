{{--
    The My Profile tab bar.

    Real tabs with real semantics: a tablist holding three tabs, each pointing at
    its own URL, each with aria-selected, and the panel they share carries
    aria-labelledby back to the selected one. A tab that is a <div> with a click
    handler would show the right words and expose nothing to a screen reader.

    The three URLs are the shared account page with ?tab=, not three separate
    pages. Using ?tab= rather than a fragment means a tab can be sent to somebody
    else and survives a reload, which a #hash also does but which then collides
    with the page's own scroll anchors.

    Only the selected panel's content is rendered. Rendering all three and hiding
    two with CSS would put a password form in the DOM while the visitor is on the
    Edit Profile tab.
--}}
@php
    use App\Http\Controllers\AccountController;

    $profileUser = auth()->user();
    $current = AccountController::normaliseTab(request()->query('tab'));

    $tabs = [
        'profile' => [
            'label' => 'Edit Profile',
            'hint' => 'Name and email',
        ],
        'password' => [
            'label' => 'Change Password',
            'hint' => 'Sign-in password',
        ],
        'verification' => [
            'label' => 'Verification Status',
            'hint' => 'Where you stand',
        ],
    ];
@endphp

<div role="tablist" aria-label="My Profile sections"
    class="flex overflow-x-auto rounded-xl border border-navy-100 bg-white p-1 shadow-card">
    @foreach ($tabs as $key => $tab)
        <a role="tab"
            id="tab-{{ $key }}"
            href="{{ route('account.edit', ['tab' => $key]) }}"
            @if ($current === $key) aria-selected="true" @else aria-selected="false" @endif
            @if ($current === $key) aria-controls="panel-{{ $key }}" @endif
            @class([
                'flex-1 whitespace-nowrap rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-400',
                'bg-navy text-white' => $current === $key,
                'text-navy-700 hover:bg-navy-50' => $current !== $key,
            ])>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
