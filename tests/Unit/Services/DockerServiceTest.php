<?php

declare(strict_types=1);

use App\Services\DockerService;

describe('DockerService', function () {
    describe('getHostOs', function () {
        it('returns correct OS identifier', function () {
            $service = new DockerService;
            $os = $service->getHostOs();

            expect($os)->toBeIn(['macos', 'linux', 'windows', 'unknown']);
        });

        it('returns macos on Darwin', function () {
            if (PHP_OS_FAMILY !== 'Darwin') {
                $this->markTestSkipped('Not running on macOS');
            }
            $service = new DockerService;
            expect($service->getHostOs())->toBe('macos');
        });
    });

    describe('getInstallUrl', function () {
        it('returns valid Docker install URL based on OS', function () {
            $service = new DockerService;
            $url = $service->getInstallUrl();

            // All valid install URLs should start with Docker docs
            expect($url)->toStartWith('https://docs.docker.com/');

            // Verify correct URL for current OS
            $expectedUrls = [
                'Darwin' => 'https://docs.docker.com/desktop/install/mac-install/',
                'Linux' => 'https://docs.docker.com/engine/install/',
                'Windows' => 'https://docs.docker.com/desktop/install/windows-install/',
            ];

            $expected = $expectedUrls[PHP_OS_FAMILY] ?? 'https://docs.docker.com/get-docker/';
            expect($url)->toBe($expected);
        });
    });

    describe('checkEndpoint', function () {
        it('returns false for unreachable endpoint', function () {
            $service = new DockerService;

            // Use a non-routable IP to avoid network delays
            $result = $service->checkEndpoint('http://192.0.2.1:9999/test', 1);

            expect($result)->toBeFalse();
        });

        it('returns false for invalid URL', function () {
            $service = new DockerService;

            $result = $service->checkEndpoint('not-a-url', 1);

            expect($result)->toBeFalse();
        });
    });

    describe('getVersion', function () {
        it('returns version string when Docker is installed', function () {
            $service = new DockerService;

            if ($service->isInstalled()) {
                $version = $service->getVersion();
                expect($version)->not->toBeNull();
                expect($version)->toMatch('/^[\d.]+/');
            } else {
                // Docker not installed, version should be null
                expect($service->getVersion())->toBeNull();
            }
        });
    });

    describe('isInstalled', function () {
        it('returns boolean', function () {
            $service = new DockerService;

            expect($service->isInstalled())->toBeBool();
        });
    });

    describe('isRunning', function () {
        it('returns boolean', function () {
            $service = new DockerService;

            // Skip if Docker is not installed (avoids timeout in CI environments)
            if (! $service->isInstalled()) {
                $this->markTestSkipped('Docker is not installed');
            }

            expect($service->isRunning())->toBeBool();
        });
    });

    describe('compose', function () {
        it('returns result array with required keys', function () {
            $service = new DockerService;

            // Test with a valid directory but no docker-compose.yml
            $tempDir = sys_get_temp_dir().'/docker-test-'.uniqid();
            mkdir($tempDir);

            try {
                $result = $service->compose($tempDir, ['ps']);

                expect($result)->toHaveKeys(['success', 'output', 'exitCode']);
                expect($result['exitCode'])->toBeInt();
            } finally {
                @rmdir($tempDir);
            }
        });
    });
});
