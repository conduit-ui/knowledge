<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SparseEmbeddingServiceInterface;
use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\ConnectionException;
use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Exceptions\Qdrant\EmbeddingException;
use App\Exceptions\Qdrant\UpsertException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\RequestException;
use TheShit\Vector\Contracts\EmbeddingClient;
use TheShit\Vector\Data\ScoredPoint;
use TheShit\Vector\Qdrant;

class QdrantService
{
    private ?SparseEmbeddingServiceInterface $sparseEmbeddingService = null;

    public function __construct(
        private readonly EmbeddingClient $embeddingService,
        private readonly Qdrant $qdrant,
        private readonly int $vectorSize = 384,
        private readonly float $scoreThreshold = 0.7,
        private readonly int $cacheTtl = 604800, // 7 days
        private readonly bool $hybridEnabled = false,
        private readonly ?KnowledgeCacheService $cacheService = null,
    ) {}

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
            $this->qdrant->getCollection($collectionName);

            return true;
        } catch (RequestException $e) {
            if ($e->getResponse()->status() === 404) {
                try {
                    $sparseVectors = $this->hybridEnabled
                        ? ['sparse' => ['modifier' => 'idf']]
                        : null;

                    $this->qdrant->createCollection($collectionName, $this->vectorSize, 'Cosine', $sparseVectors);

                    return true;
                } catch (RequestException $createException) {
                    throw CollectionCreationException::withReason($collectionName, $createException->getMessage());
                }
            }

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

        $text = $entry['title'].' '.$entry['content'];
        $vector = $this->getCachedEmbedding($text);

        if ($vector === []) {
            throw EmbeddingException::generationFailed($text);
        }

        if ($checkDuplicates) {
            $fingerprint = $this->extractFingerprint($entry['tags'] ?? []);
            if ($fingerprint !== null) {
                $existing = $this->findByFingerprint($fingerprint, $project);
                if ($existing !== null) {
                    throw DuplicateEntryException::hashMatch($existing, $fingerprint);
                }
            }

            $commitHash = $entry['commit'] ?? null;
            if (is_string($commitHash) && $commitHash !== '') {
                $existing = $this->findByTitleAndCommit($entry['title'], $commitHash, $project);
                if ($existing !== null) {
                    throw DuplicateEntryException::hashMatch($existing, $entry['title'].'@'.$commitHash);
                }
            }

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
            'commit' => $entry['commit'] ?? null,
            'superseded_by' => $entry['superseded_by'] ?? null,
            'superseded_date' => $entry['superseded_date'] ?? null,
            'superseded_reason' => $entry['superseded_reason'] ?? null,
        ];

        $point = ['id' => $entry['id'], 'payload' => $payload];

        if ($this->hybridEnabled && $this->sparseEmbeddingService instanceof SparseEmbeddingServiceInterface) {
            $sparseVector = $this->sparseEmbeddingService->generate($text);
            $point['vector'] = ['dense' => $vector, 'sparse' => $sparseVector];
        } else {
            $point['vector'] = $vector;
        }

        try {
            $this->qdrant->upsert($this->getCollectionName($project), [$point]);
        } catch (RequestException $e) {
            throw UpsertException::withReason($e->getMessage());
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

        $filter = ['must' => [['is_empty' => ['key' => 'superseded_by']]]];

        try {
            $results = $this->qdrant->search($this->getCollectionName($project), $vector, 5, $filter, $threshold);
        } catch (RequestException) {
            return collect();
        }

        return collect($results)->map(fn (ScoredPoint $p): array => [
            'id' => $p->id,
            'score' => $p->score,
            'title' => $p->payload['title'] ?? '',
            'content' => $p->payload['content'] ?? '',
        ]);
    }

    /**
     * Get supersession history for an entry.
     *
     * @return array{supersedes: array<int, array<string, mixed>>, superseded_by: array<string, mixed>|null}
     */
    public function getSupersessionHistory(string|int $id, string $project = 'default'): array
    {
        $history = ['supersedes' => [], 'superseded_by' => null];
        $entry = $this->getById($id, $project);

        if ($entry === null) {
            return $history;
        }

        $supersededBy = $entry['superseded_by'] ?? null;
        if ($supersededBy !== null && $supersededBy !== '') {
            $successor = $this->getById($supersededBy, $project);
            if ($successor !== null) {
                $history['superseded_by'] = $successor;
            }
        }

        $this->ensureCollection($project);

        $filter = ['must' => [['key' => 'superseded_by', 'match' => ['value' => (string) $id]]]];

        try {
            $result = $this->qdrant->scroll($this->getCollectionName($project), 100, $filter);
            $history['supersedes'] = array_map(
                fn (ScoredPoint $p): array => $this->mapScoredPointToEntry($p),
                $result->points
            );
        } catch (RequestException) {
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
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, array $filters = [], int $limit = 20, string $project = 'default'): Collection
    {
        if ($this->cacheService instanceof KnowledgeCacheService) {
            $cached = $this->cacheService->rememberSearch(
                $query, $filters, $limit, $project,
                fn (): array => $this->executeSearch($query, $filters, $limit, $project)->toArray()
            );

            return collect($cached);
        }

        return $this->executeSearch($query, $filters, $limit, $project);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function executeSearch(string $query, array $filters, int $limit, string $project): Collection
    {
        $this->ensureCollection($project);

        $queryVector = $this->getCachedEmbedding($query);

        if ($queryVector === []) {
            return collect();
        }

        $qdrantFilter = $this->buildFilter($filters);

        try {
            $results = $this->qdrant->search(
                $this->getCollectionName($project),
                $queryVector,
                $limit,
                $qdrantFilter,
                $this->scoreThreshold,
            );
        } catch (RequestException) {
            return collect();
        }

        return collect($results)->map(fn (ScoredPoint $p): array => $this->mapScoredPointToSearchEntry($p));
    }

    /**
     * Hybrid search using both dense and sparse vectors with RRF fusion.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function hybridSearch(
        string $query,
        array $filters = [],
        int $limit = 20,
        int $prefetchLimit = 40,
        string $project = 'default'
    ): Collection {
        if (! $this->hybridEnabled || ! $this->sparseEmbeddingService instanceof SparseEmbeddingServiceInterface) {
            return $this->search($query, $filters, $limit, $project);
        }

        $this->ensureCollection($project);

        $denseVector = $this->getCachedEmbedding($query);

        if ($denseVector === []) {
            return collect();
        }

        $sparseVector = $this->sparseEmbeddingService->generate($query);

        if ($sparseVector['indices'] === []) {
            return $this->search($query, $filters, $limit, $project);
        }

        $qdrantFilter = $this->buildFilter($filters);

        try {
            $results = $this->qdrant->hybridSearch(
                $this->getCollectionName($project),
                $denseVector,
                $sparseVector,
                limit: $limit,
                filter: $qdrantFilter,
            );
        } catch (RequestException) {
            return collect();
        }

        return collect($results)->map(fn (ScoredPoint $p): array => $this->mapScoredPointToSearchEntry($p));
    }

    /**
     * Scroll/list all entries without requiring a search query.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
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

        try {
            $result = $this->qdrant->scroll($this->getCollectionName($project), $limit, $qdrantFilter, $offset);
        } catch (RequestException) {
            return collect();
        }

        return collect($result->points)->map(fn (ScoredPoint $p): array => $this->mapScoredPointToEntry($p));
    }

    /**
     * Delete entries by ID.
     *
     * @param  array<string|int>  $ids
     */
    public function delete(array $ids, string $project = 'default'): bool
    {
        $this->ensureCollection($project);

        try {
            $this->qdrant->delete($this->getCollectionName($project), $ids);
        } catch (RequestException) {
            return false;
        }

        $this->cacheService?->invalidateOnMutation();

        return true;
    }

    /**
     * Get entry by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getById(string|int $id, string $project = 'default'): ?array
    {
        $this->ensureCollection($project);

        try {
            $points = $this->qdrant->getPoints($this->getCollectionName($project), [$id]);
        } catch (RequestException) {
            return null;
        }

        if ($points === []) {
            return null;
        }

        return $this->mapScoredPointToEntry($points[0]);
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

        $entry = array_merge($entry, $fields);
        $entry['updated_at'] = now()->toIso8601String();

        return $this->upsert($entry, $project, false);
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
            $stats = $this->cacheService->rememberStats(
                $project,
                fn (): array => ['points_count' => $this->executeCount($project)]
            );

            return $stats['points_count'];
        }

        return $this->executeCount($project);
    }

    /**
     * @codeCoverageIgnore Qdrant API integration - tested via integration tests
     */
    private function executeCount(string $project): int
    {
        $this->ensureCollection($project);

        try {
            return $this->qdrant->count($this->getCollectionName($project));
        } catch (RequestException) {
            return 0;
        }
    }

    /**
     * List all knowledge collections from Qdrant.
     *
     * @return array<int, string>
     *
     * @codeCoverageIgnore Qdrant API integration - tested via integration tests
     */
    public function listCollections(): array
    {
        try {
            return array_values(array_filter(
                $this->qdrant->listCollections(),
                fn (string $name): bool => str_starts_with($name, 'knowledge_')
            ));
        } catch (RequestException) {
            return [];
        }
    }

    /**
     * Search any Qdrant collection by name — no knowledge_ prefix, no metadata mapping.
     *
     * @return Collection<int, array{id: string|int, score: float, payload: array<string, mixed>}>
     */
    public function searchRawCollection(string $collection, string $query, int $limit = 10): Collection
    {
        $queryVector = $this->getCachedEmbedding($query);

        if ($queryVector === []) {
            return collect();
        }

        try {
            $results = $this->qdrant->search($collection, $queryVector, $limit);
        } catch (RequestException) {
            return collect();
        }

        return collect($results)->map(fn (ScoredPoint $p): array => [
            'id' => $p->id,
            'score' => $p->score,
            'payload' => $p->payload,
        ]);
    }

    /**
     * Get collection name for project namespace.
     */
    public function getCollectionName(string $project): string
    {
        return 'knowledge_'.str_replace(['/', '\\', ' '], '_', $project);
    }

    /**
     * Get cached embedding or generate new one.
     *
     * @return array<float>
     */
    private function getCachedEmbedding(string $text): array
    {
        if (! config('search.qdrant.cache_embeddings', true)) {
            return $this->embeddingService->embed($text);
        }

        if ($this->cacheService instanceof KnowledgeCacheService) {
            return $this->cacheService->rememberEmbedding($text, fn (): array => $this->embeddingService->embed($text));
        }

        $cacheKey = 'embedding:'.hash('xxh128', $text);

        /** @var array<float> */
        return Cache::remember(
            $cacheKey,
            $this->cacheTtl,
            fn (): array => $this->embeddingService->embed($text)
        );
    }

    /**
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

        if (! $includeSuperseded) {
            $must[] = ['is_empty' => ['key' => 'superseded_by']];
        }

        foreach (['category', 'module', 'priority', 'status'] as $field) {
            if (isset($filters[$field])) {
                $must[] = ['key' => $field, 'match' => ['value' => $filters[$field]]];
            }
        }

        if (isset($filters['tag'])) {
            $must[] = ['key' => 'tags', 'match' => ['value' => $filters['tag']]];
        }

        return $must === [] ? null : ['must' => $must];
    }

    /**
     * Map a ScoredPoint to a search result entry (includes score).
     *
     * @return array<string, mixed>
     */
    private function mapScoredPointToSearchEntry(ScoredPoint $point): array
    {
        return array_merge(['score' => $point->score], $this->mapScoredPointToEntry($point));
    }

    /**
     * Map a ScoredPoint to an entry array.
     *
     * @return array<string, mixed>
     */
    private function mapScoredPointToEntry(ScoredPoint $point): array
    {
        $p = $point->payload;

        return [
            'id' => $point->id,
            'title' => $p['title'] ?? '',
            'content' => $p['content'] ?? '',
            'tags' => $this->normalizeTags($p['tags'] ?? []),
            'category' => $p['category'] ?? null,
            'module' => $p['module'] ?? null,
            'priority' => $p['priority'] ?? null,
            'status' => $p['status'] ?? null,
            'confidence' => $p['confidence'] ?? 0,
            'usage_count' => $p['usage_count'] ?? 0,
            'created_at' => $p['created_at'] ?? '',
            'updated_at' => $p['updated_at'] ?? '',
            'last_verified' => $p['last_verified'] ?? null,
            'evidence' => $p['evidence'] ?? null,
            'superseded_by' => $p['superseded_by'] ?? null,
            'superseded_date' => $p['superseded_date'] ?? null,
            'superseded_reason' => $p['superseded_reason'] ?? null,
        ];
    }

    /**
     * @return array<string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (is_array($tags)) {
            return $tags;
        }

        if (is_string($tags) && str_starts_with($tags, '[')) {
            $decoded = json_decode($tags, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function extractFingerprint(array $tags): ?string
    {
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'fingerprint:')) {
                return $tag;
            }
        }

        return null;
    }

    private function findByFingerprint(string $fingerprint, string $project): string|int|null
    {
        $filter = ['must' => [
            ['key' => 'tags', 'match' => ['value' => $fingerprint]],
            ['is_empty' => ['key' => 'superseded_by']],
        ]];

        try {
            $result = $this->qdrant->scroll($this->getCollectionName($project), 1, $filter);
        } catch (RequestException) {
            return null;
        }

        return $result->points !== [] ? $result->points[0]->id : null;
    }

    private function findByTitleAndCommit(string $title, string $commit, string $project): string|int|null
    {
        $filter = ['must' => [
            ['key' => 'title', 'match' => ['text' => $title]],
            ['key' => 'commit', 'match' => ['value' => $commit]],
            ['is_empty' => ['key' => 'superseded_by']],
        ]];

        try {
            $result = $this->qdrant->scroll($this->getCollectionName($project), 1, $filter);
        } catch (RequestException) {
            return null;
        }

        return $result->points !== [] ? $result->points[0]->id : null;
    }
}
