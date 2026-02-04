<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\UpsertPoints;
use Saloon\Enums\Method;

uses()->group('qdrant-unit', 'requests');

describe('UpsertPoints', function (): void {
    describe('resolveEndpoint', function (): void {
        it('resolves endpoint with collection name', function (): void {
            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: []
            );

            expect($request->resolveEndpoint())->toBe('/collections/test-collection/points');
        });

        it('handles collection names with special characters', function (): void {
            $request = new UpsertPoints(
                collectionName: 'my-project_collection',
                points: []
            );

            expect($request->resolveEndpoint())->toBe('/collections/my-project_collection/points');
        });

        it('handles simple collection names', function (): void {
            $request = new UpsertPoints(
                collectionName: 'simple',
                points: []
            );

            expect($request->resolveEndpoint())->toBe('/collections/simple/points');
        });
    });

    describe('defaultBody', function (): void {
        it('includes single point with string ID', function (): void {
            $points = [
                [
                    'id' => 'test-id-1',
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => ['title' => 'Test Entry', 'content' => 'Test content'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body)->toHaveKey('points')
                ->and($body['points'])->toBe($points);
        });

        it('includes single point with integer ID', function (): void {
            $points = [
                [
                    'id' => 123,
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => ['title' => 'Test Entry'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toBe($points);
        });

        it('includes multiple points', function (): void {
            $points = [
                [
                    'id' => 'id-1',
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => ['title' => 'First Entry'],
                ],
                [
                    'id' => 'id-2',
                    'vector' => [0.4, 0.5, 0.6],
                    'payload' => ['title' => 'Second Entry'],
                ],
                [
                    'id' => 'id-3',
                    'vector' => [0.7, 0.8, 0.9],
                    'payload' => ['title' => 'Third Entry'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toHaveCount(3)
                ->and($body['points'])->toBe($points);
        });

        it('handles complex payload structures', function (): void {
            $points = [
                [
                    'id' => 'complex-1',
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => [
                        'title' => 'Complex Entry',
                        'content' => 'Complex content here',
                        'tags' => ['tag1', 'tag2', 'tag3'],
                        'category' => 'testing',
                        'module' => 'TestModule',
                        'priority' => 'high',
                        'status' => 'validated',
                        'confidence' => 90,
                        'usage_count' => 5,
                        'metadata' => [
                            'created_at' => '2025-01-01T00:00:00Z',
                            'updated_at' => '2025-01-01T00:00:00Z',
                        ],
                    ],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'][0]['payload'])->toMatchArray([
                'title' => 'Complex Entry',
                'tags' => ['tag1', 'tag2', 'tag3'],
                'confidence' => 90,
            ]);
        });

        it('handles minimal payload', function (): void {
            $points = [
                [
                    'id' => 'minimal-1',
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => ['title' => 'Minimal'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'][0]['payload'])->toHaveKey('title')
                ->and($body['points'][0]['payload'])->toHaveCount(1);
        });

        it('handles empty points array', function (): void {
            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: []
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toBeEmpty();
        });

        it('handles large vector dimensions', function (): void {
            $vector = array_fill(0, 1536, 0.1);
            $points = [
                [
                    'id' => 'large-vector',
                    'vector' => $vector,
                    'payload' => ['title' => 'Large Vector Entry'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'][0]['vector'])->toHaveCount(1536);
        });

        it('handles bulk upsert', function (): void {
            $points = array_map(fn ($i): array => [
                'id' => "bulk-id-{$i}",
                'vector' => [0.1 * $i, 0.2 * $i, 0.3 * $i],
                'payload' => ['title' => "Entry {$i}"],
            ], range(1, 100));

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'])->toHaveCount(100);
        });

        it('handles mixed ID types', function (): void {
            $points = [
                [
                    'id' => 'string-id',
                    'vector' => [0.1, 0.2, 0.3],
                    'payload' => ['title' => 'String ID Entry'],
                ],
                [
                    'id' => 42,
                    'vector' => [0.4, 0.5, 0.6],
                    'payload' => ['title' => 'Integer ID Entry'],
                ],
            ];

            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: $points
            );

            $reflection = new ReflectionClass($request);
            $method = $reflection->getMethod('defaultBody');
            $method->setAccessible(true);
            $body = $method->invoke($request);

            expect($body['points'][0]['id'])->toBe('string-id')
                ->and($body['points'][1]['id'])->toBe(42);
        });
    });

    describe('method', function (): void {
        it('uses PUT method', function (): void {
            $request = new UpsertPoints(
                collectionName: 'test-collection',
                points: []
            );

            $reflection = new ReflectionClass($request);
            $property = $reflection->getProperty('method');
            $property->setAccessible(true);
            $method = $property->getValue($request);

            expect($method)->toBe(Method::PUT);
        });
    });

    describe('constructor', function (): void {
        it('accepts required parameters', function (): void {
            $request = new UpsertPoints(
                collectionName: 'test',
                points: [
                    [
                        'id' => 'test-1',
                        'vector' => [0.1, 0.2, 0.3],
                        'payload' => ['title' => 'Test'],
                    ],
                ]
            );

            expect($request)->toBeInstanceOf(UpsertPoints::class);
        });

        it('implements HasBody interface', function (): void {
            $request = new UpsertPoints(
                collectionName: 'test',
                points: []
            );

            expect($request)->toBeInstanceOf(\Saloon\Contracts\Body\HasBody::class);
        });
    });
});
