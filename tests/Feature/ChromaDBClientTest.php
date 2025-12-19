<?php

declare(strict_types=1);

use App\Services\ChromaDBClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('ChromaDBClient', function () {
    it('creates or gets a collection', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'col_123', 'name' => 'test_collection'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $collection = $chromaClient->getOrCreateCollection('test_collection');

        expect($collection)->toBeArray()
            ->and($collection['id'])->toBe('col_123')
            ->and($collection['name'])->toBe('test_collection');
    });

    it('adds documents to a collection', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->add(
            'col_123',
            ['doc1'],
            [[0.1, 0.2, 0.3]],
            [['key' => 'value']],
            ['test document']
        );

        expect($mock->count())->toBe(0);
    });

    it('queries a collection', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'ids' => [['doc1', 'doc2']],
                'distances' => [[0.1, 0.2]],
                'metadatas' => [[['key' => 'value1'], ['key' => 'value2']]],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $results = $chromaClient->query('col_123', [0.1, 0.2, 0.3], 10);

        expect($results)->toBeArray()
            ->and($results['ids'][0])->toBe(['doc1', 'doc2'])
            ->and($results['distances'][0])->toBe([0.1, 0.2]);
    });

    it('deletes documents from a collection', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->delete('col_123', ['doc1', 'doc2']);

        expect($mock->count())->toBe(0);
    });

    it('updates documents in a collection', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->update(
            'col_123',
            ['doc1'],
            [[0.1, 0.2, 0.3]],
            [['key' => 'updated']],
            ['updated document']
        );

        expect($mock->count())->toBe(0);
    });

    it('checks if ChromaDB is available', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        expect($chromaClient->isAvailable())->toBeTrue();
    });

    it('returns false when ChromaDB is not available', function () {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/api/v1/heartbeat')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        expect($chromaClient->isAvailable())->toBeFalse();
    });

    it('throws exception when collection creation fails', function () {
        $mock = new MockHandler([
            new Response(400, [], json_encode(['error' => 'Invalid request'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->getOrCreateCollection('test_collection');
    })->throws(RuntimeException::class);

    it('handles add operation errors', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Request error',
                new \GuzzleHttp\Psr7\Request('POST', '/api/v1/collections/col_123/add')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->add('col_123', ['doc1'], [[0.1, 0.2]], [['key' => 'value']]);
    })->throws(RuntimeException::class);

    it('handles query operation errors', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Request error',
                new \GuzzleHttp\Psr7\Request('POST', '/api/v1/collections/col_123/query')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->query('col_123', [0.1, 0.2]);
    })->throws(RuntimeException::class);

    it('handles delete operation errors', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Request error',
                new \GuzzleHttp\Psr7\Request('POST', '/api/v1/collections/col_123/delete')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->delete('col_123', ['doc1']);
    })->throws(RuntimeException::class);

    it('handles update operation errors', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Request error',
                new \GuzzleHttp\Psr7\Request('POST', '/api/v1/collections/col_123/update')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->update('col_123', ['doc1'], [[0.1, 0.2]], [['key' => 'value']]);
    })->throws(RuntimeException::class);

    it('handles query returning invalid JSON', function () {
        $mock = new MockHandler([
            new Response(200, [], 'invalid json'),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $results = $chromaClient->query('col_123', [0.1, 0.2]);

        expect($results)->toBeArray()->toBeEmpty();
    });

    it('returns cached collection on second call', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'col_123', 'name' => 'test_collection'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        // First call - hits the API
        $collection1 = $chromaClient->getOrCreateCollection('test_collection');

        // Second call - should return cached version
        $collection2 = $chromaClient->getOrCreateCollection('test_collection');

        expect($collection1['id'])->toBe('col_123')
            ->and($collection2['id'])->toBe('col_123')
            ->and($collection2['name'])->toBe('test_collection');
    });

    it('throws exception when collection response has no id', function () {
        $mock = new MockHandler([
            // GET collection returns 200 but without id (falls through to POST)
            new Response(200, [], json_encode(['name' => 'test_collection'])),
            // POST create returns response without id
            new Response(200, [], json_encode(['name' => 'test_collection'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $chromaClient->getOrCreateCollection('test_collection');
    })->throws(RuntimeException::class, 'Invalid response from ChromaDB');

    it('passes where filters to query', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'ids' => [['doc1']],
                'distances' => [[0.1]],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $results = $chromaClient->query('col_123', [0.1, 0.2], 10, ['category' => 'test']);

        expect($results)->toBeArray()
            ->and($results['ids'][0])->toBe(['doc1']);
    });

    it('returns false when heartbeat throws generic GuzzleException', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Server error',
                new Request('GET', '/api/v1/heartbeat'),
                new Response(500)
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        expect($chromaClient->isAvailable())->toBeFalse();
    });

    it('creates collection via POST when GET returns no id', function () {
        $mock = new MockHandler([
            // GET returns 200 but without id - falls through to POST
            new Response(200, [], json_encode(['name' => 'test_collection'])),
            // POST creates collection successfully
            new Response(200, [], json_encode(['id' => 'new_col_456', 'name' => 'test_collection'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $collection = $chromaClient->getOrCreateCollection('test_collection');

        expect($collection)->toBeArray()
            ->and($collection['id'])->toBe('new_col_456')
            ->and($collection['name'])->toBe('test_collection');
    });

    it('gets collection count successfully', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(42)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $count = $chromaClient->getCollectionCount('col_123');

        expect($count)->toBe(42);
    });

    it('returns zero when count returns non-integer', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $count = $chromaClient->getCollectionCount('col_123');

        expect($count)->toBe(0);
    });

    it('returns zero when count request fails', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException(
                'Server error',
                new Request('GET', '/api/v2/collections/col_123/count'),
                new Response(500)
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $chromaClient = new ChromaDBClient('http://localhost:8000');
        $reflection = new ReflectionClass($chromaClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($chromaClient, $client);

        $count = $chromaClient->getCollectionCount('col_123');

        expect($count)->toBe(0);
    });
});
