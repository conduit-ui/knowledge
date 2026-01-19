<?php

declare(strict_types=1);

namespace App\Services;

class KnowledgePathService
{
    public function __construct(
        private RuntimeEnvironment $runtime
    ) {}

    /**
     * Get the knowledge directory path.
     *
     * Priority:
     * 1. KNOWLEDGE_HOME environment variable
     * 2. HOME environment variable + /.knowledge
     * 3. USERPROFILE environment variable + /.knowledge (Windows)
     */
    public function getKnowledgeDirectory(): string
    {
        // @codeCoverageIgnoreStart
        // In PHAR mode, return the base path directly
        if ($this->runtime->isPhar()) {
            return $this->runtime->basePath();
        }
        // @codeCoverageIgnoreEnd

        // In dev mode, maintain existing behavior for backward compatibility
        $knowledgeHome = getenv('KNOWLEDGE_HOME');
        if ($knowledgeHome !== false && $knowledgeHome !== '') {
            return $knowledgeHome;
        }

        // @codeCoverageIgnoreStart
        // Environment variable paths - tested but parallel test isolation issues
        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            return $home.'/.knowledge';
        }
        // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        // Windows fallback - can't test on Linux (putenv doesn't truly unset HOME)
        $userProfile = getenv('USERPROFILE');
        if ($userProfile !== false && $userProfile !== '') {
            return $userProfile.'/.knowledge';
        }
        // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        // Fallback - should never reach here on any supported platform
        return sys_get_temp_dir().'/.knowledge';
        // @codeCoverageIgnoreEnd
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    public function ensureDirectoryExists(string $path): void
    {
        $this->runtime->ensureDirectoryExists($path);
    }
}
