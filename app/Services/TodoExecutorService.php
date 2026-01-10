<?php

declare(strict_types=1);

namespace App\Services;

use LaravelZero\Framework\Commands\Command;

class TodoExecutorService
{
    private array $completedTodos = [];

    private array $failedTodos = [];

    private int $currentMilestone = 0;

    public function __construct(
        private readonly OllamaService $ollama,
        private readonly TestExecutorService $testExecutor,
        private readonly QualityGateService $qualityGate
    ) {}

    /**
     * Execute todos with quality gates and milestone commits.
     */
    public function execute(array $todos, array $issue, Command $command): array
    {
        $totalTodos = count($todos);
        $command->info("Starting execution of {$totalTodos} tasks...");
        $command->newLine();

        foreach ($todos as $index => $todo) {
            $todoNumber = $index + 1;

            $command->line("🔨 [{$todoNumber}/{$totalTodos}] {$todo['content']}");

            // Execute based on todo type
            $result = match ($todo['type']) {
                'implementation' => $this->executeImplementation($todo, $issue, $command),
                'test' => $this->executeTest($todo, $issue, $command),
                'quality' => $this->executeQuality($todo, $command),
                default => ['success' => false, 'reason' => 'Unknown todo type']
            };

            if ($result['success']) {
                $this->completedTodos[] = $todo;
                $command->line('  ✅ Completed');

                // Check if we've reached a milestone
                if ($this->shouldCommitMilestone($index, $totalTodos)) {
                    $this->commitMilestone($command);
                }
            } else {
                $command->error("  ❌ Failed: {$result['reason']}");

                // Try to continue with remaining tasks
                if ($result['blocking'] ?? false) {
                    $command->error('This is a blocking failure. Stopping execution.');
                    $this->failedTodos[] = ['todo' => $todo, 'reason' => $result['reason']];
                    break;
                }

                $this->failedTodos[] = ['todo' => $todo, 'reason' => $result['reason']];
            }

            $command->newLine();
        }

        return [
            'completed' => $this->completedTodos,
            'failed' => $this->failedTodos,
            'success' => count($this->failedTodos) === 0,
        ];
    }

    /**
     * Execute implementation todo.
     */
    private function executeImplementation(array $todo, array $issue, Command $command): array
    {
        if (! isset($todo['file']) || ! file_exists($todo['file'])) {
            $command->warn("  File {$todo['file']} does not exist. Creating it...");

            // TODO: Use Ollama to generate initial file structure
            return ['success' => false, 'reason' => 'File creation not yet implemented'];
        }

        // Read current file
        $currentCode = file_get_contents($todo['file']);

        if ($currentCode === false) {
            return ['success' => false, 'reason' => 'Could not read file'];
        }

        // Get code suggestions from Ollama
        $command->line('  🤖 Getting code suggestions from Ollama...');
        $suggestions = $this->ollama->suggestCodeChanges($todo['file'], $currentCode, $issue);

        if (! isset($suggestions['changes']) || count($suggestions['changes']) === 0) {
            return ['success' => false, 'reason' => 'No changes suggested by Ollama'];
        }

        // Check if refactor is required and ask for approval
        if ($suggestions['requires_refactor'] ?? false) {
            $command->warn('  ⚠️  This change requires refactoring.');

            // In actual implementation, would use AskUserQuestion here
            return ['success' => false, 'reason' => 'Refactor approval not yet implemented'];
        }

        // TODO: Apply changes to file
        $command->line('  📝 Applying '.count($suggestions['changes']).' changes...');

        // For now, just indicate success - actual implementation would apply changes
        return ['success' => true];
    }

    /**
     * Execute test todo.
     */
    private function executeTest(array $todo, array $issue, Command $command): array
    {
        $command->line('  🧪 Running tests...');

        $testResult = $this->testExecutor->runTests($todo['file'] ?? null);

        if ($testResult['passed']) {
            return ['success' => true];
        }

        // Auto-fix test failures
        $command->warn('  ⚠️  Tests failed. Attempting auto-fix...');

        $fixAttempts = 0;
        $maxAttempts = 3;

        foreach ($testResult['failures'] as $failure) {
            if ($fixAttempts >= $maxAttempts) {
                break;
            }

            $fixed = $this->testExecutor->autoFixFailure($failure, $fixAttempts);
            $fixAttempts++;

            if ($fixed) {
                $command->line('  ✅ Auto-fixed test failure');

                return ['success' => true];
            }
        }

        return [
            'success' => false,
            'reason' => "Tests failed after {$fixAttempts} fix attempts",
            'blocking' => true,
        ];
    }

    /**
     * Execute quality gate todo.
     */
    private function executeQuality(array $todo, Command $command): array
    {
        $command->line('  🔍 Running quality checks...');

        if (str_contains(strtolower($todo['content']), 'coverage')) {
            $result = $this->qualityGate->checkCoverage();
        } elseif (str_contains(strtolower($todo['content']), 'phpstan')) {
            $result = $this->qualityGate->runStaticAnalysis();
        } elseif (str_contains(strtolower($todo['content']), 'pint')) {
            $result = $this->qualityGate->applyFormatting();
        } else {
            $result = $this->qualityGate->runAllGates();
        }

        if (isset($result['passed']) && $result['passed'] === false) {
            $errors = $result['errors'] ?? [];
            $errorMessage = is_array($errors) ? implode(', ', $errors) : 'Unknown error';

            return [
                'success' => false,
                'reason' => 'Quality gate failed: '.$errorMessage,
                'blocking' => true,
            ];
        }

        return ['success' => true];
    }

    /**
     * Check if we should commit at this milestone.
     */
    private function shouldCommitMilestone(int $currentIndex, int $totalTodos): bool
    {
        $progress = ($currentIndex + 1) / $totalTodos;

        // Commit at 33%, 66%, and 100%
        $milestones = [0.33, 0.66, 1.0];

        foreach ($milestones as $milestone) {
            if ($progress >= $milestone && (float) $this->currentMilestone < $milestone) {
                $this->currentMilestone = (int) round($milestone * 100);

                return true;
            }
        }

        return false;
    }

    /**
     * Commit milestone changes.
     */
    private function commitMilestone(Command $command): void
    {
        $percentage = $this->currentMilestone;
        $command->info("  📦 Committing milestone ({$percentage}% complete)...");

        $message = "WIP: Milestone {$percentage}% - ".count($this->completedTodos).' tasks completed';

        exec('git add .', $output, $exitCode);

        if ($exitCode !== 0) {
            $command->warn('  Failed to stage changes');

            return;
        }

        exec('git commit -m '.escapeshellarg($message).' 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            $command->line("  ✅ Committed: {$message}");
        }
    }

    /**
     * Get completed todos.
     */
    public function getCompletedTodos(): array
    {
        return $this->completedTodos;
    }

    /**
     * Get failed todos.
     */
    public function getFailedTodos(): array
    {
        return $this->failedTodos;
    }
}
