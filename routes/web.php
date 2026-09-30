<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kagawad\DashboardController as KagawadDashboardController;
use App\Http\Controllers\Kagawad\ReliefController as KagawadReliefController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Resident\DocumentRequestController as ResidentDocumentRequestController;
use App\Http\Controllers\Resident\ReliefController as ResidentReliefController;
use App\Http\Controllers\Resident\VerificationController as ResidentVerificationController;
use App\Http\Controllers\Staff\AccountController as StaffAccountController;
use App\Http\Controllers\Staff\AnnouncementController as StaffAnnouncementController;
use App\Http\Controllers\Staff\AuditLogController as StaffAuditLogController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\DocumentReviewController as StaffDocumentReviewController;
use App\Http\Controllers\Staff\ReliefBatchController as StaffReliefBatchController;
use App\Http\Controllers\Staff\ResidentDirectoryController as StaffResidentDirectoryController;
use App\Http\Controllers\Staff\ResidentVerificationController as StaffResidentVerificationController;
use App\Http\Controllers\Staff\SectorController as StaffSectorController;
use App\Http\Controllers\Staff\SettingController as StaffSettingController;
use App\Http\Controllers\Staff\SystemStatusController as StaffSystemStatusController;
use App\Models\DocumentRequest;
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
    ->prefix('kagawad')
    ->name('kagawad.')
    ->group(function (): void {
        Route::get('/dashboard', KagawadDashboardController::class)->name('dashboard');

        /*
         * The Kagawad's own checklist. The distribution is a bare id rather than
         * a bound model because the row has to be looked up inside the
         * Kagawad's sectors - a global binding would resolve it, and the scope
         * would then have to be re-checked, which is the shape that turns into
         * a 403 that confirms the row exists.
         */
        Route::get('/relief', [KagawadReliefController::class, 'index'])->name('relief');
        Route::get('/relief/{reliefBatch}', [KagawadReliefController::class, 'show'])->name('relief.show');
        Route::post('/relief/{reliefBatch}/received/{distribution}', [KagawadReliefController::class, 'received'])
            ->name('relief.received');
    });

/*
|--------------------------------------------------------------------------
| Resident Area
|--------------------------------------------------------------------------
| Pending and verified residents share one area and one UI; what differs is
| what the server will accept, and that is enforced in the form request rather
| than by hiding links. A pending resident can still read everything they already
| have, which is the point of a hold rather than a lockout.
|
| The only per-state difference on the navigation is the verification banner in
| the public layout, which says what is locked and links to the page that
| unlocks it.
*/
Route::middleware(['auth', 'verified', 'role:resident', 'no-store-authenticated'])
    ->prefix('resident')
    ->name('resident.')
    ->group(function () {
        // Document requests.
        Route::get('/requests', [ResidentDocumentRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [ResidentDocumentRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [ResidentDocumentRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/{documentRequest}', [ResidentDocumentRequestController::class, 'show'])->name('requests.show');

        // Relief: the resident's own household only.
        Route::get('/relief', [ResidentReliefController::class, 'index'])->name('relief');

        // Verification: what state they are in, and the details a Secretary
        // needs in order to decide.
        Route::get('/verification', [ResidentVerificationController::class, 'edit'])->name('verification');
        Route::patch('/verification', [ResidentVerificationController::class, 'update'])->name('verification.update');
    });

/*
 * Resolving a document request.
 *
 * Route::bind() is global, so this one closure serves both areas and branches on
 * which one is asking. The branch is not optional: a resident may only reach
 * their own, while a Secretary and an Admin read every request in the barangay.
 * A single resident-scoped resolver would 404 the staff desk on every row, and a
 * single plain resolver would let a resident read a neighbour's by editing the
 * id in the URL.
 *
 * The resident branch scopes through the owner rather than resolving and then
 * checking, so another resident's request is not found at all and 404s through
 * exactly the same path as an id that never existed - which is what "a wrong
 * reference and another user's reference give identical output" needs.
 */
Route::bind('documentRequest', function (string $value) {
    $user = request()->user();

    abort_if($user === null, 403);

    $request_ = DocumentRequest::query();

    if (! request()->routeIs('staff.*')) {
        $request_->whereBelongsTo($user);
    }

    $documentRequest = $request_->find($value);

    abort_if($documentRequest === null, 404);

    return $documentRequest;
});

Route::middleware(['auth', 'verified', 'role:admin,secretary', 'no-store-authenticated'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        // Resident verification. The queue, the record behind a queue entry, the
        // uploaded ID, and the two decisions.
        Route::get('/verifications', [StaffResidentVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{profile}', [StaffResidentVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{profile}/approve', [StaffResidentVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{profile}/reject', [StaffResidentVerificationController::class, 'reject'])->name('verifications.reject');
        Route::get('/resident-verifications/{profile}/id-photo', [StaffResidentVerificationController::class, 'idPhoto'])
            ->name('resident-verifications.id-photo');

        // Residents directory.
        Route::get('/residents', [StaffResidentDirectoryController::class, 'index'])->name('residents.index');
        Route::get('/residents/{profile}', [StaffResidentDirectoryController::class, 'show'])->name('residents.show');

        // Document requests.
        Route::get('/requests', [StaffDocumentReviewController::class, 'index'])->name('requests.index');
        Route::get('/requests/{documentRequest}', [StaffDocumentReviewController::class, 'show'])->name('requests.show');
        Route::post('/requests/{documentRequest}/review', [StaffDocumentReviewController::class, 'review'])->name('requests.review');
        Route::post('/requests/{documentRequest}/ready', [StaffDocumentReviewController::class, 'ready'])->name('requests.ready');
        Route::post('/requests/{documentRequest}/claim', [StaffDocumentReviewController::class, 'claim'])->name('requests.claim');
        Route::post('/requests/{documentRequest}/reject', [StaffDocumentReviewController::class, 'reject'])->name('requests.reject');

        // Relief batches.
        Route::get('/batches', [StaffReliefBatchController::class, 'index'])->name('batches.index');
        Route::get('/batches/create', [StaffReliefBatchController::class, 'create'])->name('batches.create');
        Route::post('/batches', [StaffReliefBatchController::class, 'store'])->name('batches.store');
        Route::get('/batches/{reliefBatch}', [StaffReliefBatchController::class, 'show'])->name('batches.show');
        Route::post('/batches/{reliefBatch}/close', [StaffReliefBatchController::class, 'close'])->name('batches.close');
        Route::post('/batches/{reliefBatch}/reopen', [StaffReliefBatchController::class, 'reopen'])->name('batches.reopen');

        // Announcements.
        Route::get('/announcements', [StaffAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [StaffAnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [StaffAnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [StaffAnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [StaffAnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('/announcements/{announcement}/publish', [StaffAnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('/announcements/{announcement}/pin', [StaffAnnouncementController::class, 'pin'])->name('announcements.pin');
        Route::post('/announcements/{announcement}/unpin', [StaffAnnouncementController::class, 'unpin'])->name('announcements.unpin');
        Route::delete('/announcements/{announcement}', [StaffAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin Area
|--------------------------------------------------------------------------
| Split out of the staff group above rather than guarded case by case inside it.
| The staff routes carry role:admin,secretary; these carry role:admin alone, so
| an Admin-only screen cannot be reached by a Secretary even if somebody later
| forgets to hide the link.
|
| The audit log is deliberately the only one of these with no write route. A
| trail an Admin can edit is not a trail.
*/

Route::middleware(['auth', 'verified', 'role:admin', 'no-store-authenticated'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function (): void {
        // User accounts.
        Route::get('/accounts', [StaffAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/{user}', [StaffAccountController::class, 'show'])->name('accounts.show');
        Route::put('/accounts/{user}/role', [StaffAccountController::class, 'updateRole'])->name('accounts.role');
        Route::post('/accounts/{user}/restrict', [StaffAccountController::class, 'restrict'])->name('accounts.restrict');
        Route::post('/accounts/{user}/unrestrict', [StaffAccountController::class, 'unrestrict'])->name('accounts.unrestrict');

        // Sectors and their Kagawad.
        Route::get('/sectors', [StaffSectorController::class, 'index'])->name('sectors.index');
        Route::post('/sectors', [StaffSectorController::class, 'store'])->name('sectors.store');
        Route::put('/sectors/{sector}', [StaffSectorController::class, 'update'])->name('sectors.update');
        Route::put('/sectors/{sector}/kagawad', [StaffSectorController::class, 'assignKagawad'])->name('sectors.kagawad');
        Route::delete('/sectors/{sector}', [StaffSectorController::class, 'destroy'])->name('sectors.destroy');

        // Settings, and the audit log. Read-only.
        Route::get('/settings', [StaffSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [StaffSettingController::class, 'update'])->name('settings.update');
        Route::get('/audit', [StaffAuditLogController::class, 'index'])->name('audit.index');

        // System status.
        Route::get('/status', [StaffSystemStatusController::class, 'index'])->name('status.index');
    });

require __DIR__.'/auth.php';
