<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\ConnectionException;
use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Exceptions\Qdrant\EmbeddingException;
use App\Exceptions\Qdrant\UpsertException;
use App\Services\KnowledgeCacheService;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response as SaloonResponse;
use TheShit\Vector\Contracts\EmbeddingClient;
use TheShit\Vector\Data\CollectionInfo;
use TheShit\Vector\Data\ScoredPoint;
use TheShit\Vector\Data\ScrollResult;
use TheShit\Vector\Data\UpsertResult;
use TheShit\Vector\Qdrant;

uses()->group('qdrant-unit');

beforeEach(function (): void {
    Cache::flush();

    $this->mockEmbedding = Mockery::mock(EmbeddingClient::class);
    $this->mockQdrant = Mockery::mock(Qdrant::class);
    $this->service = new QdrantService($this->mockEmbedding, $this->mockQdrant);
});

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('makeCollectionInfo')) {
    function makeCollectionInfo(): CollectionInfo
    {
        return new CollectionInfo('green', 0, 0, 0);
    }
}

if (! function_exists('makeRequestException')) {
    function makeRequestException(int $status, string $body = ''): RequestException
    {
        $response = Mockery::mock(SaloonResponse::class);
        $response->shouldReceive('status')->andReturn($status);
        $response->shouldReceive('body')->andReturn($body);

        return new RequestException($response);
    }
}

if (! function_exists('makeUpsertResult')) {
    function makeUpsertResult(): UpsertResult
    {
        return new UpsertResult('completed');
    }
}

if (! function_exists('makeScoredPoint')) {
    /**
     * @param  array<string, mixed>  $payload
     */
    function makeScoredPoint(string|int $id, float $score = 0.0, array $payload = []): ScoredPoint
    {
        return new ScoredPoint($id, $score, $payload);
    }
}

if (! function_exists('makeScrollResult')) {
    /**
     * @param  array<ScoredPoint>  $points
     */
    function makeScrollResult(array $points = []): ScrollResult
    {
        return new ScrollResult($points);
    }
}

if (! function_exists('mockCollectionExists')) {
    function mockCollectionExists(Mockery\MockInterface $qdrant, int $times = 1): void
    {
        $qdrant->shouldReceive('getCollection')
            ->times($times)
            ->andReturn(makeCollectionInfo());
    }
}

describe('ensureCollection', function (): void {
    it('returns true when collection already exists', function (): void {
        mockCollectionExists($this->mockQdrant);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('creates collection when it does not exist (404)', function (): void {
        $this->mockQdrant->shouldReceive('getCollection')
            ->once()
            ->andThrow(makeRequestException(404));

        $this->mockQdrant->shouldReceive('createCollection')
            ->once()
            ->andReturn(true);

        expect($this->service->ensureCollection('test-project'))->toBeTrue();
    });

    it('throws exception when collection creation fails', function (): void {
        $this->mockQdrant->shouldReceive('getCollection')
            ->once()
            ->andThrow(makeRequestException(404));

        $this->mockQdrant->shouldReceive('createCollection')
            ->once()
            ->andThrow(makeRequestException(500, 'server error'));

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(CollectionCreationException::class, 'Failed to create collection');
    });

    it('throws exception on unexpected response status', function (): void {
        $this->mockQdrant->shouldReceive('getCollection')
            ->once()
            ->andThrow(makeRequestException(500));

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(ConnectionException::class);
    });

    it('throws exception when Qdrant connection fails', function (): void {
        $this->mockQdrant->shouldReceive('getCollection')
            ->once()
            ->andThrow(makeRequestException(503, 'Connection failed'));

        expect(fn () => $this->service->ensureCollection('test-project'))
            ->toThrow(ConnectionException::class, 'Qdrant connection failed');
    });
});

describe('upsert', function (): void {
    it('successfully upserts an entry with all fields', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content here')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

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

        expect($this->service->upsert($entry, 'default', false))->toBeTrue();
    });

    it('successfully upserts an entry with minimal fields', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Minimal Title Minimal content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => '456',
            'title' => 'Minimal Title',
            'content' => 'Minimal content',
        ];

        expect($this->service->upsert($entry, 'default', false))->toBeTrue();
    });

    it('throws exception when embedding generation fails', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([]);

        mockCollectionExists($this->mockQdrant);

        $entry = [
            'id' => '789',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry, 'default', false))
            ->toThrow(EmbeddingException::class);
    });

    it('throws exception when upsert request fails', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andThrow(makeRequestException(500, 'Upsert failed'));

        $entry = [
            'id' => '999',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry, 'default', false))
            ->toThrow(UpsertException::class, 'Failed to upsert entry to Qdrant');
    });

    it('uses cached embeddings when caching is enabled', function (): void {
        config(['search.qdrant.cache_embeddings' => true]);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('upsert')
            ->twice()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => '111',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call — generates embedding
        $this->service->upsert($entry, 'default', false);

        // Second call — uses cached embedding
        $this->service->upsert($entry, 'default', false);
    });

    it('does not cache embeddings when caching is disabled', function (): void {
        config(['search.qdrant.cache_embeddings' => false]);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->twice()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('upsert')
            ->twice()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => '222',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        // First call — generates embedding
        $this->service->upsert($entry, 'default', false);

        // Second call — generates embedding again (not cached)
        $this->service->upsert($entry, 'default', false);
    });
});

describe('search', function (): void {
    it('successfully searches entries with query and filters', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('laravel testing')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                makeScoredPoint('test-1', 0.95, [
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
                ]),
            ]);

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
        $this->mockEmbedding->shouldReceive('embed')
            ->with('query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andThrow(makeRequestException(500));

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when embedding generation fails', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('query')
            ->once()
            ->andReturn([]);

        mockCollectionExists($this->mockQdrant);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('handles search with tag filter', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('test query')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $filters = ['tag' => 'laravel'];

        $results = $this->service->search('test query', $filters);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom limit', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $results = $this->service->search('test', [], 50);

        expect($results)->toBeEmpty();
    });

    it('handles search with custom project', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $results = $this->service->search('test', [], 20, 'custom-project');

        expect($results)->toBeEmpty();
    });
});

describe('delete', function (): void {
    it('successfully deletes entries by ID', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('delete')
            ->once()
            ->andReturn(makeUpsertResult());

        $ids = ['id-1', 'id-2', 'id-3'];

        expect($this->service->delete($ids))->toBeTrue();
    });

    it('returns false when delete request fails', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('delete')
            ->once()
            ->andThrow(makeRequestException(500));

        $ids = ['id-1'];

        expect($this->service->delete($ids))->toBeFalse();
    });

    it('handles delete with custom project', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('delete')
            ->once()
            ->andReturn(makeUpsertResult());

        $ids = ['id-1'];

        expect($this->service->delete($ids, 'custom-project'))->toBeTrue();
    });
});

describe('getById', function (): void {
    it('successfully retrieves entry by ID with all fields', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-123', 0.0, [
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
                ]),
            ]);

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
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('minimal-456', 0.0, [
                    'title' => 'Minimal Entry',
                    'content' => 'Minimal content',
                ]),
            ]);

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
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andThrow(makeRequestException(500));

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('returns null when no points found in response', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([]);

        $result = $this->service->getById('nonexistent');

        expect($result)->toBeNull();
    });

    it('handles integer ID', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint(123, 0.0, [
                    'title' => 'Integer ID Entry',
                    'content' => 'Content',
                ]),
            ]);

        $result = $this->service->getById(123);

        expect($result)->not()->toBeNull();
        expect($result['id'])->toBe(123);
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                ]),
            ]);

        $result = $this->service->getById('test-id', 'custom-project');

        expect($result)->not()->toBeNull();
    });
});

describe('incrementUsage', function (): void {
    it('successfully increments usage count for existing entry', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-123', 0.0, [
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
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Entry Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->incrementUsage('test-123');

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([]);

        $result = $this->service->incrementUsage('nonexistent');

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andThrow(makeRequestException(500));

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                    'tags' => [],
                    'usage_count' => 0,
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->incrementUsage('test-id', 'custom-project');

        expect($result)->toBeTrue();
    });

    it('increments from zero when usage_count is not set', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->incrementUsage('test-id');

        expect($result)->toBeTrue();
    });
});

describe('updateFields', function (): void {
    it('successfully updates multiple fields', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-123', 0.0, [
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
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Updated Title Original content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $fieldsToUpdate = [
            'title' => 'Updated Title',
            'priority' => 'high',
            'confidence' => 95,
        ];

        $result = $this->service->updateFields('test-123', $fieldsToUpdate);

        expect($result)->toBeTrue();
    });

    it('successfully updates single field', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-456', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                    'status' => 'draft',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->updateFields('test-456', ['status' => 'validated']);

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([]);

        $result = $this->service->updateFields('nonexistent', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('returns false when getById fails', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andThrow(makeRequestException(500));

        $result = $this->service->updateFields('test-id', ['status' => 'validated']);

        expect($result)->toBeFalse();
    });

    it('handles custom project namespace', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                    'status' => 'draft',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->updateFields('test-id', ['status' => 'validated'], 'custom-project');

        expect($result)->toBeTrue();
    });

    it('merges updated fields with existing entry data', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-789', 0.0, [
                    'title' => 'Original Title',
                    'content' => 'Original Content',
                    'tags' => ['tag1', 'tag2'],
                    'category' => 'test',
                    'priority' => 'low',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->with('Original Title Original Content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->updateFields('test-789', [
            'priority' => 'high',
            'tags' => ['tag1', 'tag2', 'tag3'],
        ]);

        expect($result)->toBeTrue();
    });

    it('updates empty fields array does not fail', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Title',
                    'content' => 'Content',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->updateFields('test-id', []);

        expect($result)->toBeTrue();
    });
});

describe('upsert duplicate detection', function (): void {
    it('throws hash match exception when exact duplicate content exists', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findSimilar search returning exact match
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                makeScoredPoint('existing-id', 0.99, [
                    'title' => 'Test Title',
                    'content' => 'Test content',
                ]),
            ]);

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect(fn () => $this->service->upsert($entry, 'default', true))
            ->toThrow(DuplicateEntryException::class);
    });

    it('throws similarity match exception when similar entry exists', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content here')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findSimilar search returning similar (not exact) match
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                makeScoredPoint('existing-id', 0.97, [
                    'title' => 'Test Title',
                    'content' => 'Different content',
                ]),
            ]);

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content here',
        ];

        expect(fn () => $this->service->upsert($entry, 'default', true))
            ->toThrow(DuplicateEntryException::class);
    });

    it('skips duplicate detection when checkDuplicates is false', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        // Should NOT make a search call for duplicate detection
        $this->mockQdrant->shouldNotReceive('search');

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
        ];

        expect($this->service->upsert($entry, 'default', false))->toBeTrue();
    });

    it('proceeds when no similar entries found', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Unique Title Unique content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findSimilar returning no results
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'new-id',
            'title' => 'Unique Title',
            'content' => 'Unique content',
        ];

        expect($this->service->upsert($entry, 'default', true))->toBeTrue();
    });

    it('throws when fingerprint tag matches existing entry', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        // findByFingerprint scroll returning a match
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(makeScrollResult([
                makeScoredPoint('existing-fingerprint-id'),
            ]));

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'tags' => ['fingerprint:abc123', 'other-tag'],
        ];

        expect(fn () => $this->service->upsert($entry, 'default', true))
            ->toThrow(DuplicateEntryException::class);
    });

    it('throws when title and commit hash match existing entry', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        // findByTitleAndCommit scroll returning a match
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(makeScrollResult([
                makeScoredPoint('existing-commit-id'),
            ]));

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'commit' => 'abc1234',
        ];

        expect(fn () => $this->service->upsert($entry, 'default', true))
            ->toThrow(DuplicateEntryException::class);
    });

    it('proceeds when fingerprint has no match', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Unique Title Unique content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findByFingerprint scroll returning no match
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(makeScrollResult([]));

        // findSimilar returning no results (content hash check)
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'new-id',
            'title' => 'Unique Title',
            'content' => 'Unique content',
            'tags' => ['fingerprint:unique123'],
        ];

        expect($this->service->upsert($entry, 'default', true))->toBeTrue();
    });

    it('stores commit field in payload', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'test-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'commit' => 'abc1234def',
        ];

        expect($this->service->upsert($entry, 'default', false))->toBeTrue();
    });

    it('stores superseded fields in payload', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'test-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'superseded_by' => 'new-id',
            'superseded_date' => '2026-01-01T00:00:00Z',
            'superseded_reason' => 'Updated knowledge',
        ];

        expect($this->service->upsert($entry, 'default', false))->toBeTrue();
    });
});

describe('findSimilar', function (): void {
    it('returns similar entries above threshold', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                makeScoredPoint('similar-1', 0.97, [
                    'title' => 'Similar Entry',
                    'content' => 'Similar content',
                ]),
            ]);

        $results = $this->service->findSimilar([0.1, 0.2, 0.3], 'default', 0.95);

        expect($results)->toHaveCount(1);
        expect($results->first()['id'])->toBe('similar-1');
        expect($results->first()['score'])->toBe(0.97);
    });

    it('returns empty collection when no similar entries found', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $results = $this->service->findSimilar([0.1, 0.2, 0.3]);

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when search fails', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andThrow(makeRequestException(500));

        $results = $this->service->findSimilar([0.1, 0.2, 0.3]);

        expect($results)->toBeEmpty();
    });
});

describe('markSuperseded', function (): void {
    it('marks an existing entry as superseded', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('old-id', 0.0, [
                    'title' => 'Old Entry',
                    'content' => 'Old content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 50,
                    'usage_count' => 0,
                    'created_at' => '2025-01-01T00:00:00Z',
                    'updated_at' => '2025-01-01T00:00:00Z',
                ]),
            ]);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $result = $this->service->markSuperseded('old-id', 'new-id', 'Newer knowledge available');

        expect($result)->toBeTrue();
    });

    it('returns false when entry does not exist', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([]);

        $result = $this->service->markSuperseded('nonexistent', 'new-id');

        expect($result)->toBeFalse();
    });
});

describe('getSupersessionHistory', function (): void {
    it('returns empty history when entry does not exist', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([]);

        $history = $this->service->getSupersessionHistory('nonexistent');

        expect($history['supersedes'])->toBeEmpty();
        expect($history['superseded_by'])->toBeNull();
    });

    it('returns successor when entry is superseded', function (): void {
        mockCollectionExists($this->mockQdrant, 3);

        $this->mockQdrant->shouldReceive('getPoints')
            ->twice()
            ->andReturn(
                [
                    makeScoredPoint('old-id', 0.0, [
                        'title' => 'Old Entry',
                        'content' => 'Old content',
                        'superseded_by' => 'new-id',
                        'superseded_date' => '2026-01-15T00:00:00Z',
                        'superseded_reason' => 'Updated',
                    ]),
                ],
                [
                    makeScoredPoint('new-id', 0.0, [
                        'title' => 'New Entry',
                        'content' => 'New content',
                    ]),
                ],
            );

        // scroll for predecessors
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(makeScrollResult([]));

        $history = $this->service->getSupersessionHistory('old-id');

        expect($history['superseded_by'])->not()->toBeNull();
        expect($history['superseded_by']['id'])->toBe('new-id');
        expect($history['superseded_by']['title'])->toBe('New Entry');
    });

    it('returns predecessors when entry supersedes others', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('new-id', 0.0, [
                    'title' => 'New Entry',
                    'content' => 'New content',
                    'superseded_by' => null,
                ]),
            ]);

        // scroll for predecessors
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(makeScrollResult([
                makeScoredPoint('old-id-1', 0.0, [
                    'title' => 'Old Entry 1',
                    'content' => 'Old content 1',
                    'superseded_by' => 'new-id',
                    'superseded_reason' => 'Updated by new entry',
                ]),
                makeScoredPoint('old-id-2', 0.0, [
                    'title' => 'Old Entry 2',
                    'content' => 'Old content 2',
                    'superseded_by' => 'new-id',
                    'superseded_reason' => 'Also updated',
                ]),
            ]));

        $history = $this->service->getSupersessionHistory('new-id');

        expect($history['superseded_by'])->toBeNull();
        expect($history['supersedes'])->toHaveCount(2);
        expect($history['supersedes'][0]['id'])->toBe('old-id-1');
        expect($history['supersedes'][1]['id'])->toBe('old-id-2');
    });

    it('returns empty predecessors when scroll fails', function (): void {
        mockCollectionExists($this->mockQdrant, 2);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Entry',
                    'content' => 'Content',
                    'superseded_by' => null,
                ]),
            ]);

        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andThrow(makeRequestException(500));

        $history = $this->service->getSupersessionHistory('test-id');

        expect($history['supersedes'])->toBeEmpty();
        expect($history['superseded_by'])->toBeNull();
    });
});

describe('getById with superseded fields', function (): void {
    it('includes superseded fields in response', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Test Entry',
                    'content' => 'Content',
                    'superseded_by' => 'new-id',
                    'superseded_date' => '2026-01-15T00:00:00Z',
                    'superseded_reason' => 'Replaced',
                ]),
            ]);

        $result = $this->service->getById('test-id');

        expect($result)->not()->toBeNull();
        expect($result['superseded_by'])->toBe('new-id');
        expect($result['superseded_date'])->toBe('2026-01-15T00:00:00Z');
        expect($result['superseded_reason'])->toBe('Replaced');
    });

    it('returns null superseded fields when not set', function (): void {
        mockCollectionExists($this->mockQdrant);

        $this->mockQdrant->shouldReceive('getPoints')
            ->once()
            ->andReturn([
                makeScoredPoint('test-id', 0.0, [
                    'title' => 'Test Entry',
                    'content' => 'Content',
                ]),
            ]);

        $result = $this->service->getById('test-id');

        expect($result)->not()->toBeNull();
        expect($result['superseded_by'])->toBeNull();
        expect($result['superseded_date'])->toBeNull();
        expect($result['superseded_reason'])->toBeNull();
    });
});

describe('getCacheService', function (): void {
    it('returns null when no cache service is configured', function (): void {
        expect($this->service->getCacheService())->toBeNull();
    });
});

describe('search with cache service', function (): void {
    it('uses cache service rememberSearch when cache service is present', function (): void {
        $mockCacheService = Mockery::mock(KnowledgeCacheService::class);
        $serviceWithCache = new QdrantService(
            $this->mockEmbedding,
            $this->mockQdrant,
            384,
            0.7,
            604800,
            false,
            $mockCacheService,
        );

        $cachedResults = [
            ['id' => 'cached-1', 'title' => 'Cached Result', 'score' => 0.95],
        ];

        $mockCacheService->shouldReceive('rememberSearch')
            ->once()
            ->with('test query', [], 20, 'default', Mockery::type('Closure'))
            ->andReturn($cachedResults);

        $result = $serviceWithCache->search('test query');

        expect($result)->toHaveCount(1);
        expect($result->first()['id'])->toBe('cached-1');
    });
});

describe('getCachedEmbedding with cache service', function (): void {
    it('uses cache service rememberEmbedding when cache service is present', function (): void {
        $mockCacheService = Mockery::mock(KnowledgeCacheService::class);
        $serviceWithCache = new QdrantService(
            $this->mockEmbedding,
            $this->mockQdrant,
            384,
            0.7,
            604800,
            false,
            $mockCacheService,
        );

        $embedding = array_fill(0, 384, 0.1);

        $mockCacheService->shouldReceive('rememberEmbedding')
            ->once()
            ->with('test text', Mockery::type('Closure'))
            ->andReturn($embedding);

        $mockCacheService->shouldReceive('rememberSearch')
            ->never();

        $reflection = new ReflectionClass($serviceWithCache);
        $method = $reflection->getMethod('getCachedEmbedding');
        $method->setAccessible(true);

        $result = $method->invoke($serviceWithCache, 'test text');

        expect($result)->toBe($embedding);
    });
});

describe('searchRawCollection', function (): void {
    it('returns results from any collection by name', function (): void {
        $embedding = array_fill(0, 384, 0.1);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->with('punk rock')
            ->andReturn($embedding);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                makeScoredPoint('abc', 0.95, ['track' => 'Punkrocker', 'artist' => 'Teddybears']),
                makeScoredPoint('def', 0.85, ['track' => 'Blitzkrieg Bop', 'artist' => 'Ramones']),
            ]);

        $results = $this->service->searchRawCollection('music_events', 'punk rock', 10);

        expect($results)->toHaveCount(2)
            ->and($results[0]['payload']['track'])->toBe('Punkrocker')
            ->and($results[0]['score'])->toBe(0.95)
            ->and($results[1]['payload']['artist'])->toBe('Ramones');
    });

    it('returns empty collection when embedding fails', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn([]);

        $results = $this->service->searchRawCollection('music_events', 'test', 10);

        expect($results)->toBeEmpty();
    });

    it('returns empty collection on failed response', function (): void {
        $embedding = array_fill(0, 384, 0.1);

        $this->mockEmbedding->shouldReceive('embed')
            ->once()
            ->andReturn($embedding);

        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andThrow(makeRequestException(500));

        $results = $this->service->searchRawCollection('music_events', 'test', 10);

        expect($results)->toBeEmpty();
    });
});

describe('normalizeTags', function (): void {
    it('passes through normal arrays', function (): void {
        $method = new ReflectionMethod($this->service, 'normalizeTags');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, ['rock', 'punk']);

        expect($result)->toBe(['rock', 'punk']);
    });

    it('decodes JSON-encoded string arrays', function (): void {
        $method = new ReflectionMethod($this->service, 'normalizeTags');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '["rock","punk","metal"]');

        expect($result)->toBe(['rock', 'punk', 'metal']);
    });

    it('returns empty array for non-array non-JSON strings', function (): void {
        $method = new ReflectionMethod($this->service, 'normalizeTags');
        $method->setAccessible(true);

        expect($method->invoke($this->service, 'just a string'))->toBe([])
            ->and($method->invoke($this->service, null))->toBe([])
            ->and($method->invoke($this->service, '[not valid json'))->toBe([]);
    });
});

describe('findByFingerprint error handling', function (): void {
    it('returns null when scroll fails during fingerprint lookup', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findByFingerprint scroll throws
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andThrow(makeRequestException(500));

        // Since fingerprint lookup fails (returns null), duplicate check continues
        // findSimilar search returns no results
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'tags' => ['fingerprint:abc123'],
        ];

        expect($this->service->upsert($entry, 'default', true))->toBeTrue();
    });
});

describe('findByTitleAndCommit error handling', function (): void {
    it('returns null when scroll fails during commit lookup', function (): void {
        $this->mockEmbedding->shouldReceive('embed')
            ->with('Test Title Test content')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        mockCollectionExists($this->mockQdrant, 2);

        // findByTitleAndCommit scroll throws
        $this->mockQdrant->shouldReceive('scroll')
            ->once()
            ->andThrow(makeRequestException(500));

        // Since commit lookup fails (returns null), duplicate check continues
        // findSimilar search returns no results
        $this->mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->mockQdrant->shouldReceive('upsert')
            ->once()
            ->andReturn(makeUpsertResult());

        $entry = [
            'id' => 'new-id',
            'title' => 'Test Title',
            'content' => 'Test content',
            'commit' => 'abc1234',
        ];

        expect($this->service->upsert($entry, 'default', true))->toBeTrue();
    });
});
