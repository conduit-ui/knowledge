<?php

declare(strict_types=1);

use App\Services\HealthCheckService;

describe('HealthCheckService', function () {
    it('returns all service keys', function () {
        $service = new HealthCheckService;

        $services = $service->getServices();

        expect($services)->toBe(['qdrant', 'redis', 'embeddings', 'ollama']);
    });

    it('returns unhealthy status for unknown service', function () {
        $service = new HealthCheckService;

        $result = $service->check('nonexistent');

        expect($result)->toBe([
            'name' => 'nonexistent',
            'healthy' => false,
            'endpoint' => 'unknown',
            'type' => 'Unknown',
        ]);
    });

    it('checks all services and returns array', function () {
        $service = new HealthCheckService;

        $results = $service->checkAll();

        expect($results)->toBeArray();
        expect($results)->toHaveCount(4);

        foreach ($results as $result) {
            expect($result)->toHaveKeys(['name', 'healthy', 'endpoint', 'type']);
            expect($result['healthy'])->toBeBool();
            expect($result['name'])->toBeString();
            expect($result['endpoint'])->toBeString();
            expect($result['type'])->toBeString();
        }
    });

    it('returns correct structure for qdrant check', function () {
        $service = new HealthCheckService;

        $result = $service->check('qdrant');

        expect($result['name'])->toBe('Qdrant');
        expect($result['type'])->toBe('Vector Database');
        expect($result['healthy'])->toBeBool();
        expect($result['endpoint'])->toBeString();
    });

    it('returns correct structure for redis check', function () {
        $service = new HealthCheckService;

        $result = $service->check('redis');

        expect($result['name'])->toBe('Redis');
        expect($result['type'])->toBe('Cache');
        expect($result['healthy'])->toBeBool();
        expect($result['endpoint'])->toBeString();
    });

    it('returns correct structure for embeddings check', function () {
        $service = new HealthCheckService;

        $result = $service->check('embeddings');

        expect($result['name'])->toBe('Embeddings');
        expect($result['type'])->toBe('ML Service');
        expect($result['healthy'])->toBeBool();
        expect($result['endpoint'])->toBeString();
    });

    it('returns correct structure for ollama check', function () {
        $service = new HealthCheckService;

        $result = $service->check('ollama');

        expect($result['name'])->toBe('Ollama');
        expect($result['type'])->toBe('LLM Engine');
        expect($result['healthy'])->toBeBool();
        expect($result['endpoint'])->toBeString();
    });

    it('uses config values for qdrant endpoint', function () {
        config(['search.qdrant.host' => 'test-host']);
        config(['search.qdrant.port' => 9999]);

        $service = new HealthCheckService;
        $result = $service->check('qdrant');

        expect($result['endpoint'])->toBe('test-host:9999');
    });

    it('uses config values for redis endpoint', function () {
        config(['database.redis.default.host' => 'redis-host']);
        config(['database.redis.default.port' => 6380]);

        $service = new HealthCheckService;
        $result = $service->check('redis');

        expect($result['endpoint'])->toBe('redis-host:6380');
    });

    it('uses config values for embeddings endpoint', function () {
        config(['search.qdrant.embedding_server' => 'http://embed-host:8001']);

        $service = new HealthCheckService;
        $result = $service->check('embeddings');

        expect($result['endpoint'])->toBe('http://embed-host:8001');
    });

    it('uses config values for ollama endpoint', function () {
        config(['search.ollama.host' => 'ollama-host']);
        config(['search.ollama.port' => 11434]);

        $service = new HealthCheckService;
        $result = $service->check('ollama');

        expect($result['endpoint'])->toBe('ollama-host:11434');
    });

    it('checkAll returns same count as getServices', function () {
        $service = new HealthCheckService;

        $services = $service->getServices();
        $results = $service->checkAll();

        expect(count($results))->toBe(count($services));
    });
});
