<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * Number of notices the homepage features. Also the number the demo
     * seeder tops the table up to, so a fresh install fills the grid and a
     * populated one is left alone.
     */
    public const FEATURED_ON_HOMEPAGE = 3;

    /**
     * Category used when a notice is saved without one.
     */
    public const CATEGORY_GENERAL = 'general';

    /**
     * Disk holding the photographs staff attach to a notice. Public, because
     * the photograph is part of the public page rather than resident data.
     */
    public const IMAGE_DISK = 'public';

    protected $fillable = [
        'title',
        'body',
        'category',
        'location',
        'image_path',
        'published_at',
        'pinned_at',
        'created_by_user_id',
    ];

    public function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The office a notice is published by, for the card's byline.
     *
     * Falls back to the Captain's office when the notice has no author on
     * record, which is the case for anything seeded rather than written
     * through the staff screen.
     */
    public function authorLabel(): string
    {
        return $this->createdBy?->name ?: 'Office of the Captain';
    }

    /**
     * How long the notice takes to read, at the usual 200 words a minute,
     * rounded to the nearest minute and never less than one.
     */
    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));

        return max(1, (int) round($words / 200));
    }

    /**
     * A plain-text excerpt, trimmed to a whole word.
     */
    public function excerpt(int $length = 180): string
    {
        $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body)) ?? '');

        if (mb_strlen($body) <= $length) {
            return $body;
        }

        $clipped = mb_substr($body, 0, $length);
        $lastSpace = mb_strrpos($clipped, ' ');

        return rtrim($lastSpace !== false ? mb_substr($clipped, 0, $lastSpace) : $clipped, ' ,.;:-').'…';
    }

    /**
     * The configured categories, as key => label, for a category picker.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return array_map(
            fn (array $category): string => $category['label'],
            config('bdrpis.announcement_categories', []),
        );
    }

    /**
     * The record's category key, falling back to the general category so a
     * notice saved without one still resolves to a label and a photograph.
     */
    public function categoryKey(): string
    {
        $category = (string) $this->category;

        return array_key_exists($category, config('bdrpis.announcement_categories', []))
            ? $category
            : self::CATEGORY_GENERAL;
    }

    /**
     * The human-readable category label, e.g. "Health".
     */
    public function categoryLabel(): string
    {
        return config('bdrpis.announcement_categories.'.$this->categoryKey().'.label', 'General');
    }

    /**
     * The card photograph: the notice's own upload when it has one, otherwise
     * the photograph configured for its category.
     */
    public function imageUrl(): string
    {
        if (filled($this->image_path)) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(self::IMAGE_DISK);

            return $disk->url($this->image_path);
        }

        return asset($this->fallbackImagePath());
    }

    /**
     * Description of the rendered photograph, so a card is never presented
     * with an image the reader cannot account for.
     */
    public function imageAlt(): string
    {
        if (filled($this->image_path)) {
            return $this->title;
        }

        return config('bdrpis.announcement_categories.'.$this->categoryKey().'.alt', 'Barangay Linasin');
    }

    /**
     * Whether this notice carries a photograph of its own rather than a
     * borrowed one, which the staff form uses to show what is currently saved.
     */
    public function hasImage(): bool
    {
        return filled($this->image_path);
    }

    /**
     * Whether this notice is pinned to the top of the public page.
     */
    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * Resolve a category's photograph through the public image maps, exactly as
     * the Blade templates do, so a file missing from public/ falls back to the
     * neutral artwork instead of rendering a broken image.
     *
     * Every path through this method ends on a community scene. The barangay
     * hall is the one photograph an announcement must never borrow: it is the
     * place a notice is published from, not what the notice is about.
     */
    private function fallbackImagePath(): string
    {
        $key = config('bdrpis.announcement_categories.'.$this->categoryKey().'.image')
            ?: config('bdrpis.announcement_default_image');

        $photograph = config('bdrpis.images.'.$key);

        if (filled($photograph) && is_file(public_path($photograph))) {
            return $photograph;
        }

        return config('bdrpis.image_fallbacks.'.$key)
            ?: config('bdrpis.image_fallbacks.'.config('bdrpis.announcement_default_image'));
    }
}
