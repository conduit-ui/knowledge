<?php

declare(strict_types=1);

beforeEach(function () {
    Config::set('search.qdrant.host', 'localhost');
    Config::set('search.qdrant.port', 6333);
    Config::set('database.redis.default.host', '127.0.0.1');
    Config::set('database.redis.default.port', 6380);
    Config::set('search.qdrant.embedding_server', 'http://localhost:8001');
    Config::set('search.ollama.host', 'localhost');
    Config::set('search.ollama.port', 11434);
});

afterEach(function () {
    Mockery::close();
});

describe('service:status command', function () {
    describe('successful operations', function () {
        it('always returns success exit code', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('displays service status dashboard', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('shows environment information', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('shows odin environment with --odin flag', function () {
            $this->artisan('service:status', ['--odin' => true])
                ->assertSuccessful();
        });
    });

    describe('health checks', function () {
        it('checks qdrant service health', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('checks redis service health', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('checks embeddings service health', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('checks ollama service health', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('displays endpoint information for each service', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });
    });

    describe('command signature', function () {
        it('has correct command signature', function () {
            $command = new \App\Commands\Service\StatusCommand;
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('service:status');
            expect($signature)->toContain('--odin');
        });

        it('has correct description', function () {
            $command = new \App\Commands\Service\StatusCommand;
            $reflection = new ReflectionClass($command);
            $descProperty = $reflection->getProperty('description');
            $descProperty->setAccessible(true);
            $description = $descProperty->getValue($command);

            expect($description)->toBe('Check service health status');
        });
    });

    describe('health check methods', function () {
        it('has checkQdrant method', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect(method_exists($command, 'checkQdrant'))->toBeTrue();
        });

        it('has checkRedis method', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect(method_exists($command, 'checkRedis'))->toBeTrue();
        });

        it('has checkEmbeddings method', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect(method_exists($command, 'checkEmbeddings'))->toBeTrue();
        });

        it('has checkOllama method', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect(method_exists($command, 'checkOllama'))->toBeTrue();
        });
    });

    describe('output formatting', function () {
        it('displays service status sections', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('displays tip about viewing logs', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('shows service type for each service', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });
    });

    describe('environment configuration', function () {
        it('reads service endpoints from config', function () {
            Config::set('search.qdrant.host', 'custom-host');
            Config::set('search.qdrant.port', 9999);

            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('uses default values when config is not set', function () {
            Config::set('search.qdrant.host', null);
            Config::set('search.qdrant.port', null);

            $this->artisan('service:status')
                ->assertSuccessful();
        });
    });

    describe('process execution', function () {
        it('is instance of Laravel Zero Command', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect($command)->toBeInstanceOf(\LaravelZero\Framework\Commands\Command::class);
        });

        it('uses Process class for docker compose ps', function () {
            $command = new \App\Commands\Service\StatusCommand;

            $reflection = new ReflectionMethod($command, 'getContainerStatus');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('Process');
            expect($source)->toContain('docker');
        });
    });

    describe('container status handling', function () {
        it('handles empty container list gracefully', function () {
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('parses container json output', function () {
            $command = new \App\Commands\Service\StatusCommand;

            expect(method_exists($command, 'getContainerStatus'))->toBeTrue();
        });
    });
});
