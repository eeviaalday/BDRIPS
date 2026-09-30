<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail.
 *
 * Every state change in the application goes through here rather than through a
 * model observer, for one reason: a call site reads as "record that I did this"
 * next to the thing it did, so a developer adding a new action is in the place
 * where they would think to log it. An observer would log most of it and quietly
 * miss the rest.
 *
 * The IP address and user agent come from the request when there is one, so a
 * console or queued context logs NULL rather than inventing a value.
 *
 * A logging failure never breaks the action that was being logged. An audit
 * table that is unavailable is bad, but a Secretary being unable to approve a
 * resident because the log could not be written is worse, so the write is
 * wrapped and the failure reported rather than thrown.
 */
class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * Record one action.
     *
     * @param  array<string, mixed>|null  $context  before/after values, or the
     *                                              reason for a decision
     */
    public function log(
        User $actor,
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $context = null,
    ): ?AuditLog {
        try {
            return AuditLog::create([
                'user_id' => $actor->getKey(),
                // Snapshotted so the entry still reads correctly if the account
                // is later deleted; the foreign key would leave a blank.
                'user_name' => $actor->name,
                'action' => $action,
                'subject_type' => $subject === null ? null : $subject::class,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'context' => $context,
                'ip_address' => $this->request?->ip(),
                'user_agent' => $this->request === null
                    ? null
                    : mb_substr((string) $this->request->userAgent(), 0, 512),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Record a sign-in or sign-out.
     *
     * Separate from log() only because the actor is read from the session rather
     * than passed in: at the moment a sign-out is recorded there is no longer an
     * authenticated user to pass.
     */
    public function logAuth(User $user, string $action): ?AuditLog
    {
        return $this->log(
            actor: $user,
            action: $action,
            description: $action === 'login'
                ? sprintf('%s signed in', $user->name)
                : sprintf('%s signed out', $user->name),
        );
    }

    /**
     * The actor for the current request, for the rare call site that has no user
     * to hand because it is not acting on someone's behalf.
     */
    public function actor(): ?User
    {
        return Auth::user();
    }
}
