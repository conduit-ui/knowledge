<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

class CodeRabbitService
{
    private const SEVERITY_MAP = [
        'critical' => ['security', 'vulnerability', 'injection', 'exploit', 'crash', 'data loss', 'corruption', 'race condition'],
        'high' => ['bug', 'error', 'broken', 'fail', 'incorrect', 'wrong', 'memory leak', 'performance issue', 'missing validation'],
        'medium' => ['refactor', 'improvement', 'simplify', 'consider', 'suggest', 'better', 'cleaner', 'readability', 'maintainability'],
        'low' => ['nit', 'style', 'typo', 'naming', 'formatting', 'comment', 'documentation', 'minor'],
    ];

    private const SEVERITY_ORDER = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    private const CONFIDENCE_MAP = ['critical' => 85, 'high' => 75, 'medium' => 60, 'low' => 45];

    /**
     * @param  string|null  $workingDirectory  Optional working directory for gh CLI commands
     */
    public function __construct(
        private readonly ?string $workingDirectory = null
    ) {}

    /**
     * Fetch CodeRabbit review comments from a PR using gh CLI.
     *
     * @return array{comments: list<array{body: string, path: string|null, author: string}>, pr_title: string, pr_url: string}|null
     */
    public function fetchReviewComments(int $prNumber): ?array
    {
        $prData = $this->fetchPrMetadata($prNumber);
        if ($prData === null) {
            return null;
        }

        $comments = $this->fetchPrComments($prNumber);
        if ($comments === null) {
            return null;
        }

        $coderabbitComments = array_values(array_filter($comments, function (array $comment): bool {
            return str_contains(strtolower($comment['author']), 'coderabbit');
        }));

        return [
            'comments' => $coderabbitComments,
            'pr_title' => $prData['title'],
            'pr_url' => $prData['url'],
        ];
    }

    /**
     * Parse review comments into structured findings.
     *
     * @param  list<array{body: string, path: string|null, author: string}>  $comments
     * @return list<array{title: string, content: string, file: string|null, severity: string, confidence: int}>
     */
    public function parseFindings(array $comments): array
    {
        $findings = [];

        foreach ($comments as $comment) {
            $body = $comment['body'] ?? '';
            if (trim($body) === '') {
                continue;
            }

            $sections = $this->splitIntoFindings($body, $comment['path'] ?? null);
            foreach ($sections as $section) {
                $findings[] = $section;
            }
        }

        return $findings;
    }

    /**
     * Filter findings by minimum severity level.
     *
     * @param  list<array{title: string, content: string, file: string|null, severity: string, confidence: int}>  $findings
     * @return list<array{title: string, content: string, file: string|null, severity: string, confidence: int}>
     */
    public function filterBySeverity(array $findings, string $minSeverity): array
    {
        $minOrder = self::SEVERITY_ORDER[$minSeverity] ?? 1;

        return array_values(array_filter($findings, function (array $finding) use ($minOrder): bool {
            $order = self::SEVERITY_ORDER[$finding['severity']] ?? 1;

            return $order >= $minOrder;
        }));
    }

    /**
     * Split a comment body into individual findings.
     *
     * @return list<array{title: string, content: string, file: string|null, severity: string, confidence: int}>
     */
    private function splitIntoFindings(string $body, ?string $file): array
    {
        $findings = [];

        // Split on markdown headers (##, ###) or numbered list items that look like distinct findings
        $sections = preg_split('/(?=^#{2,3}\s+)/m', $body);

        if ($sections === false || count($sections) <= 1) {
            // No headers found - treat as single finding
            $severity = $this->detectSeverity($body);

            return [[
                'title' => $this->extractTitle($body),
                'content' => trim($body),
                'file' => $file,
                'severity' => $severity,
                'confidence' => self::CONFIDENCE_MAP[$severity],
            ]];
        }

        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '') {
                continue;
            }

            $severity = $this->detectSeverity($section);
            $findings[] = [
                'title' => $this->extractTitle($section),
                'content' => trim($section),
                'file' => $file,
                'severity' => $severity,
                'confidence' => self::CONFIDENCE_MAP[$severity],
            ];
        }

        return $findings;
    }

    /**
     * Detect severity from content keywords.
     */
    private function detectSeverity(string $content): string
    {
        $lower = strtolower($content);

        foreach (self::SEVERITY_MAP as $severity => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $severity;
                }
            }
        }

        return 'medium';
    }

    /**
     * Extract a title from the first line or header of a section.
     */
    private function extractTitle(string $section): string
    {
        $lines = explode("\n", trim($section));
        $firstLine = trim($lines[0]);

        // Remove markdown header markers
        $title = (string) preg_replace('/^#{1,6}\s+/', '', $firstLine);

        // Truncate long titles
        if (mb_strlen($title) > 120) {
            $title = mb_substr($title, 0, 117).'...';
        }

        return $title;
    }

    /**
     * @return array{title: string, url: string}|null
     */
    private function fetchPrMetadata(int $prNumber): ?array
    {
        $process = $this->runGhCommand([
            'pr', 'view', (string) $prNumber,
            '--json', 'title,url',
            '--jq', '.',
        ]);

        if (! $process->isSuccessful()) {
            return null;
        }

        /** @var array{title: string, url: string}|null $data */
        $data = json_decode($process->getOutput(), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return list<array{body: string, path: string|null, author: string}>|null
     */
    private function fetchPrComments(int $prNumber): ?array
    {
        // Fetch issue comments (top-level PR comments)
        $issueProcess = $this->runGhCommand([
            'api', "repos/{owner}/{repo}/issues/{$prNumber}/comments",
            '--jq', '[.[] | {body: .body, path: null, author: .user.login}]',
        ]);

        // Fetch review comments (inline code comments)
        $reviewProcess = $this->runGhCommand([
            'api', "repos/{owner}/{repo}/pulls/{$prNumber}/comments",
            '--jq', '[.[] | {body: .body, path: .path, author: .user.login}]',
        ]);

        $comments = [];

        if ($issueProcess->isSuccessful()) {
            /** @var list<array{body: string, path: string|null, author: string}>|null $issueComments */
            $issueComments = json_decode($issueProcess->getOutput(), true);
            if (is_array($issueComments)) {
                $comments = array_merge($comments, $issueComments);
            }
        }

        if ($reviewProcess->isSuccessful()) {
            /** @var list<array{body: string, path: string|null, author: string}>|null $reviewComments */
            $reviewComments = json_decode($reviewProcess->getOutput(), true);
            if (is_array($reviewComments)) {
                $comments = array_merge($comments, $reviewComments);
            }
        }

        if ($comments === [] && ! $issueProcess->isSuccessful() && ! $reviewProcess->isSuccessful()) {
            return null;
        }

        return $comments;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runGhCommand(array $command): Process
    {
        $cwd = $this->workingDirectory ?? getcwd();

        // @codeCoverageIgnoreStart
        if ($cwd === false) {
            $cwd = null;
        }
        // @codeCoverageIgnoreEnd

        $process = new Process(
            ['gh', ...$command],
            is_string($cwd) ? $cwd : null
        );

        $process->setTimeout(30);
        $process->run();

        return $process;
    }
}
