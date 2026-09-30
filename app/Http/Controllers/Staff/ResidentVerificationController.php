<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationDecisionRequest;
use App\Models\ResidentProfile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The resident verification queue: the desk where a Secretary checks a
 * registration against the master list and approves or rejects it.
 *
 * The queue is a status predicate and a search predicate combined. An ungrouped
 * OR between them would let the search term match every profile regardless of
 * status, so the two are combined inside one nested where - which is what
 * keeps "pending" and "anything with a name like Ana" from becoming "everything".
 */
class ResidentVerificationController extends Controller
{
    /**
     * The queue, oldest submission first.
     *
     * Search covers the three things a Secretary would be given: a name, a
     * household number, and a sector.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', ResidentProfile::VERIFICATION_PENDING);

        $query = ResidentProfile::query()
            ->with(['user', 'sector'])
            ->where(function ($profiles) use ($search): void {
                if ($search === '') {
                    return;
                }

                $profiles->where(function ($inner) use ($search): void {
                    $inner->where('household_number', 'like', "%{$search}%")
                        ->orWhere('national_or_voter_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                in_array($status, array_keys(ResidentProfile::verificationStates()), true),
                fn ($profiles) => $profiles->where('verification_status', $status)
            )
            ->oldest('created_at');

        return view('staff.verifications.index', [
            'profiles' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'states' => ResidentProfile::verificationStates(),
            'pendingCount' => ResidentProfile::query()
                ->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
                ->count(),
        ]);
    }

    /**
     * One profile, with everything a decision needs.
     */
    public function show(ResidentProfile $profile): View
    {
        return view('staff.verifications.show', [
            'profile' => $profile->load(['user', 'sector']),
        ]);
    }

    /**
     * Approve a verification.
     */
    public function approve(VerificationDecisionRequest $request, ResidentProfile $profile): RedirectResponse
    {
        $this->decide($request, $profile, false);

        return redirect()
            ->route('staff.verifications.index')
            ->with('success', $profile->user->name.' was verified.');
    }

    /**
     * Reject a verification, with a reason the resident will read.
     */
    public function reject(VerificationDecisionRequest $request, ResidentProfile $profile): RedirectResponse
    {
        $this->decide($request, $profile, true);

        return redirect()
            ->route('staff.verifications.index')
            ->with('success', $profile->user->name.' was rejected and can now correct their details.');
    }

    /**
     * Serve the ID a resident uploaded.
     *
     * From the private disk, through an authorised route, never from a guessable
     * public URL. The path is never echoed: the response is the file or a 404, so
     * a member of staff who guesses a filename learns nothing, and a resident's
     * storage layout is not exposed by the page that shows the picture.
     *
     * An inline disposition, because the point is for a Secretary to look at it
     * in the browser. A file that downloads instead is one extra click on every
     * review, and the access control is the route's job either way.
     */
    public function idPhoto(Request $request, ResidentProfile $profile): Response
    {
        $path = $profile->id_photo_path;

        abort_if($path === null || $path === '', 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $contents = Storage::disk('local')->get($path);

        abort_if($contents === null, 404);

        // Guessed from the stored extension, with a fixed allowlist. The extension
        // is whatever the resident uploaded, and the mimes rule already limits it
        // to jpeg, png and pdf - but a browser will act on the header it is
        // given, so it is derived from the file rather than trusted from the
        // request.
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $type = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'resident.id_viewed',
            subject: $profile,
            description: sprintf('Viewed the ID document for %s', $profile->user->name),
        );

        return response($contents, 200, [
            'Content-Type' => $type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The one place a verification decision is written.
     *
     * Both actions go through here so the two cannot drift: an approval and a
     * rejection differ in a status and a reason, nothing else. In particular the
     * account flag and the profile status are always written together - a profile
     * marked verified beside an account still marked unverified would leave the
     * resident looking locked in one place and open in another.
     */
    private function decide(
        VerificationDecisionRequest $request,
        ResidentProfile $profile,
        bool $reject
    ): void {
        $user = $profile->user;

        $profile->update([
            'verification_status' => $reject
                ? ResidentProfile::VERIFICATION_REJECTED
                : ResidentProfile::VERIFICATION_VERIFIED,
            'rejection_reason' => $reject ? $request->input('rejection_reason') : null,
            'verified_at' => $reject ? null : now(),
        ]);

        $user->update([
            'is_verified' => ! $reject,
            'assigned_sector_id' => $reject ? $user->assigned_sector_id : $profile->sector_id,
        ]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: $reject ? 'resident.rejected' : 'resident.verified',
            subject: $profile,
            description: sprintf(
                '%s %s',
                $user->name,
                $reject ? 'was rejected' : 'was verified'
            ),
            context: $reject
                ? ['reason' => $request->input('rejection_reason')]
                : ['sector_id' => $profile->sector_id],
        );
    }
}
