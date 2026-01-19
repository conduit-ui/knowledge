<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\CreateCollection;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('CreateCollection', function () {
    describe('resolveEndpoint', function () {
        it('resolves endpoint with collection name', function () {
            $request = new CreateCollection(collectionName: 'test-collection');

            expect($request->resolveEndpoint())->toBe('/collections/test-collection');
        });

        it('handles collection names with hyphens', function () {
            $request = new CreateCollection(collectionName: 'my-project-collection');

            expect($request->resolveEndpoint())->toBe('/collections/my-project-collection');
        });

        it('handles collection names with underscores', function () {
            $request = new CreateCollection(collectionName: 'my_project_collection');

            expect($request->resolveEndpoint())->toBe('/collections/my_project_collection');
        });
    });

    describe('defaultBody', function () {
        it('includes default vector size and distance', function () {
            $request = new CreateCollection(collectionName: 'test-collection');

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('vectors')
                ->and($body['vectors'])->toMatchArray([
                    'size' => 384,
                    'distance' => 'Cosine',
                ]);
        });

        it('uses custom vector size when provided', function () {
            $request = new CreateCollection(
                collectionName: 'test-collection',
                vectorSize: 768
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['vectors']['size'])->toBe(768);
        });

        it('uses custom distance metric when provided', function () {
            $request = new CreateCollection(
                collectionName: 'test-collection',
                distance: 'Euclid'
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['vectors']['distance'])->toBe('Euclid');
        });

        it('includes optimizers config', function () {
            $request = new CreateCollection(collectionName: 'test-collection');

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('optimizers_config')
                ->and($body['optimizers_config'])->toMatchArray([
                    'indexing_threshold' => 20000,
                ]);
        });

        it('includes all required body fields', function () {
            $request = new CreateCollection(collectionName: 'test-collection');

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKeys(['vectors', 'optimizers_config']);
        });

        it('uses all custom parameters', function () {
            $request = new CreateCollection(
                collectionName: 'custom-collection',
                vectorSize: 1536,
                distance: 'Dot'
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['vectors'])->toMatchArray([
                'size' => 1536,
                'distance' => 'Dot',
            ]);
        });
    });

    describe('method', function () {
        it('uses PUT method', function () {
            $request = new CreateCollection(collectionName: 'test-collection');

            $reflection = new ReflectionClass($request);
            $property = $reflection->getProperty('method');
            $property->setAccessible(true);
            $method = $property->getValue($request);

            expect($method)->toBe(Method::PUT);
        });
    });

    describe('constructor', function () {
        it('accepts minimal parameters', function () {
            $request = new CreateCollection(collectionName: 'minimal');

            expect($request)->toBeInstanceOf(CreateCollection::class);
        });

        it('accepts all parameters', function () {
            $request = new CreateCollection(
                collectionName: 'full-config',
                vectorSize: 2048,
                distance: 'Manhattan'
            );

            expect($request)->toBeInstanceOf(CreateCollection::class);
        });
    });
});
