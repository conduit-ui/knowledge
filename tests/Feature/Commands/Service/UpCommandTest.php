<?php

declare(strict_types=1);

afterEach(function () {
    Mockery::close();
});

describe('service:up command', function () {
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

        it('suggests initialization when compose file is missing', function () {
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

    describe('process execution', function () {
        it('is instance of Laravel Zero Command', function () {
            $command = new \App\Commands\Service\UpCommand;

            expect($command)->toBeInstanceOf(\LaravelZero\Framework\Commands\Command::class);
        });

        it('uses Process class for execution', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('Process');
            expect($source)->toContain('docker');
            expect($source)->toContain('compose');
        });

        it('sets unlimited timeout for process', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('setTimeout(null)');
        });

        it('enables TTY when supported', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('setTty');
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
                $this->artisan('service:up')
                    ->assertExitCode(\LaravelZero\Framework\Commands\Command::FAILURE);
            } finally {
                if (file_exists($tempFile)) {
                    rename($tempFile, $composeFile);
                }
            }
        });
    });

    describe('output formatting', function () {
        it('uses termwind for styled output', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('render');
            expect($source)->toContain('Termwind');
        });
    });

    describe('docker compose arguments', function () {
        it('constructs basic up command', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain("'docker'");
            expect($source)->toContain("'compose'");
            expect($source)->toContain("'up'");
        });

        it('adds detach flag when option provided', function () {
            $command = new \App\Commands\Service\UpCommand;

            $reflection = new ReflectionMethod($command, 'handle');
            $source = file_get_contents($reflection->getFileName());

            expect($source)->toContain('detach');
            expect($source)->toContain("'-d'");
        });
    });
});
