<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

class GitContextService
{
    public function __construct(
        private readonly ?string $workingDirectory = null
    ) {}

    public function isGitRepository(): bool
    {
        $process = $this->runGitCommand(['rev-parse', '--git-dir']);

        return $process->isSuccessful();
    }

    public function getRepositoryPath(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $process = $this->runGitCommand(['rev-parse', '--show-toplevel']);

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function getRepositoryUrl(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $process = $this->runGitCommand(['remote', 'get-url', 'origin']);

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function getCurrentBranch(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $process = $this->runGitCommand(['rev-parse', '--abbrev-ref', 'HEAD']);

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function getCurrentCommit(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $process = $this->runGitCommand(['rev-parse', 'HEAD']);

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function getAuthor(): ?string
    {
        $process = $this->runGitCommand(['config', 'user.name']);

        if (! $process->isSuccessful()) {
            return null;
        }

        $name = trim($process->getOutput());

        return $name !== '' ? $name : null;
    }

    /**
     * @return array{repo: string|null, branch: string|null, commit: string|null, author: string|null}
     */
    public function getContext(): array
    {
        return [
            'repo' => $this->getRepositoryUrl() ?? $this->getRepositoryPath(),
            'branch' => $this->getCurrentBranch(),
            'commit' => $this->getCurrentCommit(),
            'author' => $this->getAuthor(),
        ];
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runGitCommand(array $command): Process
    {
        $cwd = $this->workingDirectory ?? getcwd();

        if ($cwd === false) {
            $cwd = null;
        }

        $process = new Process(
            ['git', ...$command],
            $cwd
        );

        $process->run();

        return $process;
    }
}
