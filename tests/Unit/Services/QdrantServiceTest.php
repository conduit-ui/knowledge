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

        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andThrow(new ClientException('Connection failed'));

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('connector');
        $property->setAccessible(true);
        $property->setValue($this->service, $connector);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(\RuntimeException::class, 'Qdrant connection failed');
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
            ->toThrow(\RuntimeException::class, 'Qdrant upsert failed');
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
