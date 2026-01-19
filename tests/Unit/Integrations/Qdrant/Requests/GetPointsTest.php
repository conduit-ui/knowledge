<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\GetPoints;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('GetPoints', function () {
    describe('resolveEndpoint', function () {
        it('resolves endpoint with collection name', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1']
            );

            expect($request->resolveEndpoint())->toBe('/collections/test-collection/points');
        });

        it('handles collection names with special characters', function () {
            $request = new GetPoints(
                collectionName: 'my-project_collection',
                ids: ['id-1']
            );

            expect($request->resolveEndpoint())->toBe('/collections/my-project_collection/points');
        });

        it('handles simple collection names', function () {
            $request = new GetPoints(
                collectionName: 'simple',
                ids: [1, 2, 3]
            );

            expect($request->resolveEndpoint())->toBe('/collections/simple/points');
        });
    });

    describe('defaultBody', function () {
        it('includes IDs with string values', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1', 'id-2', 'id-3']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('ids')
                ->and($body['ids'])->toBe(['id-1', 'id-2', 'id-3']);
        });

        it('includes IDs with integer values', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: [1, 2, 3, 4, 5]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['ids'])->toBe([1, 2, 3, 4, 5]);
        });

        it('handles mixed ID types', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['string-id', 123, 'another-id', 456]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['ids'])->toBe(['string-id', 123, 'another-id', 456]);
        });

        it('sets with_payload to true', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('with_payload')
                ->and($body['with_payload'])->toBeTrue();
        });

        it('sets with_vector to false', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('with_vector')
                ->and($body['with_vector'])->toBeFalse();
        });

        it('includes all required body fields', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1', 'id-2']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKeys(['ids', 'with_payload', 'with_vector']);
        });

        it('handles single ID', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['single-id']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['ids'])->toHaveCount(1)
                ->and($body['ids'][0])->toBe('single-id');
        });

        it('handles empty array', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: []
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['ids'])->toBeEmpty();
        });

        it('handles bulk retrieval', function () {
            $ids = array_map(fn ($i) => "id-{$i}", range(1, 50));
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: $ids
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['ids'])->toHaveCount(50);
        });
    });

    describe('method', function () {
        it('uses POST method', function () {
            $request = new GetPoints(
                collectionName: 'test-collection',
                ids: ['id-1']
            );

            $reflection = new ReflectionClass($request);
            $property = $reflection->getProperty('method');
            $property->setAccessible(true);
            $method = $property->getValue($request);

            expect($method)->toBe(Method::POST);
        });
    });

    describe('constructor', function () {
        it('accepts required parameters', function () {
            $request = new GetPoints(
                collectionName: 'test',
                ids: ['id-1', 'id-2']
            );

            expect($request)->toBeInstanceOf(GetPoints::class);
        });

        it('implements HasBody interface', function () {
            $request = new GetPoints(
                collectionName: 'test',
                ids: ['id-1']
            );

            expect($request)->toBeInstanceOf(\Saloon\Contracts\Body\HasBody::class);
        });
    });
});
