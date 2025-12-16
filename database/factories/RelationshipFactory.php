<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relationship>
 */
class RelationshipFactory extends Factory
{
    protected $model = Relationship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_entry_id' => Entry::factory(),
            'to_entry_id' => Entry::factory(),
            'type' => fake()->randomElement(Relationship::types()),
            'metadata' => fake()->optional()->passthrough([
                'reason' => fake()->sentence(),
                'strength' => fake()->randomFloat(2, 0, 1),
            ]),
        ];
    }
}
