<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeRabbitService;
use App\Services\QdrantService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class CoderabbitExtractCommand extends Command
{
    protected $signature = 'coderabbit:extract
                            {--pr= : PR number to extract CodeRabbit reviews from}
                            {--min-severity=low : Minimum severity to include (critical, high, medium, low)}
                            {--dry-run : Show findings without adding to knowledge base}';

    protected $description = 'Extract CodeRabbit review findings from a PR and add to knowledge base';

    private const VALID_SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public function handle(CodeRabbitService $coderabbit, QdrantService $qdrant): int
    {
        /** @var string|null $prOption */
        $prOption = is_string($this->option('pr')) ? $this->option('pr') : null;
        /** @var string $minSeverity */
        $minSeverity = is_string($this->option('min-severity')) ? $this->option('min-severity') : 'low';
        /** @var bool $dryRun */
        $dryRun = (bool) $this->option('dry-run');

        if ($prOption === null || $prOption === '') {
            error('The --pr option is required.');

            return self::FAILURE;
        }

        if (! is_numeric($prOption) || (int) $prOption < 1) {
            error('The --pr option must be a positive integer.');

            return self::FAILURE;
        }

        $prNumber = (int) $prOption;

        if (! in_array($minSeverity, self::VALID_SEVERITIES, true)) {
            error('Invalid --min-severity. Valid: '.implode(', ', self::VALID_SEVERITIES));

            return self::FAILURE;
        }

        // Fetch CodeRabbit review comments
        /** @var array{comments: list<array{body: string, path: string|null, author: string}>, pr_title: string, pr_url: string}|null $reviewData */
        $reviewData = spin(
            fn () => $coderabbit->fetchReviewComments($prNumber),
            "Fetching CodeRabbit reviews for PR #{$prNumber}..."
        );

        if ($reviewData === null) {
            error("Failed to fetch PR #{$prNumber}. Ensure gh CLI is authenticated and the PR exists.");

            return self::FAILURE;
        }

        if ($reviewData['comments'] === []) {
            warning("No CodeRabbit review comments found on PR #{$prNumber}.");

            return self::SUCCESS;
        }

        info('Found '.count($reviewData['comments'])." CodeRabbit comment(s) on PR #{$prNumber}: {$reviewData['pr_title']}");

        // Parse into structured findings
        $findings = $coderabbit->parseFindings($reviewData['comments']);

        if ($findings === []) {
            warning('No actionable findings extracted from CodeRabbit reviews.');

            return self::SUCCESS;
        }

        // Filter by minimum severity
        $findings = $coderabbit->filterBySeverity($findings, $minSeverity);

        if ($findings === []) {
            warning("No findings meet the minimum severity threshold: {$minSeverity}");

            return self::SUCCESS;
        }

        info(count($findings)." finding(s) extracted (min severity: {$minSeverity})");

        if ($dryRun) {
            $this->displayFindings($findings, $prNumber);
            info('Dry run complete. No entries added to knowledge base.');

            return self::SUCCESS;
        }

        // Add findings to knowledge base
        $added = 0;
        $failed = 0;

        foreach ($findings as $index => $finding) {
            $semanticId = "knowledge-pr-{$prNumber}-coderabbit-".($index + 1);

            $data = [
                'id' => $semanticId,
                'title' => "[PR #{$prNumber}] {$finding['title']}",
                'content' => $finding['content'],
                'tags' => array_filter(['coderabbit', 'code-review', "pr-{$prNumber}", $finding['file']]),
                'category' => 'architecture',
                'priority' => $finding['severity'],
                'confidence' => $finding['confidence'],
                'status' => 'draft',
                'source' => $reviewData['pr_url'],
                'ticket' => "PR-{$prNumber}",
                'evidence' => "Extracted from CodeRabbit review on PR #{$prNumber}",
                'last_verified' => now()->toIso8601String(),
            ];

            try {
                $success = $qdrant->upsert($data, 'default', false);
                if ($success) {
                    $added++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->displayFindings($findings, $prNumber);

        info("{$added} finding(s) added to knowledge base.");
        if ($failed > 0) {
            warning("{$failed} finding(s) failed to store.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{title: string, content: string, file: string|null, severity: string, confidence: int}>  $findings
     */
    private function displayFindings(array $findings, int $prNumber): void
    {
        $rows = [];
        foreach ($findings as $index => $finding) {
            $rows[] = [
                "knowledge-pr-{$prNumber}-coderabbit-".($index + 1),
                Str::limit($finding['title'], 50),
                strtoupper($finding['severity']),
                "{$finding['confidence']}%",
                $finding['file'] ?? 'N/A',
            ];
        }

        table(
            ['Semantic ID', 'Title', 'Severity', 'Confidence', 'File'],
            $rows
        );
    }
}
