<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\EntryMetadataService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use App\Services\TieredSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search knowledge entries using semantic vector search with tiered retrieval. Returns results ranked by relevance, confidence, and freshness.')]
#[IsReadOnly]
#[IsIdempotent]
class RecallTool extends Tool
{
    public function __construct(
        private readonly TieredSearchService $tieredSearch,
        private readonly QdrantService $qdrant,
        private readonly EntryMetadataService $metadata,
        private readonly ProjectDetectorService $projectDetector,
    ) {}

    public function handle(Request $request): Response
    {
        /** @var string $query */
        $query = $request->get('query');

        if (! is_string($query) || strlen($query) < 2) {
            return Response::error('A search query of at least 2 characters is required.');
        }

        $project = is_string($request->get('project')) ? $request->get('project') : $this->projectDetector->detect();
        $limit = is_int($request->get('limit')) ? min($request->get('limit'), 20) : 5;
        $global = (bool) ($request->get('global') ?? false);

        $filters = array_filter([
            'category' => is_string($request->get('category')) ? $request->get('category') : null,
            'tag' => is_string($request->get('tag')) ? $request->get('tag') : null,
        ]);

        if ($global) {
            return $this->searchGlobal($query, $filters, $limit);
        }

        $results = $this->tieredSearch->search($query, $filters, $limit, project: $project);

        if ($results->isEmpty()) {
            return Response::text(json_encode([
                'results' => [],
                'meta' => [
                    'query' => $query,
                    'project' => $project,
                    'total' => 0,
                ],
            ], JSON_THROW_ON_ERROR));
        }

        $formatted = $results->map(fn (array $entry): array => $this->formatEntry($entry))->values()->all();

        return Response::text(json_encode([
            'results' => $formatted,
            'meta' => [
                'query' => $query,
                'project' => $project,
                'total' => count($formatted),
                'search_tier' => $results->first()['tier_label'] ?? 'unknown',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Natural language search query (e.g., "how do we handle database migrations", "Laravel testing patterns")')
                ->required(),
            'project' => $schema->string()
                ->description('Project namespace. Auto-detected from git if omitted.'),
            'category' => $schema->string()
                ->enum(['architecture', 'patterns', 'decisions', 'gotchas', 'debugging', 'testing', 'deployment', 'security'])
                ->description('Filter results by category.'),
            'tag' => $schema->string()
                ->description('Filter results by tag.'),
            'limit' => $schema->integer()
                ->description('Maximum results to return (default 5, max 20).')
                ->default(5),
            'global' => $schema->boolean()
                ->description('Search across all projects instead of just the current one.')
                ->default(false),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function searchGlobal(string $query, array $filters, int $limit): Response
    {
        $collections = $this->qdrant->listCollections();
        $allResults = [];

        foreach ($collections as $collection) {
            $projectName = str_replace('knowledge_', '', $collection);
            $results = $this->tieredSearch->search($query, $filters, $limit, project: $projectName);

            foreach ($results as $entry) {
                $entry['project'] = $projectName;
                $allResults[] = $this->formatEntry($entry);
            }
        }

        usort($allResults, fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score']);
        $allResults = array_slice($allResults, 0, $limit);

        return Response::text(json_encode([
            'results' => $allResults,
            'meta' => [
                'query' => $query,
                'project' => 'global',
                'total' => count($allResults),
                'collections_searched' => count($collections),
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function formatEntry(array $entry): array
    {
        $effectiveConfidence = $this->metadata->calculateEffectiveConfidence($entry);
        $isStale = $this->metadata->isStale($entry);

        return [
            'id' => $entry['id'],
            'title' => $entry['title'] ?? '',
            'content' => $entry['content'] ?? '',
            'category' => $entry['category'] ?? null,
            'tags' => $entry['tags'] ?? [],
            'confidence' => $effectiveConfidence,
            'freshness' => $isStale ? 'stale' : 'fresh',
            'relevance_score' => round((float) ($entry['tiered_score'] ?? $entry['score'] ?? 0.0), 3),
            'project' => $entry['project'] ?? $entry['_project'] ?? null,
        ];
    }
}
