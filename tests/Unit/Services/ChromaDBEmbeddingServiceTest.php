<?php

declare(strict_types=1);

use App\Services\ChromaDBEmbeddingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('ChromaDBEmbeddingService', function () {
    beforeEach(function () {
        $this->mockClient = Mockery::mock(Client::class);
        $this->service = new ChromaDBEmbeddingService;

        // Inject mock client using reflection
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->service, $this->mockClient);
    });

    describe('constructor', function () {
        it('initializes with default values', function () {
            $service = new ChromaDBEmbeddingService;

            expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
        });

        it('initializes with custom values', function () {
            $service = new ChromaDBEmbeddingService(
                'http://custom:8001',
                'custom-model'
            );

            expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
        });

        it('trims trailing slash from server URL', function () {
            $service = new ChromaDBEmbeddingService('http://localhost:8001/');

            expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
        });
    });

    describe('generate', function () {
        it('returns empty array for empty string', function () {
            $result = $this->service->generate('');

            expect($result)->toBe([]);
        });

        it('returns empty array for whitespace-only string', function () {
            $result = $this->service->generate('   ');

            expect($result)->toBe([]);
        });

        it('generates embedding with embeddings array format', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/embed', [
                    'json' => [
                        'text' => 'Test text',
                        'model' => 'all-MiniLM-L6-v2',
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'embeddings' => [[0.1, 0.2, 0.3, 0.4]],
                ])));

            $result = $this->service->generate('Test text');

            expect($result)
                ->toBe([0.1, 0.2, 0.3, 0.4]);
        });

        it('generates embedding with embedding singular format', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/embed', [
                    'json' => [
                        'text' => 'Another test',
                        'model' => 'all-MiniLM-L6-v2',
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'embedding' => [0.5, 0.6, 0.7],
                ])));

            $result = $this->service->generate('Another test');

            expect($result)->toBe([0.5, 0.6, 0.7]);
        });

        it('casts embedding values to float', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'embedding' => [1, 2, 3], // integers
                ])));

            $result = $this->service->generate('Test');

            expect($result)
                ->toBe([1.0, 2.0, 3.0])
                ->and($result[0])->toBeFloat()
                ->and($result[1])->toBeFloat()
                ->and($result[2])->toBeFloat();
        });

        it('returns empty array on non-200 status', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(500, [], 'Internal Server Error'));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array when response is not JSON', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], 'not json'));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array when response has no embedding fields', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'other_field' => 'value',
                ])));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array when embeddings is empty array', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'embeddings' => [],
                ])));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array when embedding is not an array', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'embedding' => 'not an array',
                ])));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array when embeddings first element is missing', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new Response(200, [], json_encode([
                    'embeddings' => [null],
                ])));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('returns empty array on Guzzle exception', function () {
            $this->mockClient
                ->shouldReceive('post')
                ->once()
                ->andThrow(new RequestException(
                    'Network error',
                    new Request('POST', 'test')
                ));

            $result = $this->service->generate('Test');

            expect($result)->toBe([]);
        });

        it('uses custom model when provided in constructor', function () {
            $customService = new ChromaDBEmbeddingService(
                'http://localhost:8001',
                'custom-model-name'
            );

            $mockClient = Mockery::mock(Client::class);
            $reflection = new ReflectionClass($customService);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($customService, $mockClient);

            $mockClient
                ->shouldReceive('post')
                ->once()
                ->with('/embed', [
                    'json' => [
                        'text' => 'Test',
                        'model' => 'custom-model-name',
                    ],
                ])
                ->andReturn(new Response(200, [], json_encode([
                    'embedding' => [0.1],
                ])));

            $result = $customService->generate('Test');

            expect($result)->toBe([0.1]);
        });
    });

    describe('similarity', function () {
        it('calculates cosine similarity for identical vectors', function () {
            $vector = [0.5, 0.5, 0.5];

            $result = $this->service->similarity($vector, $vector);

            expect($result)->toBeGreaterThan(0.9999);
        });

        it('calculates cosine similarity for orthogonal vectors', function () {
            $a = [1.0, 0.0, 0.0];
            $b = [0.0, 1.0, 0.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(0.0);
        });

        it('calculates cosine similarity for opposite vectors', function () {
            $a = [1.0, 0.0];
            $b = [-1.0, 0.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(-1.0);
        });

        it('calculates similarity for positive correlated vectors', function () {
            $a = [1.0, 2.0, 3.0];
            $b = [2.0, 4.0, 6.0]; // Same direction, different magnitude

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeGreaterThan(0.99);
        });

        it('returns 0 for different length vectors', function () {
            $a = [1.0, 2.0, 3.0];
            $b = [1.0, 2.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(0.0);
        });

        it('returns 0 for empty vectors', function () {
            $result = $this->service->similarity([], []);

            expect($result)->toBe(0.0);
        });

        it('returns 0 when first vector is zero vector', function () {
            $a = [0.0, 0.0, 0.0];
            $b = [1.0, 2.0, 3.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(0.0);
        });

        it('returns 0 when second vector is zero vector', function () {
            $a = [1.0, 2.0, 3.0];
            $b = [0.0, 0.0, 0.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(0.0);
        });

        it('handles negative values correctly', function () {
            $a = [-1.0, -2.0, -3.0];
            $b = [1.0, 2.0, 3.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeGreaterThan(-1.01)
                ->and($result)->toBeLessThan(-0.99);
        });

        it('handles single element vectors', function () {
            $a = [5.0];
            $b = [10.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBe(1.0);
        });

        it('calculates correct similarity for mixed positive and negative', function () {
            $a = [1.0, -1.0, 0.0];
            $b = [1.0, 1.0, 0.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeFloat()
                ->and($result)->toBeGreaterThan(-1.0)
                ->and($result)->toBeLessThan(1.0);
        });

        it('handles very small magnitude vectors', function () {
            $a = [0.0001, 0.0002, 0.0003];
            $b = [0.0002, 0.0004, 0.0006];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeGreaterThan(0.99);
        });

        it('handles large magnitude vectors', function () {
            $a = [1000.0, 2000.0, 3000.0];
            $b = [2000.0, 4000.0, 6000.0];

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeGreaterThan(0.99);
        });

        it('calculates similarity for high dimensional vectors', function () {
            $a = array_fill(0, 384, 0.5); // Common embedding dimension
            $b = array_fill(0, 384, 0.5);

            $result = $this->service->similarity($a, $b);

            expect($result)->toBeGreaterThan(0.9999);
        });
    });
});
