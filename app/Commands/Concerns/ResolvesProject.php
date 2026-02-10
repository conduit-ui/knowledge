<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Services\ProjectDetectorService;

/**
 * Provides --project and --global flag resolution for commands.
 *
 * Commands using this trait should include these in their signature:
 * {--project= : Override project namespace}
 * {--global : Search across all projects}
 */
trait ResolvesProject
{
    /**
     * Resolve the project name from --project flag or auto-detection.
     */
    protected function resolveProject(): string
    {
        $projectOption = $this->option('project');

        if (is_string($projectOption) && $projectOption !== '') {
            return $projectOption;
        }

        return app(ProjectDetectorService::class)->detect();
    }

    /**
     * Check if --global flag is set.
     */
    protected function isGlobal(): bool
    {
        return (bool) $this->option('global');
    }
}
