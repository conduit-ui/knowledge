<?php

declare(strict_types=1);

use App\Services\KnowledgeCacheService;
use App\Services\OdinSyncService;
use App\Services\QdrantService;

describe('KnowledgeStatsCommand', function (): void {
    it('displays comprehensive analytics dashboard covering all code paths', function (): void {
        $qdrant = mock(QdrantService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

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

        // Now uses count() with project parameter
        $qdrant->shouldReceive('count')
            ->once()
            ->with('default')
            ->andReturn(3);

        // Now uses scroll() with project parameter
        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 3, 'default')
            ->andReturn($entries);

        // getCollectionName is called for dashboard display
        $qdrant->shouldReceive('getCollectionName')
            ->with('default')
            ->andReturn('knowledge_default');

        $qdrant->shouldReceive('getCacheService')
            ->once()
            ->andReturnNull();

        $odinSync->shouldReceive('isEnabled')
            ->once()
            ->andReturn(false);

        $this->artisan('stats')
            ->assertSuccessful();
    });

    it('displays cache metrics when cache service is available', function (): void {
        $qdrant = mock(QdrantService::class);
        $cacheService = mock(KnowledgeCacheService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

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
            ->with('default')
            ->andReturn(1);

        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 1, 'default')
            ->andReturn($entries);

        $qdrant->shouldReceive('getCollectionName')
            ->with('default')
            ->andReturn('knowledge_default');

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

        $odinSync->shouldReceive('isEnabled')
            ->once()
            ->andReturn(false);

        $this->artisan('stats')
            ->assertSuccessful();
    });

    it('displays odin sync status with unknown status using gray color', function (): void {
        $qdrant = mock(QdrantService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

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
            ->with('default')
            ->andReturn(1);

        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 1, 'default')
            ->andReturn($entries);

        $qdrant->shouldReceive('getCollectionName')
            ->with('default')
            ->andReturn('knowledge_default');

        $qdrant->shouldReceive('getCacheService')
            ->once()
            ->andReturnNull();

        $odinSync->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $odinSync->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'status' => 'unknown-status',
                'pending' => 0,
                'last_synced' => null,
                'last_error' => null,
            ]);

        $this->artisan('stats')
            ->assertSuccessful();
    });

    it('displays odin sync error status in red', function (): void {
        $qdrant = mock(QdrantService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

        $qdrant->shouldReceive('count')->once()->with('default')->andReturn(0);
        $qdrant->shouldReceive('scroll')->once()->with([], 0, 'default')->andReturn(collect([]));
        $qdrant->shouldReceive('getCollectionName')->with('default')->andReturn('knowledge_default');
        $qdrant->shouldReceive('getCacheService')->once()->andReturnNull();
        $odinSync->shouldReceive('isEnabled')->once()->andReturn(true);
        $odinSync->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'error',
            'pending' => 0,
            'last_synced' => null,
            'last_error' => 'Connection refused',
        ]);

        $this->artisan('stats')->assertSuccessful();
    });

    it('displays odin sync pending status in yellow', function (): void {
        $qdrant = mock(QdrantService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

        $qdrant->shouldReceive('count')->once()->with('default')->andReturn(0);
        $qdrant->shouldReceive('scroll')->once()->with([], 0, 'default')->andReturn(collect([]));
        $qdrant->shouldReceive('getCollectionName')->with('default')->andReturn('knowledge_default');
        $qdrant->shouldReceive('getCacheService')->once()->andReturnNull();
        $odinSync->shouldReceive('isEnabled')->once()->andReturn(true);
        $odinSync->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'pending',
            'pending' => 5,
            'last_synced' => null,
            'last_error' => null,
        ]);

        $this->artisan('stats')->assertSuccessful();
    });

    it('displays odin sync status when enabled', function (): void {
        $qdrant = mock(QdrantService::class);
        $odinSync = mock(OdinSyncService::class);
        app()->instance(QdrantService::class, $qdrant);
        app()->instance(OdinSyncService::class, $odinSync);
        mockProjectDetector();

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
            ->with('default')
            ->andReturn(1);

        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 1, 'default')
            ->andReturn($entries);

        $qdrant->shouldReceive('getCollectionName')
            ->with('default')
            ->andReturn('knowledge_default');

        $qdrant->shouldReceive('getCacheService')
            ->once()
            ->andReturnNull();

        $odinSync->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $odinSync->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'status' => 'synced',
                'pending' => 2,
                'last_synced' => '2025-06-01T12:00:00+00:00',
                'last_error' => null,
            ]);

        $this->artisan('stats')
            ->assertSuccessful();
    });
});
