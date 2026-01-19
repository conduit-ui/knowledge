<?php

declare(strict_types=1);

use App\Services\KnowledgePathService;

beforeEach(function () {
    // Use a temporary config directory for testing
    $this->testConfigDir = sys_get_temp_dir().'/knowledge-config-test-'.uniqid();
    mkdir($this->testConfigDir, 0755, true);

    // Capture the test directory in a variable for the closure
    $testConfigDir = $this->testConfigDir;

    // Mock the path service to use test directory
    $this->app->bind(KnowledgePathService::class, function () use ($testConfigDir) {
        $mock = Mockery::mock(KnowledgePathService::class);
        $mock->shouldReceive('getKnowledgeDirectory')
            ->andReturn($testConfigDir);
        $mock->shouldReceive('getDatabasePath')
            ->andReturn($testConfigDir.'/knowledge.sqlite');
        $mock->shouldReceive('ensureDirectoryExists')
            ->andReturnUsing(function ($path) {
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            });
        $mock->shouldReceive('databaseExists')
            ->andReturnUsing(function () use ($testConfigDir) {
                return file_exists($testConfigDir.'/knowledge.sqlite');
            });

        return $mock;
    });
});

afterEach(function () {
    // Clean up test directory
    if (isset($this->testConfigDir) && is_dir($this->testConfigDir)) {
        removeDirectory($this->testConfigDir);
    }
});

describe('knowledge:config list', function () {
    it('lists all config settings when config file exists', function () {
        // Create config file
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://localhost:6333',
                'collection' => 'knowledge',
            ],
            'embeddings' => [
                'url' => 'http://localhost:8001',
            ],
        ];
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->artisan('config')
            ->expectsOutputToContain('qdrant.url: http://localhost:6333')
            ->expectsOutputToContain('qdrant.collection: knowledge')
            ->expectsOutputToContain('embeddings.url: http://localhost:8001')
            ->assertSuccessful();
    });

    it('shows default values when config file does not exist', function () {
        $this->artisan('config')
            ->expectsOutputToContain('qdrant.url: http://localhost:6333')
            ->expectsOutputToContain('qdrant.collection: knowledge')
            ->expectsOutputToContain('embeddings.url: http://localhost:8001')
            ->assertSuccessful();
    });

    it('lists config with list action explicitly', function () {
        $this->artisan('config', ['action' => 'list'])
            ->expectsOutputToContain('qdrant.url')
            ->assertSuccessful();
    });
});

describe('knowledge:config get', function () {
    it('gets a specific config value', function () {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://localhost:6333',
                'collection' => 'test-collection',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $this->artisan('config', [
            'action' => 'get',
            'key' => 'qdrant.collection',
        ])
            ->expectsOutputToContain('test-collection')
            ->assertSuccessful();
    });

    it('gets nested config value', function () {
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://custom:9000',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        $this->artisan('config', [
            'action' => 'get',
            'key' => 'qdrant.url',
        ])
            ->expectsOutputToContain('http://custom:9000')
            ->assertSuccessful();
    });

    it('gets default value when key does not exist', function () {
        $this->artisan('config', [
            'action' => 'get',
            'key' => 'qdrant.url',
        ])
            ->expectsOutputToContain('http://localhost:6333')
            ->assertSuccessful();
    });

    it('fails when key is not provided for get action', function () {
        $this->artisan('config', [
            'action' => 'get',
        ])
            ->expectsOutputToContain('Key is required for get action')
            ->assertFailed();
    });

    it('fails when key is invalid', function () {
        $this->artisan('config', [
            'action' => 'get',
            'key' => 'invalid.key',
        ])
            ->expectsOutputToContain('Invalid configuration key')
            ->assertFailed();
    });
});

describe('knowledge:config set', function () {
    it('sets a string config value', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'http://custom:9000',
        ])
            ->assertSuccessful();

        $configPath = $this->testConfigDir.'/config.json';
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['qdrant']['url'])->toBe('http://custom:9000');
    });

    it('sets collection name', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.collection',
            'value' => 'my-collection',
        ])
            ->assertSuccessful();

        $configPath = $this->testConfigDir.'/config.json';
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['qdrant']['collection'])->toBe('my-collection');
    });

    it('sets embeddings url', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'embeddings.url',
            'value' => 'http://embeddings:8001',
        ])
            ->assertSuccessful();

        $configPath = $this->testConfigDir.'/config.json';
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['embeddings']['url'])->toBe('http://embeddings:8001');
    });

    it('preserves existing config when setting new value', function () {
        // Set initial value
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://localhost:6333',
                'collection' => 'knowledge',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        // Set new value
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'embeddings.url',
            'value' => 'http://new:8001',
        ])
            ->assertSuccessful();

        // Verify both values exist
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['qdrant']['url'])->toBe('http://localhost:6333');
        expect($config['qdrant']['collection'])->toBe('knowledge');
        expect($config['embeddings']['url'])->toBe('http://new:8001');
    });

    it('updates existing config value', function () {
        // Set initial value
        $configPath = $this->testConfigDir.'/config.json';
        $config = [
            'qdrant' => [
                'url' => 'http://old:6333',
            ],
        ];
        file_put_contents($configPath, json_encode($config));

        // Update value
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'http://new:6333',
        ])
            ->assertSuccessful();

        // Verify update
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['qdrant']['url'])->toBe('http://new:6333');
    });

    it('fails when key is not provided for set action', function () {
        $this->artisan('config', [
            'action' => 'set',
        ])
            ->expectsOutputToContain('Key and value are required for set action')
            ->assertFailed();
    });

    it('fails when value is not provided for set action', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
        ])
            ->expectsOutputToContain('Key and value are required for set action')
            ->assertFailed();
    });

    it('fails when key is invalid', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'invalid.key',
            'value' => 'value',
        ])
            ->expectsOutputToContain('Invalid configuration key')
            ->assertFailed();
    });

    it('creates config directory if it does not exist', function () {
        // Remove test directory
        removeDirectory($this->testConfigDir);

        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'http://localhost:6333',
        ])
            ->assertSuccessful();

        $configPath = $this->testConfigDir.'/config.json';
        expect(file_exists($configPath))->toBeTrue();
    });
});

describe('knowledge:config invalid action', function () {
    it('fails with invalid action', function () {
        $this->artisan('config', [
            'action' => 'invalid',
        ])
            ->expectsOutputToContain('Invalid action')
            ->expectsOutputToContain('Valid actions: list, get, set')
            ->assertFailed();
    });
});

describe('knowledge:config edge cases', function () {
    it('handles invalid JSON in config file', function () {
        $configPath = $this->testConfigDir.'/config.json';
        file_put_contents($configPath, 'not valid json {{{');

        // Should use defaults when JSON is invalid
        $this->artisan('config')
            ->expectsOutputToContain('qdrant.url: http://localhost:6333')
            ->assertSuccessful();
    });

    it('handles non-array JSON in config file', function () {
        $configPath = $this->testConfigDir.'/config.json';
        file_put_contents($configPath, '"just a string"');

        // Should use defaults when JSON is not an array
        $this->artisan('config')
            ->expectsOutputToContain('qdrant.url: http://localhost:6333')
            ->assertSuccessful();
    });

    it('creates new nested path when setting value', function () {
        // Start with empty config
        $configPath = $this->testConfigDir.'/config.json';
        file_put_contents($configPath, '{}');

        $this->artisan('config', [
            'action' => 'set',
            'key' => 'embeddings.url',
            'value' => 'http://test:8001',
        ])
            ->assertSuccessful();

        $config = json_decode(file_get_contents($configPath), true);
        expect($config['embeddings']['url'])->toBe('http://test:8001');
    });
});

describe('knowledge:config validation', function () {
    it('validates url format for qdrant.url', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'not-a-url',
        ])
            ->expectsOutputToContain('must be a valid URL')
            ->assertFailed();
    });

    it('validates url format for embeddings.url', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'embeddings.url',
            'value' => 'invalid-url',
        ])
            ->expectsOutputToContain('must be a valid URL')
            ->assertFailed();
    });

    it('accepts valid http url', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'http://localhost:6333',
        ])
            ->assertSuccessful();
    });

    it('accepts valid https url', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.url',
            'value' => 'https://qdrant.example.com',
        ])
            ->assertSuccessful();
    });

    it('allows any string for collection name', function () {
        $this->artisan('config', [
            'action' => 'set',
            'key' => 'qdrant.collection',
            'value' => 'my-custom-collection',
        ])
            ->assertSuccessful();

        $configPath = $this->testConfigDir.'/config.json';
        $config = json_decode(file_get_contents($configPath), true);
        expect($config['qdrant']['collection'])->toBe('my-custom-collection');
    });
});
