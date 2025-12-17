<?php

declare(strict_types=1);

namespace App\Commands\Collection;

use App\Models\Entry;
use App\Services\CollectionService;
use LaravelZero\Framework\Commands\Command;

class RemoveCommand extends Command
{
    protected $signature = 'collection:remove
                            {collection : The name of the collection}
                            {entry_id : The ID of the entry to remove}';

    protected $description = 'Remove an entry from a collection';

    public function handle(CollectionService $service): int
    {
        /** @var string $collectionName */
        $collectionName = $this->argument('collection');
        /** @var int $entryId */
        $entryId = (int) $this->argument('entry_id');

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

        // Remove entry from collection
        $removed = $service->removeEntry($collection, $entry);

        if (! $removed) {
            $this->error("Error: Entry #{$entryId} is not in collection \"{$collectionName}\".");

            return self::FAILURE;
        }

        $this->info("Entry #{$entryId} removed from collection \"{$collectionName}\".");

        return self::SUCCESS;
    }
}
