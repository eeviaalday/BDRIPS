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
            DB::transaction(function () use ($request, $account, $idPath): void {
                $user = User::create($account);

                $profile = $request->profileAttributes();
                $profile['id_photo_path'] = $idPath;

                $user->residentProfile()->create($profile);

                event(new Registered($user));

                Auth::login($user);
            });
        } catch (Throwable $e) {
            // The file was written before the rows, so a failure here would
            // otherwise leave a resident's ID on disk belonging to no account,
            // where nothing would ever clean it up.
            Storage::disk('local')->delete($idPath);

            throw $e;
        }

        // Through /dashboard, which answers per role.
        return redirect(route('dashboard', absolute: false));
    }
}
