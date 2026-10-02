<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_verified',
    'restricted_until',
    // Set together by the archive action and cleared together by the restore
    // action. Never filled from a request on its own: the four columns describe
    // one state, and a form that wrote archive_reason without archived_at would
    // leave an account that reads as live but carries an audit trail for an
    // archive that never happened.
    'archived_at',
    'archived_by',
    'archive_reason',
    'archive_note',
    'assigned_sector_id',
    // Captured by the registration wizard, before any account exists.
    'date_of_birth',
    'gender',
    'civil_status',
    'nationality',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SECRETARY = 'secretary';

    public const ROLE_KAGAWAD = 'kagawad';

    public const ROLE_RESIDENT = 'resident';

    /**
     * Shown to an archived resident who tries to sign in, and to one who is
     * signed in when the archive happens.
     *
     * One constant for both, because the two answers must not drift. A message at
     * the login form and a message at the middleware that is worded differently
     * is two facts about the same state, and the resident who hits both in one
     * afternoon concludes one of them is a bug.
     *
     * Deliberately unlike the restriction message. A restriction is temporary and
     * punitive and the resident is the one who caused it; an archive is a staff
     * decision about a household and there is nothing for the resident to do
     * about it, so this one names the office rather than the reader's own conduct.
     */
    public const ARCHIVED_LOGIN_MESSAGE = 'This account has been archived. Please contact the barangay office.';

    /**
     * Why an account was archived, as the exact strings stored in
     * users.archive_reason.
     *
     * A closed list, validated on the way in and rendered as a select. "Why is
     * this household archived?" is the first question anybody asks of this table,
     * and the answer has to be countable - "Moved away: 34, Deceased: 6" is a
     * report, and a free-text note is not. The full sentence goes in
     * archive_note, which is why this list can stay this short.
     *
     * @return list<string>
     */
    public static function archiveReasons(): array
    {
        return [
            'Moved away',
            'Deceased',
            'Duplicate record',
            'Other',
        ];
    }

    /**
     * Every role, in descending order of access.
     *
     * One list, because the role picker, the role filter, the validation rule
     * and the count-by-role summary all need the same set. A second list is a
     * second chance to offer a role the middleware does not accept.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_SECRETARY,
            self::ROLE_KAGAWAD,
            self::ROLE_RESIDENT,
        ];
    }

    /**
     * The roles that may reach the staff area, as a role: middleware string.
     *
     * Returned rather than written at each call site, so the staff area and the
     * staff navigation cannot end up listing different roles.
     */
    public static function staffRoles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_SECRETARY];
    }

    /**
     * A human label for a role, for screens that show one.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_SECRETARY => 'Secretary',
            self::ROLE_KAGAWAD => 'Kagawad',
            self::ROLE_RESIDENT => 'Resident',
            default => ucfirst($role),
        };
    }

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'abandoned_count' => 'integer',
            'restricted_until' => 'datetime',
            'archived_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Restrict to one or more roles.
     *
     * @param  Builder<User>  $query
     * @param  string|list<string>  $roles
     * @return Builder<User>
     */
    public function scopeRole(Builder $query, string|array $roles): Builder
    {
        return $query->whereIn('role', (array) $roles);
    }

    /**
     * Restrict to residents, which is every account with a resident profile.
     *
     * The staff screens list residents through the profile rather than through
     * the role column, so "is a resident" is answered by the profile's existence
     * and not by trusting users.role to agree with it.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeResidents(Builder $query): Builder
    {
        return $query->whereHas('residentProfile');
    }

    /**
     * Only archived accounts.
     *
     * The scope every "Archived" filter and every exclusion in the app goes
     * through, so the meaning of archived is written down once. A query that
     * wanted archived records by any other route - `whereNotNull('archived_at')`
     * typed at a call site - would be the same predicate with none of the reason.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Only accounts that are not archived.
     *
     * The default shape of every staff list, and the reason a half-finished
     * archive cannot leak: an account is on the working lists until
     * archived_at is set, so anything that forgets to call archived() is showing
     * live accounts rather than dead ones. That is the safer direction to fail.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function residentProfile(): HasOne
    {
        return $this->hasOne(ResidentProfile::class);
    }

    /**
     * The staff member who archived this account.
     *
     * Null for every account that was never archived, and null again if that
     * staff member's own account is later deleted - the foreign key is
     * nullOnDelete, so an archive outlives its author rather than vanishing with
     * them.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'archived_by');
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function reliefLogs(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class, 'logged_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function assignedSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'assigned_sector_id');
    }

    /**
     * The sectors this kagawad is assigned to.
     *
     * The inverse of Sector::kagawad(). The column and its foreign key are
     * kept, so the relationship stays navigable in both directions.
     */
    public function reliefKagawadSectors(): HasMany
    {
        return $this->hasMany(Sector::class, 'kagawad_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSecretary(): bool
    {
        return $this->role === self::ROLE_SECRETARY;
    }

    public function isKagawad(): bool
    {
        return $this->role === self::ROLE_KAGAWAD;
    }

    public function isResident(): bool
    {
        return $this->role === self::ROLE_RESIDENT;
    }

    /**
     * Whether this account holds any of the given roles.
     *
     * The single place the role check happens, so the middleware and any view
     * that hides a control are reading the same rule.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Whether this account may open the staff area at all.
     *
     * Secretary and Admin share every staff route; the per-page Admin-only split
     * is done with role:admin on those routes rather than with a second role
     * check here, so there is one place that says who is staff.
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isSecretary();
    }

    /**
     * The sector ids this Kagawad is responsible for.
     *
     * Resolved from sectors.kagawad_user_id, which is the authorisation anchor
     * for the whole Kagawad area. An empty list is a legitimate state: a Kagawad
     * who has not been assigned a sector yet must see nothing rather than
     * everything, so callers get [] and scope to it.
     *
     * @return list<int>
     */
    public function assignedSectorIds(): array
    {
        return Sector::where('kagawad_user_id', $this->getKey())
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public function hasAssignedSector(): bool
    {
        return $this->assignedSectorIds() !== [];
    }

    /**
     * Whether an abuse restriction is currently in force.
     *
     * Frozen by an Admin, either until a date or indefinitely. Enforced by the
     * role middleware, so a restricted account cannot reach its own area while
     * keeping the routes to change its own password and sign out.
     */
    public function isRestricted(): bool
    {
        return $this->restricted_until !== null && $this->restricted_until->isFuture();
    }

    /**
     * Whether this account has been archived.
     *
     * The single definition of the state, checked in three places: the sign-in
     * attempt, the role middleware that ends a live session, and every staff
     * query that decides whether the account still belongs on a working list.
     *
     * Distinct from isRestricted() and not a subtype of it. A restriction is a
     * temporary, punitive freeze with an end date and a way back for the account
     * holder; an archive is a staff decision about a household that has left,
     * has no end date, and is undone by a Secretary rather than by the resident.
     * They are separate columns because they are separate facts, and treating an
     * archive as a very long restriction would let a restricted_until that ran
     * out quietly un-archive somebody.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
