<?php

declare(strict_types=1);

afterEach(function () {
    Mockery::close();
});

describe('service:down command', function () {
    describe('configuration file validation', function () {
        it('fails when local docker-compose file does not exist', function () {
            $composeFile = getcwd().'/docker-compose.yml';
            $tempFile = getcwd().'/docker-compose.yml.backup-test';

            if (file_exists($composeFile)) {
                rename($composeFile, $tempFile);
            }

            try {
                $this->artisan('service:down')
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
                $this->artisan('service:down', ['--odin' => true])
                    ->assertFailed();
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
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
            expect($signature)->toContain('--odin');
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

    describe('process execution', function () {
        it('is instance of Laravel Zero Command', function () {
            $command = new \App\Commands\Service\DownCommand;

            expect($command)->toBeInstanceOf(\LaravelZero\Framework\Commands\Command::class);
        });
    });

    describe('exit codes', function () {
        it('returns failure code when compose file is missing', function () {
            $composeFile = getcwd().'/docker-compose.yml';
            $tempFile = getcwd().'/docker-compose.yml.backup-test';

            if (file_exists($composeFile)) {
                rename($composeFile, $tempFile);
            }

            try {
                $this->artisan('service:down')
                    ->assertExitCode(\LaravelZero\Framework\Commands\Command::FAILURE);
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });
    });
});
