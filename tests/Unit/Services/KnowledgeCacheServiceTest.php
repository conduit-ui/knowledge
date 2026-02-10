<?php

declare(strict_types=1);

use App\Services\KnowledgeCacheService;
use Illuminate\Support\Facades\Cache;

uses()->group('cache-unit');

beforeEach(function (): void {
    Cache::flush();
    $this->cacheService = new KnowledgeCacheService;
});

describe('rememberEmbedding', function (): void {
    it('generates embedding on cache miss', function (): void {
        $called = false;
        $result = $this->cacheService->rememberEmbedding('test text', function () use (&$called): array {
            $called = true;

            return [0.1, 0.2, 0.3];
        });

        expect($called)->toBeTrue();
        expect($result)->toBe([0.1, 0.2, 0.3]);
    });

    it('returns cached embedding on cache hit', function (): void {
        $callCount = 0;
        $generator = function () use (&$callCount): array {
            $callCount++;

            return [0.1, 0.2, 0.3];
        };

        $this->cacheService->rememberEmbedding('test text', $generator);
        $result = $this->cacheService->rememberEmbedding('test text', $generator);

        expect($callCount)->toBe(1);
        expect($result)->toBe([0.1, 0.2, 0.3]);
    });

    it('uses different cache keys for different text', function (): void {
        $callCount = 0;
        $generator = function () use (&$callCount): array {
            $callCount++;

            return [0.1 * $callCount, 0.2 * $callCount];
        };

        $result1 = $this->cacheService->rememberEmbedding('text one', $generator);
        $result2 = $this->cacheService->rememberEmbedding('text two', $generator);

        expect($callCount)->toBe(2);
        expect($result1)->not->toBe($result2);
    });

    it('records embedding hit metric', function (): void {
        $this->cacheService->rememberEmbedding('test', fn (): array => [0.1]);
        $this->cacheService->rememberEmbedding('test', fn (): array => [0.1]);

        $metrics = $this->cacheService->getMetrics();
        expect($metrics['embedding']['hits'])->toBe(1);
        expect($metrics['embedding']['misses'])->toBe(1);
    });
});

describe('rememberSearch', function (): void {
    it('executes searcher on cache miss', function (): void {
        $called = false;
        $result = $this->cacheService->rememberSearch('query', [], 10, 'default', function () use (&$called): array {
            $called = true;

            return [['id' => '1', 'title' => 'Result']];
        });

        expect($called)->toBeTrue();
        expect($result)->toBe([['id' => '1', 'title' => 'Result']]);
    });

    it('returns cached results on cache hit', function (): void {
        $callCount = 0;
        $searcher = function () use (&$callCount): array {
            $callCount++;

            return [['id' => '1', 'title' => 'Result']];
        };

        $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);
        $result = $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);

        expect($callCount)->toBe(1);
        expect($result)->toBe([['id' => '1', 'title' => 'Result']]);
    });

    it('does not cache empty results', function (): void {
        $callCount = 0;
        $searcher = function () use (&$callCount): array {
            $callCount++;

            return [];
        };

        $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);
        $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);

        expect($callCount)->toBe(2);
    });

    it('uses different keys for different queries', function (): void {
        $callCount = 0;
        $searcher = function () use (&$callCount): array {
            $callCount++;

            return [['id' => (string) $callCount]];
        };

        $this->cacheService->rememberSearch('query1', [], 10, 'default', $searcher);
        $this->cacheService->rememberSearch('query2', [], 10, 'default', $searcher);

        expect($callCount)->toBe(2);
    });

    it('uses different keys for different filters', function (): void {
        $callCount = 0;
        $searcher = function () use (&$callCount): array {
            $callCount++;

            return [['id' => (string) $callCount]];
        };

        $this->cacheService->rememberSearch('query', ['category' => 'a'], 10, 'default', $searcher);
        $this->cacheService->rememberSearch('query', ['category' => 'b'], 10, 'default', $searcher);

        expect($callCount)->toBe(2);
    });

    it('records search hit metric', function (): void {
        $this->cacheService->rememberSearch('q', [], 10, 'default', fn (): array => [['id' => '1']]);
        $this->cacheService->rememberSearch('q', [], 10, 'default', fn (): array => [['id' => '1']]);

        $metrics = $this->cacheService->getMetrics();
        expect($metrics['search']['hits'])->toBe(1);
        expect($metrics['search']['misses'])->toBe(1);
    });
});

describe('rememberStats', function (): void {
    it('executes fetcher on cache miss', function (): void {
        $called = false;
        $result = $this->cacheService->rememberStats('default', function () use (&$called): array {
            $called = true;

            return ['points_count' => 42];
        });

        expect($called)->toBeTrue();
        expect($result)->toBe(['points_count' => 42]);
    });

    it('returns cached stats on hit', function (): void {
        $callCount = 0;
        $fetcher = function () use (&$callCount): array {
            $callCount++;

            return ['points_count' => 42];
        };

        $this->cacheService->rememberStats('default', $fetcher);
        $result = $this->cacheService->rememberStats('default', $fetcher);

        expect($callCount)->toBe(1);
        expect($result)->toBe(['points_count' => 42]);
    });

    it('uses different keys for different projects', function (): void {
        $callCount = 0;
        $fetcher = function () use (&$callCount): array {
            $callCount++;

            return ['points_count' => $callCount * 10];
        };

        $this->cacheService->rememberStats('project-a', $fetcher);
        $this->cacheService->rememberStats('project-b', $fetcher);

        expect($callCount)->toBe(2);
    });

    it('records stats hit metric', function (): void {
        $this->cacheService->rememberStats('default', fn (): array => ['points_count' => 1]);
        $this->cacheService->rememberStats('default', fn (): array => ['points_count' => 1]);

        $metrics = $this->cacheService->getMetrics();
        expect($metrics['stats']['hits'])->toBe(1);
        expect($metrics['stats']['misses'])->toBe(1);
    });
});

describe('invalidateOnMutation', function (): void {
    it('clears search cache after invalidation', function (): void {
        $callCount = 0;
        $searcher = function () use (&$callCount): array {
            $callCount++;

            return [['id' => (string) $callCount]];
        };

        // Prime the cache
        $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);
        expect($callCount)->toBe(1);

        // Invalidate
        $this->cacheService->invalidateOnMutation();

        // Should call searcher again
        $this->cacheService->rememberSearch('query', [], 10, 'default', $searcher);
        expect($callCount)->toBe(2);
    });

    it('clears stats cache after invalidation', function (): void {
        $callCount = 0;
        $fetcher = function () use (&$callCount): array {
            $callCount++;

            return ['points_count' => $callCount];
        };

        // Prime the cache
        $this->cacheService->rememberStats('default', $fetcher);
        expect($callCount)->toBe(1);

        // Invalidate
        $this->cacheService->invalidateOnMutation();

        // Should call fetcher again
        $this->cacheService->rememberStats('default', $fetcher);
        expect($callCount)->toBe(2);
    });

    it('does not clear embedding cache on invalidation', function (): void {
        $callCount = 0;
        $generator = function () use (&$callCount): array {
            $callCount++;

            return [0.1, 0.2];
        };

        // Prime embedding cache
        $this->cacheService->rememberEmbedding('text', $generator);
        expect($callCount)->toBe(1);

        // Invalidate
        $this->cacheService->invalidateOnMutation();

        // Embedding cache should still be valid
        $this->cacheService->rememberEmbedding('text', $generator);
        expect($callCount)->toBe(1);
    });
});

describe('getMetrics', function (): void {
    it('returns zero metrics when no activity', function (): void {
        $metrics = $this->cacheService->getMetrics();

        expect($metrics)->toBe([
            'embedding' => ['hits' => 0, 'misses' => 0],
            'search' => ['hits' => 0, 'misses' => 0],
            'stats' => ['hits' => 0, 'misses' => 0],
        ]);
    });

    it('tracks metrics across all cache types', function (): void {
        // Generate misses
        $this->cacheService->rememberEmbedding('t1', fn (): array => [0.1]);
        $this->cacheService->rememberSearch('q', [], 10, 'default', fn (): array => [['id' => '1']]);
        $this->cacheService->rememberStats('default', fn (): array => ['count' => 1]);

        // Generate hits
        $this->cacheService->rememberEmbedding('t1', fn (): array => [0.1]);
        $this->cacheService->rememberSearch('q', [], 10, 'default', fn (): array => [['id' => '1']]);
        $this->cacheService->rememberStats('default', fn (): array => ['count' => 1]);

        $metrics = $this->cacheService->getMetrics();

        expect($metrics['embedding'])->toBe(['hits' => 1, 'misses' => 1]);
        expect($metrics['search'])->toBe(['hits' => 1, 'misses' => 1]);
        expect($metrics['stats'])->toBe(['hits' => 1, 'misses' => 1]);
    });
});

describe('resetMetrics', function (): void {
    it('clears all metrics', function (): void {
        $this->cacheService->rememberEmbedding('t', fn (): array => [0.1]);
        $this->cacheService->resetMetrics();

        $metrics = $this->cacheService->getMetrics();

        expect($metrics['embedding'])->toBe(['hits' => 0, 'misses' => 0]);
    });
});
