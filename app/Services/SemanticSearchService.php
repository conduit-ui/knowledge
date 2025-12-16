<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SemanticSearchService
{
    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly bool $semanticEnabled = false
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
            $score = $similarity * ($entry->confidence / 100);
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
