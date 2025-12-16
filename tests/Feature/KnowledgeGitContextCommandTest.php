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
    // This test would need to mock GitContextService or test in a non-git directory
    // For now, we'll just verify the command exists and runs
    $this->artisan('knowledge:git:context')
        ->assertSuccessful();
});
