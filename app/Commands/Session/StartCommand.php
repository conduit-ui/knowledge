<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Models\Entry;
use App\Models\Session;
use App\Services\SemanticSearchService;
use LaravelZero\Framework\Commands\Command;

class StartCommand extends Command
{
    protected $signature = 'session:start
                            {--json : Output as JSON instead of markdown}
                            {--patterns : Show only power user patterns}';

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
        /** @var bool $patterns */
        $patterns = $this->option('patterns');
        /** @var bool $json */
        $json = $this->option('json');

        if ($patterns && $json) {
            $this->outputPatternsJson();
        } elseif ($patterns) {
            $this->outputPatternsMarkdown();
        } elseif ($json) {
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

    private function storeSessionId(string $sessionId): void
    {
        $envFile = getenv('CLAUDE_ENV_FILE');
        if ($envFile !== false && $envFile !== '') {
            file_put_contents($envFile, "export KNOW_SESSION_ID=\"{$sessionId}\"\n", FILE_APPEND);
        }

        // Also store in temp file as fallback
        $tempFile = sys_get_temp_dir().'/know-session-id';
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
        if (is_string($status)) {
            $changes = count(array_filter(explode("\n", trim($status))));
            $output[] = "- **Uncommitted:** {$changes} files";
        }

        // Last commit
        $lastCommit = shell_exec('git log -1 --oneline 2>/dev/null');
        if (is_string($lastCommit) && trim($lastCommit) !== '') {
            $output[] = '- **Last commit:** '.trim($lastCommit);
        }

        // Branch commits (if on feature branch)
        if ($branch !== null && $branch !== 'main' && $branch !== 'master') {
            $commits = shell_exec('git log main..HEAD --oneline 2>/dev/null') ??
                       shell_exec('git log master..HEAD --oneline 2>/dev/null');
            if (is_string($commits) && trim($commits) !== '') {
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

        // Power user patterns
        $output[] = '';
        $output = array_merge($output, $this->getPowerUserPatterns());

        // Recent relevant knowledge (semantic search, excludes session noise)
        $knowledge = $this->getRelevantKnowledge($project);
        if (count($knowledge) > 0) {
            $output[] = '';
            $output[] = '## Relevant Knowledge';
            foreach ($knowledge as $entry) {
                $output[] = "- **{$entry['title']}** (confidence: {$entry['confidence']}%)";
                if ($entry['content'] !== '') {
                    $output[] = '  '.$entry['content'].'...';
                }
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
            'power_user_patterns' => $this->getPowerUserPatternsArray(),
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
        if (is_string($lastCommit)) {
            $context['last_commit'] = trim($lastCommit);
        }

        $status = shell_exec('git status --porcelain 2>/dev/null');
        if (is_string($status)) {
            $context['uncommitted_count'] = count(array_filter(explode("\n", trim($status))));
        }

        return $context;
    }

    /**
     * @return array<int, array{title: string, confidence: int, content: string}>
     */
    private function getRelevantKnowledge(string $project): array
    {
        // Try semantic search first (uses ChromaDB if available)
        /** @var SemanticSearchService $searchService */
        $searchService = app(SemanticSearchService::class);
        $entries = $searchService->search($project, [
            'status' => 'validated',
        ]);

        // Filter: exclude session category, require high confidence
        $filtered = $entries
            ->filter(fn (Entry $e) => $e->category !== 'session')
            ->filter(fn (Entry $e) => $e->confidence >= 80)
            ->take(5);

        // Fallback to repo/tag matching if semantic search returns nothing useful
        if ($filtered->isEmpty()) {
            /** @var \Illuminate\Database\Eloquent\Builder<Entry> $query */
            $query = Entry::query();
            $filtered = $query
                ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($project): void {
                    $q->where('repo', $project)
                        ->orWhereJsonContains('tags', $project);
                })
                ->where('status', 'validated')
                ->where('category', '!=', 'session')
                ->where('confidence', '>=', 80)
                ->orderByDesc('confidence')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();
        }

        return $filtered->map(fn (Entry $e) => [
            'title' => $e->title,
            'confidence' => $e->confidence,
            'content' => mb_substr($e->content ?? '', 0, 200),
        ])->values()->toArray();
    }

    private function getLastSessionSummary(string $project): ?string
    {
        $lastSession = Session::query()
            ->where('project', $project)
            ->whereNotNull('ended_at')
            ->whereNotNull('summary')
            ->orderByDesc('ended_at')
            ->first();

        return $lastSession?->summary;
    }

    /**
     * @return array<int, string>
     */
    private function getPowerUserPatterns(): array
    {
        return [
            '## 🧠 Know Before You Act - Power User Patterns',
            '',
            '### Daily Rituals',
            '**Morning (5 min)**',
            '- ./know priorities → See top 3 blockers/intents',
            '- ./know context → Load project-specific knowledge',
            '- ./know blockers --project=X → Check what\'s blocking progress',
            '',
            '**Focus Block (2-4 hours)**',
            '- ./know focus-time <project> <hours> → Declare focus block',
            '  → Tracks context switches automatically',
            '  → Measures effectiveness (0-10 score)',
            '  → Prompts energy before/after',
            '',
            '**Evening (10 min)**',
            '- ./know daily-review → Structured reflection',
            '  → Auto-pulls merged PRs + closed issues',
            '  → 5 reflection questions',
            '  → Saves as validated entry (confidence: 95)',
            '',
            '### Context Loading',
            '- Before coding: ./know context or ./know session:start',
            '- Check blockers: ./know blockers --project=prefrontal-cortex',
            '- Recent wins: ./know milestones --today',
            '- Recent work: ./know intents --recent',
            '',
            '### Search Patterns',
            '- Already solved? ./know search "authentication flow"',
            '- High confidence only: ./know search --confidence=80',
            '- By category: ./know search --category=debugging',
            '- Semantic search: ./know search --semantic "error handling"',
            '',
            '### Anti-Patterns (Learn from Mistakes)',
            '❌ Ship 5 PRs → ask "what am I missing" → no reflection',
            '✅ Ship 5 PRs → ./know daily-review → celebrate → extract learning → plan tomorrow',
            '',
            '❌ Context switch 20+ times → scattered focus → low effectiveness',
            '✅ ./know focus-time prefrontal-cortex 3 → declare block → track switches → adjust behavior',
            '',
            '❌ Start coding immediately → miss existing solutions → duplicate work',
            '✅ ./know search first → leverage past work → avoid duplication → ship faster',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPowerUserPatternsArray(): array
    {
        return [
            'daily_rituals' => [
                'morning' => [
                    'duration' => '5 min',
                    'commands' => [
                        './know priorities → See top 3 blockers/intents',
                        './know context → Load project-specific knowledge',
                        './know blockers --project=X → Check what\'s blocking progress',
                    ],
                ],
                'focus_block' => [
                    'duration' => '2-4 hours',
                    'commands' => [
                        './know focus-time <project> <hours> → Declare focus block',
                        '→ Tracks context switches automatically',
                        '→ Measures effectiveness (0-10 score)',
                        '→ Prompts energy before/after',
                    ],
                ],
                'evening' => [
                    'duration' => '10 min',
                    'commands' => [
                        './know daily-review → Structured reflection',
                        '→ Auto-pulls merged PRs + closed issues',
                        '→ 5 reflection questions',
                        '→ Saves as validated entry (confidence: 95)',
                    ],
                ],
            ],
            'context_loading' => [
                'Before coding: ./know context or ./know session:start',
                'Check blockers: ./know blockers --project=prefrontal-cortex',
                'Recent wins: ./know milestones --today',
                'Recent work: ./know intents --recent',
            ],
            'search_patterns' => [
                'Already solved? ./know search "authentication flow"',
                'High confidence only: ./know search --confidence=80',
                'By category: ./know search --category=debugging',
                'Semantic search: ./know search --semantic "error handling"',
            ],
            'anti_patterns' => [
                [
                    'wrong' => 'Ship 5 PRs → ask "what am I missing" → no reflection',
                    'right' => 'Ship 5 PRs → ./know daily-review → celebrate → extract learning → plan tomorrow',
                ],
                [
                    'wrong' => 'Context switch 20+ times → scattered focus → low effectiveness',
                    'right' => './know focus-time prefrontal-cortex 3 → declare block → track switches → adjust behavior',
                ],
                [
                    'wrong' => 'Start coding immediately → miss existing solutions → duplicate work',
                    'right' => './know search first → leverage past work → avoid duplication → ship faster',
                ],
            ],
        ];
    }

    private function outputPatternsMarkdown(): void
    {
        $this->line(implode("\n", $this->getPowerUserPatterns()));
    }

    private function outputPatternsJson(): void
    {
        $data = [
            'power_user_patterns' => $this->getPowerUserPatternsArray(),
        ];

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
