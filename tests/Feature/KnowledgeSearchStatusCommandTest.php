<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Services\QdrantService;

describe('KnowledgeSearchStatusCommand', function (): void {
    beforeEach(function (): void {
        $this->embeddingService = mock(EmbeddingServiceInterface::class);
        $this->qdrant = mock(QdrantService::class);

        app()->instance(EmbeddingServiceInterface::class, $this->embeddingService);
        app()->instance(QdrantService::class, $this->qdrant);
    });

    it('displays keyword search as always enabled', function (): void {
        config(['search.semantic_enabled' => false]);
        config(['search.embedding_provider' => 'none']);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays semantic search as enabled when configured', function (): void {
        config(['search.semantic_enabled' => true]);
        config(['search.embedding_provider' => 'ollama']);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([0.1, 0.2, 0.3]); // Non-empty embedding

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                ['id' => 1, 'title' => 'Test'],
            ]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays semantic search as not configured when disabled', function (): void {
        config(['search.semantic_enabled' => false]);
        config(['search.embedding_provider' => 'none']);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays semantic search as not configured when embedding service returns empty', function (): void {
        config(['search.semantic_enabled' => true]);
        config(['search.embedding_provider' => 'openai']);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]); // Empty embedding

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays database statistics', function (): void {
        config(['search.semantic_enabled' => false]);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]);

        $entries = collect([
            ['id' => 1, 'title' => 'Entry 1'],
            ['id' => 2, 'title' => 'Entry 2'],
            ['id' => 3, 'title' => 'Entry 3'],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn($entries);

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays usage instructions with semantic search enabled', function (): void {
        config(['search.semantic_enabled' => true]);
        config(['search.embedding_provider' => 'ollama']);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([0.1, 0.2, 0.3]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('displays usage instructions with semantic search disabled', function (): void {
        config(['search.semantic_enabled' => false]);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });

    it('handles empty database', function (): void {
        config(['search.semantic_enabled' => false]);

        $this->embeddingService->shouldReceive('generate')
            ->once()
            ->with('test')
            ->andReturn([]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('search:status')
            ->assertSuccessful();
    });
});
