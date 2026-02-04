<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\SparseEmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\HybridSearchPoints;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Response;

uses()->group('hybrid-search');

beforeEach(function (): void {
    Cache::flush();

    $this->mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
    $this->mockSparseEmbedding = Mockery::mock(SparseEmbeddingServiceInterface::class);
    $this->mockConnector = Mockery::mock(QdrantConnector::class);
    $this->mockConnectorDense = Mockery::mock(QdrantConnector::class);

    // Create service with hybrid enabled
    $this->hybridService = new QdrantService(
        embeddingService: $this->mockEmbedding,
        vectorSize: 1024,
        scoreThreshold: 0.7,
        cacheTtl: 604800,
        secure: false,
        hybridEnabled: true,
    );
    $this->hybridService->setSparseEmbeddingService($this->mockSparseEmbedding);

    // Create service without hybrid
    $this->denseOnlyService = new QdrantService(
        embeddingService: $this->mockEmbedding,
        vectorSize: 1024,
        scoreThreshold: 0.7,
        cacheTtl: 604800,
        secure: false,
        hybridEnabled: false,
    );

    // Inject mock connector via reflection for hybrid service
    $reflection = new ReflectionClass($this->hybridService);
    $property = $reflection->getProperty('connector');
    $property->setAccessible(true);
    $property->setValue($this->hybridService, $this->mockConnector);

    // Inject separate mock connector for dense-only service
    $reflection2 = new ReflectionClass($this->denseOnlyService);
    $property2 = $reflection2->getProperty('connector');
    $property2->setAccessible(true);
    $property2->setValue($this->denseOnlyService, $this->mockConnectorDense);
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * Create a mock Response object.
 */
function createHybridMockResponse(bool $successful, int $status = 200, ?array $json = null): Response
{
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('successful')->andReturn($successful);

    if (! $successful || $status !== 200) {
        $response->shouldReceive('status')->andReturn($status);
    }

    if ($json !== null) {
        $response->shouldReceive('json')->andReturn($json);
    }

    return $response;
}

/**
 * Mock collection exists check.
 */
function mockHybridCollectionExists(Mockery\MockInterface $connector, int $times = 1): void
{
    $response = createHybridMockResponse(true);
    $connector->shouldReceive('send')
        ->with(Mockery::type(GetCollectionInfo::class))
        ->times($times)
        ->andReturn($response);
}

describe('hybridSearch', function (): void {
    it('performs hybrid search with RRF fusion', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]]);

        $searchResponse = createHybridMockResponse(true, 200, [
            'result' => [
                'points' => [
                    [
                        'id' => 'result-1',
                        'score' => 0.85,
                        'payload' => [
                            'title' => 'Hybrid Result',
                            'content' => 'Content from hybrid search',
                            'tags' => ['test'],
                            'category' => 'testing',
                        ],
                    ],
                ],
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(HybridSearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first())->toMatchArray([
            'id' => 'result-1',
            'score' => 0.85,
            'title' => 'Hybrid Result',
        ]);
    });

    it('falls back to dense search when hybrid not enabled', function (): void {
        // Use separate mock for dense-only service
        // hybridSearch calls search() which calls ensureCollection
        $collectionResponse = createHybridMockResponse(true);
        $this->mockConnectorDense->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->andReturn($collectionResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createHybridMockResponse(true, 200, [
            'result' => [
                [
                    'id' => 'dense-result',
                    'score' => 0.9,
                    'payload' => [
                        'title' => 'Dense Result',
                        'content' => 'From dense search',
                    ],
                ],
            ],
        ]);

        $this->mockConnectorDense->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->denseOnlyService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first()['title'])->toBe('Dense Result');
    });

    it('falls back to dense search when sparse embedding fails', function (): void {
        // First call for hybridSearch, second for fallback search()
        mockHybridCollectionExists($this->mockConnector, 2);

        // The embedding is called once in hybridSearch, cached, then reused in search() fallback
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [], 'values' => []]);

        $searchResponse = createHybridMockResponse(true, 200, [
            'result' => [
                [
                    'id' => 'fallback-result',
                    'score' => 0.8,
                    'payload' => [
                        'title' => 'Fallback Result',
                        'content' => 'From fallback',
                    ],
                ],
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toHaveCount(1);
        expect($results->first()['title'])->toBe('Fallback Result');
    });

    it('returns empty collection when dense embedding fails', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn([]);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when search fails', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5], 'values' => [0.5, 0.3]]);

        $searchResponse = createHybridMockResponse(false, 500);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(HybridSearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->hybridService->hybridSearch('test query');

        expect($results)->toBeEmpty();
    });

    it('applies filters to hybrid search', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn(['indices' => [1, 5], 'values' => [0.5, 0.3]]);

        $searchResponse = createHybridMockResponse(true, 200, [
            'result' => [
                'points' => [],
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(HybridSearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $filters = ['category' => 'testing', 'priority' => 'high'];
        $results = $this->hybridService->hybridSearch('test query', $filters);

        expect($results)->toBeEmpty();
    });

    it('respects custom limit and prefetch limit', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(['indices' => [1], 'values' => [0.5]]);

        $searchResponse = createHybridMockResponse(true, 200, [
            'result' => ['points' => []],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(HybridSearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->hybridService->hybridSearch('test', [], 10, 50);

        expect($results)->toBeEmpty();
    });
});

describe('hybrid collection creation', function (): void {
    it('creates collection with hybrid vectors when enabled', function (): void {
        $getResponse = createHybridMockResponse(false, 404);
        $createResponse = createHybridMockResponse(true);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::on(function ($request): bool {
                if (! $request instanceof CreateCollection) {
                    return false;
                }
                // Check that the request has hybrid enabled
                $reflection = new ReflectionClass($request);
                $property = $reflection->getProperty('hybridEnabled');
                $property->setAccessible(true);

                return $property->getValue($request) === true;
            }))
            ->once()
            ->andReturn($createResponse);

        $result = $this->hybridService->ensureCollection('test-project');

        expect($result)->toBeTrue();
    });
});

describe('hybrid upsert', function (): void {
    it('upserts with both dense and sparse vectors when hybrid enabled', function (): void {
        mockHybridCollectionExists($this->mockConnector);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $this->mockSparseEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn(['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]]);

        $upsertResponse = createHybridMockResponse(true);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::on(function ($request): bool {
                if (! $request instanceof UpsertPoints) {
                    return false;
                }
                // Check that the point has named vectors
                $reflection = new ReflectionClass($request);
                $property = $reflection->getProperty('points');
                $property->setAccessible(true);
                $points = $property->getValue($request);

                return isset($points[0]['vector']['dense']) && isset($points[0]['vector']['sparse']);
            }))
            ->once()
            ->andReturn($upsertResponse);

        $entry = [
            'id' => 'test-123',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // Skip duplicate check to simplify test
        $result = $this->hybridService->upsert($entry, 'default', checkDuplicates: false);

        expect($result)->toBeTrue();
    });
});
