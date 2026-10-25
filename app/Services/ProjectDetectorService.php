<?php

declare(strict_types=1);

namespace App\Services;

class ProjectDetectorService
{
    /**
     * Shared collection for subagent directories that have no parent project.
     * QdrantService prefixes this to `knowledge_subagents`.
     */
    public const SHARED_SUBAGENTS_PROJECT = 'subagents';

    /** @var array{project: string, subagent_id: string|null}|null */
    private ?array $resolution = null;

    public function __construct(
        private readonly GitContextService $gitContext,
    ) {}

    /**
     * Detect the current project name from the git repository.
     *
     * Resolution order:
     * 1. Worktrees, subagent directories, and sandbox or temporary folders map to the parent project
     * 2. Extract repo name from git remote URL (e.g., 'knowledge' from 'github.com/user/knowledge.git')
     * 3. Fall back to directory name from git repository path
     * 4. Return 'default' if not in a git repository
     * 5. A subagent directory with no parent project uses the shared 'subagents' collection
     */
    public function detect(): string
    {
        return $this->resolve()['project'];
    }

    /**
     * Subagent identifier stored on each point when the shared collection is in use.
     *
     * Null when the current directory maps to a real project collection.
     */
    public function subagentId(): ?string
    {
        return $this->resolve()['subagent_id'];
    }

    /**
     * @return array{project: string, subagent_id: string|null}
     */
    private function resolve(): array
    {
        if ($this->resolution !== null) {
            return $this->resolution;
        }

        $this->resolution = $this->doResolve();

        return $this->resolution;
    }

    /**
     * @return array{project: string, subagent_id: string|null}
     */
    private function doResolve(): array
    {
        if (! $this->gitContext->isGitRepository()) {
            return $this->resolveLooseDirectory($this->gitContext->getWorkingDirectory());
        }

        if ($this->gitContext->isWorktree()) {
            $mapped = $this->mapWorktree($this->gitContext);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        $path = $this->gitContext->getRepositoryPath() ?? $this->gitContext->getWorkingDirectory();

        if ($this->isEphemeralPath($path)) {
            $parent = $this->projectFromParentDirectory($path);

            if ($parent !== null) {
                return ['project' => $parent, 'subagent_id' => null];
            }

            $subagentId = $this->subagentIdFromPath($path);

            if ($subagentId !== null) {
                return ['project' => self::SHARED_SUBAGENTS_PROJECT, 'subagent_id' => $subagentId];
            }
        }

        $name = $this->nameFromGit($this->gitContext);

        if ($this->isEphemeralPath($path) && $this->isEphemeralName($name)) {
            return ['project' => 'default', 'subagent_id' => null];
        }

        return ['project' => $name, 'subagent_id' => null];
    }

    /**
     * @return array{project: string, subagent_id: string|null}
     */
    private function resolveLooseDirectory(string $path): array
    {
        $unscoped = ['project' => 'default', 'subagent_id' => null];

        if ($path === '' || ! $this->isEphemeralPath($path)) {
            return $unscoped;
        }

        $parent = $this->projectFromParentDirectory($path);

        if ($parent !== null) {
            return ['project' => $parent, 'subagent_id' => null];
        }

        $subagentId = $this->subagentIdFromPath($path);

        if ($subagentId !== null) {
            return ['project' => self::SHARED_SUBAGENTS_PROJECT, 'subagent_id' => $subagentId];
        }

        return $unscoped;
    }

    /**
     * @return array{project: string, subagent_id: string|null}|null
     */
    private function mapWorktree(GitContextService $git): ?array
    {
        $main = $git->getMainRepositoryPath();

        if (is_string($main) && $main !== '') {
            $name = $this->projectNameFromDirectory($main);

            if ($name !== null) {
                return ['project' => $name, 'subagent_id' => null];
            }
        }

        $path = $git->getRepositoryPath() ?? $git->getWorkingDirectory();
        $parent = $this->projectFromParentDirectory($path);

        if ($parent !== null) {
            return ['project' => $parent, 'subagent_id' => null];
        }

        return null;
    }

    /**
     * Walk upward until a parent project is found.
     *
     * A git repository wins over a plain directory. The nearest plain directory
     * name is kept as a fallback for the folder above a worktree or subagent.
     */
    private function projectFromParentDirectory(string $start): ?string
    {
        $current = dirname($start);
        $depth = 0;
        $plainFallback = null;

        while ($depth < 8 && $current !== dirname($current)) {
            $depth++;

            if (is_dir($current) && ! $this->isEphemeralPath($current)) {
                $git = new GitContextService($current);

                if ($git->isGitRepository() && ! $git->isWorktree()) {
                    $name = $this->nameFromGit($git);

                    if (! $this->isEphemeralName($name)) {
                        return $name;
                    }
                } elseif ($plainFallback === null) {
                    $name = $this->sanitize(basename($current));

                    if (! $this->isEphemeralName($name)) {
                        $plainFallback = $name;
                    }
                }
            }

            $current = dirname($current);
        }

        return $plainFallback;
    }

    /**
     * Project name for a directory, using the same remote-then-folder rules as detect().
     */
    private function projectNameFromDirectory(string $path): ?string
    {
        if (! is_dir($path) || $this->isEphemeralPath($path)) {
            return null;
        }

        $git = new GitContextService($path);

        if ($git->isGitRepository() && ! $git->isWorktree()) {
            $name = $this->nameFromGit($git);

            return $this->isEphemeralName($name) ? null : $name;
        }

        $name = $this->sanitize(basename($path));

        return $this->isEphemeralName($name) ? null : $name;
    }

    /**
     * Extract a project name from the given git context.
     */
    private function nameFromGit(GitContextService $git): string
    {
        $repoUrl = $git->getRepositoryUrl();

        if ($repoUrl !== null) {
            $name = $this->extractNameFromUrl($repoUrl);

            if ($name !== null) {
                return $this->sanitize($name);
            }
        }

        $repoPath = $git->getRepositoryPath();

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
        $url = rtrim($url, '/');
        if (str_ends_with($url, '.git')) {
            $url = substr($url, 0, -4);
        }

        if (preg_match('#[:/]([^/]+)$#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function subagentIdFromPath(string $path): ?string
    {
        if (preg_match('/^subagent-(.+)$/i', basename($path), $matches) !== 1) {
            return null;
        }

        $id = trim($matches[1]);

        return $id === '' ? null : $id;
    }

    private function isEphemeralPath(string $path): bool
    {
        return $this->isEphemeralName(basename($path));
    }

    /**
     * Directory or project names that should not become their own collection.
     */
    private function isEphemeralName(string $name): bool
    {
        return preg_match('/^(subagent-.+|sandbox([_-].+)?|sandboxes|tmp([_-].+)?|temp([_-].+)?)$/i', $name) === 1;
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
