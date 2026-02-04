<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\DeletePoints;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\GetPoints;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;

uses()->group('qdrant-unit');

beforeEach(function (): void {
    Cache::flush();

    $this->mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
    $this->mockConnector = Mockery::mock(QdrantConnector::class);
    $this->service = new QdrantService($this->mockEmbedding);

    // Inject mock connector via reflection
    $reflection = new ReflectionClass($this->service);
    $property = $reflection->getProperty('connector');
    $property->setAccessible(true);
    $property->setValue($this->service, $this->mockConnector);
});

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('createMockResponse')) {
    /**
     * Create a mock Response object with common configuration.
     */
    function createMockResponse(bool $successful, int $status = 200, ?array $json = null): Response
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
}

if (! function_exists('mockCollectionExists')) {
    /**
     * Set up mock for ensureCollection to return success (collection exists).
     */
    function mockCollectionExists(Mockery\MockInterface $connector, int $times = 1): void
    {
        $response = createMockResponse(true);
        $connector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->times($times)
            ->andReturn($response);
    }
}

describe('ensureCollection', function (): void {
    it('returns true when collection already exists', function (): void {
        mockCollectionExists($this->mockConnector);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('creates collection when it does not exist (404)', function (): void {
        $getResponse = createMockResponse(false, 404);
        $createResponse = createMockResponse(true);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('throws exception when collection creation fails', function (): void {
        $getResponse = createMockResponse(false, 404);
        $createResponse = createMockResponse(false, 500, ['error' => 'Failed to create']);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(RuntimeException::class, 'Failed to create collection');
    });

    it('throws exception on unexpected response status', function (): void {
        $response = createMockResponse(false, 500);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($response);

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(RuntimeException::class, 'Unexpected response: 500');
    });

    it('throws exception when Qdrant connection fails', function (): void {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(500);
        $response->shouldReceive('body')->andReturn('Connection failed');

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andThrow(new ClientException($response, 'Connection failed'));

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(RuntimeException::class, 'Qdrant connection failed: Connection failed');
    });
});

describe('upsert', function (): void {
    it('successfully upserts an entry with all fields', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content here')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

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

        expect($this->service->upsert($entry, 'default'))->toBeTrue();
    });

    it('successfully upserts an entry with minimal fields', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Minimal Title Minimal content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $entry = [
            'id' => '456',
            'title' => 'Minimal Title',
            'content' => 'Minimal content',
        ];

        expect($this->service->upsert($entry, 'default'))->toBeTrue();
    });

    it('throws exception when embedding generation fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([]);

        mockCollectionExists($this->mockConnector);

        $entry = [
            'id' => '789',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry, 'default'))
            ->toThrow(RuntimeException::class, 'Failed to generate embedding');
    });

    it('throws exception when upsert request fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $upsertResponse = createMockResponse(false, 500, ['error' => 'Upsert failed']);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $entry = [
            'id' => '999',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry, 'default'))
            ->toThrow(RuntimeException::class, 'Failed to upsert entry to Qdrant: {"error":"Upsert failed"}');
    });

    it('uses cached embeddings when caching is enabled', function (): void {
        config(['search.qdrant.cache_embeddings' => true]);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector, 2);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->twice()
            ->andReturn($upsertResponse);

        $entry = [
            'id' => '111',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call - generates embedding
        $this->service->upsert($entry, 'default');

        // Second call - uses cached embedding
        $this->service->upsert($entry, 'default');
    });

    it('does not cache embeddings when caching is disabled', function (): void {
        config(['search.qdrant.cache_embeddings' => false]);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Title Test content')
            ->twice()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector, 2);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->twice()
            ->andReturn($upsertResponse);

        $entry = [
            'id' => '222',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call - generates embedding
        $this->service->upsert($entry, 'default');

        // Second call - generates embedding again (not cached)
        $this->service->upsert($entry, 'default');
    });
});

describe('search', function (): void {
    it('successfully searches entries with query and filters', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('laravel testing')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $searchResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

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

    it('returns empty collection when search fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $searchResponse = createMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when embedding generation fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn([]);

        mockCollectionExists($this->mockConnector);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('handles search with tag filter', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $searchResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $filters = ['tag' => 'laravel'];

        $results = $this->service->search('test query', $filters);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom limit', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $searchResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('test', [], 50);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom project', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockConnector);

        $searchResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('test', [], 20, 'custom-project');

        expect($results)->toBeEmpty();
    });
});

describe('delete', function (): void {
    it('successfully deletes entries by ID', function (): void {
        mockCollectionExists($this->mockConnector);

        $deleteResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $ids = ['id-1', 'id-2', 'id-3'];

        expect($this->service->delete($ids))->toBeTrue();
    });

    it('returns false when delete request fails', function (): void {
        mockCollectionExists($this->mockConnector);

        $deleteResponse = createMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $ids = ['id-1'];

        expect($this->service->delete($ids))->toBeFalse();
    });

    it('handles delete with custom project', function (): void {
        mockCollectionExists($this->mockConnector);

        $deleteResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $ids = ['id-1'];

        expect($this->service->delete($ids, 'custom-project'))->toBeTrue();
    });
});

describe('getById', function (): void {
    it('successfully retrieves entry by ID with all fields', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

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

    it('successfully retrieves entry with minimal payload fields', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

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

    it('returns null when request fails', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('returns null when no points found in response', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('handles integer ID', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->getById(123);

        expect($result)->not()->toBeNull();
        expect($result['id'])->toBe(123);
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->getById('test-id', 'custom-project');

        expect($result)->not()->toBeNull();
    });
});

describe('incrementUsage', function (): void {
    it('successfully increments usage count for existing entry', function (): void {
        // Mock ensureCollection for getById and upsert
        mockCollectionExists($this->mockConnector, 2);

        // Mock getById response
        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        // Mock embedding generation for upsert
        $this->mockEmbedding->shouldReceive('generate')
            ->with('Test Entry Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        // Mock upsert response
        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->incrementUsage('test-123');

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->incrementUsage('nonexistent');

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->incrementUsage('test-id', 'custom-project');

        expect($result)->toBeTrue();
    });

    it('increments from zero when usage_count is not set', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeTrue();
    });
});

describe('updateFields', function (): void {
    it('successfully updates multiple fields', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Updated Title Original content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $fieldsToUpdate = [
            'title' => 'Updated Title',
            'priority' => 'high',
            'confidence' => 95,
        ];

        $result = $this->service->updateFields('test-123', $fieldsToUpdate);

        expect($result)->toBeTrue();
    });

    it('successfully updates single field', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->updateFields('test-456', ['status' => 'validated']);

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->updateFields('nonexistent', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function (): void {
        mockCollectionExists($this->mockConnector);

        $getPointsResponse = createMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $result = $this->service->updateFields('test-id', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->updateFields('test-id', ['status' => 'validated'], 'custom-project');

        expect($result)->toBeTrue();
    });

    it('merges updated fields with existing entry data', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->with('Original Title Original Content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->updateFields('test-789', [
            'priority' => 'high',
            'tags' => ['tag1', 'tag2', 'tag3'],
        ]);

        expect($result)->toBeTrue();
    });

    it('updates empty fields array does not fail', function (): void {
        mockCollectionExists($this->mockConnector, 2);

        $getPointsResponse = createMockResponse(true, 200, [
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
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetPoints::class))
            ->once()
            ->andReturn($getPointsResponse);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $upsertResponse = createMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->updateFields('test-id', []);

        expect($result)->toBeTrue();
    });
});
