<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\IssueAnalyzerService;
use App\Services\KnowledgeSearchService;
use App\Services\OllamaService;
use LaravelZero\Framework\Commands\Command;

class StartIssueCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'start-issue
                            {number : GitHub issue number}
                            {--repo= : Repository (default: auto-detect from git remote)}
                            {--no-knowledge : Skip knowledge base search}
                            {--branch= : Custom branch name (default: feature/issue-{number})}';

    /**
     * @var string
     */
    protected $description = 'Analyze a GitHub issue and implement it with AI assistance';

    public function handle(
        OllamaService $ollama,
        IssueAnalyzerService $analyzer,
        KnowledgeSearchService $knowledgeSearch,
        TodoExecutorService $executor,
        PullRequestService $prService
    ): int {
        $issueNumber = $this->argument('number');
        $repo = $this->option('repo');
        $skipKnowledge = $this->option('no-knowledge');
        $customBranch = $this->option('branch');

        // Step 1: Detect repository
        if (! $repo) {
            $repo = $this->detectRepository();
            if (! $repo) {
                $this->error('Could not detect repository from git remote. Use --repo flag.');

                return self::FAILURE;
            }
        }

        $this->info("🔍 Fetching issue #{$issueNumber} from {$repo}...");

        // Step 2: Fetch issue from GitHub
        $issue = $this->fetchIssue($issueNumber, $repo);
        if (! $issue) {
            return self::FAILURE;
        }

        $this->line("Issue: <fg=green>{$issue['title']}</>");
        $this->newLine();

        // Step 3: Search knowledge base for similar issues
        if (! $skipKnowledge) {
            $this->info('📚 Searching knowledge base for similar issues...');
            $similarIssues = $knowledgeSearch->findSimilar($issue['title'], $issue['body']);

            if (! empty($similarIssues)) {
                $this->line('Found similar past issues:');
                foreach ($similarIssues as $similar) {
                    $this->line("  • [{$similar['id']}] {$similar['title']}");
                }
                $this->newLine();
            }
        }

        // Step 4: Analyze with Ollama
        $this->info('🤖 Analyzing issue with Ollama...');

        if (! $ollama->isAvailable()) {
            $this->error('Ollama is not available. Please start Ollama service.');

            return self::FAILURE;
        }

        $analysis = $analyzer->analyzeIssue($issue);

        // Step 5: Display analysis
        $this->displayAnalysis($analysis);

        // Step 6: Check confidence and ask for approval if needed
        if ($analysis['confidence'] < 70) {
            $this->warn('⚠️  Low confidence analysis. Please review file suggestions.');

            if (! $this->confirm('Proceed with suggested files?', false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        // Step 7: Create branch
        $branchName = $customBranch ?? "feature/issue-{$issueNumber}";
        $this->info("🌿 Creating branch: {$branchName}");

        if (! $this->createBranch($branchName)) {
            return self::FAILURE;
        }

        // Step 8: Build and display todo list
        $this->info('📋 Building todo list...');
        $todos = $analyzer->buildTodoList($analysis);

        $this->displayTodos($todos);

        // Step 9: Execute todos with quality gates
        return $this->executeTodos($todos, $issue, $analysis);
    }

    /**
     * Detect repository from git remote.
     */
    private function detectRepository(): ?string
    {
        $result = shell_exec('git config --get remote.origin.url 2>/dev/null');

        if (! $result) {
            return null;
        }

        // Parse GitHub URL (both HTTPS and SSH)
        $result = trim($result);

        if (preg_match('#github\.com[:/](.+/.+?)(?:\.git)?$#', $result, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fetch issue from GitHub using gh CLI.
     */
    private function fetchIssue(int $number, string $repo): ?array
    {
        $command = "gh issue view {$number} --repo {$repo} --json title,body,labels,assignees,state";
        $output = shell_exec($command.' 2>&1');

        if (! $output) {
            $this->error('Failed to fetch issue. Is gh CLI installed and authenticated?');

            return null;
        }

        $issue = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to parse issue data.');

            return null;
        }

        return $issue;
    }

    /**
     * Display analysis results.
     */
    private function displayAnalysis(array $analysis): void
    {
        $this->line('<fg=cyan>Analysis Results:</> (Confidence: '.$analysis['confidence'].'%)');
        $this->newLine();

        foreach ($analysis['files'] as $file) {
            $icon = $file['confidence'] >= 70 ? '✓' : '?';
            $color = $file['confidence'] >= 70 ? 'green' : 'yellow';

            $this->line("<fg={$color}>{$icon}</> {$file['path']} - {$file['change_type']}");
        }

        $this->newLine();
    }

    /**
     * Display todo list.
     */
    private function displayTodos(array $todos): void
    {
        $this->line('<fg=cyan>Todo List:</>');
        $this->newLine();

        foreach ($todos as $index => $todo) {
            $number = $index + 1;
            $this->line("{$number}. {$todo['content']}");
        }

        $this->newLine();
    }

    /**
     * Create git branch.
     */
    private function createBranch(string $branchName): bool
    {
        exec("git checkout -b {$branchName} 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            // Branch might already exist, try to check it out
            exec("git checkout {$branchName} 2>&1", $output, $exitCode);

            if ($exitCode !== 0) {
                $this->error('Failed to create or checkout branch.');

                return false;
            }
        }

        return true;
    }

    /**
     * Execute todos with quality gates and milestone commits.
     */
    private function executeTodos(array $todos, array $issue, array $analysis): int
    {
        $this->info('🚀 Starting execution...');
        $this->newLine();

        // Execute todos
        $executor = app(TodoExecutorService::class);
        $result = $executor->execute($todos, $issue, $this);

        if (! $result['success']) {
            $this->error('❌ Execution failed. Some tasks could not be completed.');
            $this->displayFailures($result['failed']);

            return self::FAILURE;
        }

        $this->info('✅ All tasks completed successfully!');
        $this->newLine();

        // Get coverage data for PR
        $coverageData = $this->getCoverageData();

        // Create PR
        $this->info('📝 Creating pull request...');
        $prService = app(PullRequestService::class);
        $prResult = $prService->create($issue, $analysis, $executor->getCompletedTodos(), $coverageData);

        if (! $prResult['success']) {
            $this->error("Failed to create PR: {$prResult['error']}");

            return self::FAILURE;
        }

        $this->info("✅ Pull request created: {$prResult['url']}");
        $this->newLine();

        // Add to knowledge base
        if (! $this->option('no-knowledge')) {
            $this->info('📚 Adding to knowledge base...');
            $knowledgeSearch = app(KnowledgeSearchService::class);
            $entry = $knowledgeSearch->createFromIssue($issue, $analysis, $executor->getCompletedTodos(), $prResult);
            $this->line("Knowledge entry created: [{$entry->id}] {$entry->title}");
        }

        $this->newLine();
        $this->info('🎉 Issue implementation complete!');

        return self::SUCCESS;
    }

    /**
     * Display failed todos.
     */
    private function displayFailures(array $failures): void
    {
        $this->newLine();
        $this->line('<fg=red>Failed Tasks:</>');
        $this->newLine();

        foreach ($failures as $failure) {
            $this->line("  • {$failure['todo']['content']}");
            $this->line("    Reason: {$failure['reason']}");
        }

        $this->newLine();
    }

    /**
     * Get coverage data for PR description.
     */
    private function getCoverageData(): array
    {
        $qualityGate = app(QualityGateService::class);
        $coverage = $qualityGate->checkCoverage();

        return [
            'current' => $coverage['meta']['coverage'] ?? 0,
            'previous' => $coverage['meta']['previous_coverage'] ?? 0,
            'delta' => ($coverage['meta']['coverage'] ?? 0) - ($coverage['meta']['previous_coverage'] ?? 0),
        ];
    }
}
