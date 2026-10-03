<?php

namespace App\Models;

use Database\Factories\DocumentDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One digital copy being sent to one resident.
 *
 * An event, not a state: two downloads are two rows. That is the whole reason
 * this is a table rather than a `downloaded_at` column on the request, and it is
 * why there is no soft delete - a row that can be hidden is not a record of
 * something that happened. The foreign key still cascades, so a removed request
 * takes its deliveries with it rather than leaving a table naming somebody who
 * asked for their data to go.
 *
 * Nothing about the password is stored. See the migration for why, and for the
 * owner password that is generated per download and thrown away with it.
 *
 * @property int $id
 * @property int $document_request_id
 * @property int $user_id
 */
class DocumentDelivery extends Model
{
    /** @use HasFactory<DocumentDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'user_id',
        'channel',
    ];

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
