<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\ConnectionException;
use App\Exceptions\Qdrant\EmbeddingException;
use App\Exceptions\Qdrant\UpsertException;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\DeletePoints;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\GetPoints;
use App\Integrations\Qdrant\Requests\ScrollPoints;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\ClientException;

class QdrantService
{
    private QdrantConnector $connector;

    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly int $vectorSize = 384,
        private readonly float $scoreThreshold = 0.7,
        private readonly int $cacheTtl = 604800, // 7 days
        private readonly bool $secure = false,
    ) {
        $this->connector = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
            secure: $this->secure,
        );
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
                    new CreateCollection($collectionName, $this->vectorSize)
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
     *     updated_at?: string
     * }  $entry
     */
    public function upsert(array $entry, string $project = 'default'): bool
    {
        $this->ensureCollection($project);

        // Generate embedding for searchable text (title + content)
        $text = $entry['title'].' '.$entry['content'];
        $vector = $this->getCachedEmbedding($text);

        if (count($vector) === 0) {
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
        ];

        $response = $this->connector->send(
            new UpsertPoints(
                $this->getCollectionName($project),
                [
                    [
                        'id' => $entry['id'],
                        'vector' => $vector,
                        'payload' => $payload,
                    ],
                ]
            )
        );

        if (! $response->successful()) {
            $error = $response->json();
            throw UpsertException::withReason($error['status']['error'] ?? json_encode($error));
        }

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
     *     updated_at: string
     * }>
     */
    public function search(
        string $query,
        array $filters = [],
        int $limit = 20,
        string $project = 'default'
    ): Collection {
        $this->ensureCollection($project);

        // Generate query embedding
        $queryVector = $this->getCachedEmbedding($query);

        if (count($queryVector) === 0) {
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

        return collect($results)->map(function (array $result) {
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
     *     updated_at: string
     * }>
     */
    public function scroll(
        array $filters = [],
        int $limit = 20,
        string $project = 'default',
        string|int|null $offset = null
    ): Collection {
        $this->ensureCollection($project);

        $qdrantFilter = ! empty($filters) ? $this->buildFilter($filters) : null;

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

        return collect($points)->map(function (array $point) {
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

        return $response->successful();
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
     *     updated_at: string
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

        $cacheKey = 'embedding:'.hash('xxh128', $text);

        /** @var array<float> */
        return Cache::remember(
            $cacheKey,
            $this->cacheTtl,
            fn () => $this->embeddingService->generate($text)
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
        if (empty($filters)) {
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

        return empty($must) ? null : ['must' => $must];
    }

    /**
     * Get the total count of entries in a collection.
     */
    public function count(string $project = 'default'): int
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
