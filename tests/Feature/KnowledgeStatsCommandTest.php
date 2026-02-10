<?php

declare(strict_types=1);

use App\Services\KnowledgeCacheService;
use App\Services\QdrantService;

describe('KnowledgeStatsCommand', function (): void {
    it('displays comprehensive analytics dashboard covering all code paths', function (): void {
        $qdrant = mock(QdrantService::class);
        app()->instance(QdrantService::class, $qdrant);

        // Mixed entries testing all display logic in a single test due to static caching
        $entries = collect([
            [
                'id' => 1,
                'title' => 'Laravel Entry',
                'content' => 'Laravel content',
                'category' => 'tutorial',
                'status' => 'validated',
                'usage_count' => 50, // Most used
                'tags' => ['laravel'],
            ],
            [
                'id' => 2,
                'title' => 'PHP Entry',
                'content' => 'PHP content',
                'category' => 'guide',
                'status' => 'draft',
                'usage_count' => 10,
                'tags' => ['php'],
            ],
            [
                'id' => 3,
                'title' => 'Uncategorized Entry',
                'content' => 'No category',
                'category' => null,
                'status' => 'deprecated',
                'usage_count' => 0,
                'tags' => [],
            ],
        ]);

        // Now uses count() instead of search('')
        $qdrant->shouldReceive('count')
            ->once()
            ->andReturn(3);

        // Now uses scroll() to get sample entries
        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 3)
            ->andReturn($entries);

        $qdrant->shouldReceive('getCacheService')
            ->once()
            ->andReturnNull();

        $this->artisan('stats')
            ->assertSuccessful();
    });

    it('displays cache metrics when cache service is available', function (): void {
        $qdrant = mock(QdrantService::class);
        $cacheService = mock(KnowledgeCacheService::class);
        app()->instance(QdrantService::class, $qdrant);

        $entries = collect([
            [
                'id' => 1,
                'title' => 'Test Entry',
                'content' => 'Content',
                'category' => 'testing',
                'status' => 'validated',
                'usage_count' => 5,
                'tags' => [],
            ],
        ]);

        $qdrant->shouldReceive('count')
            ->once()
            ->andReturn(1);

        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 1)
            ->andReturn($entries);

        $qdrant->shouldReceive('getCacheService')
            ->once()
            ->andReturn($cacheService);

        $cacheService->shouldReceive('getMetrics')
            ->once()
            ->andReturn([
                'embedding' => ['hits' => 10, 'misses' => 5],
                'search' => ['hits' => 20, 'misses' => 3],
                'stats' => ['hits' => 8, 'misses' => 2],
            ]);

        $this->artisan('stats')
            ->assertSuccessful();
    });
});
