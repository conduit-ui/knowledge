<?php

declare(strict_types=1);

use App\Integrations\Qdrant\QdrantConnector;

uses()->group('qdrant-unit', 'connector');

describe('QdrantConnector', function () {
    describe('resolveBaseUrl', function () {
        it('resolves HTTP base URL when secure is false', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                secure: false
            );

            expect($connector->resolveBaseUrl())->toBe('http://localhost:6333');
        });

        it('resolves HTTPS base URL when secure is true', function () {
            $connector = new QdrantConnector(
                host: 'qdrant.example.com',
                port: 6333,
                secure: true
            );

            expect($connector->resolveBaseUrl())->toBe('https://qdrant.example.com:6333');
        });

        it('handles custom port numbers', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 8080,
                secure: false
            );

            expect($connector->resolveBaseUrl())->toBe('http://localhost:8080');
        });

        it('handles secure connections with custom ports', function () {
            $connector = new QdrantConnector(
                host: 'secure.qdrant.io',
                port: 443,
                secure: true
            );

            expect($connector->resolveBaseUrl())->toBe('https://secure.qdrant.io:443');
        });
    });

    describe('defaultHeaders', function () {
        it('includes Content-Type header', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $reflection = new ReflectionClass($connector);
            $method = $reflection->getMethod('defaultHeaders');
            $method->setAccessible(true);
            $headers = $method->invoke($connector);

            expect($headers)->toHaveKey('Content-Type')
                ->and($headers['Content-Type'])->toBe('application/json');
        });

        it('includes API key header when provided', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: 'test-api-key-123'
            );

            $reflection = new ReflectionClass($connector);
            $method = $reflection->getMethod('defaultHeaders');
            $method->setAccessible(true);
            $headers = $method->invoke($connector);

            expect($headers)->toHaveKey('api-key')
                ->and($headers['api-key'])->toBe('test-api-key-123');
        });

        it('omits API key header when not provided', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: null
            );

            $reflection = new ReflectionClass($connector);
            $method = $reflection->getMethod('defaultHeaders');
            $method->setAccessible(true);
            $headers = $method->invoke($connector);

            expect($headers)->not->toHaveKey('api-key')
                ->and($headers)->toHaveKey('Content-Type');
        });

        it('includes both headers when API key is provided', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333,
                apiKey: 'secret-key'
            );

            $reflection = new ReflectionClass($connector);
            $method = $reflection->getMethod('defaultHeaders');
            $method->setAccessible(true);
            $headers = $method->invoke($connector);

            expect($headers)->toHaveKeys(['Content-Type', 'api-key'])
                ->and($headers)->toHaveCount(2);
        });
    });

    describe('defaultConfig', function () {
        it('sets timeout to 30 seconds', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            $config = $connector->defaultConfig();

            expect($config)->toHaveKey('timeout')
                ->and($config['timeout'])->toBe(30);
        });
    });

    describe('constructor', function () {
        it('accepts all parameters', function () {
            $connector = new QdrantConnector(
                host: 'test.host',
                port: 9999,
                apiKey: 'key123',
                secure: true
            );

            expect($connector)->toBeInstanceOf(QdrantConnector::class);
        });

        it('accepts minimal parameters', function () {
            $connector = new QdrantConnector(
                host: 'localhost',
                port: 6333
            );

            expect($connector)->toBeInstanceOf(QdrantConnector::class);
        });
    });
});
