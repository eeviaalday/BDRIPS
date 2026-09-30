<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
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
        if ($user->isRestricted() && ! $request->routeIs('profile.*', 'password.*', 'logout')) {
            abort(403, 'This account is temporarily restricted. Please contact the barangay office.');
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
