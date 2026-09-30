<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory;

    protected $fillable = [
        'sector_name',
        'purok_name',
        'kagawad_user_id',
    ];

    /**
     * The kagawad assigned to this sector, if the barangay has appointed one.
     *
     * Read by the public officials directory. The column and its foreign key
     * are kept; the staff screen that used to set it goes with the staff area.
     */
    public function kagawad(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kagawad_user_id');
    }

    public function residents(): HasMany
    {
        return $this->hasMany(ResidentProfile::class);
    }

    /**
     * The relief checklist rows filed under this sector.
     *
     * The reverse of ReliefDistribution::sector(), and the reason a sector cannot
     * be deleted while it has any: these rows are the record of who was given
     * goods, and they are keyed on sector_id rather than on a resident.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class);
    }

    /**
     * Whether anything at all still points at this sector.
     *
     * Checked before a delete so the refusal can name what is in the way, rather
     * than the database refusing with a foreign key and the operator guessing.
     */
    public function isEmpty(): bool
    {
        return $this->residents()->doesntExist() && $this->distributions()->doesntExist();
    }
}
