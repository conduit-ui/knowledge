<?php

declare(strict_types=1);

afterEach(function () {
    Mockery::close();
});

describe('service:logs command', function () {
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

    describe('process execution', function () {
        it('is instance of Laravel Zero Command', function () {
            $command = new \App\Commands\Service\LogsCommand;

            expect($command)->toBeInstanceOf(\LaravelZero\Framework\Commands\Command::class);
        });

        it('uses Process class for execution', function () {
            $command = new \App\Commands\Service\LogsCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('Process');
            expect($source)->toContain('docker');
            expect($source)->toContain('compose');
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
                $this->artisan('service:logs', ['service' => 'qdrant'])
                    ->assertExitCode(\LaravelZero\Framework\Commands\Command::FAILURE);
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });
    });
});
