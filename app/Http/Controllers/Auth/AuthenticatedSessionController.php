<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * A resident is sent to the public home page, which is their home: the
     * resident area hangs off it, and it is the finished public design. Staff and
     * a Kagawad are sent to /dashboard, which answers per role and is the signpost
     * the navbar and the e-mail verification links already point at.
     *
     * The default is role-dependent rather than one value for everybody. Sending a
     * resident through /dashboard as well would land them on the same page, but by
     * way of a route whose entire job is to forward them somewhere else - and the
     * resident's own pages, My Profile among them, sit on the other side of that
     * hop. Naming their destination here keeps "where does a resident go" in the
     * one place that decides it, and leaves the signpost for the roles that are
     * actually going somewhere else.
     *
     * intended() still wins when there is a remembered destination, so a guest who
     * was refused a protected page and then signed in still arrives where they were
     * originally headed rather than being dropped on the home page.
     *
     * hasVerifiedEmail() rather than the is_verified column, because that is the
     * exact test the "verified" middleware on /dashboard applies. An account that
     * has not confirmed its address still goes through the signpost: the home page
     * carries no such middleware, so naming it here directly would let an
     * unverified account walk past the verification prompt it is supposed to be
     * held at. Reading the same predicate the guard reads keeps the two agreeing.
     *
     * The role lives on the session, so this cannot be influenced by the request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        $default = $user->isResident() && $user->hasVerifiedEmail()
            ? route('home', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($default);
    }

    /**
     * Destroy an authenticated session.
     *
     * Redirects to the login screen rather than the home page. The person who
     * just logged out is not a visitor any more, and the home page would be an
     * odd place to land after signing out; the login screen also makes it
     * obvious that the session is gone, which is the whole point of having come
     * here.
     *
     * The three calls matter in this order. logout() drops the guard's record
     * of the user; invalidate() throws away the session and gives it a new id,
     * so a copy of the old session cookie is worthless; regenerateToken() then
     * replaces the CSRF token, so a form captured before the logout cannot be
     * replayed against the new session either.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
