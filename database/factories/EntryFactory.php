<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(['debugging', 'architecture', 'testing', 'deployment', 'security']),
            'tags' => fake()->randomElements(['php', 'laravel', 'pest', 'docker', 'redis', 'mysql'], rand(1, 4)),
            'module' => fake()->randomElement(['auth', 'api', 'frontend', 'backend', 'database']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'confidence' => fake()->numberBetween(0, 100),
            'source' => fake()->optional()->url(),
            'ticket' => fake()->optional()->regexify('[A-Z]{3,4}-[0-9]{3,5}'),
            'files' => fake()->optional()->randomElements([
                'app/Models/User.php',
                'app/Http/Controllers/AuthController.php',
                'config/app.php',
                'routes/api.php',
            ], rand(1, 3)),
            'repo' => fake()->optional()->randomElement(['conduit-ui/knowledge', 'laravel/framework']),
            'branch' => fake()->optional()->randomElement(['main', 'develop', 'feature/auth']),
            'commit' => fake()->optional()->sha1(),
            'author' => fake()->optional()->name(),
            'status' => fake()->randomElement(['draft', 'validated', 'deprecated']),
            'usage_count' => fake()->numberBetween(0, 100),
            'last_used' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'validation_date' => fake()->optional()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'validated',
            'confidence' => fake()->numberBetween(80, 100),
            'validation_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'validation_date' => null,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => 'critical',
            'confidence' => fake()->numberBetween(90, 100),
        ]);
    }
}
