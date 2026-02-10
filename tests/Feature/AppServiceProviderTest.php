<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\HealthCheckInterface;
use App\Services\EmbeddingService;
use App\Services\HealthCheckService;
use App\Services\KnowledgePathService;
use App\Services\QdrantService;
use App\Services\RuntimeEnvironment;
use App\Services\StubEmbeddingService;

describe('AppServiceProvider', function (): void {
    it('registers RuntimeEnvironment', function (): void {
        $service = app(RuntimeEnvironment::class);

        expect($service)->toBeInstanceOf(RuntimeEnvironment::class);
    });

    it('registers KnowledgePathService', function (): void {
        $service = app(KnowledgePathService::class);

        expect($service)->toBeInstanceOf(KnowledgePathService::class);
    });

    it('registers StubEmbeddingService by default', function (): void {
        config(['search.embedding_provider' => 'none']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(StubEmbeddingService::class);
    });

    it('registers EmbeddingService when provider is chromadb', function (): void {
        config(['search.embedding_provider' => 'chromadb']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });

    it('registers EmbeddingService when provider is qdrant', function (): void {
        config(['search.embedding_provider' => 'qdrant']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });

    it('registers QdrantService with mocked embedding service', function (): void {
        $mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
        app()->instance(EmbeddingServiceInterface::class, $mockEmbedding);

        config([
            'search.embedding_dimension' => 384,
            'search.minimum_similarity' => 0.7,
            'search.qdrant.cache_ttl' => 604800,
            'search.qdrant.secure' => false,
        ]);

        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('registers QdrantService with secure connection configuration', function (): void {
        $mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
        app()->instance(EmbeddingServiceInterface::class, $mockEmbedding);

        config([
            'search.embedding_dimension' => 1536,
            'search.minimum_similarity' => 0.8,
            'search.qdrant.cache_ttl' => 86400,
            'search.qdrant.secure' => true,
        ]);

        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('registers HealthCheckService', function (): void {
        $service = app(HealthCheckInterface::class);

        expect($service)->toBeInstanceOf(HealthCheckService::class);
    });

    it('uses custom embedding server configuration for qdrant provider', function (): void {
        config([
            'search.embedding_provider' => 'qdrant',
            'search.qdrant.embedding_server' => 'http://custom-server:8001',
            'search.qdrant.model' => 'custom-model',
        ]);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });
});

describe('AppServiceProvider user config loading', function (): void {
    beforeEach(function (): void {
        // Create a temporary directory for testing user config
        $this->testConfigDir = sys_get_temp_dir().'/knowledge-provider-test-'.uniqid();
        mkdir($this->testConfigDir, 0755, true);
    });

    afterEach(function (): void {
        // Clean up test directory
        if (property_exists($this, 'testConfigDir') && $this->testConfigDir !== null && is_dir($this->testConfigDir)) {
            removeDirectory($this->testConfigDir);
        }
    });

    it('loads qdrant url and parses host and port', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://custom-host:7333',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        // Mock path service to return our test directory
        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        // Call boot to load user config
        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.host'))->toBe('custom-host');
        expect(config('search.qdrant.port'))->toBe(7333);
        expect(config('search.qdrant.secure'))->toBeFalse();
    });

    it('loads qdrant url with https and sets secure to true', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'https://secure-host:443',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.host'))->toBe('secure-host');
        expect(config('search.qdrant.port'))->toBe(443);
        expect(config('search.qdrant.secure'))->toBeTrue();
    });

    it('loads qdrant collection', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'collection' => 'my-custom-collection',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.collection'))->toBe('my-custom-collection');
    });

    it('loads embeddings url into embedding_server config', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'embeddings' => [
                'url' => 'http://custom-embeddings:9001',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.embedding_server'))->toBe('http://custom-embeddings:9001');
    });

    it('loads all config values together', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://qdrant-server:6334',
                'collection' => 'test-knowledge',
            ],
            'embeddings' => [
                'url' => 'http://embedding-server:8002',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.host'))->toBe('qdrant-server');
        expect(config('search.qdrant.port'))->toBe(6334);
        expect(config('search.qdrant.collection'))->toBe('test-knowledge');
        expect(config('search.qdrant.embedding_server'))->toBe('http://embedding-server:8002');
    });

    it('does not modify config when config file does not exist', function (): void {
        // Set some default values
        config([
            'search.qdrant.host' => 'default-host',
            'search.qdrant.port' => 6333,
        ]);

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        // Config should remain unchanged
        expect(config('search.qdrant.host'))->toBe('default-host');
        expect(config('search.qdrant.port'))->toBe(6333);
    });

    it('handles invalid JSON in config file gracefully', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        file_put_contents($configPath, 'not valid json {{{');

        // Set some default values
        config([
            'search.qdrant.host' => 'default-host',
        ]);

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        // Config should remain unchanged
        expect(config('search.qdrant.host'))->toBe('default-host');
    });

    it('handles non-array JSON in config file gracefully', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        file_put_contents($configPath, '"just a string"');

        config([
            'search.qdrant.host' => 'default-host',
        ]);

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.host'))->toBe('default-host');
    });

    it('handles non-string values in config gracefully', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 12345, // Not a string
                'collection' => ['not', 'a', 'string'],
            ],
            'embeddings' => [
                'url' => null,
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        config([
            'search.qdrant.host' => 'default-host',
            'search.qdrant.collection' => 'default-collection',
        ]);

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        // Config should remain unchanged since values are not strings
        expect(config('search.qdrant.host'))->toBe('default-host');
        expect(config('search.qdrant.collection'))->toBe('default-collection');
    });

    it('handles url without port', function (): void {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://qdrant-server',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        config([
            'search.qdrant.port' => 6333,
        ]);

        $testConfigDir = $this->testConfigDir;
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);

        app()->instance(KnowledgePathService::class, $mock);

        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();

        expect(config('search.qdrant.host'))->toBe('qdrant-server');
        // Port should remain unchanged since it's not in the URL
        expect(config('search.qdrant.port'))->toBe(6333);
    });
});

afterEach(function (): void {
    Mockery::close();
});
