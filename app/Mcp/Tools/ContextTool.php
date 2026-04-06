<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\EntryMetadataService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Load project-relevant knowledge context. Returns entries grouped by category, ranked by usage and recency. Use at session start for deep project context.')]
#[IsReadOnly]
#[IsIdempotent]
class ContextTool extends Tool
{
    private const CHARS_PER_TOKEN = 4;

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

    public function __construct(
        private readonly QdrantService $qdrant,
        private readonly EntryMetadataService $metadata,
        private readonly ProjectDetectorService $projectDetector,
    ) {}

    public function handle(Request $request): Response
    {
        $project = is_string($request->get('project')) ? $request->get('project') : $this->projectDetector->detect();

        /** @var array<string>|null $categories */
        $categories = is_array($request->get('categories')) ? $request->get('categories') : null;
        $maxTokens = is_int($request->get('max_tokens')) ? min($request->get('max_tokens'), 16000) : 4000;
        $limit = is_int($request->get('limit')) ? min($request->get('limit'), 100) : 50;

        $entries = $this->fetchEntries($categories, $limit, $project);

        if ($entries === []) {
            $available = $this->qdrant->listCollections();
            $projects = array_map(
                fn (string $c): string => str_replace('knowledge_', '', $c),
                $available
            );

            return Response::text(json_encode([
                'project' => $project,
                'entries' => [],
                'total' => 0,
                'message' => "No knowledge entries found for project '{$project}'.",
                'available_projects' => array_values($projects),
            ], JSON_THROW_ON_ERROR));
        }

        $ranked = $this->rankEntries($entries);
        $grouped = $this->groupByCategory($ranked);
        $truncated = $this->truncateToTokenBudget($grouped, $maxTokens);

        $totalEntries = array_sum(array_map('count', $truncated));

        return Response::text(json_encode([
            'project' => $project,
            'categories' => $truncated,
            'total' => $totalEntries,
            'available' => count($entries),
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Project namespace. Auto-detected from git if omitted.'),
            'categories' => $schema->array()
                ->description('Filter to specific categories (e.g., ["architecture", "debugging"]).'),
            'max_tokens' => $schema->integer()
                ->description('Maximum approximate token budget for response (default 4000).')
                ->default(4000),
            'limit' => $schema->integer()
                ->description('Maximum entries to fetch (default 50).')
                ->default(50),
        ];
    }

    /**
     * @param  array<string>|null  $categories
     * @return array<int, array<string, mixed>>
     */
    private function fetchEntries(?array $categories, int $limit, string $project): array
    {
        if ($categories !== null && $categories !== []) {
            $entries = [];
            $perCategory = max(1, intdiv($limit, count($categories)));

            foreach ($categories as $category) {
                $results = $this->qdrant->scroll(
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

        return $this->qdrant->scroll([], $limit, $project)->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function rankEntries(array $entries): array
    {
        $now = time();

        usort($entries, function (array $a, array $b) use ($now): int {
            $scoreA = $this->entryScore($a, $now);
            $scoreB = $this->entryScore($b, $now);

            return $scoreB <=> $scoreA;
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
        $timestamp = is_string($updatedAt) && $updatedAt !== '' ? strtotime($updatedAt) : $now;

        if ($timestamp === false) {
            $timestamp = $now; // @codeCoverageIgnore
        }

        $daysAgo = max(1, (int) (($now - $timestamp) / 86400));

        return ($usageCount * 2.0) + (100.0 / $daysAgo);
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

            $grouped[$category][] = $this->formatEntry($entry);
        }

        $ordered = [];

        foreach (self::CATEGORY_ORDER as $cat) {
            if (isset($grouped[$cat])) {
                $ordered[$cat] = $grouped[$cat];
                unset($grouped[$cat]);
            }
        }

        ksort($grouped);

        return array_merge($ordered, $grouped);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function truncateToTokenBudget(array $grouped, int $maxTokens): array
    {
        $maxChars = $maxTokens * self::CHARS_PER_TOKEN;
        $charCount = 0;
        $result = [];

        foreach ($grouped as $category => $entries) {
            $categoryEntries = [];

            foreach ($entries as $entry) {
                $entryJson = json_encode($entry, JSON_THROW_ON_ERROR);
                $entryLen = strlen($entryJson);

                if ($charCount + $entryLen > $maxChars) {
                    break 2;
                }

                $categoryEntries[] = $entry;
                $charCount += $entryLen;
            }

            if ($categoryEntries !== []) {
                $result[$category] = $categoryEntries;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function formatEntry(array $entry): array
    {
        $effectiveConfidence = $this->metadata->calculateEffectiveConfidence($entry);

        return [
            'id' => $entry['id'],
            'title' => $entry['title'] ?? '',
            'content' => $entry['content'] ?? '',
            'confidence' => $effectiveConfidence,
            'freshness' => $this->metadata->isStale($entry) ? 'stale' : 'fresh',
            'priority' => $entry['priority'] ?? 'medium',
            'tags' => $entry['tags'] ?? [],
        ];
    }
}
