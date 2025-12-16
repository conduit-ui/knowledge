<?php

declare(strict_types=1);

namespace App\Commands\Collection;

use App\Models\Entry;
use App\Services\CollectionService;
use LaravelZero\Framework\Commands\Command;

class AddCommand extends Command
{
    protected $signature = 'knowledge:collection:add
                            {collection : The name of the collection}
                            {entry_id : The ID of the entry to add}
                            {--order= : Optional sort order position}';

    protected $description = 'Add an entry to a collection';

    public function handle(CollectionService $service): int
    {
        /** @var string $collectionName */
        $collectionName = $this->argument('collection');
        /** @var int $entryId */
        $entryId = (int) $this->argument('entry_id');
        /** @var string|null $sortOrder */
        $sortOrder = $this->option('order');

        // Find collection
        $collection = $service->findByName($collectionName);
        if ($collection === null) {
            $this->error("Error: Collection \"{$collectionName}\" not found.");

            return self::FAILURE;
        }

        // Find entry
        /** @var Entry|null $entry */
        $entry = Entry::query()->find($entryId);
        if ($entry === null) {
            $this->error("Error: Entry #{$entryId} not found.");

            return self::FAILURE;
        }

        // Add entry to collection
        $added = $service->addEntry(
            $collection,
            $entry,
            $sortOrder !== null ? (int) $sortOrder : null
        );

        if (! $added) {
            $this->error("Error: Entry #{$entryId} is already in collection \"{$collectionName}\".");

            return self::FAILURE;
        }

        $this->info("Entry #{$entryId} added to collection \"{$collectionName}\".");

        return self::SUCCESS;
    }
}
