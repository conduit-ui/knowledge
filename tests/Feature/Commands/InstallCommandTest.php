<?php

declare(strict_types=1);

use App\Services\DatabaseInitializer;
use App\Services\KnowledgePathService;

describe('install command', function (): void {
    it('shows already initialized message when database exists', function (): void {
        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getDatabasePath')
            ->andReturn('/home/user/.knowledge/knowledge.sqlite');

        $initializer = Mockery::mock(DatabaseInitializer::class);
        $initializer->shouldReceive('isInitialized')->andReturn(true);

        $this->app->instance(KnowledgePathService::class, $pathService);
        $this->app->instance(DatabaseInitializer::class, $initializer);

        $this->artisan('install')
            ->expectsOutputToContain('Knowledge database already exists')
            ->assertExitCode(0);
    });

    it('initializes database when not yet installed', function (): void {
        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getDatabasePath')
            ->andReturn('/home/user/.knowledge/knowledge.sqlite');

        $initializer = Mockery::mock(DatabaseInitializer::class);
        $initializer->shouldReceive('isInitialized')->andReturn(false);
        $initializer->shouldReceive('initialize')->once();

        $this->app->instance(KnowledgePathService::class, $pathService);
        $this->app->instance(DatabaseInitializer::class, $initializer);

        $this->artisan('install')
            ->expectsOutputToContain('Initializing knowledge database')
            ->expectsOutputToContain('Knowledge database initialized successfully')
            ->assertExitCode(0);
    });

    it('displays usage instructions after initialization', function (): void {
        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getDatabasePath')
            ->andReturn('/tmp/test/.knowledge/knowledge.sqlite');

        $initializer = Mockery::mock(DatabaseInitializer::class);
        $initializer->shouldReceive('isInitialized')->andReturn(false);
        $initializer->shouldReceive('initialize')->once();

        $this->app->instance(KnowledgePathService::class, $pathService);
        $this->app->instance(DatabaseInitializer::class, $initializer);

        $this->artisan('install')
            ->expectsOutputToContain('know add')
            ->expectsOutputToContain('know search')
            ->expectsOutputToContain('know list')
            ->assertExitCode(0);
    });

    it('does not show usage instructions when already initialized', function (): void {
        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getDatabasePath')
            ->andReturn('/home/user/.knowledge/knowledge.sqlite');

        $initializer = Mockery::mock(DatabaseInitializer::class);
        $initializer->shouldReceive('isInitialized')->andReturn(true);

        $this->app->instance(KnowledgePathService::class, $pathService);
        $this->app->instance(DatabaseInitializer::class, $initializer);

        $this->artisan('install')
            ->doesntExpectOutputToContain('know add')
            ->assertExitCode(0);
    });
});
