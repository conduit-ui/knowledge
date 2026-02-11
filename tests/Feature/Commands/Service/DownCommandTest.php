<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('service:down command', function () {
    beforeEach(function () {
        // Fake all docker commands by default
        Process::fake([
            '*docker*' => Process::result(output: 'OK', exitCode: 0),
        ]);
    });

    describe('configuration file validation', function () {
        it('fails when local docker-compose file does not exist', function () {
            $tempDir = sys_get_temp_dir().'/know-test-'.uniqid();
            mkdir($tempDir, 0755, true);
            $this->app->setBasePath($tempDir);

            try {
                $this->artisan('service:down')
                    ->assertFailed();
            } finally {
                rmdir($tempDir);
            }
        });

        it('fails when remote docker-compose file does not exist', function () {
            $tempDir = sys_get_temp_dir().'/know-test-'.uniqid();
            mkdir($tempDir, 0755, true);
            $this->app->setBasePath($tempDir);

            try {
                $this->artisan('service:down', ['--remote' => true])
                    ->assertFailed();
            } finally {
                rmdir($tempDir);
            }
        });
    });

    describe('docker compose execution', function () {
        it('runs docker compose down successfully', function () {
            $this->artisan('service:down')
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('down', $process->command));
        });

        it('runs docker compose down with volumes flag when forced', function () {
            $this->artisan('service:down', ['--volumes' => true, '--force' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker', $process->command)
                && in_array('-v', $process->command));
        });

        it('uses remote compose file when remote flag is set', function () {
            $this->artisan('service:down', ['--remote' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker-compose.remote.yml', $process->command));
        });

        it('returns failure when docker compose fails', function () {
            Process::fake([
                '*docker*' => Process::result(
                    errorOutput: 'Docker daemon not running',
                    exitCode: 1,
                ),
            ]);

            $this->artisan('service:down')
                ->assertFailed();
        });

        it('combines remote and volumes flags correctly when forced', function () {
            $this->artisan('service:down', ['--remote' => true, '--volumes' => true, '--force' => true])
                ->assertSuccessful();

            Process::assertRan(fn ($process) => in_array('docker-compose.remote.yml', $process->command)
                && in_array('-v', $process->command));
        });
    });

    describe('command signature', function () {
        it('has correct command signature', function () {
            $command = new \App\Commands\Service\DownCommand;
            $reflection = new ReflectionClass($command);
            $signatureProperty = $reflection->getProperty('signature');
            $signatureProperty->setAccessible(true);
            $signature = $signatureProperty->getValue($command);

            expect($signature)->toContain('service:down');
            expect($signature)->toContain('--volumes');
            expect($signature)->toContain('--remote');
            expect($signature)->toContain('--force');
        });

        it('has correct description', function () {
            $command = new \App\Commands\Service\DownCommand;
            $reflection = new ReflectionClass($command);
            $descProperty = $reflection->getProperty('description');
            $descProperty->setAccessible(true);
            $description = $descProperty->getValue($command);

            expect($description)->toBe('Stop knowledge services');
        });
    });
});
