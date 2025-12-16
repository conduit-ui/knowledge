<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\GitContextService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeGitContextCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:git:context';

    /**
     * @var string
     */
    protected $description = 'Display current git context (repo, branch, commit, author) for knowledge attribution';

    public function handle(GitContextService $gitService): int
    {
        if (! $gitService->isGitRepository()) {
            $this->warn('Not in a git repository');

            return self::SUCCESS;
        }

        $this->info('Git Context Information');
        $this->newLine();

        $context = $gitService->getContext();

        $this->line('Repository: '.($context['repo'] ?? 'N/A'));
        $this->line('Branch: '.($context['branch'] ?? 'N/A'));
        $this->line('Commit: '.($context['commit'] ?? 'N/A'));
        $this->line('Author: '.($context['author'] ?? 'N/A'));

        return self::SUCCESS;
    }
}
