<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('GetCollectionInfo', function () {
    describe('resolveEndpoint', function () {
        it('resolves endpoint with collection name', function () {
            $request = new GetCollectionInfo(collectionName: 'test-collection');

            expect($request->resolveEndpoint())->toBe('/collections/test-collection');
        });

        it('handles collection names with hyphens', function () {
            $request = new GetCollectionInfo(collectionName: 'my-project-collection');

            expect($request->resolveEndpoint())->toBe('/collections/my-project-collection');
        });

        it('handles collection names with underscores', function () {
            $request = new GetCollectionInfo(collectionName: 'my_project_collection');

            expect($request->resolveEndpoint())->toBe('/collections/my_project_collection');
        });

        it('handles simple collection names', function () {
            $request = new GetCollectionInfo(collectionName: 'simple');

            expect($request->resolveEndpoint())->toBe('/collections/simple');
        });

        it('handles collection names with numbers', function () {
            $request = new GetCollectionInfo(collectionName: 'collection-123');

            expect($request->resolveEndpoint())->toBe('/collections/collection-123');
        });
    });

    describe('method', function () {
        it('uses GET method', function () {
            $request = new GetCollectionInfo(collectionName: 'test-collection');

            $reflection = new ReflectionClass($request);
            $property = $reflection->getProperty('method');
            $property->setAccessible(true);
            $method = $property->getValue($request);

            expect($method)->toBe(Method::GET);
        });
    });

    describe('constructor', function () {
        it('accepts collection name parameter', function () {
            $request = new GetCollectionInfo(collectionName: 'test');

            expect($request)->toBeInstanceOf(GetCollectionInfo::class);
        });

        it('creates instance with various collection name formats', function () {
            $names = [
                'simple',
                'with-hyphens',
                'with_underscores',
                'with-both_formats',
                'collection123',
            ];

            foreach ($names as $name) {
                $request = new GetCollectionInfo(collectionName: $name);
                expect($request)->toBeInstanceOf(GetCollectionInfo::class);
            }
        });
    });

    describe('request properties', function () {
        it('does not implement HasBody interface', function () {
            $request = new GetCollectionInfo(collectionName: 'test');

            expect($request)->not->toBeInstanceOf(\Saloon\Contracts\Body\HasBody::class);
        });

        it('is a valid Saloon request', function () {
            $request = new GetCollectionInfo(collectionName: 'test');

            expect($request)->toBeInstanceOf(\Saloon\Http\Request::class);
        });
    });
});
