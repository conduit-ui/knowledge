<?php

declare(strict_types=1);

use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses()->group('qdrant-unit', 'connector');

describe('QdrantConnector', function (): void {
    describe('resolveBaseUrl', function (): void {
        it('resolves HTTP base URL when secure is false', function (): void {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                secure: false
            );

            expect($connector->resolveBaseUrl())->toBe('http://localhost:6333');
        });

        it('resolves HTTPS base URL when secure is true', function (): void {
            $connector = new QdrantConnector(
                host: 'qdrant.example.com',
                port: 6333,
                secure: true
            );

            expect($connector->resolveBaseUrl())->toBe('https://qdrant.example.com:6333');
        });

        it('handles custom port numbers', function (): void {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 8080,
                secure: false
            );

            expect($connector->resolveBaseUrl())->toBe('http://localhost:8080');
        });

        it('handles secure connections with custom ports', function (): void {
            $connector = new QdrantConnector(
                host: 'secure.qdrant.io',
                port: 443,
                secure: true
            );

            expect($connector->resolveBaseUrl())->toBe('https://secure.qdrant.io:443');
        });
    });

    describe('headers', function (): void {
        it('includes Content-Type header in requests', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make(['result' => ['status' => 'green']], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $connector->send(new GetCollectionInfo('test-collection'), $mockClient);

            $mockClient->assertSent(function ($request, $response): bool {
                $headers = $response->getPendingRequest()->headers();

                return $headers->get('Content-Type') === 'application/json';
            });
        });

        it('includes API key header when provided', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make(['result' => ['status' => 'green']], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: 'test-api-key-123'
            );

            $connector->send(new GetCollectionInfo('test-collection'), $mockClient);

            $mockClient->assertSent(function ($request, $response): bool {
                $headers = $response->getPendingRequest()->headers();

                return $headers->get('api-key') === 'test-api-key-123';
            });
        });

        it('omits API key header when not provided', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make(['result' => ['status' => 'green']], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: null
            );

            $connector->send(new GetCollectionInfo('test-collection'), $mockClient);

            $mockClient->assertSent(function ($request, $response): bool {
                $headers = $response->getPendingRequest()->headers();

                return $headers->get('api-key') === null;
            });
        });
    });

    describe('defaultConfig', function (): void {
        it('sets timeout to 30 seconds', function (): void {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $config = $connector->defaultConfig();

            expect($config)->toHaveKey('timeout')
                ->and($config['timeout'])->toBe(30);
        });
    });

    describe('constructor', function (): void {
        it('accepts all parameters', function (): void {
            $connector = new QdrantConnector(
                host: 'test.host',
                port: 9999,
                apiKey: 'key123',
                secure: true
            );

            expect($connector)->toBeInstanceOf(QdrantConnector::class);
        });

        it('accepts minimal parameters', function (): void {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            expect($connector)->toBeInstanceOf(QdrantConnector::class);
        });
    });

    describe('request sending with mocks', function (): void {
        it('sends requests to the correct base URL', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make(['result' => ['status' => 'green']], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'qdrant.local',
                port: 6334,
                secure: false
            );

            $connector->send(new GetCollectionInfo('my-collection'), $mockClient);

            $mockClient->assertSent(function ($request, $response): bool {
                $url = $response->getPendingRequest()->getUrl();

                return str_starts_with($url, 'http://qdrant.local:6334/');
            });
        });

        it('handles successful responses', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make([
                    'result' => [
                        'status' => 'green',
                        'points_count' => 100,
                    ],
                ], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $response = $connector->send(new GetCollectionInfo('test-collection'), $mockClient);

            expect($response->successful())->toBeTrue()
                ->and($response->json('result.status'))->toBe('green')
                ->and($response->json('result.points_count'))->toBe(100);
        });

        it('handles error responses', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make([
                    'status' => ['error' => 'Collection not found'],
                ], 404),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $response = $connector->send(new GetCollectionInfo('nonexistent'), $mockClient);

            expect($response->successful())->toBeFalse()
                ->and($response->status())->toBe(404);
        });

        it('includes all configured headers in requests', function (): void {
            $mockClient = new MockClient([
                GetCollectionInfo::class => MockResponse::make(['result' => []], 200),
            ]);

            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: 'secret-key'
            );

            $connector->send(new GetCollectionInfo('test'), $mockClient);

            $mockClient->assertSent(function ($request, $response): bool {
                $headers = $response->getPendingRequest()->headers();

                return $headers->get('Content-Type') === 'application/json'
                    && $headers->get('api-key') === 'secret-key';
            });
        });
    });
});
