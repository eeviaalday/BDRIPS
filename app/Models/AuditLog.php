<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded action. Append only.
 *
 * There is no updated_at and nothing in the application updates a row, so the
 * absence is deliberate rather than an oversight. A trail that can be edited is
 * not a trail.
 *
 * user_id is nullable and nullOnDelete, and user_name is stored alongside it, so
 * an entry still reads correctly after the person it describes is deleted. That
 * is the whole reason both columns exist.
 *
 * @property string $action
 * @property string $description
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * The only timestamp, so the model opts out of Eloquent's updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'context',
        'ip_address',
        'user_agent',
    ];

    public function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): ?Model
    {
        if ($this->subject_type === null || ! class_exists($this->subject_type)) {
            return null;
        }

        return $this->subject_type::find($this->subject_id);
    }

    /**
     * A stable list of the verbs the log uses, so the filter on the admin screen
     * is a closed set rather than whatever happens to be in the column.
     *
     * @return array<string, string>
     */
    public static function actions(): array
    {
        return [
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'account.updated' => 'Account details updated',
            'resident.verified' => 'Resident verified',
            'resident.rejected' => 'Resident rejected',
            'resident.id_viewed' => 'Resident ID viewed',
            'resident.updated' => 'Resident details updated',
            'resident.archived' => 'Resident archived',
            'resident.restored' => 'Resident restored',
            'request.approved' => 'Request approved',
            'request.submitted' => 'Request submitted',
            'request.under_review' => 'Request taken for review',
            'request.ready' => 'Document marked ready',
            'request.claimed' => 'Document collected',
            'request.rejected' => 'Request rejected',
            'request.unclaimed' => 'Request marked unclaimed',
            'request.excused' => 'Strike excused',
            'request.cancelled' => 'Request cancelled by resident',
            'request.pdf_downloaded' => 'Digital copy downloaded',
            'request.pdf_emailed' => 'Digital copy emailed',
            'relief.batch_created' => 'Relief batch created',
            'relief.batch_closed' => 'Relief batch closed',
            'relief.batch_reopened' => 'Relief batch reopened',
            'relief.received' => 'Relief marked received',
            'relief.corrected' => 'Relief entry corrected',
            'announcement.created' => 'Announcement created',
            'announcement.updated' => 'Announcement updated',
            'announcement.published' => 'Announcement published',
            'announcement.unpublished' => 'Announcement unpublished',
            'announcement.scheduled' => 'Announcement scheduled',
            'announcement.pinned' => 'Announcement pinned',
            'announcement.unpinned' => 'Announcement unpinned',
            'announcement.deleted' => 'Announcement deleted',
            'user.role_changed' => 'Role changed',
            'user.restricted' => 'Account restricted',
            'user.unrestricted' => 'Restriction lifted',
            'settings.updated' => 'Settings updated',
            'sector.kagawad_assigned' => 'Kagawad assigned to sector',
        ];
    }

    public function actionLabel(): string
    {
        return static::actions()[$this->action] ?? $this->action;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeAction(Builder $query, ?string $action): Builder
    {
        return $action === null || $action === '' ? $query : $query->where('action', $action);
    }
}
