<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('service:logs command', function () {
    beforeEach(function () {
        // Fake all docker commands by default
        Process::fake([
            '*docker*' => Process::result(output: 'Logs output...', exitCode: 0),
        ]);
    });

    describe('configuration file validation', function () {
        it('fails when local docker-compose file does not exist', function () {
            $composeFile = getcwd().'/docker-compose.yml';
            $tempFile = getcwd().'/docker-compose.yml.backup-test';

            if (file_exists($composeFile)) {
                rename($composeFile, $tempFile);
            }

            try {
                $this->artisan('service:logs', ['service' => 'qdrant'])
                    ->assertFailed();
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });

        it('fails when odin docker-compose file does not exist', function () {
            $composeFile = getcwd().'/docker-compose.odin.yml';
            $tempFile = getcwd().'/docker-compose.odin.yml.backup-test';

            if (file_exists($composeFile)) {
                rename($composeFile, $tempFile);
            }

            try {
                $this->artisan('service:logs', [
                    'service' => 'qdrant',
                    '--odin' => true,
                ])
                    ->assertFailed();
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });
    });

    describe('docker compose execution', function () {
        it('runs docker compose logs for specific service', function () {
            $this->artisan('service:logs', ['service' => 'qdrant'])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('logs', $process->command)
                && in_array('qdrant', $process->command));
        });

        it('runs docker compose logs with follow flag', function () {
            $this->artisan('service:logs', ['service' => 'qdrant', '--follow' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('-f', $process->command)
                && in_array('logs', $process->command));
        });

        it('runs docker compose logs with custom tail count', function () {
            $this->artisan('service:logs', ['service' => 'redis', '--tail' => 100])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('--tail=100', $process->command));
        });

        it('uses odin compose file when odin flag is set', function () {
            $this->artisan('service:logs', ['service' => 'qdrant', '--odin' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker-compose.odin.yml', $process->command));
        });

        it('returns exit code from docker compose', function () {
            Process::fake([
                '*docker*' => Process::result(
                    errorOutput: 'Service not found',
                    exitCode: 1,
                ),
            ]);

            $this->artisan('service:logs', ['service' => 'nonexistent'])
                ->assertExitCode(1);
        });

        it('combines follow and tail flags correctly', function () {
            $this->artisan('service:logs', [
                'service' => 'embeddings',
                '--follow' => true,
                '--tail' => 200,
            ])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('-f', $process->command)
                && in_array('--tail=200', $process->command));
        });

        it('skips prompt when following without service', function () {
            // When --follow is specified without a service, it doesn't prompt
            $this->artisan('service:logs', ['--follow' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('-f', $process->command)
                && in_array('logs', $process->command));
        });

        // Note: Interactive service selection prompt (no service arg, no --follow) uses Laravel Prompts
        // which cannot be easily tested with expectsChoice(). The prompt is covered by explicit service
        // argument tests above. Manual testing confirms the selection flow works correctly.
    });

    describe('command signature', function () {
        it('has correct command signature', function () {
            $command = new \App\Commands\Service\LogsCommand;
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('service:logs');
            expect($signature)->toContain('{service?');
            expect($signature)->toContain('--f|follow');
            expect($signature)->toContain('--tail=50');
            expect($signature)->toContain('--odin');
        });

        it('has correct description', function () {
            $command = new \App\Commands\Service\LogsCommand;
            $reflection = new ReflectionClass($command);
            $descProperty = $reflection->getProperty('description');
            $descProperty->setAccessible(true);
            $description = $descProperty->getValue($command);

            expect($description)->toBe('View service logs');
        });

        it('makes service argument optional', function () {
            $command = new \App\Commands\Service\LogsCommand;
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('{service?');
        });
    });
});
