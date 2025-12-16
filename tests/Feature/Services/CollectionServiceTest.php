<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;
use App\Services\CollectionService;

describe('CollectionService', function (): void {
    beforeEach(function (): void {
        $this->service = app(CollectionService::class);
    });

    describe('create', function (): void {
        it('creates a collection with name only', function (): void {
            $collection = $this->service->create('My Collection');

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection->name)->toBe('My Collection');
            expect($collection->description)->toBeNull();
        });

        it('creates a collection with name and description', function (): void {
            $collection = $this->service->create('My Collection', 'Test description');

            expect($collection->name)->toBe('My Collection');
            expect($collection->description)->toBe('Test description');
        });

        it('creates a collection with tags', function (): void {
            $collection = $this->service->create('My Collection', null, ['tag1', 'tag2']);

            expect($collection->tags)->toBeArray();
            expect($collection->tags)->toContain('tag1', 'tag2');
        });
    });

    describe('addEntry', function (): void {
        it('adds entry to collection', function (): void {
            $collection = Collection::factory()->create();
            $entry = Entry::factory()->create();

            $result = $this->service->addEntry($collection, $entry);

            expect($result)->toBeTrue();
            expect($collection->entries)->toHaveCount(1);
            expect($collection->entries->first()->id)->toBe($entry->id);
        });

        it('assigns next sort_order automatically', function (): void {
            $collection = Collection::factory()->create();
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            $this->service->addEntry($collection, $entry1);
            $this->service->addEntry($collection, $entry2);
            $this->service->addEntry($collection, $entry3);

            $collection->refresh();
            $entries = $collection->entries;

            expect($entries[0]->pivot->sort_order)->toBe(0);
            expect($entries[1]->pivot->sort_order)->toBe(1);
            expect($entries[2]->pivot->sort_order)->toBe(2);
        });

        it('uses custom sort_order when provided', function (): void {
            $collection = Collection::factory()->create();
            $entry = Entry::factory()->create();

            $this->service->addEntry($collection, $entry, 5);

            $collection->refresh();
            expect($collection->entries->first()->pivot->sort_order)->toBe(5);
        });

        it('does not add duplicate entries', function (): void {
            $collection = Collection::factory()->create();
            $entry = Entry::factory()->create();

            $this->service->addEntry($collection, $entry);
            $result = $this->service->addEntry($collection, $entry);

            expect($result)->toBeFalse();
            expect($collection->entries()->count())->toBe(1);
        });
    });

    describe('removeEntry', function (): void {
        it('removes entry from collection', function (): void {
            $collection = Collection::factory()->create();
            $entry = Entry::factory()->create();
            $collection->entries()->attach($entry, ['sort_order' => 0]);

            $result = $this->service->removeEntry($collection, $entry);

            expect($result)->toBeTrue();
            expect($collection->entries()->count())->toBe(0);
        });

        it('returns false when entry not in collection', function (): void {
            $collection = Collection::factory()->create();
            $entry = Entry::factory()->create();

            $result = $this->service->removeEntry($collection, $entry);

            expect($result)->toBeFalse();
        });
    });

    describe('findByName', function (): void {
        it('finds collection by exact name', function (): void {
            $collection = Collection::factory()->create(['name' => 'Exact Name']);

            $found = $this->service->findByName('Exact Name');

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($collection->id);
        });

        it('returns null when not found', function (): void {
            $found = $this->service->findByName('Nonexistent');

            expect($found)->toBeNull();
        });
    });

    describe('getAll', function (): void {
        it('returns all collections ordered by name', function (): void {
            Collection::factory()->create(['name' => 'Zebra']);
            Collection::factory()->create(['name' => 'Alpha']);
            Collection::factory()->create(['name' => 'Beta']);

            $collections = $this->service->getAll();

            expect($collections)->toHaveCount(3);
            expect($collections[0]->name)->toBe('Alpha');
            expect($collections[1]->name)->toBe('Beta');
            expect($collections[2]->name)->toBe('Zebra');
        });

        it('returns empty collection when no collections exist', function (): void {
            $collections = $this->service->getAll();

            expect($collections)->toHaveCount(0);
        });
    });

    describe('getEntriesWithSortOrder', function (): void {
        it('returns entries with pivot data', function (): void {
            $collection = Collection::factory()->create();
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $collection->entries()->attach($entry1, ['sort_order' => 0]);
            $collection->entries()->attach($entry2, ['sort_order' => 1]);

            $entries = $this->service->getEntriesWithSortOrder($collection);

            expect($entries)->toHaveCount(2);
            expect($entries[0]->pivot->sort_order)->toBe(0);
            expect($entries[1]->pivot->sort_order)->toBe(1);
        });
    });
});
