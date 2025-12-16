<?php

declare(strict_types=1);

namespace App\Commands\Collection;

use App\Services\CollectionService;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    protected $signature = 'knowledge:collection:show
                            {name : The name of the collection}';

    protected $description = 'Show details of a collection and its entries';

    public function handle(CollectionService $service): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        // Find collection
        $collection = $service->findByName($name);
        if ($collection === null) {
            $this->error("Error: Collection \"{$name}\" not found.");

            return self::FAILURE;
        }

        // Display collection details
        $this->info("Collection: {$collection->name}");
        $this->line("ID: {$collection->id}");

        if ($collection->description !== null) {
            $this->line("Description: {$collection->description}");
        }

        $this->newLine();

        // Get entries with sort order
        $entries = $service->getEntriesWithSortOrder($collection);

        if ($entries->isEmpty()) {
            $this->warn('No entries in this collection.');

            return self::SUCCESS;
        }

        // Display entries table
        $tableData = $entries->map(function ($entry): array {
            /** @var Entry $entry */
            return [
                'order' => $entry->pivot?->sort_order ?? 0,
                'id' => $entry->id,
                'title' => $entry->title,
                'category' => $entry->category ?? '',
            ];
        })->toArray();

        $this->table(
            ['Order', 'ID', 'Title', 'Category'],
            $tableData
        );

        $this->info("Total entries: {$entries->count()}");

        return self::SUCCESS;
    }
}
