<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kagawad\DashboardController as KagawadDashboardController;
use App\Http\Controllers\Kagawad\ReliefController as KagawadReliefController;
use App\Http\Controllers\Kagawad\ResidentController as KagawadResidentController;
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
use App\Http\Controllers\TransparencyController;
use App\Models\DocumentRequest;
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

/*
| The officials used to have a page of their own at /directory. Its content is
| now part of the About page's officials section, which is the only place a
| reader now has to go, and every internal link points there directly.
|
| The address itself stays, as a permanent redirect, because it is in old
| bookmarks, in search results and in messages people have already sent. The
| route name stays too, so anything still generating route('public.directory')
| resolves rather than throwing.
*/
Route::permanentRedirect('/directory', '/about#officials')->name('public.directory');

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
| Public Pages That Answer A Lookup
|--------------------------------------------------------------------------
| These three used to be stubs that redirected, because the header, footer and
| services catalogue link into them and their route names had to stay defined.
| All three are now real.
|
| Two of them are lookups against a small guessable key - a household number, a
| four-digit reference - so both are throttled and both answer only about the one
| thing asked about. Neither reveals a name, an address, or anything that would
| turn a lookup into a way of browsing the neighbourhood.
*/

// The relief transparency board: what has gone out, and whether your own
// household is on a list. Public, because a resident should not have to sign in
// to find out whether they are expected at a handout.
Route::get('/transparency', [TransparencyController::class, 'index'])->name('transparency.index');

/*
| The public tracking page is gone. It took a reference number and a household
| number and answered for anybody who had both, and a resident now reads their own
| requests from their own Document Requests page, where the same information sits
| behind a login and beside everything else about the request.
|
| The address stays as a permanent redirect rather than a 404. It is in old
| bookmarks, in search results and in messages people have already sent, and a
| resident who has one of those in their history is a resident who should end up
| looking at their own requests rather than at an error. The route name stays too,
| so the links that are not being repointed in this change keep resolving.
|
| This does mean a resident whose email address is not yet confirmed loses the one
| path they had to checking a request: this page needed no login, and the Document
| Requests routes do. That was the trade for closing a public lookup, and it is
| recorded here so it is a decision on the record rather than a surprise.
*/
Route::permanentRedirect('/track', '/resident/requests')->name('requests.track');

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

        /*
         * The residents of this Kagawad's own sectors, read-only.
         *
         * This is the one place a Kagawad sees people rather than households. It is
         * GET-only and has no POST, PUT, PATCH or DELETE behind it, so "no editing"
         * is a property of the route table rather than a rule that could be
         * forgotten inside a controller.
         *
         * The sector scope is applied in the query (see KagawadSectorResidents), not
         * by filtering a full list in the view, and an unassigned Kagawad is given
         * the empty state rather than an unscoped list. Both matter: a scope applied
         * in Blade would still put every other sector's household number in the page
         * source, which is the thing this page exists to prevent.
         */
        Route::get('/residents', [KagawadResidentController::class, 'index'])->name('residents.index');
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
        Route::post('/requests/{documentRequest}/cancel', [ResidentDocumentRequestController::class, 'cancel'])->name('requests.cancel');

        // Relief: the resident's own household only.
        Route::get('/relief', [ResidentReliefController::class, 'index'])->name('relief');

        // Verification: what state they are in, and the details a Secretary
        // needs in order to decide. The status page is read only and separate
        // from the form, because "what is happening to my registration" should
        // not mean reading past a form to find out.
        Route::get('/verification', [ResidentVerificationController::class, 'edit'])->name('verification');
        Route::get('/verification/status', [ResidentVerificationController::class, 'status'])->name('verification.status');
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
        //
        // The two routes below are staff-ONLY in a second sense as well as the
        // middleware's: they write the resident's own identity and address
        // columns. Nothing here touches email, password, role, restriction or the
        // verification status - those belong to the account area and to the
        // verification flow, and putting them behind this form would let a data
        // correction look like a decision.
        Route::get('/residents', [StaffResidentDirectoryController::class, 'index'])->name('residents.index');
        Route::get('/residents/{profile}', [StaffResidentDirectoryController::class, 'show'])->name('residents.show');
        Route::get('/residents/{profile}/edit', [StaffResidentDirectoryController::class, 'edit'])->name('residents.edit');
        Route::put('/residents/{profile}', [StaffResidentDirectoryController::class, 'update'])->name('residents.update');

        // Archiving and restoring.
        //
        // POST, not PUT, and not DELETE. Both actions write four columns on an
        // account, and neither removes anything: there is no hard delete of a
        // resident anywhere in this application, and a DELETE verb here would
        // invite the first one.
        //
        // POST for both is also the honest shape for restore. Archive and restore
        // are not two writes of one resource - they are two separate decisions
        // with their own dialogs, their own audit entries and their own
        // preconditions, and only one of them applies at a time. The restore route
        // answers 409 on a household that is not archived, which is the same answer
        // the archive route gives for one that already is, so neither can be reached
        // by a replayed form.
        Route::post('/residents/{profile}/archive', [StaffResidentDirectoryController::class, 'archive'])->name('residents.archive');
        Route::post('/residents/{profile}/restore', [StaffResidentDirectoryController::class, 'restore'])->name('residents.restore');

        // Document requests.
        Route::get('/requests', [StaffDocumentReviewController::class, 'index'])->name('requests.index');
        Route::get('/requests/{documentRequest}', [StaffDocumentReviewController::class, 'show'])->name('requests.show');
        Route::post('/requests/{documentRequest}/review', [StaffDocumentReviewController::class, 'review'])->name('requests.review');
        Route::post('/requests/{documentRequest}/ready', [StaffDocumentReviewController::class, 'ready'])->name('requests.ready');
        Route::post('/requests/{documentRequest}/claim', [StaffDocumentReviewController::class, 'claim'])->name('requests.claim');
        Route::post('/requests/{documentRequest}/reject', [StaffDocumentReviewController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{documentRequest}/unclaim', [StaffDocumentReviewController::class, 'unclaim'])->name('requests.unclaim');
        Route::post('/requests/{documentRequest}/excuse', [StaffDocumentReviewController::class, 'excuse'])->name('requests.excuse');

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
    });

require __DIR__.'/auth.php';
