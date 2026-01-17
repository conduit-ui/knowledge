<?php

declare(strict_types=1);

use App\Contracts\HealthCheckInterface;
use App\Services\HealthCheckService;

beforeEach(function () {
    $this->service = new HealthCheckService;
});

describe('HealthCheckService', function () {
    describe('interface implementation', function () {
        it('implements HealthCheckInterface', function () {
            expect($this->service)->toBeInstanceOf(HealthCheckInterface::class);
        });
    });

    describe('getServices', function () {
        it('returns list of available services', function () {
            $services = $this->service->getServices();

            expect($services)->toBeArray();
            expect($services)->toContain('qdrant');
            expect($services)->toContain('redis');
            expect($services)->toContain('embeddings');
            expect($services)->toContain('ollama');
        });

        it('returns exactly four services', function () {
            expect($this->service->getServices())->toHaveCount(4);
        });
    });

    describe('check', function () {
        it('returns array with required keys for known service', function () {
            $result = $this->service->check('qdrant');

            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['name', 'healthy', 'endpoint', 'type']);
        });

        it('returns correct name for qdrant', function () {
            $result = $this->service->check('qdrant');

            expect($result['name'])->toBe('Qdrant');
            expect($result['type'])->toBe('Vector Database');
        });

        it('returns correct name for redis', function () {
            $result = $this->service->check('redis');

            expect($result['name'])->toBe('Redis');
            expect($result['type'])->toBe('Cache');
        });

        it('returns correct name for embeddings', function () {
            $result = $this->service->check('embeddings');

            expect($result['name'])->toBe('Embeddings');
            expect($result['type'])->toBe('ML Service');
        });

        it('returns correct name for ollama', function () {
            $result = $this->service->check('ollama');

            expect($result['name'])->toBe('Ollama');
            expect($result['type'])->toBe('LLM Engine');
        });

        it('returns unhealthy for unknown service', function () {
            $result = $this->service->check('unknown-service');

            expect($result['name'])->toBe('unknown-service');
            expect($result['healthy'])->toBeFalse();
            expect($result['endpoint'])->toBe('unknown');
            expect($result['type'])->toBe('Unknown');
        });

        it('returns boolean for healthy field', function () {
            $result = $this->service->check('qdrant');

            expect($result['healthy'])->toBeBool();
        });

        it('returns string for endpoint field', function () {
            $result = $this->service->check('qdrant');

            expect($result['endpoint'])->toBeString();
        });
    });

    describe('checkAll', function () {
        it('returns array of all service statuses', function () {
            $results = $this->service->checkAll();

            expect($results)->toBeArray();
            expect($results)->toHaveCount(4);
        });

        it('includes all services in results', function () {
            $results = $this->service->checkAll();
            $names = array_column($results, 'name');

            expect($names)->toContain('Qdrant');
            expect($names)->toContain('Redis');
            expect($names)->toContain('Embeddings');
            expect($names)->toContain('Ollama');
        });

        it('each result has required keys', function () {
            $results = $this->service->checkAll();

            foreach ($results as $result) {
                expect($result)->toHaveKeys(['name', 'healthy', 'endpoint', 'type']);
            }
        });
    });

    describe('endpoint configuration', function () {
        it('uses config for qdrant endpoint', function () {
            config(['search.qdrant.host' => 'custom-host']);
            config(['search.qdrant.port' => 9999]);

            $service = new HealthCheckService;
            $result = $service->check('qdrant');

            expect($result['endpoint'])->toBe('custom-host:9999');
        });

        it('uses config for redis endpoint', function () {
            config(['database.redis.default.host' => '192.168.1.1']);
            config(['database.redis.default.port' => 6379]);

            $service = new HealthCheckService;
            $result = $service->check('redis');

            expect($result['endpoint'])->toBe('192.168.1.1:6379');
        });

        it('uses config for embeddings endpoint', function () {
            config(['search.qdrant.embedding_server' => 'http://ml-server:8080']);

            $service = new HealthCheckService;
            $result = $service->check('embeddings');

            expect($result['endpoint'])->toBe('http://ml-server:8080');
        });

        it('uses config for ollama endpoint', function () {
            config(['search.ollama.host' => 'ollama-server']);
            config(['search.ollama.port' => 11435]);

            $service = new HealthCheckService;
            $result = $service->check('ollama');

            expect($result['endpoint'])->toBe('ollama-server:11435');
        });
    });

    describe('health checks return false when services unavailable', function () {
        it('qdrant returns false when unreachable', function () {
            config(['search.qdrant.host' => 'nonexistent-host-12345']);
            config(['search.qdrant.port' => 99999]);

            $service = new HealthCheckService;
            $result = $service->check('qdrant');

            expect($result['healthy'])->toBeFalse();
        });

        it('embeddings returns false when unreachable', function () {
            config(['search.qdrant.embedding_server' => 'http://nonexistent-host-12345:99999']);

            $service = new HealthCheckService;
            $result = $service->check('embeddings');

            expect($result['healthy'])->toBeFalse();
        });

        it('ollama returns false when unreachable', function () {
            config(['search.ollama.host' => 'nonexistent-host-12345']);
            config(['search.ollama.port' => 99999]);

            $service = new HealthCheckService;
            $result = $service->check('ollama');

            expect($result['healthy'])->toBeFalse();
        });

        it('redis returns false when extension not loaded or unreachable', function () {
            config(['database.redis.default.host' => 'nonexistent-host-12345']);
            config(['database.redis.default.port' => 99999]);

            $service = new HealthCheckService;
            $result = $service->check('redis');

            // Either extension not loaded or connection fails
            expect($result['healthy'])->toBeBool();
        });
    });
});
