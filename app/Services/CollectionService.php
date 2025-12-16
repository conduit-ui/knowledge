<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Collection;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CollectionService
{
    /**
     * Create a new collection.
     *
     * @param  array<string>|null  $tags
     */
    public function create(string $name, ?string $description = null, ?array $tags = null): Collection
    {
        /** @var Collection */
        return Collection::query()->create([
            'name' => $name,
            'description' => $description,
            'tags' => $tags,
        ]);
    }

    /**
     * Add an entry to a collection.
     */
    public function addEntry(Collection $collection, Entry $entry, ?int $sortOrder = null): bool
    {
        // Check if entry already exists in collection
        if ($collection->entries()->where('entry_id', $entry->id)->exists()) {
            return false;
        }

        // If no sort order provided, use next available
        if ($sortOrder === null) {
            /** @var int|null $maxOrder */
            $maxOrder = $collection->entries()->max('sort_order');
            $sortOrder = $maxOrder !== null ? ((int) $maxOrder) + 1 : 0;
        }

        $collection->entries()->attach($entry, ['sort_order' => $sortOrder]);

        return true;
    }

    /**
     * Remove an entry from a collection.
     */
    public function removeEntry(Collection $collection, Entry $entry): bool
    {
        $detached = $collection->entries()->detach($entry);

        return $detached > 0;
    }

    /**
     * Find a collection by name.
     */
    public function findByName(string $name): ?Collection
    {
        /** @var Collection|null */
        return Collection::query()->where('name', $name)->first();
    }

    /**
     * Get all collections ordered by name.
     *
     * @return EloquentCollection<int, Collection>
     */
    public function getAll(): EloquentCollection
    {
        /** @var EloquentCollection<int, Collection> */
        return Collection::query()->orderBy('name')->get();
    }

    /**
     * Get entries with their sort order.
     *
     * @return EloquentCollection<int, Entry>
     */
    public function getEntriesWithSortOrder(Collection $collection): EloquentCollection
    {
        /** @var EloquentCollection<int, Entry> */
        return $collection->entries()->get();
    }
}
