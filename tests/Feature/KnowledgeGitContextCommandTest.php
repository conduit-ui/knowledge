<?php

declare(strict_types=1);

it('displays git context information', function () {
    $this->artisan('knowledge:git:context')
        ->expectsOutputToContain('Git Context Information')
        ->expectsOutputToContain('Repository:')
        ->expectsOutputToContain('Branch:')
        ->expectsOutputToContain('Commit:')
        ->assertSuccessful();
});

it('handles non-git directory gracefully', function () {
    $mockService = mock(App\Services\GitContextService::class);
    $mockService->shouldReceive('isGitRepository')->andReturn(false);

    $this->app->instance(App\Services\GitContextService::class, $mockService);

    $this->artisan('knowledge:git:context')
        ->expectsOutputToContain('Not in a git repository')
        ->assertSuccessful();
});
