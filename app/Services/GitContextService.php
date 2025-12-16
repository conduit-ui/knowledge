<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * GitContextService - Detect and retrieve git repository metadata
 *
 * This service provides automatic git context detection for knowledge attribution.
 * It captures repository, branch, commit, and author information to enable:
 * - Knowledge attribution (git blame for documentation)
 * - Evolution tracking (how knowledge changes over time)
 * - Cross-repository knowledge sharing
 *
 * Graceful Fallback Strategy:
 * - All methods return null when git is not available
 * - No exceptions thrown for missing git or non-git directories
 * - Repository URL falls back to local path if remote origin is not configured
 *
 * Testing Support:
 * - Custom working directory can be provided via constructor
 * - Enables testing with temporary directories or mocked git repos
 *
 * Example Usage:
 * ```php
 * $service = new GitContextService();
 * if ($service->isGitRepository()) {
 *     $context = $service->getContext();
 *     // ['repo' => '...', 'branch' => '...', 'commit' => '...', 'author' => '...']
 * }
 * ```
 */
class GitContextService
{
    /**
     * @param  string|null  $workingDirectory  Optional working directory for git commands (default: current directory)
     */
    public function __construct(
        private readonly ?string $workingDirectory = null
    ) {}

    /**
     * Check if the current directory is a git repository
     *
     * Uses `git rev-parse --git-dir` to detect .git directory.
     * Returns false for non-git directories or when git is not installed.
     *
     * @return bool True if in a git repository, false otherwise
     */
    public function isGitRepository(): bool
    {
        $process = $this->runGitCommand(['rev-parse', '--git-dir']);

        return $process->isSuccessful();
    }

    /**
     * Get the absolute path to the repository root
     *
     * Uses `git rev-parse --show-toplevel` to find the top-level directory.
     * This works correctly with git submodules and nested repositories.
     *
     * Fallback: Returns null if not in a git repository or if git command fails.
     *
     * @return string|null Absolute path to repository root, or null if not in a git repo
     */
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

    /**
     * Get the repository URL from remote origin
     *
     * Uses `git remote get-url origin` to retrieve the remote URL.
     * Supports HTTPS, SSH, and git protocol URLs.
     *
     * Fallback: Returns null if:
     * - Not in a git repository
     * - Remote origin is not configured
     * - Git command fails
     *
     * Note: Use getContext() to automatically fall back to local path when URL is unavailable.
     *
     * @return string|null Repository URL (e.g., 'https://github.com/user/repo.git'), or null
     */
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

    /**
     * Get the current branch name
     *
     * Uses `git rev-parse --abbrev-ref HEAD` to get symbolic branch name.
     * Returns 'HEAD' when in detached HEAD state.
     *
     * Fallback: Returns null if not in a git repository or if git command fails.
     *
     * @return string|null Branch name (e.g., 'main', 'feature/new-feature'), or null
     */
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

    /**
     * Get the current commit hash
     *
     * Uses `git rev-parse HEAD` to get the full SHA-1 commit hash (40 characters).
     * This uniquely identifies the exact state of the repository.
     *
     * Fallback: Returns null if not in a git repository or if git command fails.
     *
     * @return string|null Full commit hash (e.g., 'abc123def456...'), or null
     */
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

    /**
     * Get the git user name from git config
     *
     * Uses `git config user.name` to retrieve the configured author name.
     * This name is used for knowledge attribution (similar to git blame).
     *
     * Fallback: Returns null if:
     * - Git config user.name is not set
     * - Git command fails
     * - The configured name is empty
     *
     * Note: Does not require being in a git repository (reads global config).
     *
     * @return string|null Author name (e.g., 'John Doe'), or null
     */
    public function getAuthor(): ?string
    {
        $process = $this->runGitCommand(['config', 'user.name']);

        // @codeCoverageIgnoreStart
        if (! $process->isSuccessful()) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        $name = trim($process->getOutput());

        return $name !== '' ? $name : null;
    }

    /**
     * Get all git context information in a single call
     *
     * This is the primary method for capturing git metadata.
     * It retrieves all relevant information and applies intelligent fallbacks:
     *
     * Repository Fallback Strategy:
     * 1. Try remote origin URL (best for cross-repo knowledge sharing)
     * 2. Fall back to local path (works for local repos without remotes)
     * 3. Return null if not in a git repository
     *
     * All other fields return null on failure for graceful degradation.
     *
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
     * Execute a git command in the configured working directory
     *
     * This is the internal method used by all public git operations.
     * It handles working directory resolution and process execution.
     *
     * Working Directory Resolution:
     * 1. Use constructor-provided workingDirectory (for testing)
     * 2. Fall back to getcwd() (current working directory)
     * 3. Use null if getcwd() fails (let Process handle it)
     *
     * @param  array<int, string>  $command  Git command arguments (e.g., ['rev-parse', 'HEAD'])
     * @return Process The executed process (check isSuccessful() and getOutput())
     */
    private function runGitCommand(array $command): Process
    {
        $cwd = $this->workingDirectory ?? getcwd();

        // @codeCoverageIgnoreStart
        if ($cwd === false) {
            $cwd = null;
        }
        // @codeCoverageIgnoreEnd

        $process = new Process(
            ['git', ...$command],
            $cwd
        );

        $process->run();

        return $process;
    }
}
