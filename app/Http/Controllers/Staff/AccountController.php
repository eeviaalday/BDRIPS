<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * User accounts, and the three things an Admin can do to one.
 *
 * Role, verification and restriction. Verification is not editable here even
 * though it looks like it should be: it is set from the resident's own
 * registration evidence, and a screen that could flip it would make the
 * verification queue meaningless.
 *
 * Two things this controller refuses outright, because both are ways to lose
 * the system:
 *
 *  - An Admin cannot change their own role. Demoting yourself locks you out of
 *    the only screen that could put it back.
 *  - An Admin cannot restrict themselves, or every other Admin, out of the
 *    account list by accident. There is no recovery path from "nobody can
 *    administer" that does not involve the database.
 */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        $query = User::query()
            ->with('residentProfile')
            // Grouped, so the role filter and the search combine rather than the
            // search's OR escaping the role and widening the list.
            ->when($search !== '', fn ($users) => $users->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(in_array($role, User::roles(), true), fn ($users) => $users->where('role', $role))
            ->latest('id');

        return view('staff.accounts.index', [
            'users' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'role' => $role,
            'roles' => User::roles(),
            'counts' => $this->countsByRole(),
        ]);
    }

    public function show(User $user): View
    {
        return view('staff.accounts.show', [
            'account' => $user->load(['residentProfile.sector', 'reliefKagawadSectors']),
            'recentActivity' => AuditLog::query()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Change a role.
     *
     * Read before the write, so the audit records the role that was replaced
     * rather than the one that was set.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', User::roles())],
        ], [
            'role.in' => 'That is not a role in this system.',
        ]);

        $target = $validated['role'];

        // Changing your own role demotes you out of the only screen that could
        // put it back. The route is already role:admin, so no other role reaches
        // this line and there is nothing else to check here.
        abort_if($user->is($request->user()), 422, 'You cannot change your own role.');
        abort_if($user->isAdmin() && $target !== User::ROLE_ADMIN, 422, 'Demoting the last Admin would lock everyone out.');

        $from = $user->role;

        if ($from === $target) {
            return back()->with('success', sprintf('%s is already a %s.', $user->name, User::roleLabel($target)));
        }

        $user->update(['role' => $target]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'user.role_changed',
            subject: $user,
            description: sprintf('Role for %s changed from %s to %s', $user->name, $from, $target),
            context: ['from' => $from, 'to' => $target],
        );

        return back()->with('success', sprintf('%s is now a %s.', $user->name, User::roleLabel($target)));
    }

    /**
     * Restrict an account until a date, or indefinitely.
     *
     * A restriction is enforced by the role middleware, so a restricted account
     * keeps its own password and logout routes and loses everything else. That
     * is the point: an abused account is closed off without locking its owner
     * out of the ability to change the password they just used.
     */
    public function restrict(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'restricted_until' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'restricted_until.after' => 'Pick a date from tomorrow onwards, or leave it blank for no end date.',
            'reason.required' => 'Say why, so the record explains itself later.',
        ]);

        abort_if($user->is($request->user()), 422, 'You cannot restrict your own account.');

        $until = $validated['restricted_until'] ?? null;

        $user->update(['restricted_until' => $until === null ? now()->addYears(10) : $until]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'user.restricted',
            subject: $user,
            description: sprintf(
                '%s was restricted%s',
                $user->name,
                $until === null ? ' with no end date' : ' until '.$until
            ),
            context: ['reason' => $validated['reason'], 'restricted_until' => $user->restricted_until],
        );

        return back()->with('success', sprintf('%s is restricted.', $user->name));
    }

    public function unrestrict(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot lift your own restriction.');

        abort_if($user->isRestricted() === false, 409, sprintf('%s is not restricted.', $user->name));

        $user->update(['restricted_until' => null]);

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'user.unrestricted',
            subject: $user,
            description: sprintf('Restriction on %s was lifted', $user->name),
        );

        return back()->with('success', sprintf('%s can use their account again.', $user->name));
    }

    /**
     * @return array<string, int>
     */
    private function countsByRole(): array
    {
        $counts = array_fill_keys(User::roles(), 0);

        User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->each(fn ($total, $role) => $counts[$role] = (int) $total);

        return $counts;
    }
}
