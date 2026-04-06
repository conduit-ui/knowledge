<?php

declare(strict_types=1);

use App\Contracts\SparseEmbeddingServiceInterface;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response as SaloonResponse;
use TheShit\Vector\Contracts\EmbeddingClient;
use TheShit\Vector\Data\CollectionInfo;
use TheShit\Vector\Data\ScoredPoint;
use TheShit\Vector\Data\UpsertResult;
use TheShit\Vector\Qdrant;

uses()->group('hybrid-search');

beforeEach(function (): void {
    Cache::flush();

    $this->mockEmbedding = Mockery::mock(EmbeddingClient::class);
    $this->mockSparseEmbedding = Mockery::mock(SparseEmbeddingServiceInterface::class);
    $this->mockQdrant = Mockery::mock(Qdrant::class);
    $this->mockQdrantDense = Mockery::mock(Qdrant::class);

    // Create service with hybrid enabled
    $this->hybridService = new QdrantService(
        embeddingService: $this->mockEmbedding,
        qdrant: $this->mockQdrant,
        vectorSize: 1024,
        scoreThreshold: 0.7,
        cacheTtl: 604800,
        hybridEnabled: true,
    );
    $this->hybridService->setSparseEmbeddingService($this->mockSparseEmbedding);

    // Create service without hybrid
    $this->denseOnlyService = new QdrantService(
        embeddingService: $this->mockEmbedding,
        qdrant: $this->mockQdrantDense,
        vectorSize: 1024,
        scoreThreshold: 0.7,
        cacheTtl: 604800,
        hybridEnabled: false,
    );
});

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('makeHybridCollectionInfo')) {
    function makeHybridCollectionInfo(): CollectionInfo
    {
        return new CollectionInfo('green', 0, 0, 0);
    }
}

if (! function_exists('makeHybridRequestException')) {
    function makeHybridRequestException(int $status): RequestException
    {
        $response = Mockery::mock(SaloonResponse::class);
        $response->shouldReceive('status')->andReturn($status);
        $response->shouldReceive('body')->andReturn('');

        return new RequestException($response);
    }
}

if (! function_exists('mockHybridCollectionExists')) {
    function mockHybridCollectionExists(Mockery\MockInterface $qdrant, int $times = 1): void
    {
        $qdrant->shouldReceive('getCollection')
            ->times($times)
            ->andReturn(makeHybridCollectionInfo());
    }
}

describe('hybridSearch', function (): void {
    it('performs hybrid search with RRF fusion', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]]);

        $this->mockQdrant->shouldReceive('hybridSearch')
            ->once()
            ->andReturn([
                new ScoredPoint('result-1', 0.85, [
                    'title' => 'Hybrid Result',
                    'content' => 'Content from hybrid search',
                    'tags' => ['test'],
                    'category' => 'testing',
                ]),
            ]);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first())->toMatchArray([
            'id' => 'result-1',
            'score' => 0.85,
            'title' => 'Hybrid Result',
        ]);
    });

    it('falls back to dense search when hybrid not enabled', function (): void {
        mockHybridCollectionExists($this->mockQdrantDense);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockQdrantDense->shouldReceive('search')
            ->once()
            ->andReturn([
                new ScoredPoint('dense-result', 0.9, [
                    'title' => 'Dense Result',
                    'content' => 'From dense search',
                ]),
            ]);

        $results = $this->denseOnlyService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first()['title'])->toBe('Dense Result');
    });

    it('falls back to dense search when sparse embedding fails', function (): void {
        mockHybridCollectionExists($this->mockQdrant, 2);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [], 'values' => []]);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                new ScoredPoint('fallback-result', 0.8, [
                    'title' => 'Fallback Result',
                    'content' => 'From fallback',
                ]),
            ]);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first()['title'])->toBe('Fallback Result');
    });

    it('returns empty collection when dense embedding fails', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn([]);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when search fails', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5], 'values' => [0.5, 0.3]]);

        $this->mockQdrant->shouldReceive('hybridSearch')
            ->once()
            ->andThrow(makeHybridRequestException(500));

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toBeEmpty();
    });

    it('applies filters to hybrid search', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5], 'values' => [0.5, 0.3]]);

        $this->mockQdrant->shouldReceive('hybridSearch')
            ->once()
            ->andReturn([]);

        $filters = ['category' => 'testing', 'priority' => 'high'];
        $results = $this->hybridService->hybridSearch('test query', $filters);

        expect($results)->toBeEmpty();
    });

    it('respects custom limit and prefetch limit', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(['indices' => [1], 'values' => [0.5]]);

        $this->mockQdrant->shouldReceive('hybridSearch')
            ->once()
            ->andReturn([]);

        $results = $this->hybridService->hybridSearch('test', [], 10, 50);

        expect($results)->toBeEmpty();
    });
});

describe('hybrid collection creation', function (): void {
    it('creates collection with hybrid vectors when enabled', function (): void {
        $this->mockQdrant->shouldReceive('getCollection')
            ->once()
            ->andThrow(makeHybridRequestException(404));

        $this->mockQdrant->shouldReceive('createCollection')
            ->once()
            ->with('knowledge_test-project', 1024, 'Cosine', Mockery::on(fn ($val) => is_array($val) && isset($val['sparse'])))
            ->andReturn(true);

        $result = $this->hybridService->ensureCollection('test-project');

        expect($result)->toBeTrue();
    });
});

describe('hybrid upsert', function (): void {
    it('upserts with both dense and sparse vectors when hybrid enabled', function (): void {
        mockHybridCollectionExists($this->mockQdrant);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn(['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->with('knowledge_default', Mockery::on(function ($points) {
                return isset($points[0]['vector']['dense']) && isset($points[0]['vector']['sparse']);
            }))
            ->andReturn(new UpsertResult('completed'));

        $entry = [
            'id' => 'test-123',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        $result = $this->hybridService->upsert($entry, 'default', checkDuplicates: false);

        expect($result)->toBeTrue();
    });
});
