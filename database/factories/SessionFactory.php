<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    protected $model = Session::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'project' => fake()->randomElement([
                'conduit-ui',
                'conduit-core',
                'my-app',
                'laravel-project',
            ]),
            'branch' => fake()->randomElement([
                'main',
                'master',
                'develop',
                'feature/'.fake()->slug(2),
            ]),
            'started_at' => $startedAt,
            'ended_at' => fake()->boolean(70) ? fake()->dateTimeBetween($startedAt, 'now') : null,
            'summary' => fake()->boolean(60) ? fake()->paragraph() : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? now()->subHours(2);

            return [
                'ended_at' => fake()->dateTimeBetween($startedAt, 'now'),
                'summary' => fake()->paragraph(),
            ];
        });
    }

    public function forProject(string $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project' => $project,
        ]);
    }
}
