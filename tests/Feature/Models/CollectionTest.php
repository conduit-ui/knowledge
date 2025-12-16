<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

describe('Collection model', function (): void {
    it('can be created with factory', function (): void {
        $collection = Collection::factory()->create();

        expect($collection)->toBeInstanceOf(Collection::class);
        expect($collection->id)->toBeInt();
        expect($collection->name)->toBeString();
    });

    it('casts tags to array', function (): void {
        $collection = Collection::factory()->create(['tags' => ['tutorial', 'beginner']]);

        expect($collection->tags)->toBeArray();
        expect($collection->tags)->toContain('tutorial', 'beginner');
    });

    it('has entries relationship', function (): void {
        $collection = Collection::factory()->create();
        $entry = Entry::factory()->create();

        $collection->entries()->attach($entry, ['sort_order' => 0]);

        expect($collection->entries)->toHaveCount(1);
        expect($collection->entries->first()->id)->toBe($entry->id);
    });

    it('orders entries by sort_order', function (): void {
        $collection = Collection::factory()->create();
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        $collection->entries()->attach($entry3, ['sort_order' => 2]);
        $collection->entries()->attach($entry1, ['sort_order' => 0]);
        $collection->entries()->attach($entry2, ['sort_order' => 1]);

        $orderedEntries = $collection->entries;

        expect($orderedEntries[0]->id)->toBe($entry1->id);
        expect($orderedEntries[1]->id)->toBe($entry2->id);
        expect($orderedEntries[2]->id)->toBe($entry3->id);
    });
});
