<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kagawad\DashboardController as KagawadDashboardController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (no authentication required)
|--------------------------------------------------------------------------
| These are the finished viewer-facing pages and are not being changed.
*/
Route::get('/', [PublicController::class, 'welcome'])->name('home');
Route::get('/services', [PublicController::class, 'services'])->name('services.index');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/announcements', [PublicController::class, 'announcements'])->name('announcements.index');
Route::get('/directory', [PublicController::class, 'directory'])->name('public.directory');

/*
|--------------------------------------------------------------------------
| Where Each Role Lands
|--------------------------------------------------------------------------
| One route, answering "where does this person go" from the role on their
| session. A resident gets the public home page, which is their home; staff and
| a Kagawad get their own dashboard.
|
| No role middleware here on purpose: this is the signpost, and the guard belongs
| on the destination rather than on the arrow. A role whose area does not exist
| yet still has to be able to sign in and be sent somewhere real.
*/
Route::middleware(['auth', 'verified', 'no-store-authenticated'])
    ->get('/dashboard', DashboardController::class)
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| My Account (every role)
|--------------------------------------------------------------------------
| Available to a resident, a Kagawad, a Secretary and an Admin alike, because
| all four edit the same two columns. Role and verification status are not here
| on purpose: an account that could change its own role could grant itself
| access to anything.
|
| No role middleware, for the same reason as above. The restricted-account check
| lives in the role middleware, which this route deliberately does not use, so a
| frozen account can still reach the screen where it would change its own
| password and sign out.
*/
Route::middleware(['auth', 'verified', 'no-store-authenticated'])->group(function () {
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('/account/password', [AccountController::class, 'password'])->name('account.password');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
});

/*
|--------------------------------------------------------------------------
| Temporary Stubs For Pages Still Being Rebuilt
|--------------------------------------------------------------------------
| The finished public pages link into these destinations - the header, the
| footer, the services catalogue and the homepage's quick-services grid - and
| their markup, link text and styling are not to change. So the route names
| they resolve have to stay defined, or those pages throw rather than render.
|
| Each answers one question and nothing else: a guest is sent to the login
| screen, which is one step from the thing they came for, and a signed-in user
| to the home page, because the destination does not exist yet and there is no
| honest intermediate page to show them.
|
| They carry no middleware on purpose. The branch is the whole contract, and
| leaving it in one readable place is what marks these as scaffolding rather
| than as forgotten routes.
|
| TODO: rebuild - replace each with the real route and its authorization. The
| resident equivalents are listed in App\Support\RoleNavigation and will appear
| in the navigation automatically once their routes exist.
*/
$stub = fn (): RedirectResponse => Auth::user() !== null
    ? redirect()->route('home')
    : redirect()->route('login');

// TODO: rebuild - the resident document request form.
Route::get('/resident/requests/create', $stub)->name('resident.requests.create');

// TODO: rebuild - resident verification status lookup.
Route::get('/resident/verification/status', $stub)->name('resident.verification.status');

// TODO: rebuild - the public relief transparency board.
Route::get('/transparency', $stub)->name('transparency.index');

// TODO: rebuild - the throttled household search behind the transparency board.
Route::get('/transparency/search', $stub)->name('transparency.search');

// TODO: rebuild - document request tracking by reference number.
Route::get('/track', $stub)->name('requests.track');

/*
|--------------------------------------------------------------------------
| Landing Pages For The Two Portal Roles
|--------------------------------------------------------------------------
| /dashboard redirects here, so these have to exist or a staff member or a
| Kagawad would sign in to a route that does not. They are the landing page and
| nothing more: the rest of each area arrives in later phases, and the sidebar
| picks up each destination automatically as its route is registered.
|
| role:admin,secretary on the staff one rather than two separate pages, because
| Secretary and Admin genuinely share this page. The per-page Admin-only split
| is applied to the routes they do not share.
*/
Route::middleware(['auth', 'verified', 'no-store-authenticated', 'role:admin,secretary'])
    ->get('/staff/dashboard', StaffDashboardController::class)
    ->name('staff.dashboard');

Route::middleware(['auth', 'verified', 'no-store-authenticated', 'role:kagawad'])
    ->get('/kagawad/dashboard', KagawadDashboardController::class)
    ->name('kagawad.dashboard');

require __DIR__.'/auth.php';
