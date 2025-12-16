<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Entry;
use App\Models\Relationship;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create tags
        $tags = Tag::factory()->createMany([
            ['name' => 'php', 'category' => 'language'],
            ['name' => 'laravel', 'category' => 'framework'],
            ['name' => 'pest', 'category' => 'testing'],
            ['name' => 'docker', 'category' => 'tool'],
            ['name' => 'redis', 'category' => 'tool'],
            ['name' => 'debugging', 'category' => 'concept'],
            ['name' => 'architecture', 'category' => 'concept'],
            ['name' => 'tdd', 'category' => 'pattern'],
        ]);

        // Create entries
        $entries = Entry::factory(10)->create();

        // Attach tags to entries
        $entries->each(function (Entry $entry) use ($tags): void {
            $entry->normalizedTags()->attach(
                $tags->random(rand(2, 4))->pluck('id')
            );
        });

        // Create some relationships
        Relationship::factory()->create([
            'from_entry_id' => $entries[0]->id,
            'to_entry_id' => $entries[1]->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entries[2]->id,
            'to_entry_id' => $entries[0]->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        // Create a collection
        $collection = Collection::factory()->create([
            'name' => 'Getting Started with Laravel',
            'description' => 'Essential knowledge for Laravel development',
        ]);

        $collection->entries()->attach(
            $entries->take(3)->pluck('id')->mapWithKeys(fn ($id, $index): array => [$id => ['sort_order' => $index]])
        );
    }
}
