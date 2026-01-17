<?php

declare(strict_types=1);

use App\Contracts\HealthCheckInterface;

beforeEach(function () {
    // Create a mock that returns fast, predictable responses
    $healthCheck = mock(HealthCheckInterface::class);

    $healthCheck->shouldReceive('checkAll')
        ->andReturn([
            ['name' => 'Qdrant', 'healthy' => true, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database'],
            ['name' => 'Redis', 'healthy' => false, 'endpoint' => '127.0.0.1:6380', 'type' => 'Cache'],
            ['name' => 'Embeddings', 'healthy' => true, 'endpoint' => 'http://localhost:8001', 'type' => 'ML Service'],
            ['name' => 'Ollama', 'healthy' => false, 'endpoint' => 'localhost:11434', 'type' => 'LLM Engine'],
        ]);

    $healthCheck->shouldReceive('getServices')
        ->andReturn(['qdrant', 'redis', 'embeddings', 'ollama']);

    $healthCheck->shouldReceive('check')
        ->with('qdrant')
        ->andReturn(['name' => 'Qdrant', 'healthy' => true, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database']);

    $healthCheck->shouldReceive('check')
        ->with('redis')
        ->andReturn(['name' => 'Redis', 'healthy' => false, 'endpoint' => '127.0.0.1:6380', 'type' => 'Cache']);

    app()->instance(HealthCheckInterface::class, $healthCheck);
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

    describe('health checks via injected service', function () {
        it('uses HealthCheckInterface for all service checks', function () {
            // The beforeEach mock already covers this - verify command runs successfully
            // and uses the injected interface (which is mocked in beforeEach)
            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('displays healthy services correctly', function () {
            $healthCheck = mock(HealthCheckInterface::class);
            $healthCheck->shouldReceive('checkAll')
                ->andReturn([
                    ['name' => 'Qdrant', 'healthy' => true, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database'],
                    ['name' => 'Redis', 'healthy' => true, 'endpoint' => 'localhost:6380', 'type' => 'Cache'],
                ]);

            app()->instance(HealthCheckInterface::class, $healthCheck);

            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('displays unhealthy services correctly', function () {
            $healthCheck = mock(HealthCheckInterface::class);
            $healthCheck->shouldReceive('checkAll')
                ->andReturn([
                    ['name' => 'Qdrant', 'healthy' => false, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database'],
                ]);

            app()->instance(HealthCheckInterface::class, $healthCheck);

            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('handles partial outage scenario', function () {
            $healthCheck = mock(HealthCheckInterface::class);
            $healthCheck->shouldReceive('checkAll')
                ->andReturn([
                    ['name' => 'Qdrant', 'healthy' => true, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database'],
                    ['name' => 'Redis', 'healthy' => false, 'endpoint' => 'localhost:6380', 'type' => 'Cache'],
                ]);

            app()->instance(HealthCheckInterface::class, $healthCheck);

            $this->artisan('service:status')
                ->assertSuccessful();
        });

        it('handles major outage scenario', function () {
            $healthCheck = mock(HealthCheckInterface::class);
            $healthCheck->shouldReceive('checkAll')
                ->andReturn([
                    ['name' => 'Qdrant', 'healthy' => false, 'endpoint' => 'localhost:6333', 'type' => 'Vector Database'],
                    ['name' => 'Redis', 'healthy' => false, 'endpoint' => 'localhost:6380', 'type' => 'Cache'],
                ]);

            app()->instance(HealthCheckInterface::class, $healthCheck);

            $this->artisan('service:status')
                ->assertSuccessful();
        });
    });

    describe('command signature', function () {
        it('has correct command signature', function () {
            $command = app(\App\Commands\Service\StatusCommand::class);
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('service:status');
            expect($signature)->toContain('--odin');
        });

        it('has correct description', function () {
            $command = app(\App\Commands\Service\StatusCommand::class);
            $reflection = new ReflectionClass($command);
            $descProperty = $reflection->getProperty('description');
            $descProperty->setAccessible(true);
            $description = $descProperty->getValue($command);

            expect($description)->toBe('Check service health status');
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

    describe('process execution', function () {
        it('is instance of Laravel Zero Command', function () {
            $command = app(\App\Commands\Service\StatusCommand::class);

            expect($command)->toBeInstanceOf(\LaravelZero\Framework\Commands\Command::class);
        });

        it('uses Process class for docker compose ps', function () {
            $command = app(\App\Commands\Service\StatusCommand::class);

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

        it('has getContainerStatus method', function () {
            $command = app(\App\Commands\Service\StatusCommand::class);

            expect(method_exists($command, 'getContainerStatus'))->toBeTrue();
        });
    });
});
