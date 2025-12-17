<?php

declare(strict_types=1);

namespace App\Services;

class RuntimeEnvironment
{
    private string $basePath;

    private bool $isPhar;

    public function __construct()
    {
        $this->isPhar = \Phar::running() !== '';
        $this->basePath = $this->resolveBasePath();
    }

    /**
     * Check if running as PHAR.
     */
    public function isPhar(): bool
    {
        return $this->isPhar;
    }

    /**
     * Get the base path for storage.
     *
     * In PHAR mode: ~/.knowledge
     * In dev mode: project root
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the database path.
     *
     * Priority:
     * 1. KNOWLEDGE_DB_PATH environment variable
     * 2. Base path + /knowledge.sqlite
     */
    public function databasePath(): string
    {
        $dbPath = getenv('KNOWLEDGE_DB_PATH');
        if ($dbPath !== false && $dbPath !== '') {
            return $dbPath;
        }

        return $this->basePath.'/knowledge.sqlite';
    }

    /**
     * Get the cache path for a specific type.
     *
     * @param  string  $type  Cache type (e.g., 'views', 'data')
     */
    public function cachePath(string $type = ''): string
    {
        // @codeCoverageIgnoreStart
        if ($this->isPhar) {
            $cachePath = $this->basePath.'/cache';

            return $type !== '' ? $cachePath.'/'.$type : $cachePath;
        }
        // @codeCoverageIgnoreEnd

        // In dev mode, use Laravel's standard storage/framework structure
        $projectRoot = dirname(__DIR__, 2);
        $cachePath = $projectRoot.'/storage/framework';

        return $type !== '' ? $cachePath.'/'.$type : $cachePath;
    }

    /**
     * Resolve the base path based on environment.
     *
     * Priority for PHAR mode:
     * 1. KNOWLEDGE_PATH environment variable
     * 2. KNOWLEDGE_HOME environment variable
     * 3. HOME environment variable + /.knowledge
     * 4. USERPROFILE environment variable + /.knowledge (Windows)
     *
     * For dev mode: returns project root
     */
    private function resolveBasePath(): string
    {
        if (! $this->isPhar) {
            // In dev mode, return project root
            return dirname(__DIR__, 2);
        }

        // @codeCoverageIgnoreStart
        // PHAR mode - use ~/.knowledge directory
        $knowledgePath = getenv('KNOWLEDGE_PATH');
        if ($knowledgePath !== false && $knowledgePath !== '') {
            return $knowledgePath;
        }

        $knowledgeHome = getenv('KNOWLEDGE_HOME');
        if ($knowledgeHome !== false && $knowledgeHome !== '') {
            return $knowledgeHome;
        }

        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            return $home.'/.knowledge';
        }

        $userProfile = getenv('USERPROFILE');
        if ($userProfile !== false && $userProfile !== '') {
            return $userProfile.'/.knowledge';
        }

        // Fallback - should never reach here on any supported platform
        return sys_get_temp_dir().'/.knowledge';
        // @codeCoverageIgnoreEnd
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    public function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
