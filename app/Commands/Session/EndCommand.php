<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Models\Entry;
use App\Models\Session;
use LaravelZero\Framework\Commands\Command;

class EndCommand extends Command
{
    protected $signature = 'session:end
                            {--sync : Sync to cloud after saving}';

    protected $description = 'End the current session and capture summary to knowledge base';

    public function handle(): int
    {
        $sessionId = $this->findSessionId();
        $session = $sessionId !== null ? Session::find($sessionId) : null;

        $project = $this->detectProject();
        $branch = $this->detectBranch();
        $summary = $this->buildSummary($project, $branch);

        // Update session if found
        if ($session !== null) {
            $session->update([
                'ended_at' => now(),
                'summary' => $summary,
            ]);
        }

        // Save to knowledge base
        $this->saveToKnowledge($project, $branch, $summary);

        // Clean up session ID file
        $this->cleanupSessionId();

        if (!$this->option('quiet')) {
            $this->info('[session:end] Session captured to knowledge base');
        }

        // Sync if requested
        if ($this->option('sync')) {
            $this->call('sync', ['--push' => true, '--quiet' => true]);
        }

        return self::SUCCESS;
    }

    private function findSessionId(): ?string
    {
        // Try environment variable first (from CLAUDE_ENV_FILE)
        $envSessionId = getenv('KNOW_SESSION_ID');
        if ($envSessionId !== false && $envSessionId !== '') {
            return $envSessionId;
        }

        // Fallback to temp file
        $tempFile = sys_get_temp_dir() . '/know-session-id';
        if (file_exists($tempFile)) {
            $id = trim(file_get_contents($tempFile) ?: '');
            return $id !== '' ? $id : null;
        }

        return null;
    }

    private function cleanupSessionId(): void
    {
        $tempFile = sys_get_temp_dir() . '/know-session-id';
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    private function detectProject(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return 'unknown';
        }

        $gitRoot = shell_exec('git rev-parse --show-toplevel 2>/dev/null');
        if (is_string($gitRoot) && trim($gitRoot) !== '') {
            return basename(trim($gitRoot));
        }

        return basename($cwd);
    }

    private function detectBranch(): ?string
    {
        $branch = shell_exec('git branch --show-current 2>/dev/null');
        if (is_string($branch) && trim($branch) !== '') {
            return trim($branch);
        }

        return null;
    }

    private function buildSummary(string $project, ?string $branch): string
    {
        $lines = [];
        $lines[] = "Session ended: " . now()->format('Y-m-d H:i:s');
        $lines[] = "Project: {$project}";

        if ($branch !== null) {
            $lines[] = "Branch: {$branch}";
        }

        // Get commit count from session (approximate - last hour)
        $commits = shell_exec('git log --since="1 hour ago" --oneline 2>/dev/null');
        if (is_string($commits)) {
            $commitCount = count(array_filter(explode("\n", trim($commits))));
            if ($commitCount > 0) {
                $lines[] = "Commits: {$commitCount}";
                $lines[] = '';
                $lines[] = 'Recent commits:';
                foreach (array_slice(array_filter(explode("\n", trim($commits))), 0, 5) as $commit) {
                    $lines[] = "- {$commit}";
                }
            }
        }

        // Get modified files
        $modified = shell_exec('git diff --name-only HEAD~1 2>/dev/null');
        if (is_string($modified) && trim($modified) !== '') {
            $files = array_filter(explode("\n", trim($modified)));
            if (count($files) > 0) {
                $lines[] = '';
                $lines[] = 'Files modified:';
                foreach (array_slice($files, 0, 10) as $file) {
                    $lines[] = "- {$file}";
                }
                if (count($files) > 10) {
                    $lines[] = "- ... and " . (count($files) - 10) . " more";
                }
            }
        }

        return implode("\n", $lines);
    }

    private function saveToKnowledge(string $project, ?string $branch, string $summary): void
    {
        try {
            $title = "Session: {$project} " . now()->format('Y-m-d H:i');

            $tags = ['session-end', $project, now()->format('Y-m-d')];
            if ($branch !== null) {
                $tags[] = "branch:{$branch}";
            }

            Entry::create([
                'title' => $title,
                'content' => $summary,
                'category' => 'session',
                'tags' => $tags,
                'priority' => 'low',
                'confidence' => 80,
                'status' => 'validated',
                'repo' => $project,
                'branch' => $branch,
            ]);
        } catch (\Exception $e) {
            if (!$this->option('quiet')) {
                $this->warn("[session:end] Could not save to knowledge: " . $e->getMessage());
            }
        }
    }
}
