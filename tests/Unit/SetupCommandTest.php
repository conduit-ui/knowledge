<?php

declare(strict_types=1);

use App\Commands\SetupCommand;

it('setup command exists with correct signature', function (): void {
    $command = app(SetupCommand::class);

    expect($command)->toBeInstanceOf(SetupCommand::class);
    expect($command->getName())->toBe('setup');
});
