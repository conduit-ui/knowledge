<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class KnowledgeCacheService
{
    private const PREFIX_EMBEDDING = 'embedding:';

    private const PREFIX_SEARCH = 'search:';

    private const PREFIX_STATS = 'stats:';

    private const METRICS_KEY = 'cache:metrics';

    private const SEARCH_KEYS_TRACKER = 'cache:keys:search';

    private const STATS_KEYS_TRACKER = 'cache:keys:stats';

    private const TTL_EMBEDDING = 604800; // 7 days

    private const TTL_SEARCH = 3600; // 1 hour

    private const TTL_STATS = 300; // 5 minutes

    /**
     * Get or compute a cached embedding.
     *
     * @param  callable(): array<float>  $generator
     * @return array<float>
     */
    public function rememberEmbedding(string $text, callable $generator): array
    {
        $cacheKey = self::PREFIX_EMBEDDING.hash('xxh128', $text);

        if (Cache::has($cacheKey)) {
            $this->recordHit('embedding');

            /** @var array<float> */
            return Cache::get($cacheKey);
        }

        $this->recordMiss('embedding');

        /** @var array<float> $result */
        $result = $generator();

        Cache::put($cacheKey, $result, self::TTL_EMBEDDING);

        return $result;
    }

    /**
     * Get or compute cached search results.
     *
     * @param  array<string, mixed>  $filters
     * @param  callable(): array<int, array<string, mixed>>  $searcher
     * @return array<int, array<string, mixed>>
     */
    public function rememberSearch(string $query, array $filters, int $limit, string $project, callable $searcher): array
    {
        $cacheKey = self::PREFIX_SEARCH.hash('xxh128', serialize([
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
            'project' => $project,
        ]));

        if (Cache::has($cacheKey)) {
            $this->recordHit('search');

            /** @var array<int, array<string, mixed>> */
            return Cache::get($cacheKey);
        }

        $this->recordMiss('search');

        /** @var array<int, array<string, mixed>> $results */
        $results = $searcher();

        if ($results !== []) {
            Cache::put($cacheKey, $results, self::TTL_SEARCH);
            $this->trackKey(self::SEARCH_KEYS_TRACKER, $cacheKey);
        }

        return $results;
    }

    /**
     * Get or compute cached collection stats.
     *
     * @param  callable(): array<string, mixed>  $fetcher
     * @return array<string, mixed>
     */
    public function rememberStats(string $project, callable $fetcher): array
    {
        $cacheKey = self::PREFIX_STATS.$project;

        if (Cache::has($cacheKey)) {
            $this->recordHit('stats');

            /** @var array<string, mixed> */
            return Cache::get($cacheKey);
        }

        $this->recordMiss('stats');

        /** @var array<string, mixed> $result */
        $result = $fetcher();

        Cache::put($cacheKey, $result, self::TTL_STATS);
        $this->trackKey(self::STATS_KEYS_TRACKER, $cacheKey);

        return $result;
    }

    /**
     * Invalidate search and stats caches after entry mutations.
     */
    public function invalidateOnMutation(): void
    {
        $this->flushTrackedKeys(self::SEARCH_KEYS_TRACKER);
        $this->flushTrackedKeys(self::STATS_KEYS_TRACKER);
    }

    /**
     * Get cache metrics for display.
     *
     * @return array{
     *     embedding: array{hits: int, misses: int},
     *     search: array{hits: int, misses: int},
     *     stats: array{hits: int, misses: int}
     * }
     */
    public function getMetrics(): array
    {
        /** @var array<string, array{hits: int, misses: int}> $metrics */
        $metrics = Cache::get(self::METRICS_KEY, []);

        return [
            'embedding' => $metrics['embedding'] ?? ['hits' => 0, 'misses' => 0],
            'search' => $metrics['search'] ?? ['hits' => 0, 'misses' => 0],
            'stats' => $metrics['stats'] ?? ['hits' => 0, 'misses' => 0],
        ];
    }

    /**
     * Reset cache metrics.
     */
    public function resetMetrics(): void
    {
        Cache::forget(self::METRICS_KEY);
    }

    private function recordHit(string $type): void
    {
        $this->updateMetric($type, 'hits');
    }

    private function recordMiss(string $type): void
    {
        $this->updateMetric($type, 'misses');
    }

    private function updateMetric(string $type, string $field): void
    {
        /** @var array<string, array{hits: int, misses: int}> $metrics */
        $metrics = Cache::get(self::METRICS_KEY, []);

        if (! isset($metrics[$type])) {
            $metrics[$type] = ['hits' => 0, 'misses' => 0];
        }

        $metrics[$type][$field]++;

        Cache::put(self::METRICS_KEY, $metrics, self::TTL_EMBEDDING);
    }

    private function trackKey(string $tracker, string $key): void
    {
        /** @var array<int, string> $keys */
        $keys = Cache::get($tracker, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($tracker, $keys, self::TTL_EMBEDDING);
        }
    }

    private function flushTrackedKeys(string $tracker): void
    {
        /** @var array<int, string> $keys */
        $keys = Cache::get($tracker, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($tracker);
    }
}
