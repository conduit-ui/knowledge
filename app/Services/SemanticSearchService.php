<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SemanticSearchService
{
    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly bool $semanticEnabled = false,
        private readonly ?ChromaDBClientInterface $chromaDBClient = null,
        private readonly bool $useChromaDB = false
    ) {}

    /**
     * Search entries using semantic search if enabled, fallback to keyword search.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Additional filters (tag, category, module, priority, status)
     * @return Collection<int, Entry>
     */
    public function search(string $query, array $filters = []): Collection
    {
        // Try semantic search if enabled and embeddings are available
        if ($this->semanticEnabled && $this->hasEmbeddingSupport()) {
            $results = $this->semanticSearch($query, $filters);
            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        // Fallback to keyword search
        return $this->keywordSearch($query, $filters);
    }

    /**
     * Check if embedding support is available.
     */
    public function hasEmbeddingSupport(): bool
    {
        // Check if the embedding service can generate embeddings
        $testEmbedding = $this->embeddingService->generate('test');

        return count($testEmbedding) > 0;
    }

    /**
     * Check if ChromaDB is available and enabled.
     */
    public function hasChromaDBSupport(): bool
    {
        return $this->useChromaDB
            && $this->chromaDBClient !== null
            && $this->chromaDBClient->isAvailable();
    }

    /**
     * Perform semantic search using embeddings.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Additional filters
     * @return Collection<int, Entry>
     */
    private function semanticSearch(string $query, array $filters): Collection
    {
        $queryEmbedding = $this->embeddingService->generate($query);

        if (count($queryEmbedding) === 0) {
            return new Collection;
        }

        // Use ChromaDB if available and enabled
        if ($this->hasChromaDBSupport()) {
            return $this->chromaDBSearch($query, $queryEmbedding, $filters);
        }

        // Fallback to SQLite-based semantic search
        return $this->sqliteSemanticSearch($queryEmbedding, $filters);
    }

    /**
     * Perform semantic search using ChromaDB.
     *
     * @param  string  $query  The search query
     * @param  array<int, float>  $queryEmbedding  Query embedding vector
     * @param  array<string, mixed>  $filters  Additional filters
     * @return Collection<int, Entry>
     */
    private function chromaDBSearch(string $query, array $queryEmbedding, array $filters): Collection
    {
        if ($this->chromaDBClient === null) {
            return new Collection;
        }

        try {
            $collection = $this->chromaDBClient->getOrCreateCollection('knowledge_entries');
            $maxResults = config('search.max_results', 20);

            // Build ChromaDB metadata filters
            $where = [];
            if (isset($filters['category'])) {
                $where['category'] = $filters['category'];
            }
            if (isset($filters['module'])) {
                $where['module'] = $filters['module'];
            }
            if (isset($filters['priority'])) {
                $where['priority'] = $filters['priority'];
            }
            if (isset($filters['status'])) {
                $where['status'] = $filters['status'];
            }

            $results = $this->chromaDBClient->query(
                $collection['id'],
                $queryEmbedding,
                $maxResults,
                $where
            );

            // Extract IDs and distances from ChromaDB results
            $ids = $results['ids'][0] ?? [];
            $distances = $results['distances'][0] ?? [];

            if (count($ids) === 0) {
                return new Collection;
            }

            // Convert ChromaDB IDs (entry_X) to entry IDs
            $entryIds = array_map(fn (string $id): int => (int) str_replace('entry_', '', $id), $ids);

            // Fetch entries from database
            /** @var \Illuminate\Database\Eloquent\Builder<Entry> $query */
            $query = Entry::query();
            /** @var \Illuminate\Database\Eloquent\Builder<Entry> $queryWithFilters */
            $queryWithFilters = $query->whereIn('id', $entryIds);

            if (isset($filters['tag'])) {
                $queryWithFilters->whereJsonContains('tags', $filters['tag']);
            }

            $entries = $queryWithFilters->get()->keyBy('id');

            // Map results with scores and maintain ChromaDB order
            $rankedResults = collect();
            foreach ($entryIds as $index => $entryId) {
                /** @var Entry|null $entry */
                $entry = $entries->get($entryId);
                if ($entry === null) {
                    continue;
                }

                // Convert distance to similarity (1 - distance for L2, adjust if using cosine)
                $similarity = 1.0 - ($distances[$index] ?? 0.0);
                $confidence = $entry->confidence ?? 0;
                $score = $confidence > 0 ? $similarity * ($confidence / 100) : $similarity;
                $entry->setAttribute('search_score', $score);

                $rankedResults->push($entry);
            }

            /** @var Collection<int, Entry> */
            return new Collection($rankedResults->all());
        } catch (\RuntimeException $e) {
            // Fallback to SQLite search if ChromaDB fails
            return $this->sqliteSemanticSearch($queryEmbedding, $filters);
        }
    }

    /**
     * Perform semantic search using SQLite embeddings.
     *
     * @param  array<int, float>  $queryEmbedding  Query embedding vector
     * @param  array<string, mixed>  $filters  Additional filters
     * @return Collection<int, Entry>
     */
    private function sqliteSemanticSearch(array $queryEmbedding, array $filters): Collection
    {
        // Get all entries with embeddings
        $entries = Entry::whereNotNull('embedding')
            ->when($filters['tag'] ?? null, function (Builder $q, string $tag): void {
                $q->whereJsonContains('tags', $tag);
            })
            ->when($filters['category'] ?? null, function (Builder $q, string $category): void {
                $q->where('category', $category);
            })
            ->when($filters['module'] ?? null, function (Builder $q, string $module): void {
                $q->where('module', $module);
            })
            ->when($filters['priority'] ?? null, function (Builder $q, string $priority): void {
                $q->where('priority', $priority);
            })
            ->when($filters['status'] ?? null, function (Builder $q, string $status): void {
                $q->where('status', $status);
            })
            ->get();

        // Calculate similarity scores and rank results
        $rankedResults = $entries->map(function (Entry $entry) use ($queryEmbedding) {
            $entryEmbedding = json_decode((string) $entry->embedding, true);
            if (! is_array($entryEmbedding)) {
                return null;
            }

            $similarity = $this->embeddingService->similarity($queryEmbedding, $entryEmbedding);
            $confidence = $entry->confidence ?? 0;
            $score = $confidence > 0 ? $similarity * ($confidence / 100) : $similarity;
            $entry->setAttribute('search_score', $score);

            return $entry;
        })
            ->filter()
            ->sortByDesc('search_score')
            ->values();

        /** @var Collection<int, Entry> */
        return new Collection($rankedResults->all());
    }

    /**
     * Perform keyword-based search.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Additional filters
     * @return Collection<int, Entry>
     */
    private function keywordSearch(string $query, array $filters): Collection
    {
        return Entry::where(function (Builder $q) use ($query): void {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })
            ->when($filters['tag'] ?? null, function (Builder $q, string $tag): void {
                $q->whereJsonContains('tags', $tag);
            })
            ->when($filters['category'] ?? null, function (Builder $q, string $category): void {
                $q->where('category', $category);
            })
            ->when($filters['module'] ?? null, function (Builder $q, string $module): void {
                $q->where('module', $module);
            })
            ->when($filters['priority'] ?? null, function (Builder $q, string $priority): void {
                $q->where('priority', $priority);
            })
            ->when($filters['status'] ?? null, function (Builder $q, string $status): void {
                $q->where('status', $status);
            })
            ->orderBy('confidence', 'desc')
            ->orderBy('usage_count', 'desc')
            ->get();
    }
}
