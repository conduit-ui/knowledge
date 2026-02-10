<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\SparseEmbeddingServiceInterface;
use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\ConnectionException;
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
     *     evidence?: string|null
     * }  $entry
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
     * Search entries using semantic similarity.
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
     *     evidence: ?string
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
     *     evidence: ?string
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

        return collect($results)->map(function (array $result): array {
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
            ];
        });
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
     *     evidence: ?string
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

        return collect($points)->map(function (array $point): array {
            $payload = $point['payload'] ?? [];

            return [
                'id' => $point['id'],
                'score' => $point['score'] ?? 0.0,
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
            ];
        });
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

        return collect($points)->map(function (array $point): array {
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
            ];
        });
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
     *     evidence: ?string
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

        if (empty($points)) {
            return null;
        }

        $point = $points[0];
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
        ];
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

        return $this->upsert($entry, $project);
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

        return $this->upsert($entry, $project);
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
     *     status?: string
     * }  $filters
     * @return array<string, mixed>|null
     */
    private function buildFilter(array $filters): ?array
    {
        if ($filters === []) {
            return null;
        }

        $must = [];

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
