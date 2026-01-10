<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * PullRequestService - GitHub PR creation and git workflow automation
 *
 * This service handles the complete PR workflow including:
 * - Git commits and branch management
 * - PR creation via gh CLI with formatted descriptions
 * - Coverage delta tracking
 * - Linking to Linear issues
 *
 * PR Description Format:
 * - Links to original Linear issue (Closes #number)
 * - AI analysis summary
 * - Todo checklist for implementation tracking
 * - Coverage delta to ensure quality standards
 *
 * Example Usage:
 * ```php
 * $service = new PullRequestService();
 * $result = $service->create($issue, $analysis, $todos, $coverage);
 * if ($result['success']) {
 *     echo "PR created: {$result['url']}";
 * }
 * ```
 */
class PullRequestService
{
    /**
     * Create a pull request with formatted description.
     *
     * @param  array<string, mixed>  $issue  Linear issue data with 'number' and 'title'
     * @param  array<string, mixed>  $analysis  AI analysis with 'summary' and 'confidence'
     * @param  array<int, array<string, mixed>>  $todos  Todo items with 'content' and 'type'
     * @param  array<string, mixed>  $coverage  Coverage data with 'current', 'previous', and 'delta'
     * @return array<string, mixed> Result with 'success', 'url', 'number', and optional 'error'
     */
    public function create(array $issue, array $analysis, array $todos, array $coverage): array
    {
        // Commit changes
        $issueNumber = $issue['number'] ?? 'unknown';
        $title = $issue['title'] ?? 'Implementation';
        $commitMessage = "Implement #{$issueNumber}: {$title}";

        if (! $this->commitChanges($commitMessage)) {
            return [
                'success' => false,
                'url' => null,
                'number' => null,
                'error' => 'Failed to commit changes',
            ];
        }

        // Get current branch
        $branchProcess = $this->runCommand(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        if (! $branchProcess->isSuccessful()) {
            return [
                'success' => false,
                'url' => null,
                'number' => null,
                'error' => 'Failed to get current branch',
            ];
        }
        $branchName = trim($branchProcess->getOutput());

        // Push branch
        if (! $this->pushBranch($branchName)) {
            return [
                'success' => false,
                'url' => null,
                'number' => null,
                'error' => 'Failed to push branch',
            ];
        }

        // Build the PR description
        $description = $this->buildDescription($issue, $analysis, $todos, $coverage);

        // Create PR using gh CLI
        $process = $this->runCommand([
            'gh', 'pr', 'create',
            '--title', $commitMessage,
            '--body', $description,
        ]);

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            if ($errorOutput !== '') {
                $error = $errorOutput;
            } else {
                $error = trim($process->getOutput());
            }

            return [
                'success' => false,
                'url' => null,
                'number' => null,
                'error' => 'Failed to create PR: '.$error,
            ];
        }

        // Extract PR URL and number from output
        $output = trim($process->getOutput());
        $url = $this->extractPrUrl($output);
        $number = $this->extractPrNumber($url);

        return [
            'success' => true,
            'url' => $url,
            'number' => $number,
            'error' => null,
        ];
    }

    /**
     * Build formatted PR description with all sections.
     *
     * @param  array<string, mixed>  $issue  Linear issue data
     * @param  array<string, mixed>  $analysis  AI analysis data
     * @param  array<int, array<string, mixed>>  $todos  Todo items
     * @param  array<string, mixed>  $coverage  Coverage data
     */
    public function buildDescription(array $issue, array $analysis, array $todos, array $coverage): string
    {
        $issueNumber = $issue['number'] ?? 'unknown';
        $summary = $analysis['summary'] ?? 'AI-powered implementation';
        $approach = $analysis['approach'] ?? '';

        $description = "Closes #{$issueNumber}\n\n";

        $description .= "## Summary\n\n";
        $description .= "{$summary}\n\n";

        if ($approach !== '') {
            $description .= "## AI Analysis\n\n";
            $description .= "{$approach}\n\n";
        }

        if (count($todos) > 0) {
            $description .= "## Todo Checklist\n\n";
            foreach ($todos as $todo) {
                $content = $todo['content'] ?? 'Unknown task';
                $description .= "- [ ] {$content}\n";
            }
            $description .= "\n";
        }

        if (count($coverage) > 0) {
            $description .= $this->formatCoverageSection($coverage);
        }

        return $description;
    }

    /**
     * Get current test coverage percentage.
     */
    public function getCurrentCoverage(): float
    {
        $process = $this->runCommand(['composer', 'test-coverage']);

        if (! $process->isSuccessful()) {
            return 0.0;
        }

        $output = $process->getOutput();

        // Extract coverage percentage from output
        if (preg_match('/(\d+\.?\d*)%/', $output, $matches)) {
            return (float) $matches[1];
        }

        return 0.0;
    }

    /**
     * Commit changes with given message.
     */
    public function commitChanges(string $message): bool
    {
        // Stage all changes
        $stageProcess = $this->runCommand(['git', 'add', '.']);

        if (! $stageProcess->isSuccessful()) {
            return false;
        }

        // Commit with message
        $commitProcess = $this->runCommand(['git', 'commit', '-m', $message]);

        return $commitProcess->isSuccessful();
    }

    /**
     * Push branch to remote origin.
     */
    public function pushBranch(string $branchName): bool
    {
        $process = $this->runCommand(['git', 'push', '-u', 'origin', $branchName]);

        return $process->isSuccessful();
    }

    /**
     * Format coverage section with delta.
     *
     * @param  array<string, mixed>  $coverage
     */
    private function formatCoverageSection(array $coverage): string
    {
        $current = $coverage['current'] ?? 0.0;
        $previous = $coverage['previous'] ?? 0.0;
        $delta = $coverage['delta'] ?? 0.0;

        $section = "## Coverage\n\n";

        // Format: "95.5% → 97.2% (+1.7%)"
        $deltaStr = $delta >= 0 ? "+{$delta}" : "{$delta}";
        $section .= "{$previous}% → {$current}% ({$deltaStr}%)\n\n";

        return $section;
    }

    /**
     * Extract PR URL from gh CLI output.
     */
    private function extractPrUrl(string $output): ?string
    {
        // gh CLI outputs PR URL on successful creation
        if (preg_match('#https://github\.com/[^/]+/[^/]+/pull/\d+#', $output, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Extract PR number from URL.
     */
    private function extractPrNumber(?string $url): ?int
    {
        if ($url === null) {
            return null;
        }

        if (preg_match('#/pull/(\d+)$#', $url, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Execute a command using Symfony Process.
     *
     * @param  array<int, string>  $command  Command and arguments
     */
    private function runCommand(array $command): Process
    {
        $cwd = getcwd();

        // @codeCoverageIgnoreStart
        if ($cwd === false) {
            $cwd = null;
        }
        // @codeCoverageIgnoreEnd

        $process = new Process($command, $cwd);
        $process->setTimeout(300); // 5 minutes timeout for long-running commands
        $process->run();

        return $process;
    }
}
