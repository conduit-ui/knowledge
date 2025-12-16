<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DatabaseInitializer;
use App\Services\KnowledgePathService;
use LaravelZero\Framework\Commands\Command;

class InstallCommand extends Command
{
    protected $signature = 'install';

    protected $description = 'Initialize the knowledge database in ~/.knowledge/';

    public function handle(
        DatabaseInitializer $initializer,
        KnowledgePathService $pathService
    ): int {
        $dbPath = $pathService->getDatabasePath();

        if ($initializer->isInitialized()) {
            $this->info("Knowledge database already exists at: {$dbPath}");

            return self::SUCCESS;
        }

        $this->info('Initializing knowledge database...');
        $this->line("Location: {$dbPath}");

        $initializer->initialize();

        $this->info('Knowledge database initialized successfully!');
        $this->line('');
        $this->line('You can now use:');
        $this->line('  know add "Title" "Content"     Add a knowledge entry');
        $this->line('  know search "query"            Search your knowledge');
        $this->line('  know list                      List all commands');

        return self::SUCCESS;
    }
}
