<?php

namespace App\Models;

use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory;

    public const TYPE_CLEARANCE = 'barangay_clearance';

    public const TYPE_INDIGENCY = 'certificate_of_indigency';

    public const TYPE_JOBSEEKER = 'first_time_jobseeker_certificate';

    public const TYPE_RESIDENCY = 'barangay_residency_certificate';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_READY = 'ready_for_pickup';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_ABANDONED,
    ];

    /**
     * The document types a resident may actually ask for.
     *
     * This is the list, and store() validates 'in:' against the same four, so
     * the chooser in the "New Request" modal cannot offer a type the server
     * would then refuse. A resident with no profile is also a locked state, but
     * that is the controller's job to enforce, not the form's.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_CLEARANCE,
        self::TYPE_INDIGENCY,
        self::TYPE_JOBSEEKER,
        self::TYPE_RESIDENCY,
    ];

    /**
     * The statuses in which a request still occupies one of the three open
     * slots. store() counts on exactly these three, so the "n / 3" a resident
     * reads is the same number the server counts.
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_READY,
    ];

    /**
     * The label for a document type, without needing an instance.
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_CLEARANCE => 'Barangay Clearance',
            self::TYPE_INDIGENCY => 'Certificate of Indigency',
            self::TYPE_JOBSEEKER => 'First-Time Jobseeker Certificate',
            self::TYPE_RESIDENCY => 'Barangay Residency Certificate',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    protected $fillable = [
        'user_id',
        'reference_number',
        'document_type',
        'purpose',
        'status',
        'fee_amount',
        'secretary_comment',
        'rejection_reason',
        'reviewed_at',
        'ready_at',
        'claimed_at',
    ];

    public function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'ready_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateReferenceNumber(): string
    {
        do {
            $reference = 'BRGY-'.now()->year.'-'.strtoupper((string) mt_rand(1000, 9999));
        } while (static::where('reference_number', $reference)->exists());

        return $reference;
    }

    public function documentTypeLabel(): string
    {
        return static::typeLabel($this->document_type);
    }
}
