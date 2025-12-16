<?php

declare(strict_types=1);

use App\Services\ChromaDBEmbeddingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('ChromaDBEmbeddingService', function () {
    it('generates embeddings from text', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'embedding' => [0.1, 0.2, 0.3, 0.4, 0.5],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        $embedding = $service->generate('test text');

        expect($embedding)->toBeArray()
            ->and($embedding)->toHaveCount(5)
            ->and($embedding[0])->toBe(0.1);
    });

    it('returns empty array for empty text', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $embedding = $service->generate('');

        expect($embedding)->toBeArray()->toBeEmpty();
    });

    it('returns empty array on API failure', function () {
        $mock = new MockHandler([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handlerStack,
            'http_errors' => false,
        ]);

        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        $embedding = $service->generate('test text');

        expect($embedding)->toBeArray()->toBeEmpty();
    });

    it('returns empty array on invalid response format', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => 'invalid'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        $embedding = $service->generate('test text');

        expect($embedding)->toBeArray()->toBeEmpty();
    });

    it('calculates cosine similarity correctly', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $a = [1.0, 0.0, 0.0];
        $b = [1.0, 0.0, 0.0];

        $similarity = $service->similarity($a, $b);

        expect($similarity)->toBe(1.0);
    });

    it('returns zero similarity for different vectors', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $a = [1.0, 0.0, 0.0];
        $b = [0.0, 1.0, 0.0];

        $similarity = $service->similarity($a, $b);

        expect($similarity)->toBe(0.0);
    });

    it('returns zero similarity for mismatched vector lengths', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $a = [1.0, 0.0];
        $b = [1.0, 0.0, 0.0];

        $similarity = $service->similarity($a, $b);

        expect($similarity)->toBe(0.0);
    });

    it('returns zero similarity for empty vectors', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $similarity = $service->similarity([], []);

        expect($similarity)->toBe(0.0);
    });

    it('handles network errors gracefully', function () {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection failed',
                new \GuzzleHttp\Psr7\Request('POST', '/embed')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        $embedding = $service->generate('test text');

        expect($embedding)->toBeArray()->toBeEmpty();
    });

    it('returns zero similarity for zero magnitude vectors', function () {
        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');

        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 0.0, 0.0];

        $similarity = $service->similarity($a, $b);

        expect($similarity)->toBe(0.0);
    });

    it('returns empty array when response status is not 200', function () {
        $mock = new MockHandler([
            new Response(404, [], json_encode(['error' => 'Not found'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handlerStack,
            'http_errors' => false,
        ]);

        $service = new ChromaDBEmbeddingService('http://localhost:8001', 'all-MiniLM-L6-v2');
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        $embedding = $service->generate('test text');

        expect($embedding)->toBeArray()->toBeEmpty();
    });
});
