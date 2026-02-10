<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\GitContextService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class ContextCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'context
                            {--project= : Project namespace (auto-detected from git if omitted)}
                            {--categories= : Comma-separated categories to include (e.g. architecture,patterns,decisions,gotchas)}
                            {--max-tokens=4000 : Maximum approximate token count for output}
                            {--limit=50 : Maximum entries to fetch from Qdrant}
                            {--no-usage : Do not increment usage_count for accessed entries}';

    /**
     * @var string
     */
    protected $description = 'Load semantic session context for AI consumption';

    private const CHARS_PER_TOKEN = 4;

    /**
     * Default category display order.
     *
     * @var array<int, string>
     */
    private const CATEGORY_ORDER = [
        'architecture',
        'patterns',
        'decisions',
        'gotchas',
        'debugging',
        'testing',
        'deployment',
        'security',
    ];

    public function handle(QdrantService $qdrant, GitContextService $git): int
    {
        $project = $this->resolveProject($git);
        $categories = $this->resolveCategories();
        $maxTokens = (int) $this->option('max-tokens');
        $limit = (int) $this->option('limit');
        $trackUsage = $this->option('no-usage') !== true;

        $entries = $this->fetchEntries($qdrant, $categories, $limit, $project);

        if ($entries === []) {
            $this->line('No context entries found.');

            return self::SUCCESS;
        }

        $ranked = $this->rankEntries($entries);
        $grouped = $this->groupByCategory($ranked);
        $markdown = $this->formatMarkdown($grouped, $maxTokens, $project);

        foreach (explode("\n", $markdown) as $markdownLine) {
            $this->line($markdownLine);
        }

        if ($trackUsage) {
            $this->incrementUsageCounts($qdrant, $ranked, $maxTokens, $project);
        }

        return self::SUCCESS;
    }

    private function resolveProject(GitContextService $git): string
    {
        $explicit = $this->option('project');

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if ($git->isGitRepository()) {
            $repoPath = $git->getRepositoryPath();

            if (is_string($repoPath) && $repoPath !== '') {
                return basename($repoPath);
            }
        }

        return 'default';
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveCategories(): ?array
    {
        $raw = $this->option('categories');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return array_map('trim', explode(',', $raw));
    }

    /**
     * @param  array<int, string>|null  $categories
     * @return array<int, array<string, mixed>>
     */
    private function fetchEntries(QdrantService $qdrant, ?array $categories, int $limit, string $project): array
    {
        if ($categories !== null) {
            $entries = [];
            $perCategory = max(1, intdiv($limit, count($categories)));

            foreach ($categories as $category) {
                $results = $qdrant->scroll(
                    ['category' => $category],
                    $perCategory,
                    $project
                );

                foreach ($results->all() as $entry) {
                    $entries[] = $entry;
                }
            }

            return $entries;
        }

        return $qdrant->scroll([], $limit, $project)->all();
    }

    /**
     * Rank entries by recency and usage_count.
     *
     * Score = (usage_count * 2) + recency_days_inverse
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function rankEntries(array $entries): array
    {
        $now = time();

        usort($entries, function (array $a, array $b) use ($now): int {
            return $this->entryScore($b, $now) <=> $this->entryScore($a, $now);
        });

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function entryScore(array $entry, int $now): float
    {
        $usageCount = (int) ($entry['usage_count'] ?? 0);
        $updatedAt = $entry['updated_at'] ?? '';

        $timestamp = is_string($updatedAt) && $updatedAt !== ''
            ? strtotime($updatedAt)
            : $now;

        if ($timestamp === false) {
            $timestamp = $now;
        }

        $daysAgo = max(1, (int) (($now - $timestamp) / 86400));
        $recencyScore = 100.0 / $daysAgo;

        return ($usageCount * 2.0) + $recencyScore;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCategory(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $category = is_string($entry['category'] ?? null) && ($entry['category'] ?? '') !== ''
                ? $entry['category']
                : 'uncategorized';

            $grouped[$category][] = $entry;
        }

        // Sort categories by defined order
        $ordered = [];

        foreach (self::CATEGORY_ORDER as $cat) {
            if (isset($grouped[$cat])) {
                $ordered[$cat] = $grouped[$cat];
                unset($grouped[$cat]);
            }
        }

        // Append remaining categories alphabetically
        ksort($grouped);

        return array_merge($ordered, $grouped);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     */
    private function formatMarkdown(array $grouped, int $maxTokens, string $project): string
    {
        $maxChars = $maxTokens * self::CHARS_PER_TOKEN;
        $lines = [];
        $charCount = 0;

        $header = "# Session Context: {$project}";
        $lines[] = $header;
        $lines[] = '';
        $charCount += strlen($header) + 1;

        foreach ($grouped as $category => $entries) {
            $catHeader = '## '.ucfirst($category);
            $catHeaderLen = strlen($catHeader) + 2;

            if ($charCount + $catHeaderLen > $maxChars) {
                break;
            }

            $lines[] = $catHeader;
            $lines[] = '';
            $charCount += $catHeaderLen;

            foreach ($entries as $entry) {
                $block = $this->formatEntry($entry);
                $blockLen = strlen($block) + 1;

                if ($charCount + $blockLen > $maxChars) {
                    break 2;
                }

                $lines[] = $block;
                $charCount += $blockLen;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function formatEntry(array $entry): string
    {
        $title = $entry['title'] ?? 'Untitled';
        $content = $entry['content'] ?? '';
        $tags = $entry['tags'] ?? [];
        $priority = $entry['priority'] ?? 'medium';
        $confidence = $entry['confidence'] ?? 0;

        $lines = [];
        $lines[] = "### {$title}";
        $lines[] = '';

        $lines[] = "Priority: {$priority}";
        $lines[] = "Confidence: {$confidence}%";

        if (is_array($tags) && $tags !== []) {
            $lines[] = 'Tags: '.implode(', ', $tags);
        }

        $lines[] = '';
        $lines[] = $content;
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Increment usage_count for entries that fit within the token budget.
     *
     * @param  array<int, array<string, mixed>>  $ranked
     */
    private function incrementUsageCounts(QdrantService $qdrant, array $ranked, int $maxTokens, string $project): void
    {
        $maxChars = $maxTokens * self::CHARS_PER_TOKEN;
        $charCount = 0;

        foreach ($ranked as $entry) {
            $block = $this->formatEntry($entry);
            $blockLen = strlen($block);

            if ($charCount + $blockLen > $maxChars) {
                break;
            }

            $charCount += $blockLen;

            $id = $entry['id'] ?? null;

            if ($id !== null) {
                $qdrant->incrementUsage($id, $project);
            }
        }
    }
}
