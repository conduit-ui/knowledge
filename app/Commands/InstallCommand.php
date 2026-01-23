<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

class InstallCommand extends Command
{
    protected $signature = 'install {--project=default : Project/collection name}';

    protected $description = 'Initialize the Qdrant knowledge collection';

    public function handle(QdrantService $qdrant): int
    {
        /** @var string $project */
        $project = is_string($this->option('project')) ? $this->option('project') : 'default';

        note("Initializing collection: knowledge_{$project}");

        try {
            spin(
                fn () => $qdrant->ensureCollection($project),
                'Connecting to Qdrant...'
            );

            info('Qdrant collection initialized successfully!');

            $this->newLine();
            $this->line('<fg=gray>Next steps:</>');
            $this->line('  <fg=cyan>know add "Title" --content="..."</>  Add an entry');
            $this->line('  <fg=cyan>know search "query"</>               Search entries');
            $this->line('  <fg=cyan>know entries</>                      List all entries');

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('Failed to initialize: '.$e->getMessage());
            note('Make sure Qdrant is running: docker start knowledge-qdrant');

            return self::FAILURE;
        }
    }
}
