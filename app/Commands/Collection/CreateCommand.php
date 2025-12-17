<?php

declare(strict_types=1);

namespace App\Commands\Collection;

use App\Services\CollectionService;
use LaravelZero\Framework\Commands\Command;

class CreateCommand extends Command
{
    protected $signature = 'collection:create
                            {name : The name of the collection}
                            {--description= : Optional description for the collection}';

    protected $description = 'Create a new knowledge collection';

    public function handle(CollectionService $service): int
    {
        /** @var string $name */
        $name = $this->argument('name');
        /** @var string|null $description */
        $description = $this->option('description');

        // Check if collection already exists
        if ($service->findByName($name) !== null) {
            $this->error("Error: Collection \"{$name}\" already exists.");

            return self::FAILURE;
        }

        $collection = $service->create($name, $description);

        $this->info("Collection \"{$name}\" created successfully.");
        $this->line("ID: {$collection->id}");

        return self::SUCCESS;
    }
}
