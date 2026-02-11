<?php

declare(strict_types=1);

use App\Services\EnhancementQueueService;
use App\Services\EntryMetadataService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantService = mock(QdrantService::class);
    $this->metadataService = mock(EntryMetadataService::class);
    $this->enhancementQueue = mock(EnhancementQueueService::class);

    $this->qdrantService->shouldReceive('getSupersessionHistory')
        ->andReturn(['supersedes' => [], 'superseded_by' => null])
        ->byDefault();

    app()->instance(QdrantService::class, $this->qdrantService);
    app()->instance(EntryMetadataService::class, $this->metadataService);
    app()->instance(EnhancementQueueService::class, $this->enhancementQueue);
});

describe('show command', function (): void {
    it('displays entry details', function (): void {
        $entry = [
            'id' => 'test-id',
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'testing',
            'priority' => 'medium',
            'status' => 'draft',
            'confidence' => 75,
            'usage_count' => 3,
            'last_verified' => '2025-06-01T12:00:00+00:00',
            'evidence' => null,
            'module' => null,
            'tags' => ['php'],
            'created_at' => '2025-06-01T12:00:00+00:00',
            'updated_at' => '2025-06-01T12:00:00+00:00',
        ];

        $this->qdrantService->shouldReceive('getById')
            ->once()
            ->with('test-id')
            ->andReturn($entry);

        $this->qdrantService->shouldReceive('incrementUsage')
            ->once()
            ->with('test-id');

        $this->metadataService->shouldReceive('isStale')->once()->andReturn(false);
        $this->metadataService->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(75);
        $this->metadataService->shouldReceive('confidenceLevel')->once()->andReturn('high');

        $this->enhancementQueue->shouldReceive('isQueued')
            ->once()
            ->with('test-id')
            ->andReturn(false);

        $this->artisan('show', ['id' => 'test-id'])
            ->assertSuccessful();
    });

    it('shows enhancement pending status', function (): void {
        $entry = [
            'id' => 'test-id',
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'testing',
            'priority' => 'medium',
            'status' => 'draft',
            'confidence' => 50,
            'usage_count' => 0,
            'last_verified' => '2025-06-01T12:00:00+00:00',
            'evidence' => null,
            'module' => null,
            'tags' => [],
            'created_at' => '2025-06-01T12:00:00+00:00',
            'updated_at' => '2025-06-01T12:00:00+00:00',
        ];

        $this->qdrantService->shouldReceive('getById')->once()->andReturn($entry);
        $this->qdrantService->shouldReceive('incrementUsage')->once();

        $this->metadataService->shouldReceive('isStale')->once()->andReturn(false);
        $this->metadataService->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(50);
        $this->metadataService->shouldReceive('confidenceLevel')->once()->andReturn('medium');

        $this->enhancementQueue->shouldReceive('isQueued')
            ->once()
            ->with('test-id')
            ->andReturn(true);

        $this->artisan('show', ['id' => 'test-id'])
            ->assertSuccessful();
    });

    it('shows enhanced entry with concepts and summary', function (): void {
        $entry = [
            'id' => 'test-id',
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'testing',
            'priority' => 'medium',
            'status' => 'draft',
            'confidence' => 50,
            'usage_count' => 0,
            'last_verified' => '2025-06-01T12:00:00+00:00',
            'evidence' => null,
            'module' => null,
            'tags' => ['php', 'testing'],
            'enhanced' => true,
            'enhanced_at' => '2025-06-01T13:00:00+00:00',
            'concepts' => ['unit testing', 'code coverage'],
            'summary' => 'A guide to PHP testing.',
            'created_at' => '2025-06-01T12:00:00+00:00',
            'updated_at' => '2025-06-01T13:00:00+00:00',
        ];

        $this->qdrantService->shouldReceive('getById')->once()->andReturn($entry);
        $this->qdrantService->shouldReceive('incrementUsage')->once();

        $this->metadataService->shouldReceive('isStale')->once()->andReturn(false);
        $this->metadataService->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(50);
        $this->metadataService->shouldReceive('confidenceLevel')->once()->andReturn('medium');

        $this->enhancementQueue->shouldReceive('isQueued')->never();

        $this->artisan('show', ['id' => 'test-id'])
            ->assertSuccessful();
    });

    it('returns failure for non-existent entry', function (): void {
        $this->qdrantService->shouldReceive('getById')
            ->once()
            ->with('missing-id')
            ->andReturn(null);

        $this->artisan('show', ['id' => 'missing-id'])
            ->assertFailed();
    });
});
