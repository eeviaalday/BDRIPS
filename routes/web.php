<?php

use App\Http\Controllers\PublicController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (no authentication required)
|--------------------------------------------------------------------------
*/
/*
| Each public content area is a standalone page reached by its own route and
| route name. These are the finished viewer-facing pages and are not being
| changed. Note the deliberate asymmetry: the public page is
| "announcements.index".
*/
Route::get('/', [PublicController::class, 'welcome'])->name('home');
Route::get('/services', [PublicController::class, 'services'])->name('services.index');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/announcements', [PublicController::class, 'announcements'])->name('announcements.index');
Route::get('/directory', [PublicController::class, 'directory'])->name('public.directory');

/*
|--------------------------------------------------------------------------
| Temporary Stubs For The Areas Being Rebuilt
|--------------------------------------------------------------------------
| The resident, kagawad and staff areas have been removed so the logged-in
| roles can be rebuilt from a clean base. The finished public pages still link
| into those areas, and their markup, link text and styling are not to change,
| so the route names they resolve stay defined here.
|
| Each of these answers the same two questions and nothing else: a guest is
| sent to the login screen, because that is one step from the thing they came
| for and it is where the original routes sent them, and a signed-in user is
| sent to the home page, because the destination has not been rebuilt yet and
| there is no honest intermediate page to show them.
|
| They carry no middleware on purpose. The branch below is the whole
| contract, and leaving it in one readable place is what makes these
| recognisable as scaffolding rather than as forgotten routes.
|
| TODO: rebuild - replace each stub with the real route, controller and
| authorization when the role-based areas are reinstated.
*/
$stub = fn (): RedirectResponse => Auth::user() !== null
    ? redirect()->route('home')
    : redirect()->route('login');

// TODO: rebuild - the role-based dashboard redirect.
Route::get('/dashboard', $stub)->name('dashboard');

// TODO: rebuild - the resident document request form.
Route::get('/resident/requests/create', $stub)->name('resident.requests.create');

// TODO: rebuild - resident verification status lookup.
Route::get('/resident/verification/status', $stub)->name('resident.verification.status');

// TODO: rebuild - the resident-facing relief transparency lookup.
Route::get('/transparency', $stub)->name('transparency.index');

// TODO: rebuild - the throttled household search behind transparency.
Route::get('/transparency/search', $stub)->name('transparency.search');

// TODO: rebuild - document request tracking by reference number.
Route::get('/track', $stub)->name('requests.track');

require __DIR__.'/auth.php';
