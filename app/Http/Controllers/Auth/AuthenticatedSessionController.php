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
     * Every role lands on the home page for now, because the role-based areas
     * are being rebuilt and the home page is the one page that is finished.
     *
     * TODO: rebuild - point each role at its own dashboard again.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('home', absolute: false));
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
