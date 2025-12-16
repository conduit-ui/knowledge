<?php

declare(strict_types=1);

namespace App\Commands\Collection;

use App\Services\CollectionService;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    protected $signature = 'knowledge:collection:list';

    protected $description = 'List all knowledge collections';

    public function handle(CollectionService $service): int
    {
        $collections = $service->getAll();

        if ($collections->isEmpty()) {
            $this->info('No collections found.');

            return self::SUCCESS;
        }

        $tableData = $collections->map(function ($collection): array {
            /** @var \App\Models\Collection $collection */
            return [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description ?? '',
                'entries' => (string) $collection->entries()->count(),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Name', 'Description', 'Entries'],
            $tableData
        );

        return self::SUCCESS;
    }
}
