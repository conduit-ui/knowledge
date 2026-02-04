<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\CreateCollection;

uses()->group('qdrant-requests');

describe('CreateCollection with hybrid mode', function (): void {
    it('creates dense-only collection when hybrid disabled', function (): void {
        $request = new CreateCollection(
            collectionName: 'test_collection',
            vectorSize: 1024,
            distance: 'Cosine',
            hybridEnabled: false,
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        expect($body['vectors'])->toHaveKey('size');
        expect($body['vectors'])->toHaveKey('distance');
        expect($body['vectors']['size'])->toBe(1024);
        expect($body['vectors']['distance'])->toBe('Cosine');
        expect($body)->not->toHaveKey('sparse_vectors');
    });

    it('creates hybrid collection with named vectors when hybrid enabled', function (): void {
        $request = new CreateCollection(
            collectionName: 'test_collection',
            vectorSize: 1024,
            distance: 'Cosine',
            hybridEnabled: true,
        );

        $reflection = new ReflectionClass($request);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);
        $body = $method->invoke($request);

        // Check named dense vector config
        expect($body['vectors'])->toHaveKey('dense');
        expect($body['vectors']['dense']['size'])->toBe(1024);
        expect($body['vectors']['dense']['distance'])->toBe('Cosine');

        // Check sparse vector config
        expect($body)->toHaveKey('sparse_vectors');
        expect($body['sparse_vectors'])->toHaveKey('sparse');
        expect($body['sparse_vectors']['sparse']['modifier'])->toBe('idf');
    });

    it('includes optimizers config in both modes', function (): void {
        $denseRequest = new CreateCollection(
            collectionName: 'test1',
            hybridEnabled: false,
        );
        $hybridRequest = new CreateCollection(
            collectionName: 'test2',
            hybridEnabled: true,
        );

        $reflection = new ReflectionClass($denseRequest);
        $method = $reflection->getMethod('defaultBody');
        $method->setAccessible(true);

        $denseBody = $method->invoke($denseRequest);
        $hybridBody = $method->invoke($hybridRequest);

        expect($denseBody['optimizers_config']['indexing_threshold'])->toBe(20000);
        expect($hybridBody['optimizers_config']['indexing_threshold'])->toBe(20000);
    });

    it('uses correct endpoint', function (): void {
        $request = new CreateCollection(
            collectionName: 'my_collection',
            hybridEnabled: true,
        );

        expect($request->resolveEndpoint())->toBe('/collections/my_collection');
    });
});
