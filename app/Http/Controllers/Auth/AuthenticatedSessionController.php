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
     * Every role is sent to /dashboard, which answers per role: a resident to
     * the public home page, staff and a Kagawad to their own dashboard. The role
     * lives on the session, so this cannot be influenced by the request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
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
