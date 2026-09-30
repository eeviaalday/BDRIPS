<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'category' => Announcement::CATEGORY_GENERAL,
            'image_path' => null,
            'published_at' => null,
            'created_by_user_id' => null,
        ];
    }

    /**
     * Indicate that the announcement belongs to a configured category, which
     * is what supplies its card tag and its fallback photograph.
     */
    public function category(string $category): static
    {
        return $this->state([
            'category' => $category,
        ]);
    }

    /**
     * Indicate that the announcement carries a photograph of its own rather
     * than borrowing the one configured for its category.
     */
    public function withImage(string $imagePath): static
    {
        return $this->state([
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Indicate that the announcement is published and visible to the public.
     */
    public function published(): static
    {
        return $this->state([
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the announcement is still a draft.
     */
    public function unpublished(): static
    {
        return $this->state([
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the announcement is pinned to the top of the public page.
     */
    public function pinned(): static
    {
        return $this->state([
            'pinned_at' => now(),
        ]);
    }

    /**
     * Indicate that the announcement is published and pinned, which is the
     * only combination the public page can actually show a pinned notice in.
     */
    public function publishedAndPinned(): static
    {
        return $this->published()->pinned();
    }
}
