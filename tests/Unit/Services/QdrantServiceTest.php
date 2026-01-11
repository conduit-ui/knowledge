<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\DeletePoints;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;

uses()->group('qdrant-unit');

beforeEach(function () {
    Cache::flush();

    $this->mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
    $this->service = new QdrantService($this->mockEmbedding);
});

afterEach(function () {
    Mockery::close();
});

describe('ensureCollection', function () {
    it('returns true when collection already exists', function () {
        $connector = Mockery::mock(QdrantConnector::class);
        $response = Mockery::mock(Response::class);

        $response->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($response);

        // Inject connector via reflection
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('creates collection when it does not exist (404)', function () {
        $connector = Mockery::mock(QdrantConnector::class);
        $getResponse = Mockery::mock(Response::class);
        $createResponse = Mockery::mock(Response::class);

        $getResponse->shouldReceive('successful')->andReturn(false);
        $getResponse->shouldReceive('status')->andReturn(404);

        $createResponse->shouldReceive('successful')->andReturn(true);

        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $connector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        // Inject connector
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('throws exception when collection creation fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);
        $getResponse = Mockery::mock(Response::class);
        $createResponse = Mockery::mock(Response::class);

        $getResponse->shouldReceive('successful')->andReturn(false);
        $getResponse->shouldReceive('status')->andReturn(404);

        $createResponse->shouldReceive('successful')->andReturn(false);
        $createResponse->shouldReceive('json')->andReturn(['error' => 'Failed to create']);

        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $connector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(\RuntimeException::class, 'Failed to create collection');
    });

    it('throws exception on unexpected response status', function () {
        $connector = Mockery::mock(QdrantConnector::class);
        $response = Mockery::mock(Response::class);

        $response->shouldReceive('successful')->andReturn(false);
        $response->shouldReceive('status')->andReturn(500);

        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($response);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(\RuntimeException::class, 'Unexpected response: 500');
    });

    it('throws exception when Qdrant connection fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        // Create a mock response for ClientException
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(500);
        $response->shouldReceive('body')->andReturn('Connection failed');

        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andThrow(new ClientException($response, 'Connection failed'));

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(\RuntimeException::class, 'Qdrant connection failed: Connection failed');
    });
});

describe('upsert', function () {
    it('successfully upserts an entry with all fields', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content here')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        // Mock collection check
        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        // Mock upsert
        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '123',
            'title' => 'Test Title',
            'content' => 'Test content here',
            'tags' => ['tag1', 'tag2'],
            'category' => 'testing',
            'module' => 'TestModule',
            'priority' => 'high',
            'status' => 'validated',
            'confidence' => 85,
            'usage_count' => 5,
        ];

        expect($this->service->upsert($entry))->toBeTrue();
    });

    it('successfully upserts an entry with minimal fields', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Minimal Title Minimal content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '456',
            'title' => 'Minimal Title',
            'content' => 'Minimal content',
        ];

        expect($this->service->upsert($entry))->toBeTrue();
    });

    it('throws exception when embedding generation fails', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '789',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry))
            ->toThrow(\RuntimeException::class, 'Failed to generate embedding');
    });

    it('throws exception when upsert request fails', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(false);
        $upsertResponse->shouldReceive('json')->andReturn(['error' => 'Upsert failed']);
        $connector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '999',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry))
            ->toThrow(\RuntimeException::class, 'Failed to upsert entry to Qdrant: {"error":"Upsert failed"}');
    });

    it('uses cached embeddings when caching is enabled', function () {
        config(['search.qdrant.cache_embeddings' => true]);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getResponse);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->twice()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '111',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call - should generate embedding
        $this->service->upsert($entry);

        // Second call - should use cached embedding
        $this->service->upsert($entry);
    });

    it('does not cache embeddings when caching is disabled', function () {
        config(['search.qdrant.cache_embeddings' => false]);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->twice()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getResponse);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->twice()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $entry = [
            'id' => '222',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call - should generate embedding
        $this->service->upsert($entry);

        // Second call - should generate embedding again (not cached)
        $this->service->upsert($entry);
    });
});

describe('search', function () {
    it('successfully searches entries with query and filters', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('laravel testing')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $searchResponse = Mockery::mock(Response::class);
        $searchResponse->shouldReceive('successful')->andReturn(true);
        $searchResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-1',
                    'score' => 0.95,
                    'payload' => [
                        'title' => 'Laravel Testing Guide',
                        'content' => 'Testing with Pest',
                        'tags' => ['laravel', 'pest'],
                        'category' => 'testing',
                        'module' => 'Testing',
                        'priority' => 'high',
                        'status' => 'validated',
                        'confidence' => 90,
                        'usage_count' => 10,
                        'created_at' => '2025-01-01T00:00:00Z',
                        'updated_at' => '2025-01-01T00:00:00Z',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $filters = [
            'category' => 'testing',
            'priority' => 'high',
        ];

        $results = $this->service->search('laravel testing', $filters);

        expect($results)->toHaveCount(1);
        expect($results->first())->toMatchArray([
            'id' => 'test-1',
            'score' => 0.95,
            'title' => 'Laravel Testing Guide',
            'category' => 'testing',
            'priority' => 'high',
        ]);
    });

    it('returns empty collection when search fails', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $searchResponse = Mockery::mock(Response::class);
        $searchResponse->shouldReceive('successful')->andReturn(false);
        $connector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when embedding generation fails', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn([]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('handles search with tag filter', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $searchResponse = Mockery::mock(Response::class);
        $searchResponse->shouldReceive('successful')->andReturn(true);
        $searchResponse->shouldReceive('json')->andReturn(['result' => []]);
        $connector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $filters = ['tag' => 'laravel'];

        $results = $this->service->search('test query', $filters);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom limit', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $searchResponse = Mockery::mock(Response::class);
        $searchResponse->shouldReceive('successful')->andReturn(true);
        $searchResponse->shouldReceive('json')->andReturn(['result' => []]);
        $connector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $results = $this->service->search('test', [], 50);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom project', function () {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $searchResponse = Mockery::mock(Response::class);
        $searchResponse->shouldReceive('successful')->andReturn(true);
        $searchResponse->shouldReceive('json')->andReturn(['result' => []]);
        $connector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $results = $this->service->search('test', [], 20, 'custom-project');

        expect($results)->toBeEmpty();
    });
});

describe('delete', function () {
    it('successfully deletes entries by ID', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $deleteResponse = Mockery::mock(Response::class);
        $deleteResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $ids = ['id-1', 'id-2', 'id-3'];

        expect($this->service->delete($ids))->toBeTrue();
    });

    it('returns false when delete request fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $deleteResponse = Mockery::mock(Response::class);
        $deleteResponse->shouldReceive('successful')->andReturn(false);
        $connector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $ids = ['id-1'];

        expect($this->service->delete($ids))->toBeFalse();
    });

    it('handles delete with custom project', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getResponse = Mockery::mock(Response::class);
        $getResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $deleteResponse = Mockery::mock(Response::class);
        $deleteResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $ids = ['id-1'];

        expect($this->service->delete($ids, 'custom-project'))->toBeTrue();
    });
});

describe('getById', function () {
    it('successfully retrieves entry by ID with all fields', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-123',
                    'payload' => [
                        'title' => 'Test Entry',
                        'content' => 'Test content here',
                        'tags' => ['tag1', 'tag2'],
                        'category' => 'testing',
                        'module' => 'TestModule',
                        'priority' => 'high',
                        'status' => 'validated',
                        'confidence' => 85,
                        'usage_count' => 5,
                        'created_at' => '2025-01-01T00:00:00Z',
                        'updated_at' => '2025-01-10T00:00:00Z',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById('test-123');

        expect($result)->not()->toBeNull();
        expect($result)->toMatchArray([
            'id' => 'test-123',
            'title' => 'Test Entry',
            'content' => 'Test content here',
            'tags' => ['tag1', 'tag2'],
            'category' => 'testing',
            'module' => 'TestModule',
            'priority' => 'high',
            'status' => 'validated',
            'confidence' => 85,
            'usage_count' => 5,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-10T00:00:00Z',
        ]);
    });

    it('successfully retrieves entry with minimal payload fields', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'minimal-456',
                    'payload' => [
                        'title' => 'Minimal Entry',
                        'content' => 'Minimal content',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById('minimal-456');

        expect($result)->not()->toBeNull();
        expect($result)->toMatchArray([
            'id' => 'minimal-456',
            'title' => 'Minimal Entry',
            'content' => 'Minimal content',
            'tags' => [],
            'category' => null,
            'module' => null,
            'priority' => null,
            'status' => null,
            'confidence' => 0,
            'usage_count' => 0,
            'created_at' => '',
            'updated_at' => '',
        ]);
    });

    it('returns null when request fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(false);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('returns null when no points found in response', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('handles integer ID', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 123,
                    'payload' => [
                        'title' => 'Integer ID Entry',
                        'content' => 'Content',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById(123);

        expect($result)->not()->toBeNull();
        expect($result['id'])->toBe(123);
    });

    it('handles custom project namespace', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-id',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->getById('test-id', 'custom-project');

        expect($result)->not()->toBeNull();
    });
});

describe('incrementUsage', function () {
    it('successfully increments usage count for existing entry', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        // Mock ensureCollection for getById
        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice() // Once for getById, once for upsert
            ->andReturn($getCollectionResponse);

        // Mock getById response
        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-123',
                    'payload' => [
                        'title' => 'Test Entry',
                        'content' => 'Test content',
                        'tags' => ['tag1'],
                        'category' => 'testing',
                        'module' => 'TestModule',
                        'priority' => 'high',
                        'status' => 'validated',
                        'confidence' => 85,
                        'usage_count' => 5,
                        'created_at' => '2025-01-01T00:00:00Z',
                        'updated_at' => '2025-01-05T00:00:00Z',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        // Mock embedding generation for upsert
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Entry Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        // Mock upsert response
        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->incrementUsage('test-123');

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->incrementUsage('nonexistent');

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(false);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-id',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                        'tags' => [],
                        'usage_count' => 0,
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->incrementUsage('test-id', 'custom-project');

        expect($result)->toBeTrue();
    });

    it('increments from zero when usage_count is not set', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-id',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeTrue();
    });
});

describe('updateFields', function () {
    it('successfully updates multiple fields', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-123',
                    'payload' => [
                        'title' => 'Original Title',
                        'content' => 'Original content',
                        'tags' => ['tag1'],
                        'category' => 'original',
                        'priority' => 'low',
                        'status' => 'draft',
                        'confidence' => 50,
                        'usage_count' => 3,
                        'created_at' => '2025-01-01T00:00:00Z',
                        'updated_at' => '2025-01-05T00:00:00Z',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Updated Title Original content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $fieldsToUpdate = [
            'title' => 'Updated Title',
            'priority' => 'high',
            'confidence' => 95,
        ];

        $result = $this->service->updateFields('test-123', $fieldsToUpdate);

        expect($result)->toBeTrue();
    });

    it('successfully updates single field', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-456',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                        'status' => 'draft',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('test-456', ['status' => 'validated']);

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('nonexistent', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(false);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('test-id', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-id',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                        'status' => 'draft',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('test-id', ['status' => 'validated'], 'custom-project');

        expect($result)->toBeTrue();
    });

    it('merges updated fields with existing entry data', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-789',
                    'payload' => [
                        'title' => 'Original Title',
                        'content' => 'Original Content',
                        'tags' => ['tag1', 'tag2'],
                        'category' => 'test',
                        'priority' => 'low',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Original Title Original Content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('test-789', [
            'priority' => 'high',
            'tags' => ['tag1', 'tag2', 'tag3'],
        ]);

        expect($result)->toBeTrue();
    });

    it('updates empty fields array does not fail', function () {
        $connector = Mockery::mock(QdrantConnector::class);

        $getCollectionResponse = Mockery::mock(Response::class);
        $getCollectionResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->twice()
            ->andReturn($getCollectionResponse);

        $getPointsResponse = Mockery::mock(Response::class);
        $getPointsResponse->shouldReceive('successful')->andReturn(true);
        $getPointsResponse->shouldReceive('json')->andReturn([
            'result' => [
                [
                    'id' => 'test-id',
                    'payload' => [
                        'title' => 'Title',
                        'content' => 'Content',
                    ],
                ],
            ],
        ]);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \App\Integrations\Qdrant\Requests\GetPoints;
            }))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = Mockery::mock(Response::class);
        $upsertResponse->shouldReceive('successful')->andReturn(true);
        $connector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        $result = $this->service->updateFields('test-id', []);

        expect($result)->toBeTrue();
    });
});
