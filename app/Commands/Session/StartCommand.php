<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Models\Entry;
use App\Models\Session;
use LaravelZero\Framework\Commands\Command;

class StartCommand extends Command
{
    protected $signature = 'session:start
                            {--json : Output as JSON instead of markdown}';

    protected $description = 'Start a new session and output context for Claude Code hooks';

    public function handle(): int
    {
        $project = $this->detectProject();
        $branch = $this->detectBranch();

        // Create session record
        $session = Session::create([
            'project' => $project,
            'branch' => $branch,
            'started_at' => now(),
        ]);

        // Store session ID for session:end to find
        $this->storeSessionId($session->id);

        // Output context for Claude
        if ($this->option('json')) {
            $this->outputJson($project, $branch, $session->id);
        } else {
            $this->outputMarkdown($project, $branch, $session->id);
        }

        return self::SUCCESS;
    }

    private function detectProject(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return 'unknown';
        }

        // Try to get git repo name
        $gitRoot = shell_exec('git rev-parse --show-toplevel 2>/dev/null');
        if ($gitRoot !== null && trim($gitRoot) !== '') {
            return basename(trim($gitRoot));
        }

        return basename($cwd);
    }

    private function detectBranch(): ?string
    {
        $branch = shell_exec('git branch --show-current 2>/dev/null');
        if ($branch !== null && trim($branch) !== '') {
            return trim($branch);
        }

        return null;
    }

    private function storeSessionId(string $sessionId): void
    {
        $envFile = getenv('CLAUDE_ENV_FILE');
        if ($envFile !== false && $envFile !== '') {
            file_put_contents($envFile, "export KNOW_SESSION_ID=\"{$sessionId}\"\n", FILE_APPEND);
        }

        // Also store in temp file as fallback
        $tempFile = sys_get_temp_dir() . '/know-session-id';
        file_put_contents($tempFile, $sessionId);
    }

    private function outputMarkdown(string $project, ?string $branch, string $sessionId): void
    {
        $output = [];

        // Git context
        $output[] = '## Current Repository';
        $output[] = "- **Project:** {$project}";
        if ($branch !== null) {
            $output[] = "- **Branch:** {$branch}";
        }

        // Uncommitted changes
        $status = shell_exec('git status --porcelain 2>/dev/null');
        if ($status !== null) {
            $changes = count(array_filter(explode("\n", trim($status))));
            $output[] = "- **Uncommitted:** {$changes} files";
        }

        // Last commit
        $lastCommit = shell_exec('git log -1 --oneline 2>/dev/null');
        if ($lastCommit !== null && trim($lastCommit) !== '') {
            $output[] = "- **Last commit:** " . trim($lastCommit);
        }

        // Branch commits (if on feature branch)
        if ($branch !== null && $branch !== 'main' && $branch !== 'master') {
            $commits = shell_exec('git log main..HEAD --oneline 2>/dev/null') ??
                       shell_exec('git log master..HEAD --oneline 2>/dev/null');
            if ($commits !== null && trim($commits) !== '') {
                $commitLines = array_slice(array_filter(explode("\n", trim($commits))), 0, 5);
                if (count($commitLines) > 0) {
                    $output[] = '';
                    $output[] = '### Branch Commits';
                    foreach ($commitLines as $line) {
                        $output[] = "- {$line}";
                    }
                }
            }
        }

        // Recent relevant knowledge
        $knowledge = $this->getRelevantKnowledge($project);
        if (count($knowledge) > 0) {
            $output[] = '';
            $output[] = '## Relevant Knowledge';
            foreach ($knowledge as $entry) {
                $output[] = "- **{$entry['title']}** (confidence: {$entry['confidence']}%)";
            }
        }

        // Last session summary
        $lastSession = $this->getLastSessionSummary($project);
        if ($lastSession !== null) {
            $output[] = '';
            $output[] = '## Last Session';
            $output[] = $lastSession;
        }

        $this->line(implode("\n", $output));
    }

    private function outputJson(string $project, ?string $branch, string $sessionId): void
    {
        $data = [
            'session_id' => $sessionId,
            'project' => $project,
            'branch' => $branch,
            'started_at' => now()->toIso8601String(),
            'git' => $this->getGitContext(),
            'knowledge' => $this->getRelevantKnowledge($project),
        ];

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function getGitContext(): array
    {
        $context = [];

        $lastCommit = shell_exec('git log -1 --oneline 2>/dev/null');
        if ($lastCommit !== null) {
            $context['last_commit'] = trim($lastCommit);
        }

        $status = shell_exec('git status --porcelain 2>/dev/null');
        if ($status !== null) {
            $context['uncommitted_count'] = count(array_filter(explode("\n", trim($status))));
        }

        return $context;
    }

    /**
     * @return array<int, array{title: string, confidence: int}>
     */
    private function getRelevantKnowledge(string $project): array
    {
        try {
            $entries = Entry::query()
                ->where(function ($query) use ($project) {
                    $query->where('tags', 'like', "%{$project}%")
                        ->orWhere('repo', 'like', "%{$project}%");
                })
                ->where('status', '!=', 'deprecated')
                ->orderByDesc('confidence')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['title', 'confidence']);

            return $entries->map(fn ($e) => [
                'title' => $e->title,
                'confidence' => $e->confidence,
            ])->toArray();
        } catch (\Exception) {
            return [];
        }
    }

    private function getLastSessionSummary(string $project): ?string
    {
        try {
            $lastSession = Session::query()
                ->where('project', $project)
                ->whereNotNull('ended_at')
                ->whereNotNull('summary')
                ->orderByDesc('ended_at')
                ->first();

            if ($lastSession !== null && $lastSession->summary !== null) {
                return $lastSession->summary;
            }
        } catch (\Exception) {
            // Ignore - session table may not exist yet
        }

        return null;
    }
}
