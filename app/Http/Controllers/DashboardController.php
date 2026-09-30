<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single post-sign-in destination.
 *
 * One route rather than four, because the question "where does this person go"
 * has one answer per role and the roles are known here. A resident lands on the
 * public home page, which is their home: the portal hangs off it, and it is the
 * finished public design. A staff member or Kagawad lands on their own
 * dashboard, because for them the portal is the product and the public pages
 * are somewhere they chose to visit.
 *
 * Note this route is behind auth + verified but carries no role middleware. It
 * has to be reachable by every role, including one whose own area is not yet
 * built - the role check belongs on the destination, not on the signpost.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // A user with no resident profile and a role that expects one is still
        // sent to the role's landing rather than to an error page: the profile
        // is created at registration, so this only happens to a hand-made
        // account, and refusing to sign anyone in is worse than landing them
        // somewhere with nothing to show.
        return redirect()->route(EnsureUserHasRole::landingFor($user));
    }
}
