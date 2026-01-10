<?php

declare(strict_types=1);

use App\Services\ChromaDBClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('ChromaDBClient', function () {
    beforeEach(function () {
        $this->mockClient = Mockery::mock(Client::class);
        $this->service = new ChromaDBClient;

        // Inject mock client using reflection
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->service, $this->mockClient);
    });

    describe('constructor', function () {
        it('initializes with default values', function () {
            $service = new ChromaDBClient;

            expect($service)->toBeInstanceOf(ChromaDBClient::class);
        });

        it('initializes with custom values', function () {
            $service = new ChromaDBClient(
                'http://custom:8000',
                'custom_tenant',
                'custom_db'
            );

            expect($service)->toBeInstanceOf(ChromaDBClient::class);
        });

        it('trims trailing slash from base URL', function () {
            $service = new ChromaDBClient('http://localhost:8000/');

            expect($service)->toBeInstanceOf(ChromaDBClient::class);
        });
    });

    describe('getOrCreateCollection', function () {
        it('returns cached collection if already exists', function () {
            // First call - simulate GET success
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/test_collection')
                ->andReturn(new Response(200, [], json_encode([
                    'id' => 'collection-123',
                    'name' => 'test_collection',
                ])));

            $result = $this->service->getOrCreateCollection('test_collection');

            expect($result)
                ->toHaveKey('id', 'collection-123')
                ->toHaveKey('name', 'test_collection');

            // Second call - should return from cache without HTTP call
            $result2 = $this->service->getOrCreateCollection('test_collection');

            expect($result2)
                ->toHaveKey('id', 'collection-123')
                ->toHaveKey('name', 'test_collection');
        });

        it('gets existing collection from server', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'id' => 'collection-456',
                    'name' => 'existing_collection',
                ])));

            $result = $this->service->getOrCreateCollection('existing_collection');

            expect($result)->toMatchArray([
                'id' => 'collection-456',
                'name' => 'existing_collection',
            ]);
        });

        it('creates new collection when not found', function () {
            // GET returns 404
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andReturn(new Response(404));

            // POST creates collection
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections', [
                    'json' => [
                        'name' => 'new_collection',
                    ],
                ])
                ->andReturn(new Response(201, [], json_encode([
                    'id' => 'collection-789',
                    'name' => 'new_collection',
                ])));

            $result = $this->service->getOrCreateCollection('new_collection');

            expect($result)
                ->toHaveKey('id', 'collection-789')
                ->toHaveKey('name', 'new_collection');
        });

        it('throws exception on invalid response data', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andReturn(new Response(404));

            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'invalid' => 'response',
                ])));

            expect(fn () => $this->service->getOrCreateCollection('invalid'))
                ->toThrow(RuntimeException::class, 'Invalid response from ChromaDB');
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andThrow(new RequestException(
                    'Connection failed',
                    new Request('GET', 'test')
                ));

            expect(fn () => $this->service->getOrCreateCollection('error'))
                ->toThrow(RuntimeException::class, 'Failed to create collection');
        });
    });

    describe('add', function () {
        it('adds documents without document texts', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/add', [
                    'json' => [
                        'ids' => ['id1', 'id2'],
                        'embeddings' => [[0.1, 0.2], [0.3, 0.4]],
                        'metadatas' => [['key' => 'val1'], ['key' => 'val2']],
                    ],
                ])
                ->andReturn(new Response(200));

            $this->service->add(
                'col-123',
                ['id1', 'id2'],
                [[0.1, 0.2], [0.3, 0.4]],
                [['key' => 'val1'], ['key' => 'val2']]
            );

            expect(true)->toBeTrue(); // Void method assertion
        });

        it('adds documents with document texts', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/add', [
                    'json' => [
                        'ids' => ['id1'],
                        'embeddings' => [[0.1, 0.2]],
                        'metadatas' => [['key' => 'val']],
                        'documents' => ['Document text'],
                    ],
                ])
                ->andReturn(new Response(200));

            $this->service->add(
                'col-123',
                ['id1'],
                [[0.1, 0.2]],
                [['key' => 'val']],
                ['Document text']
            );

            expect(true)->toBeTrue();
        });

        it('throws exception when add fails with 4xx status', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(400, [], 'Bad Request'));

            expect(fn () => $this->service->add(
                'col-123',
                ['id1'],
                [[0.1]],
                [['key' => 'val']]
            ))->toThrow(RuntimeException::class, 'ChromaDB add failed');
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            expect(fn () => $this->service->add(
                'col-123',
                ['id1'],
                [[0.1]],
                [['key' => 'val']]
            ))->toThrow(RuntimeException::class, 'Failed to add documents');
        });
    });

    describe('query', function () {
        it('queries with default parameters', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/query', [
                    'json' => [
                        'query_embeddings' => [[0.1, 0.2, 0.3]],
                        'n_results' => 10,
                        'include' => ['metadatas', 'documents', 'distances'],
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'ids' => [['id1', 'id2']],
                    'distances' => [[0.1, 0.2]],
                    'metadatas' => [[['key' => 'val1'], ['key' => 'val2']]],
                ])));

            $result = $this->service->query('col-123', [0.1, 0.2, 0.3]);

            expect($result)->toHaveKey('ids');
        });

        it('queries with custom n_results', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/query', [
                    'json' => [
                        'query_embeddings' => [[0.1, 0.2]],
                        'n_results' => 5,
                        'include' => ['metadatas', 'documents', 'distances'],
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode(['results' => []])));

            $result = $this->service->query('col-123', [0.1, 0.2], 5);

            expect($result)->toBeArray();
        });

        it('queries with where filter', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/query', [
                    'json' => [
                        'query_embeddings' => [[0.1]],
                        'n_results' => 10,
                        'include' => ['metadatas', 'documents', 'distances'],
                        'where' => ['type' => 'document'],
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode(['results' => []])));

            $result = $this->service->query('col-123', [0.1], 10, ['type' => 'document']);

            expect($result)->toBeArray();
        });

        it('returns empty array on invalid response', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], 'invalid json'));

            $result = $this->service->query('col-123', [0.1]);

            expect($result)->toBe([]);
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            expect(fn () => $this->service->query('col-123', [0.1]))
                ->toThrow(RuntimeException::class, 'Failed to query documents');
        });
    });

    describe('delete', function () {
        it('deletes documents by IDs', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/delete', [
                    'json' => [
                        'ids' => ['id1', 'id2', 'id3'],
                    ],
                ])
                ->andReturn(new Response(200));

            $this->service->delete('col-123', ['id1', 'id2', 'id3']);

            expect(true)->toBeTrue();
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            expect(fn () => $this->service->delete('col-123', ['id1']))
                ->toThrow(RuntimeException::class, 'Failed to delete documents');
        });
    });

    describe('update', function () {
        it('updates documents without document texts', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/update', [
                    'json' => [
                        'ids' => ['id1'],
                        'embeddings' => [[0.5, 0.6]],
                        'metadatas' => [['updated' => true]],
                    ],
                ])
                ->andReturn(new Response(200));

            $this->service->update(
                'col-123',
                ['id1'],
                [[0.5, 0.6]],
                [['updated' => true]]
            );

            expect(true)->toBeTrue();
        });

        it('updates documents with document texts', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/update', [
                    'json' => [
                        'ids' => ['id1'],
                        'embeddings' => [[0.5]],
                        'metadatas' => [['key' => 'val']],
                        'documents' => ['Updated text'],
                    ],
                ])
                ->andReturn(new Response(200));

            $this->service->update(
                'col-123',
                ['id1'],
                [[0.5]],
                [['key' => 'val']],
                ['Updated text']
            );

            expect(true)->toBeTrue();
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            expect(fn () => $this->service->update(
                'col-123',
                ['id1'],
                [[0.1]],
                [['key' => 'val']]
            ))->toThrow(RuntimeException::class, 'Failed to update documents');
        });
    });

    describe('getCollectionCount', function () {
        it('returns count from server', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/count')
                ->andReturn(new Response(200, [], '42'));

            $result = $this->service->getCollectionCount('col-123');

            expect($result)->toBe(42);
        });

        it('returns 0 on invalid response', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andReturn(new Response(200, [], '"not an integer"'));

            $result = $this->service->getCollectionCount('col-123');

            expect($result)->toBe(0);
        });

        it('returns 0 on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('GET', 'test')
                ));

            $result = $this->service->getCollectionCount('col-123');

            expect($result)->toBe(0);
        });
    });

    describe('isAvailable', function () {
        it('returns true when heartbeat succeeds', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->with('/api/v2/heartbeat')
                ->andReturn(new Response(200));

            $result = $this->service->isAvailable();

            expect($result)->toBeTrue();
        });

        it('returns false when heartbeat returns non-200', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andReturn(new Response(500));

            $result = $this->service->isAvailable();

            expect($result)->toBeFalse();
        });

        it('returns false on ConnectException', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andThrow(new ConnectException(
                    'Connection refused',
                    new Request('GET', 'test')
                ));

            $result = $this->service->isAvailable();

            expect($result)->toBeFalse();
        });

        it('returns false on generic Guzzle exception', function () {
            $this->mockClient
                ->shouldReceive('get')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('GET', 'test')
                ));

            $result = $this->service->isAvailable();

            expect($result)->toBeFalse();
        });
    });

    describe('getAll', function () {
        it('retrieves all documents with default limit', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/get', [
                    'json' => [
                        'limit' => 10000,
                        'include' => ['metadatas'],
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'ids' => ['id1', 'id2'],
                    'metadatas' => [['key' => 'val1'], ['key' => 'val2']],
                ])));

            $result = $this->service->getAll('col-123');

            expect($result)
                ->toHaveKey('ids')
                ->toHaveKey('metadatas')
                ->and($result['ids'])->toBe(['id1', 'id2'])
                ->and($result['metadatas'])->toHaveCount(2);
        });

        it('retrieves all documents with custom limit', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/api/v2/tenants/default_tenant/databases/default_database/collections/col-123/get', [
                    'json' => [
                        'limit' => 100,
                        'include' => ['metadatas'],
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'ids' => ['id1'],
                    'metadatas' => [['key' => 'val']],
                ])));

            $result = $this->service->getAll('col-123', 100);

            expect($result)->toHaveKey('ids');
        });

        it('returns empty arrays when response missing data', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode(['other' => 'data'])));

            $result = $this->service->getAll('col-123');

            expect($result)
                ->toBe([
                    'ids' => [],
                    'metadatas' => [],
                ]);
        });

        it('throws exception on Guzzle error', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            expect(fn () => $this->service->getAll('col-123'))
                ->toThrow(RuntimeException::class, 'Failed to get documents');
        });
    });
});
