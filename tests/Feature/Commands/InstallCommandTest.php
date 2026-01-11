<?php

declare(strict_types=1);

use App\Services\QdrantService;

describe('install command', function (): void {
    it('initializes Qdrant collection successfully', function (): void {
        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('ensureCollection')
            ->with('default')
            ->once();

        $this->app->instance(QdrantService::class, $qdrant);

        $this->artisan('install')
            ->expectsOutputToContain('knowledge_default')
            ->expectsOutputToContain('initialized successfully')
            ->assertExitCode(0);
    });

    it('accepts custom project name', function (): void {
        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('ensureCollection')
            ->with('myproject')
            ->once();

        $this->app->instance(QdrantService::class, $qdrant);

        $this->artisan('install', ['--project' => 'myproject'])
            ->expectsOutputToContain('knowledge_myproject')
            ->assertExitCode(0);
    });

    it('displays usage instructions after initialization', function (): void {
        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('ensureCollection')
            ->with('default')
            ->once();

        $this->app->instance(QdrantService::class, $qdrant);

        $this->artisan('install')
            ->expectsOutputToContain('know add')
            ->expectsOutputToContain('know search')
            ->expectsOutputToContain('know entries')
            ->assertExitCode(0);
    });

    it('shows error when Qdrant connection fails', function (): void {
        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('ensureCollection')
            ->andThrow(new \Exception('Connection refused'));

        $this->app->instance(QdrantService::class, $qdrant);

        $this->artisan('install')
            ->expectsOutputToContain('Failed to initialize')
            ->expectsOutputToContain('docker start')
            ->assertExitCode(1);
    });
});
