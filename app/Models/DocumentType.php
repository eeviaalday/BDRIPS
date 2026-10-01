<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A document the barangay can issue.
 *
 * The list is data rather than configuration now, so the Admin settings screen
 * can add, rename, reorder and retire a type. Retiring flips is_active instead
 * of deleting, so requests already filed under the type keep resolving.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $tone
 * @property bool $is_active
 * @property int $sort_order
 */
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'icon',
        'tone',
        'is_active',
        'sort_order',
    ];

    public function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /**
     * The types a resident may actually choose from.
     *
     * Ordered by sort_order then id, so the catalogue reads the way the
     * barangay arranged it and two types sharing an order keep a stable
     * sequence between requests rather than shuffling per query.
     *
     * @param  Builder<DocumentType>  $query
     * @return Builder<DocumentType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
