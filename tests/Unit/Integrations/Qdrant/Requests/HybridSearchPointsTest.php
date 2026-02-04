<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\HybridSearchPoints;

uses()->group('qdrant-requests');

describe('HybridSearchPoints', function (): void {
    it('creates request with correct endpoint', function (): void {
        $request = new HybridSearchPoints(
            collectionName: 'test_collection',
            denseVector: [0.1, 0.2, 0.3],
            sparseVector: ['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]],
        );

        expect($request->resolveEndpoint())->toBe('/collections/test_collection/points/query');
    });

    it('builds correct body with prefetch and RRF fusion', function (): void {
        $denseVector = array_fill(0, 1024, 0.1);
        $sparseVector = ['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.2]];

        $request = new HybridSearchPoints(
            collectionName: 'test_collection',
            denseVector: $denseVector,
            sparseVector: $sparseVector,
            limit: 10,
            prefetchLimit: 30,
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        expect($body)->toHaveKey('prefetch');
        expect($body)->toHaveKey('query');
        expect($body)->toHaveKey('limit');
        expect($body)->toHaveKey('with_payload');
        expect($body)->toHaveKey('with_vector');

        // Check prefetch structure
        expect($body['prefetch'])->toBeArray();
        expect($body['prefetch'])->toHaveCount(2);

        // Dense prefetch
        expect($body['prefetch'][0]['query'])->toBe($denseVector);
        expect($body['prefetch'][0]['using'])->toBe('dense');
        expect($body['prefetch'][0]['limit'])->toBe(30);

        // Sparse prefetch
        expect($body['prefetch'][1]['query']['indices'])->toBe([1, 5, 10]);
        expect($body['prefetch'][1]['query']['values'])->toBe([0.5, 0.3, 0.2]);
        expect($body['prefetch'][1]['using'])->toBe('sparse');
        expect($body['prefetch'][1]['limit'])->toBe(30);

        // RRF fusion query
        expect($body['query'])->toBe(['fusion' => 'rrf']);

        // Final limit
        expect($body['limit'])->toBe(10);

        // Payload configuration
        expect($body['with_payload'])->toBeTrue();
        expect($body['with_vector'])->toBeFalse();
    });

    it('includes filter in prefetch when provided', function (): void {
        $filter = [
            'must' => [
                ['key' => 'category', 'match' => ['value' => 'testing']],
            ],
        ];

        $request = new HybridSearchPoints(
            collectionName: 'test_collection',
            denseVector: [0.1, 0.2, 0.3],
            sparseVector: ['indices' => [1], 'values' => [0.5]],
            filter: $filter,
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        // Both prefetches should have the filter
        expect($body['prefetch'][0])->toHaveKey('filter');
        expect($body['prefetch'][0]['filter'])->toBe($filter);
        expect($body['prefetch'][1])->toHaveKey('filter');
        expect($body['prefetch'][1]['filter'])->toBe($filter);
    });

    it('uses default values when not specified', function (): void {
        $request = new HybridSearchPoints(
            collectionName: 'test_collection',
            denseVector: [0.1, 0.2, 0.3],
            sparseVector: ['indices' => [1], 'values' => [0.5]],
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        expect($body['limit'])->toBe(20);
        expect($body['prefetch'][0]['limit'])->toBe(40);
        expect($body['prefetch'][1]['limit'])->toBe(40);
    });

    it('does not include filter when null', function (): void {
        $request = new HybridSearchPoints(
            collectionName: 'test_collection',
            denseVector: [0.1, 0.2, 0.3],
            sparseVector: ['indices' => [1], 'values' => [0.5]],
            filter: null,
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        expect($body['prefetch'][0])->not->toHaveKey('filter');
        expect($body['prefetch'][1])->not->toHaveKey('filter');
    });
});
