<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\SparseEmbeddingServiceInterface;
use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\ConnectionException;
use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Exceptions\Qdrant\EmbeddingException;
use App\Exceptions\Qdrant\UpsertException;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\DeletePoints;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\GetPoints;
use App\Integrations\Qdrant\Requests\HybridSearchPoints;
use App\Integrations\Qdrant\Requests\ScrollPoints;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\ClientException;

class QdrantService
{
    private QdrantConnector $connector;

    private ?SparseEmbeddingServiceInterface $sparseEmbeddingService = null;

    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly int $vectorSize = 384,
        private readonly float $scoreThreshold = 0.7,
        private readonly int $cacheTtl = 604800, // 7 days
        private readonly bool $secure = false,
        private readonly bool $hybridEnabled = false,
        private readonly ?KnowledgeCacheService $cacheService = null,
    ) {
        $this->connector = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
            secure: $this->secure,
        );
    }

    /**
     * Set the sparse embedding service for hybrid search.
     */
    public function setSparseEmbeddingService(SparseEmbeddingServiceInterface $service): void
    {
        $this->sparseEmbeddingService = $service;
    }

    /**
     * Get the cache service instance.
     */
    public function getCacheService(): ?KnowledgeCacheService
    {
        return $this->cacheService;
    }

    /**
     * Ensure collection exists for the given project namespace.
     */
    public function ensureCollection(string $project = 'default'): bool
    {
        $collectionName = $this->getCollectionName($project);

        try {
            // Check if collection exists
            $response = $this->connector->send(new GetCollectionInfo($collectionName));

            if ($response->successful()) {
                return true;
            }

            // Collection doesn't exist (404), create it
            if ($response->status() === 404) {
                $createResponse = $this->connector->send(
                    new CreateCollection($collectionName, $this->vectorSize, 'Cosine', $this->hybridEnabled)
                );

                if (! $createResponse->successful()) {
                    $error = $createResponse->json();
                    throw CollectionCreationException::withReason(
                        $collectionName,
                        $error['status']['error'] ?? json_encode($error)
                    );
                }

                return true;
            }

            throw CollectionCreationException::withReason($collectionName, 'Unexpected response: '.$response->status());
        } catch (ClientException $e) {
            throw ConnectionException::withMessage($e->getMessage());
        }
    }

    /**
     * Add or update an entry in Qdrant.
     *
     * @param  array{
     *     id: string|int,
     *     title: string,
     *     content: string,
     *     tags?: array<string>,
     *     category?: string,
     *     module?: string,
     *     priority?: string,
     *     status?: string,
     *     confidence?: int,
     *     usage_count?: int,
     *     created_at?: string,
     *     updated_at?: string,
     *     last_verified?: string|null,
     *     evidence?: string|null,
     *     superseded_by?: string,
     *     superseded_date?: string,
     *     superseded_reason?: string
     * }  $entry
     *
     * @throws DuplicateEntryException
     */
    public function upsert(array $entry, string $project = 'default', bool $checkDuplicates = true): bool
    {
        $this->ensureCollection($project);

        // Generate embedding for searchable text (title + content)
        $text = $entry['title'].' '.$entry['content'];
        $vector = $this->getCachedEmbedding($text);

        if ($vector === []) {
            throw EmbeddingException::generationFailed($text);
        }

        // Check for duplicates when requested (for new entries)
        if ($checkDuplicates) {
            $contentHash = hash('sha256', $entry['title'].$entry['content']);
            $similar = $this->findSimilar($vector, $project, 0.95);

            foreach ($similar as $existing) {
                $existingHash = hash('sha256', $existing['title'].$existing['content']);
                if ($existingHash === $contentHash) {
                    throw DuplicateEntryException::hashMatch($existing['id'], $contentHash);
                }
            }

            if ($similar->isNotEmpty()) {
                $topMatch = $similar->first();
                throw DuplicateEntryException::similarityMatch($topMatch['id'], $topMatch['score']);
            }
        }

        // Store full entry data in payload
        $payload = [
            'title' => $entry['title'],
            'content' => $entry['content'],
            'tags' => $entry['tags'] ?? [],
            'category' => $entry['category'] ?? null,
            'module' => $entry['module'] ?? null,
            'priority' => $entry['priority'] ?? null,
            'status' => $entry['status'] ?? null,
            'confidence' => $entry['confidence'] ?? 0,
            'usage_count' => $entry['usage_count'] ?? 0,
            'created_at' => $entry['created_at'] ?? now()->toIso8601String(),
            'updated_at' => $entry['updated_at'] ?? now()->toIso8601String(),
            'last_verified' => $entry['last_verified'] ?? null,
            'evidence' => $entry['evidence'] ?? null,
            'superseded_by' => $entry['superseded_by'] ?? null,
            'superseded_date' => $entry['superseded_date'] ?? null,
            'superseded_reason' => $entry['superseded_reason'] ?? null,
        ];

        // Build point with appropriate vector format
        $point = [
            'id' => $entry['id'],
            'payload' => $payload,
        ];

        if ($this->hybridEnabled && $this->sparseEmbeddingService instanceof \App\Contracts\SparseEmbeddingServiceInterface) {
            $sparseVector = $this->sparseEmbeddingService->generate($text);
            $point['vector'] = [
                'dense' => $vector,
                'sparse' => $sparseVector,
            ];
        } else {
            $point['vector'] = $vector;
        }

        $response = $this->connector->send(
            new UpsertPoints(
                $this->getCollectionName($project),
                [$point]
            )
        );

        if (! $response->successful()) {
            $error = $response->json();
            throw UpsertException::withReason($error['status']['error'] ?? json_encode($error));
        }

        $this->cacheService?->invalidateOnMutation();

        return true;
    }

    /**
     * Mark an existing entry as superseded by a new entry.
     */
    public function markSuperseded(
        string|int $existingId,
        string|int $newId,
        string $reason = 'Updated with newer knowledge',
        string $project = 'default'
    ): bool {
        return $this->updateFields($existingId, [
            'superseded_by' => (string) $newId,
            'superseded_date' => now()->toIso8601String(),
            'superseded_reason' => $reason,
        ], $project);
    }

    /**
     * Find entries similar to the given vector above a threshold.
     *
     * @param  array<float>  $vector
     * @return Collection<int, array{id: string|int, score: float, title: string, content: string}>
     */
    public function findSimilar(array $vector, string $project = 'default', float $threshold = 0.95): Collection
    {
        $this->ensureCollection($project);

        // Exclude already-superseded entries from duplicate detection
        $filter = [
            'must' => [
                [
                    'is_empty' => ['key' => 'superseded_by'],
                ],
            ],
        ];

        $response = $this->connector->send(
            new SearchPoints(
                $this->getCollectionName($project),
                $vector,
                5,
                $threshold,
                $filter
            )
        );

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $results = $data['result'] ?? [];

        return collect($results)->map(fn (array $result): array => [
            'id' => $result['id'],
            'score' => $result['score'] ?? 0.0,
            'title' => $result['payload']['title'] ?? '',
            'content' => $result['payload']['content'] ?? '',
        ]);
    }

    /**
     * Get supersession history for an entry (entries it superseded and entries that supersede it).
     *
     * @return array{supersedes: array<int, array<string, mixed>>, superseded_by: array<string, mixed>|null}
     */
    public function getSupersessionHistory(string|int $id, string $project = 'default'): array
    {
        $history = [
            'supersedes' => [],
            'superseded_by' => null,
        ];

        $entry = $this->getById($id, $project);

        if ($entry === null) {
            return $history;
        }

        // Check if this entry is superseded by another
        $supersededBy = $entry['superseded_by'] ?? null;
        if ($supersededBy !== null && $supersededBy !== '') {
            $successor = $this->getById($supersededBy, $project);
            if ($successor !== null) {
                $history['superseded_by'] = $successor;
            }
        }

        // Find entries that this entry superseded (entries whose superseded_by == this id)
        $this->ensureCollection($project);
        $filter = [
            'must' => [
                [
                    'key' => 'superseded_by',
                    'match' => ['value' => (string) $id],
                ],
            ],
        ];

        $response = $this->connector->send(
            new ScrollPoints(
                $this->getCollectionName($project),
                100,
                $filter,
                null
            )
        );

        if ($response->successful()) {
            $data = $response->json();
            $points = $data['result']['points'] ?? [];

            $history['supersedes'] = array_map(fn (array $point): array => $this->mapPointToEntry($point), $points);
        }

        return $history;
    }

    /**
     * Search entries using semantic similarity.
     *
     * @param  array{
     *     tag?: string,
     *     category?: string,
     *     module?: string,
     *     priority?: string,
     *     status?: string,
     *     include_superseded?: bool
     * }  $filters
     * @return Collection<int, array{
     *     id: string|int,
     *     score: float,
     *     title: string,
     *     content: string,
     *     tags: array<string>,
     *     category: ?string,
     *     module: ?string,
     *     priority: ?string,
     *     status: ?string,
     *     confidence: int,
     *     usage_count: int,
     *     created_at: string,
     *     updated_at: string,
     *     last_verified: ?string,
     *     evidence: ?string,
     *     superseded_by: ?string,
     *     superseded_date: ?string,
     *     superseded_reason: ?string
     * }>
     */
    public function search(
        string $query,
        array $filters = [],
        int $limit = 20,
        string $project = 'default'
    ): Collection {
        if ($this->cacheService instanceof KnowledgeCacheService) {
            $cached = $this->cacheService->rememberSearch($query, $filters, $limit, $project, fn (): array => $this->executeSearch($query, $filters, $limit, $project)->toArray());

            return collect($cached);
        }

        return $this->executeSearch($query, $filters, $limit, $project);
    }

    /**
     * Execute the actual search against Qdrant.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{
     *     id: string|int,
     *     score: float,
     *     title: string,
     *     content: string,
     *     tags: array<string>,
     *     category: ?string,
     *     module: ?string,
     *     priority: ?string,
     *     status: ?string,
     *     confidence: int,
     *     usage_count: int,
     *     created_at: string,
     *     updated_at: string,
     *     last_verified: ?string,
     *     evidence: ?string,
     *     superseded_by: ?string,
     *     superseded_date: ?string,
     *     superseded_reason: ?string
     * }>
     */
    private function executeSearch(
        string $query,
        array $filters,
        int $limit,
        string $project
    ): Collection {
        $this->ensureCollection($project);

        // Generate query embedding
        $queryVector = $this->getCachedEmbedding($query);

        if ($queryVector === []) {
            return collect();
        }

        // Build Qdrant filter from search filters
        $qdrantFilter = $this->buildFilter($filters);

        $response = $this->connector->send(
            new SearchPoints(
                $this->getCollectionName($project),
                $queryVector,
                $limit,
                $this->scoreThreshold,
                $qdrantFilter
            )
        );

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $results = $data['result'] ?? [];

        return collect($results)->map(fn (array $result): array => $this->mapResultToEntry($result));
    }

    /**
     * Hybrid search using both dense and sparse vectors with RRF fusion.
     *
     * Falls back to dense-only search if hybrid is not enabled or sparse embedding fails.
     *
     * @param  array{
     *     tag?: string,
     *     category?: string,
     *     module?: string,
     *     priority?: string,
     *     status?: string
     * }  $filters
     * @return Collection<int, array{
     *     id: string|int,
     *     score: float,
     *     title: string,
     *     content: string,
     *     tags: array<string>,
     *     category: ?string,
     *     module: ?string,
     *     priority: ?string,
     *     status: ?string,
     *     confidence: int,
     *     usage_count: int,
     *     created_at: string,
     *     updated_at: string,
     *     last_verified: ?string,
     *     evidence: ?string,
     *     superseded_by: ?string,
     *     superseded_date: ?string,
     *     superseded_reason: ?string
     * }>
     */
    public function hybridSearch(
        string $query,
        array $filters = [],
        int $limit = 20,
        int $prefetchLimit = 40,
        string $project = 'default'
    ): Collection {
        // Fall back to dense search if hybrid not enabled or no sparse service
        if (! $this->hybridEnabled || ! $this->sparseEmbeddingService instanceof \App\Contracts\SparseEmbeddingServiceInterface) {
            return $this->search($query, $filters, $limit, $project);
        }

        $this->ensureCollection($project);

        // Generate dense embedding
        $denseVector = $this->getCachedEmbedding($query);

        if ($denseVector === []) {
            return collect();
        }

        // Generate sparse embedding
        $sparseVector = $this->sparseEmbeddingService->generate($query);

        // Fall back to dense search if sparse embedding fails
        if ($sparseVector['indices'] === []) {
            return $this->search($query, $filters, $limit, $project);
        }

        // Build Qdrant filter from search filters
        $qdrantFilter = $this->buildFilter($filters);

        $response = $this->connector->send(
            new HybridSearchPoints(
                $this->getCollectionName($project),
                $denseVector,
                $sparseVector,
                $limit,
                $prefetchLimit,
                $qdrantFilter
            )
        );

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $points = $data['result']['points'] ?? [];

        return collect($points)->map(fn (array $point): array => $this->mapResultToEntry($point));
    }

    /**
     * Scroll/list all entries without requiring a search query.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{
     *     id: string|int,
     *     title: string,
     *     content: string,
     *     tags: array<string>,
     *     category: ?string,
     *     module: ?string,
     *     priority: ?string,
     *     status: ?string,
     *     confidence: int,
     *     usage_count: int,
     *     created_at: string,
     *     updated_at: string,
     *     last_verified: ?string,
     *     evidence: ?string
     * }>
     *
     * @codeCoverageIgnore Qdrant API integration - tested via integration tests
     */
    public function scroll(
        array $filters = [],
        int $limit = 20,
        string $project = 'default',
        string|int|null $offset = null
    ): Collection {
        $this->ensureCollection($project);

        $qdrantFilter = $filters === [] ? null : $this->buildFilter($filters);

        $response = $this->connector->send(
            new ScrollPoints(
                $this->getCollectionName($project),
                $limit,
                $qdrantFilter,
                $offset
            )
        );

        if (! $response->successful()) {
            return collect();
        }

        $data = $response->json();
        $points = $data['result']['points'] ?? [];

        return collect($points)->map(fn (array $point): array => $this->mapPointToEntry($point));
    }

    /**
     * Delete entries by ID.
     *
     * @param  array<string|int>  $ids
     */
    public function delete(array $ids, string $project = 'default'): bool
    {
        $this->ensureCollection($project);

        $response = $this->connector->send(
            new DeletePoints($this->getCollectionName($project), $ids)
        );

        if ($response->successful()) {
            $this->cacheService?->invalidateOnMutation();

            return true;
        }

        return false;
    }

    /**
     * Get entry by ID.
     *
     * @return array{
     *     id: string|int,
     *     title: string,
     *     content: string,
     *     tags: array<string>,
     *     category: ?string,
     *     module: ?string,
     *     priority: ?string,
     *     status: ?string,
     *     confidence: int,
     *     usage_count: int,
     *     created_at: string,
     *     updated_at: string,
     *     last_verified: ?string,
     *     evidence: ?string,
     *     superseded_by: ?string,
     *     superseded_date: ?string,
     *     superseded_reason: ?string
     * }|null
     */
    public function getById(string|int $id, string $project = 'default'): ?array
    {
        $this->ensureCollection($project);

        $response = $this->connector->send(
            new GetPoints($this->getCollectionName($project), [$id])
        );

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $points = $data['result'] ?? [];

        if ($points === []) {
            return null;
        }

        return $this->mapPointToEntry($points[0]);
    }

    /**
     * Increment usage count for an entry.
     */
    public function incrementUsage(string|int $id, string $project = 'default'): bool
    {
        $entry = $this->getById($id, $project);

        if ($entry === null) {
            return false;
        }

        $entry['usage_count']++;
        $entry['updated_at'] = now()->toIso8601String();

        return $this->upsert($entry, $project, false);
    }

    /**
     * Update specific fields of an entry.
     *
     * @param  array<string, mixed>  $fields
     */
    public function updateFields(string|int $id, array $fields, string $project = 'default'): bool
    {
        $entry = $this->getById($id, $project);

        if ($entry === null) {
            return false;
        }

        // Merge updated fields
        $entry = array_merge($entry, $fields);
        $entry['updated_at'] = now()->toIso8601String();

        return $this->upsert($entry, $project, false);
    }

    /**
     * Get cached embedding or generate new one.
     *
     * @return array<float>
     */
    private function getCachedEmbedding(string $text): array
    {
        if (! config('search.qdrant.cache_embeddings', true)) {
            return $this->embeddingService->generate($text);
        }

        if ($this->cacheService instanceof KnowledgeCacheService) {
            return $this->cacheService->rememberEmbedding($text, fn (): array => $this->embeddingService->generate($text));
        }

        $cacheKey = 'embedding:'.hash('xxh128', $text);

        /** @var array<float> */
        return Cache::remember(
            $cacheKey,
            $this->cacheTtl,
            fn (): array => $this->embeddingService->generate($text)
        );
    }

    /**
     * Build Qdrant filter from search filters.
     *
     * @param  array{
     *     tag?: string,
     *     category?: string,
     *     module?: string,
     *     priority?: string,
     *     status?: string,
     *     include_superseded?: bool
     * }  $filters
     * @return array<string, mixed>|null
     */
    private function buildFilter(array $filters): ?array
    {
        $includeSuperseded = (bool) ($filters['include_superseded'] ?? false);
        unset($filters['include_superseded']);

        $must = [];

        // Exclude superseded entries by default
        if (! $includeSuperseded) {
            $must[] = [
                'is_empty' => ['key' => 'superseded_by'],
            ];
        }

        // Exact match filters
        foreach (['category', 'module', 'priority', 'status'] as $field) {
            if (isset($filters[$field])) {
                $must[] = [
                    'key' => $field,
                    'match' => ['value' => $filters[$field]],
                ];
            }
        }

        // Tag filter (array contains)
        if (isset($filters['tag'])) {
            $must[] = [
                'key' => 'tags',
                'match' => ['value' => $filters['tag']],
            ];
        }

        return $must === [] ? null : ['must' => $must];
    }

    /**
     * Map a Qdrant search result (with score) to an entry array.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mapResultToEntry(array $result): array
    {
        $payload = $result['payload'] ?? [];

        return [
            'id' => $result['id'],
            'score' => $result['score'] ?? 0.0,
            'title' => $payload['title'] ?? '',
            'content' => $payload['content'] ?? '',
            'tags' => $payload['tags'] ?? [],
            'category' => $payload['category'] ?? null,
            'module' => $payload['module'] ?? null,
            'priority' => $payload['priority'] ?? null,
            'status' => $payload['status'] ?? null,
            'confidence' => $payload['confidence'] ?? 0,
            'usage_count' => $payload['usage_count'] ?? 0,
            'created_at' => $payload['created_at'] ?? '',
            'updated_at' => $payload['updated_at'] ?? '',
            'last_verified' => $payload['last_verified'] ?? null,
            'evidence' => $payload['evidence'] ?? null,
            'superseded_by' => $payload['superseded_by'] ?? null,
            'superseded_date' => $payload['superseded_date'] ?? null,
            'superseded_reason' => $payload['superseded_reason'] ?? null,
        ];
    }

    /**
     * Map a Qdrant point (without score) to an entry array.
     *
     * @param  array<string, mixed>  $point
     * @return array<string, mixed>
     */
    private function mapPointToEntry(array $point): array
    {
        $payload = $point['payload'] ?? [];

        return [
            'id' => $point['id'],
            'title' => $payload['title'] ?? '',
            'content' => $payload['content'] ?? '',
            'tags' => $payload['tags'] ?? [],
            'category' => $payload['category'] ?? null,
            'module' => $payload['module'] ?? null,
            'priority' => $payload['priority'] ?? null,
            'status' => $payload['status'] ?? null,
            'confidence' => $payload['confidence'] ?? 0,
            'usage_count' => $payload['usage_count'] ?? 0,
            'created_at' => $payload['created_at'] ?? '',
            'updated_at' => $payload['updated_at'] ?? '',
            'last_verified' => $payload['last_verified'] ?? null,
            'evidence' => $payload['evidence'] ?? null,
            'superseded_by' => $payload['superseded_by'] ?? null,
            'superseded_date' => $payload['superseded_date'] ?? null,
            'superseded_reason' => $payload['superseded_reason'] ?? null,
        ];
    }

    /**
     * Get the total count of entries in a collection.
     *
     * @codeCoverageIgnore Qdrant API integration - tested via integration tests
     */
    public function count(string $project = 'default'): int
    {
        if ($this->cacheService instanceof KnowledgeCacheService) {
            /** @var array{points_count: int} $stats */
            $stats = $this->cacheService->rememberStats($project, fn (): array => ['points_count' => $this->executeCount($project)]);

            return $stats['points_count'];
        }

        return $this->executeCount($project);
    }

    /**
     * Execute the actual count query against Qdrant.
     *
     * @codeCoverageIgnore Qdrant API integration - tested via integration tests
     */
    private function executeCount(string $project): int
    {
        $this->ensureCollection($project);

        $response = $this->connector->send(
            new GetCollectionInfo($this->getCollectionName($project))
        );

        if (! $response->successful()) {
            return 0;
        }

        $data = $response->json();

        return $data['result']['points_count'] ?? 0;
    }

    /**
     * Get collection name for project namespace.
     */
    private function getCollectionName(string $project): string
    {
        return 'knowledge_'.str_replace(['/', '\\', ' '], '_', $project);
    }
}
