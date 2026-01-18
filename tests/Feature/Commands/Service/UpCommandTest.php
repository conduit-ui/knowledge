<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('service:up command', function () {
    beforeEach(function () {
        // Fake all docker commands by default
        Process::fake([
            '*docker*' => Process::result(output: 'OK', exitCode: 0),
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
                $this->artisan('service:up')
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
                $this->artisan('service:up', ['--odin' => true])
                    ->assertFailed();
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });
    });

    describe('docker compose execution', function () {
        it('runs docker compose up successfully', function () {
            $this->artisan('service:up')
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('up', $process->command));
        });

        it('runs docker compose up with detach flag', function () {
            $this->artisan('service:up', ['--detach' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('-d', $process->command));
        });

        it('uses odin compose file when odin flag is set', function () {
            $this->artisan('service:up', ['--odin' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker-compose.odin.yml', $process->command));
        });

        it('returns failure when docker compose fails', function () {
            Process::fake([
                '*docker*' => Process::result(
                    errorOutput: 'Docker daemon not running',
                    exitCode: 1,
                ),
            ]);

            $this->artisan('service:up')
                ->assertFailed();
        });

        it('combines odin and detach flags correctly', function () {
            $this->artisan('service:up', ['--odin' => true, '--detach' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker-compose.odin.yml', $process->command)
                && in_array('-d', $process->command));
        });
    });

    describe('command signature', function () {
        it('has correct command signature', function () {
            $command = new \App\Commands\Service\UpCommand;
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('service:up');
            expect($signature)->toContain('--d|detach');
            expect($signature)->toContain('--odin');
        });

        it('has correct description', function () {
            $command = new \App\Commands\Service\UpCommand;
            $reflection = new ReflectionClass($command);
            $descProperty = $reflection->getProperty('description');
            $descProperty->setAccessible(true);
            $description = $descProperty->getValue($command);

            expect($description)->toContain('Start knowledge services');
        });
    });
});
