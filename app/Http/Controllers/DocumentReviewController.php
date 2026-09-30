<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'queued');

        $query = DocumentRequest::with('user.residentProfile');

        if ($status === 'queued') {
            $query->whereIn('status', [
                DocumentRequest::STATUS_SUBMITTED,
                DocumentRequest::STATUS_UNDER_REVIEW,
            ]);
        } elseif (in_array($status, DocumentRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('staff.requests.index', [
            'status' => $status,
            'requests' => $query->latest()->paginate(12),
        ]);
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        abort_if(! in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_SECRETARY], true), 403);

        $documentRequest->load('user.residentProfile.sector');

        return view('staff.requests.show', ['documentRequest' => $documentRequest]);
    }

    public function approve(Request $request, DocumentRequest $documentRequest)
    {
        $documentRequest->update([
            'status' => DocumentRequest::STATUS_UNDER_REVIEW,
            'secretary_comment' => $request->input('secretary_comment'),
            'reviewed_at' => now(),
        ]);

        if ($request->input('ready', false) === '1' || $request->boolean('ready')) {
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_READY,
                'ready_at' => now(),
            ]);
        }

        return back()->with('success', 'Request approved and moved to under review.');
    }

    public function ready(DocumentRequest $documentRequest)
    {
        $documentRequest->update([
            'status' => DocumentRequest::STATUS_READY,
            'ready_at' => now(),
        ]);

        return back()->with('success', 'Document marked Ready for Pickup.');
    }

    public function claim(DocumentRequest $documentRequest)
    {
        $documentRequest->update([
            'status' => DocumentRequest::STATUS_COMPLETED,
            'claimed_at' => now(),
        ]);

        return back()->with('success', 'Document marked as completed/claimed.');
    }

    public function reject(Request $request, DocumentRequest $documentRequest)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $documentRequest->update([
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => $request->input('rejection_reason'),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Request rejected. The resident has been notified via their dashboard.');
    }

    public function verifyProfile(Request $request, ResidentProfile $profile)
    {
        $action = $request->input('action');

        if (! in_array($action, [
            ResidentProfile::VERIFICATION_VERIFIED,
            ResidentProfile::VERIFICATION_REJECTED,
        ], true)) {
            return back()->withErrors(['action' => 'Invalid verification action.']);
        }

        $profile->update([
            'verification_status' => $action,
            'verified_at' => $action === ResidentProfile::VERIFICATION_VERIFIED ? now() : null,
            'rejection_reason' => $action === ResidentProfile::VERIFICATION_REJECTED
                ? $request->input('rejection_reason')
                : null,
        ]);

        $user = $profile->user;
        if ($user !== null) {
            $user->update([
                'is_verified' => $action === ResidentProfile::VERIFICATION_VERIFIED,
                'assigned_sector_id' => $action === ResidentProfile::VERIFICATION_VERIFIED
                    ? $profile->sector_id
                    : null,
            ]);
        }

        return back()->with('success',
            $action === ResidentProfile::VERIFICATION_VERIFIED
                ? 'Profile verified and linked to the master list.'
                : 'Profile verification rejected with comment.');
    }

    public function profileQueue(Request $request): View
    {
        /*
         * Grouped. The two status clauses are joined with OR, and an ungrouped
         * where()->orWhere() leaks past any predicate added to the same query
         * later - so a filter applied below would return every profile of every
         * other status, not just the ones matching it. Grouping makes the OR
         * mean "pending OR verified" and nothing more.
         */
        $query = ResidentProfile::with('user', 'sector')
            ->where(function ($builder): void {
                $builder->where('verification_status', ResidentProfile::VERIFICATION_PENDING)
                    ->orWhere('verification_status', ResidentProfile::VERIFICATION_VERIFIED);
            });

        $status = $request->query('status');

        if (in_array($status, [
            ResidentProfile::VERIFICATION_PENDING,
            ResidentProfile::VERIFICATION_VERIFIED,
            ResidentProfile::VERIFICATION_REJECTED,
        ], true)) {
            $query->where('verification_status', $status);
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');

            $query->where(function ($builder) use ($escaped): void {
                $builder->where('household_number', 'like', '%'.$escaped.'%')
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', '%'.$escaped.'%')
                        ->orWhere('email', 'like', '%'.$escaped.'%'));
            });
        }

        return view('staff.verifications.index', [
            'profiles' => $query->latest()->paginate(12)->withQueryString(),
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * One profile, in full, for the Secretary to check before deciding.
     *
     * This adds no permission: it sits in the same role:admin,secretary group
     * as the queue, and the decision it leads to is the existing
     * verifyProfile() POST. It exists so the ID document, the details and the
     * approve/reject controls are on one screen, rather than spread across a
     * queue card, a pop-out image and a typed reason.
     */
    public function showProfile(Request $request, ResidentProfile $profile): View
    {
        abort_if(! in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_SECRETARY], true), 403);

        $profile->load(['user', 'sector']);

        return view('staff.verifications.show', [
            'profile' => $profile,
            'requests' => $profile->user?->documentRequests()->latest()->limit(5)->get() ?? collect(),
            'distributions' => $profile->household_number
                ? ReliefDistribution::with('batch')
                    ->where('household_number', $profile->household_number)
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect(),
        ]);
    }
}
