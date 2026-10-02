<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the given roles.
 *
 * Usage: ->middleware('role:admin,secretary')
 *
 * Two behaviours worth stating, because both were asked for explicitly:
 *
 * 1. A guest is sent to the login screen rather than refused. The area they were
 *    heading for needs an account, so login is one step from where they were
 *    going instead of a dead end.
 *
 * 2. A signed-in user in the WRONG role is refused with a 403, not quietly
 *    redirected. Redirecting would hide the fact that the page exists and would
 *    leave a Secretary wondering why their link does nothing; a 403 is the
 *    honest answer to "you may not see this".
 *
 * The role comes from the session via the authenticated user, never from the
 * request, so it cannot be spoofed with a query string or a form field.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to view this page.');
        }

        // A restricted account keeps its role but loses its access, so the check
        // sits here rather than in each controller: forgetting it in one action
        // would be enough to defeat the whole restriction.
        //
        // It is allowed to keep the routes where it could get itself out again -
        // change its password, read its own account, and sign out. Forgetting
        // those is how a frozen account whose password it has forgotten becomes
        // permanently stuck.
        //
        // The account routes deliberately carry no role middleware, so this check
        // does not currently run for them. They are listed anyway, so that adding
        // role: to those routes later cannot quietly strand a restricted account.
        if ($user->isRestricted() && ! $request->routeIs('account.*', 'password.*', 'logout')) {
            abort(403, 'This account is temporarily restricted. Please contact the barangay office.');
        }

        /*
         * An archived account loses its session on its next request.
         *
         * This is the live-session half of the archive. The sign-in half lives in
         * LoginRequest::authenticate(), which is the only place that runs before
         * an account exists in the session - this middleware never sees a guest,
         * so without that check a resident archived while signed in would carry
         * on browsing until they happened to hit a route guarded by role:.
         *
         * Signing the account out rather than aborting, and redirecting to login
         * rather than 403, is deliberate on both counts:
         *
         *  - abort() would leave the session cookie standing. The next request
         *    would fail the same way, but the account would still be "signed in"
         *    to every page that is not behind this middleware, and nothing would
         *    have been cleaned up.
         *  - A 403 tells the reader this page is forbidden. The true answer is
         *    different and more useful: the account is closed, here is why, and
         *    here is who can reopen it. That is the message they get.
         *
         * Regenerating the session token is part of logging out rather than an
         * extra precaution. The archived account's session id is no longer a
         * credential worth keeping valid, and logout() already invalidates it.
         */
        if ($user->isArchived()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => User::ARCHIVED_LOGIN_MESSAGE,
            ]);
        }

        return $next($request);
    }

    /**
     * The landing page for a role, used after sign-in and by the dashboard
     * route.
     *
     * Residents go to the public home page rather than a portal: the finished
     * public design is their home, and the portal hangs off it. Staff go to the
     * staff dashboard and a Kagawad to the sector dashboard, because for them
     * the portal IS the product.
     */
    public static function landingFor(User $user): string
    {
        return match (true) {
            $user->isKagawad() => 'kagawad.dashboard',
            $user->isAdmin(), $user->isSecretary() => 'staff.dashboard',
            default => 'home',
        };
    }
}
