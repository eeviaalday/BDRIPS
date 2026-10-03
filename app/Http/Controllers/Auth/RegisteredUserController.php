<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * The wizard is one form with five client-side steps, so everything it can
     * render has to be here: the sector list for the address step, and the
     * closed lists for the dropdowns.
     */
    public function create(): View
    {
        return view('auth.register', [
            'sectors' => $this->sectors(),
        ]);
    }

    /**
     * The barangay's puroks, in the order the Secretary reads them out.
     *
     * Ordered by id rather than by name, because the ids run in the intended
     * sequence: the migration that replaced the "Sector 1" placeholders mapped
     * them onto the first four entries in order and inserted the rest behind
     * them. Sorting alphabetically would scramble that - the list is meant to
     * read "Kasabaan, Petron, INC, Benesa", not "Benesa, Capitulacion, INC".
     *
     * @return Collection<int, Sector>
     */
    private function sectors()
    {
        return Sector::orderBy('id')->get();
    }

    /**
     * Handle an incoming registration request.
     *
     * Creates the account and the resident profile together. The profile is
     * written here rather than at the verification screen so the resident types
     * their address and household number once, and attaches the valid ID they
     * uploaded during signup, leaving the Secretary to review one submission
     * rather than a set of details retyped at a second form.
     *
     * The confirmation email is sent after the rows are committed, never inside
     * the transaction. It is delivered synchronously - there is no queue worker
     * on this deployment - and a synchronous SMTP call is a network request to a
     * server this code does not control, on credentials an operator can get
     * wrong. Sending it inside the transaction would mean one wrong App
     * Password rolls back a registration that had already succeeded: the
     * resident would be shown a 500 for a problem with our mail settings, and
     * the account they had just created would be gone with it. So the rows
     * commit first and the notice is best-effort afterwards.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $account = $request->accountAttributes();
        $account['password'] = Hash::make($request->string('password')->value());

        // The private disk, not the public one. A resident's ID is a personal
        // document: it is served only through an authorised endpoint, never
        // from a guessable public URL.
        $idPath = $request->file('valid_id_photo')->store('id-photos', 'local');

        try {
            $user = DB::transaction(function () use ($request, $account, $idPath): User {
                $user = User::create($account);

                $profile = $request->profileAttributes();
                $profile['id_photo_path'] = $idPath;

                $user->residentProfile()->create($profile);

                Auth::login($user);

                return $user;
            });
        } catch (Throwable $e) {
            // The file was written before the rows, so a failure here would
            // otherwise leave a resident's ID on disk belonging to no account,
            // where nothing would ever clean it up.
            Storage::disk('local')->delete($idPath);

            throw $e;
        }

        // Through /dashboard, which answers per role. An unverified resident
        // arrives at the confirmation page instead, which offers the resend.
        //
        // When the notice did not go out, go straight there instead. Two hops
        // would be the wrong shape for the message: a flashed session value is
        // discarded after the next request, so a warning set here would be gone
        // by the time /dashboard handed the resident on to the confirmation
        // page - leaving them reading "the link we just emailed to you" about an
        // email that was never sent.
        if (! $this->sendVerificationNotice($user)) {
            return redirect(route('verification.notice', absolute: false))
                ->with('status', 'verification-notice-failed');
        }

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Send the "confirm your email address" notice, and survive failing to.
     *
     * A failure here is an operator's mail settings, not the resident's
     * mistake, so it must not become their error page. The account is already
     * committed and already signed in; all that is missing is a link they can
     * press, and the confirmation page has a resend button for exactly this.
     *
     * Logged with the user id and the exception, because "the resident says no
     * email arrived" is otherwise unanswerable from the log - there is nothing
     * in the request that would have recorded a transport failure.
     *
     * @return bool Whether the notice was handed to the mailer. Only a false
     *              return is quiet: the exception is caught and logged, never
     *              rethrown, because a resident's registration does not fail
     *              because our mail settings do.
     *
     * @throws Throwable Never. The catch is the point of the method.
     */
    private function sendVerificationNotice(User $user): bool
    {
        try {
            event(new Registered($user));

            return true;
        } catch (Throwable $e) {
            Log::error('Email verification notice could not be sent after registration; '
                .'the resident can request another from the confirmation page.', [
                    'user_id' => $user->getKey(),
                    'exception' => $e,
                ]);

            return false;
        }
    }
}
