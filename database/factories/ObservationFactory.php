<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'type' => fake()->randomElement(ObservationType::cases()),
            'concept' => fake()->optional(0.8)->randomElement([
                'authentication',
                'database',
                'api',
                'testing',
                'deployment',
                'refactoring',
                'performance',
            ]),
            'title' => fake()->sentence(4),
            'subtitle' => fake()->optional(0.5)->sentence(6),
            'narrative' => fake()->paragraphs(2, true),
            'facts' => fake()->optional(0.6)->randomElements([
                'file_count' => fake()->numberBetween(1, 20),
                'line_changes' => fake()->numberBetween(10, 500),
                'test_count' => fake()->numberBetween(0, 50),
            ], fake()->numberBetween(1, 3)),
            'files_read' => fake()->optional(0.7)->randomElements([
                'src/Controllers/UserController.php',
                'src/Models/User.php',
                'tests/Feature/UserTest.php',
                'config/auth.php',
                'routes/api.php',
            ], fake()->numberBetween(1, 4)),
            'files_modified' => fake()->optional(0.6)->randomElements([
                'src/Controllers/UserController.php',
                'src/Models/User.php',
                'database/migrations/create_users_table.php',
            ], fake()->numberBetween(1, 3)),
            'tools_used' => fake()->optional(0.5)->randomElements([
                'Read',
                'Write',
                'Edit',
                'Bash',
                'Grep',
                'Glob',
            ], fake()->numberBetween(1, 4)),
            'work_tokens' => fake()->numberBetween(100, 5000),
            'read_tokens' => fake()->numberBetween(500, 20000),
        ];
    }

    public function forSession(Session $session): static
    {
        return $this->state(fn (array $attributes) => [
            'session_id' => $session->id,
        ]);
    }

    public function ofType(ObservationType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function withConcept(string $concept): static
    {
        return $this->state(fn (array $attributes) => [
            'concept' => $concept,
        ]);
    }

    public function bugfix(): static
    {
        return $this->ofType(ObservationType::Bugfix);
    }

    public function feature(): static
    {
        return $this->ofType(ObservationType::Feature);
    }

    public function discovery(): static
    {
        return $this->ofType(ObservationType::Discovery);
    }

    public function decision(): static
    {
        return $this->ofType(ObservationType::Decision);
    }
}
