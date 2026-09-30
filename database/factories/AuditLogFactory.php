<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->secretary(),
            'user_name' => fake()->name(),
            'action' => fake()->randomElement(array_keys(AuditLog::actions())),
            'description' => fake()->sentence(10),
            'context' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function action(string $action): static
    {
        return $this->state(['action' => $action]);
    }

    /**
     * An entry whose account has since been deleted, so the name snapshot is the
     * only record of who did it.
     */
    public function orphaned(?string $name = null): static
    {
        return $this->state([
            'user_id' => null,
            'user_name' => $name ?? fake()->name(),
        ]);
    }
}
