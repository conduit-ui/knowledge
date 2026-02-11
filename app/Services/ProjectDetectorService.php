<?php

declare(strict_types=1);

namespace App\Services;

class ProjectDetectorService
{
    public function __construct(
        private readonly GitContextService $gitContext,
    ) {}

    /**
     * Detect the current project name from the git repository.
     *
     * Resolution order:
     * 1. Extract repo name from git remote URL (e.g., 'knowledge' from 'github.com/user/knowledge.git')
     * 2. Fall back to directory name from git repository path
     * 3. Return 'default' if not in a git repository
     */
    public function detect(): string
    {
        if (! $this->gitContext->isGitRepository()) {
            return 'default';
        }

        $repoUrl = $this->gitContext->getRepositoryUrl();

        if ($repoUrl !== null) {
            $name = $this->extractNameFromUrl($repoUrl);

            if ($name !== null) {
                return $this->sanitize($name);
            }
        }

        $repoPath = $this->gitContext->getRepositoryPath();

        if ($repoPath !== null) {
            return $this->sanitize(basename($repoPath));
        }

        return 'default';
    }

    /**
     * Extract repository name from a remote URL.
     *
     * Handles formats:
     * - https://github.com/user/repo.git
     * - git@github.com:user/repo.git
     * - ssh://git@github.com/user/repo.git
     */
    private function extractNameFromUrl(string $url): ?string
    {
        // Remove trailing .git
        $url = rtrim($url, '/');
        if (str_ends_with($url, '.git')) {
            $url = substr($url, 0, -4);
        }

        // SSH format: git@github.com:user/repo
        if (preg_match('#[:/]([^/]+)$#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Sanitize a project name for use as a Qdrant collection suffix.
     */
    private function sanitize(string $name): string
    {
        $sanitized = strtolower(trim($name));
        $sanitized = (string) preg_replace('/[^a-z0-9_-]/', '_', $sanitized);
        $sanitized = (string) preg_replace('/_+/', '_', $sanitized);
        $sanitized = trim($sanitized, '_');

        if ($sanitized === '') {
            return 'default';
        }

        return $sanitized;
    }
}
