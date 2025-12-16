<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

class DatabaseInitializer
{
    public function __construct(
        private KnowledgePathService $pathService
    ) {}

    /**
     * Initialize the database - create directory and run migrations if needed.
     */
    public function initialize(): void
    {
        // Ensure ~/.knowledge/ directory exists
        $this->pathService->ensureDirectoryExists(
            $this->pathService->getKnowledgeDirectory()
        );

        // If database doesn't exist, create it and run migrations
        if (! $this->pathService->databaseExists()) {
            $dbPath = $this->pathService->getDatabasePath();
            touch($dbPath);

            Artisan::call('migrate', ['--force' => true]);
        }
    }

    /**
     * Check if the database has been initialized.
     */
    public function isInitialized(): bool
    {
        return $this->pathService->databaseExists();
    }
}
