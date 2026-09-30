<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileVerificationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReliefBatchController;
use App\Http\Controllers\ReliefDistributionController;
use App\Http\Controllers\ResidentDashboardController;
use App\Http\Controllers\ResidentDocumentController;
use App\Http\Controllers\ResidentDocumentPdfController;
use App\Http\Controllers\ResidentIdPhotoController;
use App\Http\Controllers\ResidentOwnIdPhotoController;
use App\Http\Controllers\ResidentPortalController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransparencyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (no authentication required)
|--------------------------------------------------------------------------
*/
/*
| Each public content area is a standalone page reached by its own route and
| route name. Previously these were "#section" anchors on a single scrolling
| homepage, which meant a deep link could not address a section directly and
| the header could not mark the current destination. The home route now carries
| only the hero plus featured services and announcements; everything below has
| a dedicated page. Note the deliberate asymmetry: the public page is
| "announcements.index" while the staff CRUD area is "staff.announcements.index".
*/
Route::get('/', [PublicController::class, 'welcome'])->name('home');
Route::get('/services', [PublicController::class, 'services'])->name('services.index');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/announcements', [PublicController::class, 'announcements'])->name('announcements.index');
Route::get('/directory', [PublicController::class, 'directory'])->name('public.directory');

/*
|--------------------------------------------------------------------------
| Authenticated Public Lookups
|--------------------------------------------------------------------------
| Two of the public pages sit behind "auth" rather than living with the
| content pages above, because both of them read a record belonging to a
| household:
|
|   /track      - a document request, looked up by reference number
|   /transparency - a household's relief distribution records
|
| A guest sent to 'login' rather than bounced home, so they land one step
| from the thing they came for. The reference number and the household number
| are both short and guessable, so leaving either lookup open let anyone read
| another resident's records by trying numbers; 'auth' is the floor, and the
| per-user scoping in DocumentRequestController::track() is the other half.
|
| These use 'auth' alone and not 'verified': a resident who has signed up but
| not yet confirmed their email still has a legitimate reason to read their own
| request, and blocking them here would only strand them.
|
| The routes keep their names and paths, so the header, the footer and every
| page that links to them keep working - a guest simply arrives at the login
| screen instead of the page.
*/
Route::middleware(['auth', 'no-store-authenticated'])->group(function () {
    Route::get('/transparency', [TransparencyController::class, 'index'])->name('transparency.index');
    Route::get('/transparency/search', [TransparencyController::class, 'search'])->name('transparency.search');
    Route::get('/track', [DocumentRequestController::class, 'track'])->name('requests.track');
});

/*
|--------------------------------------------------------------------------
| Authenticated + Verified (all roles) -> role-based dashboard redirect
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'no-store-authenticated'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Resident area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:resident', 'no-store-authenticated'])->prefix('resident')->name('resident.')->group(function () {
    Route::get('/dashboard', [ResidentDashboardController::class, 'index'])->name('dashboard');

    // One-time profile verification
    Route::get('/verification', [ProfileVerificationController::class, 'create'])->name('verification');
    Route::post('/verification', [ProfileVerificationController::class, 'store'])->name('verification.store');
    Route::get('/verification/status', [ProfileVerificationController::class, 'status'])->name('verification.status');

    // Document requests
    Route::get('/requests', [DocumentRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [DocumentRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [DocumentRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{documentRequest}', [DocumentRequestController::class, 'show'])->name('requests.show');

    // The resident's own certificate. The staff PDF route is admin/secretary
    // only, so a resident needs their own door to the same rendered document.
    Route::get('/requests/{documentRequest}/pdf', ResidentDocumentPdfController::class)->name('requests.pdf');

    /*
     * The "My Profile" tabs. Each is a page rather than a tab of one page, so a
     * tab is a real URL that can be linked to, bookmarked and reloaded, and the
     * server decides which one is showing. The update handlers are the ones
     * that already existed - ProfileController for the account, and
     * ProfileVerificationController for the resident record - so no form here
     * posts to a second, parallel endpoint that could disagree with them.
     */
    // The resident looking at the ID they submitted. The staff version of this
    // file is role:admin,secretary, so the resident needs their own door to it.
    Route::get('/verification/id-photo', ResidentOwnIdPhotoController::class)->name('verification.id-photo');

    Route::get('/profile/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/password', [ResidentPortalController::class, 'changePassword'])->name('profile.change-password');

    // The resident's own pages, inside the portal. The sidebar links to these
    // rather than to the public routes, so a signed-in resident never gets
    // thrown out of the portal layout to read a notice or check relief status.
    Route::get('/directory', [ResidentPortalController::class, 'directory'])->name('directory');
    Route::get('/relief', [ResidentPortalController::class, 'relief'])->name('relief');
    Route::get('/profile', [ResidentPortalController::class, 'profile'])->name('profile');
    Route::get('/settings', [ResidentPortalController::class, 'settings'])->name('settings');
    Route::get('/documents', [ResidentDocumentController::class, 'index'])->name('documents');
    Route::get('/faq', [ResidentPortalController::class, 'faq'])->name('faq');
});

/*
|--------------------------------------------------------------------------
| Sector Kagawad area (Staff / Sector Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:kagawad', 'no-store-authenticated'])->prefix('kagawad')->name('kagawad.')->group(function () {
    Route::get('/dashboard', [ReliefDistributionController::class, 'index'])->name('dashboard');
    Route::get('/batches/{batch}/checklist/{sector}', [ReliefDistributionController::class, 'checklist'])->name('checklist');
    Route::post('/distributions/{distribution}/status', [ReliefDistributionController::class, 'markReceived'])->name('distributions.update');
});

/*
|--------------------------------------------------------------------------
| Staff area (Barangay Secretary + Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin,secretary', 'no-store-authenticated'])->prefix('staff')->name('staff.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Document review queue
    Route::get('/requests', [DocumentReviewController::class, 'index'])->name('requests.index');
    Route::get('/requests/{documentRequest}', [DocumentReviewController::class, 'show'])->name('requests.show');
    Route::post('/requests/{documentRequest}/approve', [DocumentReviewController::class, 'approve'])->name('requests.approve');
    Route::post('/requests/{documentRequest}/ready', [DocumentReviewController::class, 'ready'])->name('requests.ready');
    Route::post('/requests/{documentRequest}/claim', [DocumentReviewController::class, 'claim'])->name('requests.claim');
    Route::post('/requests/{documentRequest}/reject', [DocumentReviewController::class, 'reject'])->name('requests.reject');
    Route::get('/requests/{documentRequest}/pdf', DocumentPdfController::class)->name('requests.pdf');

    // Resident profile verification queue
    Route::get('/verifications', [DocumentReviewController::class, 'profileQueue'])->name('verifications.index');
    Route::get('/verifications/{profile}', [DocumentReviewController::class, 'showProfile'])->name('verifications.show');
    Route::get('/resident-verifications/{residentProfile}/id-photo', [ResidentIdPhotoController::class, 'show'])
        ->name('resident-verifications.id-photo');
    Route::post('/verifications/{profile}', [DocumentReviewController::class, 'verifyProfile'])->name('verifications.update');

    // Relief batches
    Route::get('/batches', [ReliefBatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/create', [ReliefBatchController::class, 'create'])->name('batches.create');
    Route::post('/batches', [ReliefBatchController::class, 'store'])->name('batches.store');
    Route::get('/batches/{batch}', [ReliefBatchController::class, 'show'])->name('batches.show');
    Route::post('/batches/{batch}/status', [ReliefBatchController::class, 'toggleStatus'])->name('batches.status');

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // System settings & kagawad assignment
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/assign-kagawad', [SettingController::class, 'assignKagawad'])->name('settings.assign-kagawad');

    // Sector management
    Route::get('/sectors', [SectorController::class, 'index'])->name('sectors.index');
    Route::post('/sectors', [SectorController::class, 'store'])->name('sectors.store');
});

// Breeze account/profile management (available to every authenticated user)
Route::middleware(['auth', 'verified', 'no-store-authenticated'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
