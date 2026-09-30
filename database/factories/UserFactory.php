<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'resident',
            'is_verified' => false,
            'abandoned_count' => 0,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function resident(): static
    {
        return $this->state(['role' => 'resident']);
    }

    public function kagawad(): static
    {
        return $this->state(['role' => 'kagawad', 'is_verified' => true]);
    }

    public function secretary(): static
    {
        return $this->state(['role' => 'secretary', 'is_verified' => true]);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin', 'is_verified' => true]);
    }
}
