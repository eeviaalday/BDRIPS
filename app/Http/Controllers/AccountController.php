<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * My Account, for every role.
 *
 * One screen rather than a resident copy and a staff copy. A Secretary editing
 * their name and a resident editing their name are the same action on the same
 * two columns, and the previous version had a resident route and a staff route
 * rendering the same partial - so a fix to one was not a fix to the other.
 *
 * Role and verification status are deliberately NOT editable here. Role is the
 * Admin's to set, and verification is the Secretary's decision; an account able
 * to change either would be able to grant itself access.
 */
class AccountController extends Controller
{
    /**
     * The three tabs of a resident's profile, in the order they are shown.
     *
     * One list, because the tab bar, the redirects and the panel visibility all
     * need the same set. A tab the redirects do not know about is a tab nobody
     * can link to.
     *
     * @return list<string>
     */
    public const RESIDENT_TABS = ['profile', 'password', 'verification'];

    /**
     * Reduce a requested tab to one that exists.
     *
     * An absent or unrecognised value falls back to the first tab rather than
     * rendering nothing: a stale bookmark should land a resident on their profile
     * with a working page, not on an empty tab.
     */
    public static function normaliseTab(mixed $requested): string
    {
        $tab = is_string($requested) ? $requested : '';

        return in_array($tab, self::RESIDENT_TABS, true) ? $tab : self::RESIDENT_TABS[0];
    }

    /**
     * The account screen, in the layout that matches how this person travels.
     *
     * A resident arrives from the public top navbar, so theirs is wrapped in the
     * public layout. Staff and a Kagawad arrive from the staff sidebar, so theirs
     * keeps the sidebar beside it. The content is one shared partial either way,
     * so the two cannot drift.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view($user->isResident() ? 'account.edit' : 'account.edit-staff', [
            'user' => $user,
            'focus' => null,
        ]);
    }

    /**
     * The old "Change Password" address, kept working.
     *
     * A resident's password form is the second tab of one page now, so this URL
     * redirects rather than rendering a second copy of the screen. Staff keep
     * their own version of it, which is still one screen with everything on it,
     * so redirecting theirs would gain nothing.
     */
    public function password(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->isResident()) {
            return redirect()->route('account.edit', ['tab' => 'password']);
        }

        return view('account.edit-staff', [
            'user' => $user,
            'focus' => 'password',
        ]);
    }

    /**
     * Save the two fields an account owns.
     *
     * The email is unique and case-insensitive at the database level, so the
     * uniqueness check is a query against the other rows rather than a regex -
     * two addresses differing only in case are the same mailbox, and the
     * database is what will decide.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ]);

        $changedAddress = $user->email !== $validated['email'];

        $user->fill($validated);

        // A new address has not been proven, so the account stops being verified
        // for sign-in until it is. Leaving email_verified_at set would let
        // somebody claim an address they do not own and keep the session.
        if ($changedAddress) {
            $user->email_verified_at = null;
        }

        $user->save();

        app(AuditLogger::class)->log(
            actor: $user,
            action: 'account.updated',
            description: sprintf('%s updated their account details', $user->name),
            context: $changedAddress ? ['email_changed' => true] : null,
        );

        return redirect()
            ->route('account.edit')
            ->with('success', $changedAddress
                ? 'Your details were saved. Confirm your new email address to sign in again.'
                : 'Your details were saved.');
    }
}
