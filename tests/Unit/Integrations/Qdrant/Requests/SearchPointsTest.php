<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\SearchPoints;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('SearchPoints', function (): void {
    describe('resolveEndpoint', function (): void {
        it('resolves endpoint with collection name', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            expect($request->resolveEndpoint())->toBe('/collections/test-collection/points/search');
        });

        it('handles collection names with special characters', function (): void {
            $request = new SearchPoints(
                collectionName: 'my-project_collection',
                vector: [0.1]
            );

            expect($request->resolveEndpoint())->toBe('/collections/my-project_collection/points/search');
        });
    });

    describe('defaultBody', function (): void {
        it('includes vector in body', function (): void {
            $vector = [0.1, 0.2, 0.3, 0.4, 0.5];
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: $vector
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('vector')
                ->and($body['vector'])->toBe($vector);
        });

        it('includes default limit when not provided', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('limit')
                ->and($body['limit'])->toBe(20);
        });

        it('includes custom limit when provided', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                limit: 50
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['limit'])->toBe(50);
        });

        it('includes default score threshold when not provided', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('score_threshold')
                ->and($body['score_threshold'])->toBe(0.7);
        });

        it('includes custom score threshold when provided', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                scoreThreshold: 0.85
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['score_threshold'])->toBe(0.85);
        });

        it('sets with_payload to true', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('with_payload')
                ->and($body['with_payload'])->toBeTrue();
        });

        it('sets with_vector to false', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('with_vector')
                ->and($body['with_vector'])->toBeFalse();
        });

        it('excludes filter when null', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                filter: null
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->not->toHaveKey('filter');
        });

        it('includes filter when provided', function (): void {
            $filter = [
                'must' => [
                    ['key' => 'category', 'match' => ['value' => 'testing']],
                ],
            ];

            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                filter: $filter
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('filter')
                ->and($body['filter'])->toBe($filter);
        });

        it('includes all body fields when filter is provided', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                limit: 10,
                scoreThreshold: 0.9,
                filter: ['key' => 'value']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKeys([
                'vector',
                'limit',
                'score_threshold',
                'with_payload',
                'with_vector',
                'filter',
            ]);
        });

        it('includes all required body fields without filter', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKeys([
                'vector',
                'limit',
                'score_threshold',
                'with_payload',
                'with_vector',
            ]);
        });

        it('handles complex filter structures', function (): void {
            $filter = [
                'must' => [
                    ['key' => 'category', 'match' => ['value' => 'testing']],
                    ['key' => 'priority', 'match' => ['value' => 'high']],
                ],
                'should' => [
                    ['key' => 'status', 'match' => ['value' => 'validated']],
                ],
            ];

            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3],
                filter: $filter
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['filter'])->toBe($filter);
        });

        it('handles large vector dimensions', function (): void {
            $vector = array_fill(0, 1536, 0.1); // GPT-3 embedding size
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: $vector
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['vector'])->toHaveCount(1536);
        });
    });

    describe('method', function (): void {
        it('uses POST method', function (): void {
            $request = new SearchPoints(
                collectionName: 'test-collection',
                vector: [0.1, 0.2, 0.3]
            );

            $reflection = new ReflectionClass($request);
            $property = $reflection->getProperty('method');
            $property->setAccessible(true);
            $method = $property->getValue($request);

            expect($method)->toBe(Method::POST);
        });
    });

    describe('constructor', function (): void {
        it('accepts minimal parameters', function (): void {
            $request = new SearchPoints(
                collectionName: 'test',
                vector: [0.1, 0.2, 0.3]
            );

            expect($request)->toBeInstanceOf(SearchPoints::class);
        });

        it('accepts all parameters', function (): void {
            $request = new SearchPoints(
                collectionName: 'test',
                vector: [0.1, 0.2, 0.3],
                limit: 30,
                scoreThreshold: 0.8,
                filter: ['key' => 'value']
            );

            expect($request)->toBeInstanceOf(SearchPoints::class);
        });

        it('implements HasBody interface', function (): void {
            $request = new SearchPoints(
                collectionName: 'test',
                vector: [0.1, 0.2, 0.3]
            );

            expect($request)->toBeInstanceOf(\Saloon\Contracts\Body\HasBody::class);
        });
    });
});
