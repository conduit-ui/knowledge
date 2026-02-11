<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class ProjectsCommand extends Command
{
    protected $signature = 'projects';

    protected $description = 'List all project knowledge bases';

    public function handle(QdrantService $qdrant, ProjectDetectorService $detector): int
    {
        $currentProject = $detector->detect();

        $collections = spin(
            fn (): array => $qdrant->listCollections(),
            'Fetching project collections...'
        );

        if ($collections === []) {
            warning('No project knowledge bases found.');

            return self::SUCCESS;
        }

        info('Project Knowledge Bases');
        $this->newLine();

        $rows = [];
        foreach ($collections as $collection) {
            $projectName = str_replace('knowledge_', '', $collection);
            $isCurrent = $projectName === $currentProject ? ' (current)' : '';

            $count = $qdrant->count($projectName);

            $rows[] = [
                $projectName.$isCurrent,
                $collection,
                (string) $count,
            ];
        }

        table(['Project', 'Collection', 'Entries'], $rows);

        $this->newLine();
        $this->line("<fg=gray>Current project: <fg=cyan>{$currentProject}</></>");

        return self::SUCCESS;
    }
}
