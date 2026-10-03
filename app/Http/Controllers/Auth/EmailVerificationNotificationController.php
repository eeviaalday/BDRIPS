<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     *
     * The second attempt at the same message, so it carries the same reasoning
     * as the first: a transport failure here is an operator's mail settings, and
     * the resident pressing this button is doing exactly what we asked them to
     * do. Letting it raise would answer their correct action with a 500, on the
     * one screen that exists to get them unstuck - so the failure is reported
     * as a status on the same page, next to the button, and logged with the
     * account it belongs to.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('status', 'verification-link-sent');
        } catch (Throwable $e) {
            Log::error('Email verification notice could not be resent; '
                .'the resident was left on the confirmation page to try again.', [
                    'user_id' => $request->user()->getKey(),
                    'exception' => $e,
                ]);

            return back()->with('status', 'verification-notice-failed');
        }
    }
}
