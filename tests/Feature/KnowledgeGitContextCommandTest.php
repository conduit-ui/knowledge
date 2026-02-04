<?php

declare(strict_types=1);

use App\Services\GitContextService;

describe('KnowledgeGitContextCommand', function (): void {
    describe('displaying git context', function (): void {
        it('displays git context information', function (): void {
            $this->artisan('git:context')
                ->expectsOutputToContain('Git Context Information')
                ->expectsOutputToContain('Repository:')
                ->expectsOutputToContain('Branch:')
                ->expectsOutputToContain('Commit:')
                ->assertSuccessful();
        });

        it('displays all context fields when in git repository', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(true);
            $mockService->shouldReceive('getContext')->andReturn([
                'repo' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'commit' => 'abc123def456',
                'author' => 'John Doe',
            ]);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Git Context Information')
                ->expectsOutputToContain('Repository: https://github.com/test/repo.git')
                ->expectsOutputToContain('Branch: main')
                ->expectsOutputToContain('Commit: abc123def456')
                ->expectsOutputToContain('Author: John Doe')
                ->assertSuccessful();
        });

        it('handles null values in context gracefully', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(true);
            $mockService->shouldReceive('getContext')->andReturn([
                'repo' => null,
                'branch' => null,
                'commit' => null,
                'author' => null,
            ]);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Git Context Information')
                ->expectsOutputToContain('Repository: N/A')
                ->expectsOutputToContain('Branch: N/A')
                ->expectsOutputToContain('Commit: N/A')
                ->expectsOutputToContain('Author: N/A')
                ->assertSuccessful();
        });

        it('displays local path as repository when no remote configured', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(true);
            $mockService->shouldReceive('getContext')->andReturn([
                'repo' => '/path/to/local/repo',
                'branch' => 'feature/test',
                'commit' => 'def456abc789',
                'author' => 'Jane Smith',
            ]);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Repository: /path/to/local/repo')
                ->expectsOutputToContain('Branch: feature/test')
                ->expectsOutputToContain('Author: Jane Smith')
                ->assertSuccessful();
        });
    });

    describe('handling non-git directories', function (): void {
        it('handles non-git directory gracefully', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(false);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Not in a git repository')
                ->assertSuccessful();
        });

        it('exits early when not in git repository', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(false);
            $mockService->shouldNotReceive('getContext');

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Not in a git repository')
                ->assertSuccessful();
        });
    });

    describe('service integration', function (): void {
        it('uses GitContextService for context retrieval', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->once()->andReturn(true);
            $mockService->shouldReceive('getContext')->once()->andReturn([
                'repo' => 'test-repo',
                'branch' => 'test-branch',
                'commit' => 'test-commit',
                'author' => 'test-author',
            ]);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')->assertSuccessful();
        });

        it('handles detached HEAD state', function (): void {
            $mockService = mock(GitContextService::class);
            $mockService->shouldReceive('isGitRepository')->andReturn(true);
            $mockService->shouldReceive('getContext')->andReturn([
                'repo' => 'https://github.com/test/repo.git',
                'branch' => 'HEAD',
                'commit' => 'abc123def456',
                'author' => 'Developer',
            ]);

            $this->app->instance(GitContextService::class, $mockService);

            $this->artisan('git:context')
                ->expectsOutputToContain('Branch: HEAD')
                ->assertSuccessful();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeGitContextCommand::class);
            expect($command->getName())->toBe('git:context');
        });

        it('has the correct description', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeGitContextCommand::class);
            expect($command->getDescription())->toContain('git context');
        });

        it('requires no arguments', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeGitContextCommand::class);
            $definition = $command->getDefinition();
            expect($definition->getArguments())->toBeEmpty();
        });

        it('has no options', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeGitContextCommand::class);
            $definition = $command->getDefinition();
            $options = $definition->getOptions();
            // Filter out default Laravel options (help, quiet, verbose, etc.)
            $customOptions = array_filter($options, fn ($option): bool => ! in_array($option->getName(), [
                'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env',
            ]));
            expect($customOptions)->toBeEmpty();
        });
    });
});
