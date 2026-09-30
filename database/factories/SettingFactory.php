<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The key is generated rather than fixed, because settings.key is UNIQUE and
     * a fixed key would make the second call in a test collide with the first.
     * `key` is also a reserved word in the schema, which is why the column is
     * quoted wherever it is written.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'setting_'.fake()->unique()->slug(2),
            'value' => fake()->sentence(3),
            'group' => fake()->randomElement(['header', 'contact', 'general']),
            'description' => null,
        ];
    }

    /**
     * A setting with a known key, for a test about a particular one.
     */
    public function withKey(string $key, ?string $value = null): static
    {
        return $this->state([
            'key' => $key,
            'value' => $value ?? fake()->sentence(3),
        ]);
    }
}
