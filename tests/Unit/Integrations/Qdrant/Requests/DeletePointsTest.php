<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\DeletePoints;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('DeletePoints', function () {
    describe('resolveEndpoint', function () {
        it('resolves endpoint with collection name', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: ['id-1']
            );

            expect($request->resolveEndpoint())->toBe('/collections/test-collection/points/delete');
        });

        it('handles collection names with special characters', function () {
            $request = new DeletePoints(
                collectionName: 'my-project_collection',
                pointIds: ['id-1']
            );

            expect($request->resolveEndpoint())->toBe('/collections/my-project_collection/points/delete');
        });
    });

    describe('defaultBody', function () {
        it('includes point IDs with string values', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: ['id-1', 'id-2', 'id-3']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('points')
                ->and($body['points'])->toBe(['id-1', 'id-2', 'id-3']);
        });

        it('includes point IDs with integer values', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: [1, 2, 3, 4, 5]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toBe([1, 2, 3, 4, 5]);
        });

        it('handles mixed ID types', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: ['string-id', 123, 'another-id', 456]
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toBe(['string-id', 123, 'another-id', 456]);
        });

        it('handles single point ID', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: ['single-id']
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toHaveCount(1)
                ->and($body['points'][0])->toBe('single-id');
        });

        it('handles empty array', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: []
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toBeEmpty();
        });

        it('handles bulk deletion', function () {
            $ids = array_map(fn ($i) => "id-{$i}", range(1, 100));
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: $ids
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toHaveCount(100);
        });
    });

    describe('method', function () {
        it('uses POST method', function () {
            $request = new DeletePoints(
                collectionName: 'test-collection',
                pointIds: ['id-1']
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
            $request = new DeletePoints(
                collectionName: 'test',
                pointIds: ['id-1', 'id-2']
            );

            expect($request)->toBeInstanceOf(DeletePoints::class);
        });
    });
});
