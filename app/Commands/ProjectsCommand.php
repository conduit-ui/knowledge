<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\OdinSyncService;
use LaravelZero\Framework\Commands\Command;

class ProjectsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'projects
                            {--local : Show only local project collections}';

    /**
     * @var string
     */
    protected $description = 'List synced knowledge base projects from Odin';

    public function handle(OdinSyncService $odinSync): int
    {
        if ((bool) $this->option('local')) {
            $this->info('Local project listing requires Qdrant collection enumeration.');
            $this->line('Use "know stats" to see current project statistics.');

            return self::SUCCESS;
        }

        if (! $odinSync->isEnabled()) {
            $this->error('Odin sync is disabled. Set ODIN_SYNC_ENABLED=true to enable.');

            return self::FAILURE;
        }

        $this->line('Fetching projects from Odin...');

        if (! $odinSync->isAvailable()) {
            $this->warn('Odin server is not reachable. Cannot list remote projects.');

            return self::SUCCESS;
        }

        $projects = $odinSync->listProjects();

        if ($projects === []) {
            $this->info('No projects found on Odin server.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($projects as $project) {
            $rows[] = [
                $project['name'],
                (string) $project['entry_count'],
                $project['last_synced'] ?? 'Never',
            ];
        }

        $this->table(['Project', 'Entries', 'Last Synced'], $rows);

        return self::SUCCESS;
    }
}
